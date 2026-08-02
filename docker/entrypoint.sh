#!/bin/sh
set -e

# Runs on every container boot, before nginx and FPM start.

cd /var/www/html

# APP_KEY must be set in the environment, not generated here. A container that
# invents its own key cannot decrypt anything the previous one wrote — every
# session and every encrypted column would break on each deploy.
if [ -z "${APP_KEY}" ]; then
    echo "FATAL: APP_KEY is not set. Generate one locally with 'php artisan key:generate --show'"
    echo "and add it to the environment. Do not let the container invent one."
    exit 1
fi

# A missing DB_CONNECTION falls back to sqlite, and Laravel will happily create
# the file and migrate into it. The container then looks perfectly healthy while
# writing every payment and invoice to an ephemeral disk that is erased on the
# next deploy. Silent data loss is worse than a failed boot, so refuse.
if [ "${APP_ENV}" = "production" ] && [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    echo "FATAL: DB_CONNECTION is sqlite (or unset) in production."
    echo "The container disk is ephemeral — everything written there is destroyed"
    echo "on the next deploy. Set DB_CONNECTION=mysql and the DB_* credentials."
    exit 1
fi

# Aiven refuses unencrypted connections, so PDO needs their CA on disk. Rather
# than committing it, paste the certificate into the DB_SSL_CA_CERT environment
# variable and it gets written here on boot — the image stays generic, and
# rotating the CA is a dashboard edit rather than a redeploy.
if [ -n "${DB_SSL_CA_CERT}" ]; then
    echo "==> Writing database CA certificate"
    mkdir -p /var/www/html/storage/certs
    printf '%s\n' "${DB_SSL_CA_CERT}" > /var/www/html/storage/certs/aiven-ca.pem
    # 644, not 600. This script runs as root but php-fpm serves requests as
    # www-data, and a CA certificate it cannot read fails the TLS handshake with
    # "Cannot connect to MySQL using SSL". That failure is invisible at deploy
    # time — migrations and /up both succeed as root — and every actual page
    # then returns 500. The certificate is public anyway; it is not a secret.
    chmod 644 /var/www/html/storage/certs/aiven-ca.pem
fi

# Catch the mismatch explicitly. PDO's own failure here is "SQLSTATE[HY000]
# [2002]", which says nothing about a missing file and sends you looking at
# credentials instead.
if [ -n "${MYSQL_ATTR_SSL_CA}" ] && [ ! -f "${MYSQL_ATTR_SSL_CA}" ]; then
    echo "FATAL: MYSQL_ATTR_SSL_CA points at ${MYSQL_ATTR_SSL_CA}, which does not exist."
    echo "Paste the CA certificate from the Aiven console into the DB_SSL_CA_CERT"
    echo "environment variable, or unset MYSQL_ATTR_SSL_CA if the database does"
    echo "not require TLS."
    exit 1
fi

echo "==> Caching configuration"
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migrations run automatically. --force only skips the interactive confirmation.
#
# Deliberately NOT --isolated: that takes its lock through the cache store, which
# is `database` here, and `cache_locks` does not exist until migrations have run.
# On a fresh database the very first deploy would fail trying to lock a table it
# is about to create.
#
# The lock it would provide guards against two containers migrating at once.
# Render's free tier runs a single instance, so that cannot happen. If you ever
# scale to more than one, run migrations as a separate release step instead of
# on boot.
echo "==> Running migrations"
php artisan migrate --force

# The FULL seeder, not just permissions.
#
# Render's free tier has no shell, so there is no way to run this by hand after
# a deploy — and without it there is no owner account, meaning nobody can sign
# in at all. Every seeder here is idempotent: StageSetSeeder returns early if
# any set exists, CompanySetting uses firstOrCreate, and the owner is looked up
# by email before being created.
#
# Set SEED_OWNER_EMAIL and SEED_OWNER_PASSWORD in the environment to choose the
# first account's credentials. Without them a random password is generated and
# printed below — readable in the Render deploy log, but only on the boot that
# actually creates the account.
echo "==> Seeding roles, permissions, stage sets and the owner account"
php artisan db:seed --force

# Populate the videos table immediately rather than waiting for the hourly
# scheduled run — on a free instance that sleeps, "hourly" can be a long time.
#
# `|| true` is essential: this script runs under `set -e`, and youtube:sync
# returns a non-zero exit when the feed is unreachable. Without it, YouTube
# having a bad day would stop the container from booting at all.
echo "==> Syncing YouTube videos"
php artisan youtube:sync || true

# storage/app/public is only used when FILESYSTEM_DISK=public. On Render the
# disk is ephemeral and uploads go to R2, so this is a no-op there.
if [ "${FILESYSTEM_DISK}" = "public" ]; then
    echo "==> Linking storage"
    php artisan storage:link || true
fi

echo "==> Ready"
exec "$@"
