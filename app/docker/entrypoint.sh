#!/bin/sh
set -e

# storage/ is bind-mounted from the host in prod (docker-compose.prod.yml),
# so the Dockerfile's build-time chown never touches the real files — a
# host-side ownership drift (e.g. wrong group) silently breaks every FPM
# worker's ability to write storage/logs/laravel.log, which surfaces as an
# unexplained 500 with no log entry (the log write itself is what fails).
# Self-heal on every boot instead of relying on the host state staying correct.
# Group-only: the host's git deploy hook runs as the host user and needs to
# stay the owner (setgid on these dirs already makes new files inherit the
# group below, which is all www-data actually needs to read/write them).
chown -R :www-data storage

# Compiled Blade views live in the bind-mounted storage/, so they outlive the image. During a zero-downtime deploy
# the old and the new container run side by side (deploy.sh): each image compiles into its own folder, named after
# its build, so neither ever serves the other's markup. The three newest folders stay; older ones go.
VIEW_COMPILED_PATH="/var/www/html/storage/framework/views/$(cat .build-id)"
export VIEW_COMPILED_PATH
mkdir -p "$VIEW_COMPILED_PATH"
chown :www-data "$VIEW_COMPILED_PATH" && chmod 2775 "$VIEW_COMPILED_PATH"
find storage/framework/views -maxdepth 1 -type f -name '*.php' -delete # the old shared folder, before per-build ones
ls -1dt storage/framework/views/*/ 2>/dev/null | tail -n +4 | xargs -r rm -rf

# nginx serves public/ from a volume this container fills (docker-compose.prod.yml), so nginx never has to be rebuilt
# or restarted for new assets. New files go in; old build assets stay for pages still open in someone's browser.
if [ -d /shared-public ]; then
    cp -r public/. /shared-public/
fi

# `docker compose run app <command>` runs just the command
if [ $# -gt 0 ]; then
    exec "$@"
fi

# Migrations run before this container takes traffic, while the previous one still serves: they must only add
# (new tables, nullable or defaulted columns) — drop or rename in a later deploy, once no running code needs the old.
php artisan migrate --force
exec php-fpm
