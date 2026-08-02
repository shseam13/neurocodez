# Neuro Codez

The business system for Neuro Codez: a public marketing site, plus an admin app
that tracks clients, projects, money in and commission out — with separate
portals for clients and partners.

Built to solve four specific problems: forgetting what was agreed for a project,
losing project files, losing track of what has been paid, and forgetting what is
owed to whoever brought the work in.

## Stack

| | |
|---|---|
| **Laravel 13** on PHP 8.4+ | |
| **MySQL 8** | Chosen over Postgres so a later move to cPanel is a config change, not a rewrite |
| **Tailwind 4 + Blade** | CSS-first theming; no separate frontend build to maintain |
| **dompdf** | Invoice PDFs |
| **spatie/laravel-permission** | Roles and permissions |

## Getting started

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# Create the database, then:
php artisan migrate --seed                    # roles, permissions, stage sets, owner account
php artisan db:seed --class=DemoDataSeeder    # optional sample records

npm run build
php artisan serve
```

`migrate --seed` prints a temporary owner password. Change it after first sign-in.

### Tests

```bash
php artisan test
```

Tests run against **MySQL**, not SQLite — the whole reason this project uses
MySQL is portability, and SQLite differs on the things that bite (enum columns,
strict mode, date handling). Create a `neuro_codez_test` database first.

## How it is organised

```
app/
  Enums/          AccountType, ChargeKind, CommissionBasis, BillingTarget, Permission…
  Models/         Client, Partner, Project, ProjectCharge, Invoice, Post…
  Policies/       Authorisation. Every check goes through one of these.
  Services/       Business logic. Controllers stay thin.
  Support/        Money, Percent, AmountInWords
resources/views/
  public/         The marketing site
  admin/          The staff app
  portal/         Client and partner portals
  pdf/            Invoice template — its own stylesheet, shares nothing with the app
tools/brand/      Regenerates logo assets from brand/source/
```

## Decisions worth knowing before changing things

**Money is stored as integers in minor units (poisha), never floats.**
`(int) (1.15 * 100)` is `114`, and that error compounds across partial payments
until figures stop reconciling. Use the `Money` value object — and do not reach
past it to `->minor` when assigning to a cast column.

**A project's commission rate is snapshotted at creation.** Changing a partner's
default must never alter what is owed on work already agreed.

**`agreed_amount` is never edited to absorb extra work.** Additions become
`project_charges` rows, so the original figure stays visible alongside every
change — which is what you need if a client disputes a bill.

**Stages have per-audience visibility.** Internal stages collapse into the
previous visible one for clients and partners, so they see steady progress
rather than your working steps.

**The portfolio is a separate table from projects.** A flag on `projects` would
mean one careless query publishes client names and money. A curated table makes
that structurally impossible.

**Public pages run without session middleware.** `StartSession` stamps
`Cache-Control: private` and a `Set-Cookie`, and a shared cache refuses to store
a response carrying either — leaving them on would silently disable CDN caching.

**Uploaded files are never public.** They live outside the web root and are
served through an authorised controller rather than a signed URL, because signed
links stay valid for anyone they are forwarded to.

## Deployment

See [DEPLOYMENT.md](DEPLOYMENT.md) — runbooks for Render (free) and cPanel
(later), written together so moving between them is configuration only.

## Scheduled commands

| Command | When | What |
|---|---|---|
| `youtube:sync` | hourly | Mirrors the channel RSS feed into the local `videos` table |
| `retainers:generate` | daily | Creates each month's retainer charge; idempotent, and catches up after a missed day |
