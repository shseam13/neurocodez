# Deploying Neuro Codez

Two runbooks, written together on purpose: **Render** (free, now) and **cPanel**
(paid, later). Both are supported by the same codebase because the stack is
PHP + MySQL — that choice, not the host, is what makes the move a config change
rather than a rewrite.

---

## Before either host

You need three free accounts:

| What | Why | Free tier |
|---|---|---|
| **Aiven MySQL** | The database. Render has no free MySQL, and Postgres would break cPanel portability. | 1 GB, always free |
| **Cloudflare R2** | Uploaded files. S3-compatible, so Laravel's existing `s3` driver works unchanged. | 10 GB |
| **Brevo** or **Resend** | Sending invitations and password resets. | 300/day · 3,000/month |

Generate the app key once and keep it somewhere safe:

```bash
php artisan key:generate --show
```

> Set `APP_KEY` once per environment and never rotate it. Changing it makes every
> existing session and every encrypted value unreadable — everyone is signed out
> and nothing decrypts.

---

## Render (free tier)

### 1. Database

Create a MySQL service on Aiven, then take the connection details from its
overview page — host, port, database, user, password.

Aiven **requires TLS**, so PDO needs their CA certificate on disk. Download it
from the service page ("CA Certificate") and paste the entire contents —
`-----BEGIN CERTIFICATE-----` and `-----END CERTIFICATE-----` lines included —
into the `DB_SSL_CA_CERT` environment variable in Render.

The entrypoint writes it to `storage/certs/aiven-ca.pem` on every boot, which is
where `MYSQL_ATTR_SSL_CA` already points. Nothing is committed, so the image
stays usable against any database, and rotating the CA is a dashboard edit
rather than a redeploy.

> Miss this and the connection fails with `SQLSTATE[HY000] [2002]`, which reads
> like bad credentials. The entrypoint checks for the file first and says so
> plainly instead.

### 2. Storage

Create an R2 bucket. Keep it **private** — project files are served through an
authorised controller (`/files/{file}`), never by public URL, and making the
bucket public would bypass every permission check in the app.

You need: Access Key ID, Secret, bucket name, and the endpoint
`https://<account-id>.r2.cloudflarestorage.com`.

### 3. Deploy

Push to GitHub, then in Render: **New → Blueprint**, point it at the repo. It
reads `render.yaml`. Fill in every variable marked `sync: false`.

The container runs migrations and syncs permissions on boot
(`docker/entrypoint.sh`), so there is no manual release step.

### 4. Create your account

Once the first deploy is green, open a shell on the service:

```bash
php artisan db:seed --force
```

That seeds roles, permissions, the three starter stage sets, company settings,
and your owner account — printing a temporary password. Change it immediately.

### 5. Put Cloudflare in front

**This is not optional for the public site.** Render's free tier sleeps after
~15 minutes idle, and a 30–60 second cold start on your marketing pages loses
visitors and drags on search ranking.

1. Add your domain to Cloudflare (free plan).
2. Point it at the Render URL with a CNAME, proxied (orange cloud).
3. Add a Cache Rule: for `/`, `/blog*`, `/work*`, `/videos`, `/about` →
   **Cache eligibility: eligible**, Edge TTL: respect origin headers.

The app already sends the right headers. Public pages run **without session
middleware** precisely so they carry no `Set-Cookie` and no `Cache-Control:
private` — a shared cache refuses to store a response with either, and the whole
strategy would silently do nothing.

Verify after setup:

```bash
curl -sI https://yourdomain.com/blog | grep -i -E "cache-control|cf-cache-status"
# expect: s-maxage=3600 ... and eventually cf-cache-status: HIT
```

### Known free-tier limits

- **Sleeping.** The scheduler (`schedule:work`) sleeps too. Retainer generation
  is written to catch up, so a missed billing day is picked up the next morning
  rather than lost — but it is not instant.
- **Ephemeral disk.** Nothing written to the container survives a deploy. This is
  why sessions, cache and queue are on the database, and uploads are on R2.
- **No cron.** The scheduler runs as a supervised process inside the web
  container instead.

