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

# Seeds roles and permissions only. Idempotent, and it is what makes a new
# permission available after a deploy without a manual step.
echo "==> Syncing roles and permissions"
php artisan db:seed --class=PermissionSeeder --force

# storage/app/public is only used when FILESYSTEM_DISK=public. On Render the
# disk is ephemeral and uploads go to R2, so this is a no-op there.
if [ "${FILESYSTEM_DISK}" = "public" ]; then
    echo "==> Linking storage"
    php artisan storage:link || true
fi

echo "==> Ready"
exec "$@"
