#!/bin/sh
# Zero-downtime deploy, run on the VPS in ~/naraya-app after the push:
#   nohup ./deploy.sh > ~/deploy-<name>.log 2>&1 &
# 1. build the images while the running site keeps serving;
# 2. start a new app container beside the old one (it migrates, then must pass its healthcheck);
# 3. wait until nginx's DNS cache (5 s) knows both, then stop the old one gracefully — nginx retries a failed
#    connection on the other container within 1 s, so no visitor sees an error;
# 4. nginx is never restarted: assets reach it through a shared volume, its config is re-rendered and reloaded;
# 5. the scheduler and the sandbox (naraya-coba) follow the same image.
# Ends with DEPLOY_EXIT=0 on success; on a failed healthcheck the new container is removed and the old keeps serving.
set -eu
cd "$(dirname "$0")"
C="docker compose -f docker-compose.prod.yml"

echo "== build"
$C build app scheduler

echo "== new app container beside the old"
old=$($C ps -q app)
$C up -d --no-deps --no-recreate --scale app=$(( $(echo "$old" | grep -c . ) + 1 )) app
new=$($C ps -q app | grep -v -F "${old:-none}" || true)
[ -n "$new" ] || { echo "no new container started"; echo DEPLOY_EXIT=1; exit 1; }

echo "== waiting for $new to be healthy"
i=0
until [ "$(docker inspect -f '{{.State.Health.Status}}' "$new")" = healthy ]; do
    i=$((i + 1))
    if [ "$i" -gt 60 ] || [ "$(docker inspect -f '{{.State.Status}}' "$new")" != running ]; then
        echo "new container did not become healthy — keeping the old one"
        docker logs --tail 40 "$new" || true
        docker rm -f "$new" >/dev/null
        echo DEPLOY_EXIT=1
        exit 1
    fi
    sleep 5
done

echo "== retiring the old container"
sleep 8 # nginx re-resolves "app" every 5 s: let it see the new container before the old one goes
for o in $old; do
    docker stop -t 30 "$o" >/dev/null && docker rm "$o" >/dev/null
done

echo "== nginx: config re-rendered from the repo and reloaded (no restart), then the scheduler"
$C up -d --no-deps nginx # starts it if it is not running; a running one is left alone
$C exec -T nginx sh -c '/docker-entrypoint.d/20-envsubst-on-templates.sh >/dev/null && nginx -t -q && nginx -s reload'
$C up -d --no-deps scheduler

echo "== sandbox follows the same image"
if [ -f docker-compose.sandbox.yml ] && [ -f .env.sandbox ]; then
    docker compose -p naraya-coba -f docker-compose.sandbox.yml up -d
    docker compose -p naraya-coba -f docker-compose.sandbox.yml exec -T coba-nginx sh -c '/docker-entrypoint.d/20-envsubst-on-templates.sh >/dev/null && nginx -s reload'
fi

docker image prune -f >/dev/null
# Every deploy leaves ~200 MB of BuildKit cache and nothing else frees it: by 2026-10-16 it was 40 GB, 84% of the disk
# (and 73% of the inodes). Keep only the newest 8 GB (enough for the next build); it never fails a finished deploy.
docker builder prune -f --reserved-space 8GB >/dev/null 2>&1 || true
echo DEPLOY_EXIT=0