---

## cPanel (when you move to paid hosting)

### What changes

Only environment variables:

| Setting | Render | cPanel |
|---|---|---|
| `DB_*` | Aiven | cPanel MySQL |
| `DB_SSL_CA_CERT` / `MYSQL_ATTR_SSL_CA` | Aiven CA | *(remove both — local socket)* |
| `FILESYSTEM_DISK` | `s3` | `local` |
| `SESSION_DRIVER` | `database` | `database` (keep) |
| `LOG_CHANNEL` | `stderr` | `stack` |

Files already uploaded to R2 keep resolving after the switch, because each row
records **its own disk**. New uploads land on local disk. Nothing needs migrating
unless you want to.

### Steps

1. **Upload above the web root.** The app goes in `~/neuro-codez`, *not* in
   `public_html`. Only `public/` may be web-reachable — everything else, `.env`
   included, must be unreachable over HTTP.

2. **Point the document root** at `~/neuro-codez/public` (cPanel → Domains →
   set document root). If your host will not allow that, symlink instead:

   ```bash
   rm -rf ~/public_html
   ln -s ~/neuro-codez/public ~/public_html
   ```

3. **Install dependencies** over SSH:

   ```bash
   cd ~/neuro-codez
   composer install --no-dev --optimize-autoloader
   ```

   No SSH? Run `composer install` locally and upload `vendor/` with the rest.

4. **Build assets locally and upload `public/build`.** Shared hosting rarely has
   Node, and it does not need it — Vite output is static.

5. **Migrate and cache:**

   ```bash
   php artisan migrate --force
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```

6. **Add the cron job** (cPanel → Cron Jobs, every minute):

   ```
   * * * * * cd ~/neuro-codez && php artisan schedule:run >> /dev/null 2>&1
   ```

   This replaces the supervised `schedule:work` process and is what keeps
   retainer billing and the YouTube sync running.

7. **Set permissions:**

   ```bash
   chmod -R 775 storage bootstrap/cache
   ```

8. **Narrow the trusted proxies.** `bootstrap/app.php` trusts `*`, which is
   correct on Render — the container has no public address, so the edge is the
   only way in and a forged `X-Forwarded-Proto` cannot arrive. On cPanel
   requests hit Apache directly, so change `at: '*'` to the server's own IP, or
   drop the `trustProxies()` call entirely if there is no proxy in front.

### Rehearse it before you pay

You can prove the switch works without buying anything:

```bash
FILESYSTEM_DISK=local php artisan test
mysqldump -u root -p neuro_codez > backup.sql   # then import into a fresh DB
```

If the suite passes with the local disk and a fresh MySQL import, the move is
config only.

---

## Backups

Nothing here is backed up by default. Aiven's free tier has no automated
backups, so take your own:

```bash
mysqldump --set-gtid-purged=OFF -h <host> -P <port> -u <user> -p <database> \
  | gzip > "neuro-codez-$(date +%F).sql.gz"
```

Worth scheduling weekly from your own machine. The database holds every payment,
invoice and commission record — the one thing in this system that cannot be
rebuilt.

---

## Post-deploy checklist

- [ ] `/up` returns 200
- [ ] **`/` and `/blog` return 200 — not just `/up`.** The health check passes
      without touching the database, so a broken connection still shows green
- [ ] View source on `/` and confirm the `/build/assets/…` links are `https://`
      *(proves the forwarded-proto handling; `http://` here means no CSS or JS)*
- [ ] Sign in as owner; change the seeded password
- [ ] `/blog` returns `s-maxage=3600` and **no** `Set-Cookie`
- [ ] `cf-cache-status: HIT` on a second request to `/`
- [ ] Upload a project file, redeploy, confirm it still downloads *(proves R2, not local disk)*
- [ ] Send yourself a staff invitation and complete it *(proves SMTP)*
- [ ] Open an invoice PDF and **print it** — confirm the purple rules are not clipped
- [ ] `php artisan youtube:sync` pulls real videos
- [ ] `php artisan retainers:generate --dry-run` reports sensibly
