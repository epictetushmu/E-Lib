#!/usr/bin/env bash
# E-Lib deployment: sync the code to the server and (re)build the Docker stack there.
#
#   DEPLOY_HOST=user@server DEPLOY_DIR=/srv/e-lib ./deploy.sh
#
# The server needs Docker + the compose plugin. .env.production (next to this script) becomes the
# server's .env. Uploaded books live in the `uploads` Docker volume, so redeploys never touch them —
# see README "Production deployment" for the one-time import of existing uploads.
set -euo pipefail

: "${DEPLOY_HOST:?Set DEPLOY_HOST, e.g. user@your-server}"
: "${DEPLOY_DIR:?Set DEPLOY_DIR, e.g. /srv/e-lib}"
SOURCE_DIR="$(cd "$(dirname "$0")" && pwd)"
ENV_FILE="$SOURCE_DIR/.env.production"

if [ ! -f "$ENV_FILE" ]; then
    echo "Missing $ENV_FILE — copy .env.example to it and fill in production values." >&2
    exit 1
fi
# The local-dev COMPOSE_FILE would make the server run the dev overrides (debug on, code bind-mounted).
if grep -q '^COMPOSE_FILE=' "$ENV_FILE"; then
    echo "$ENV_FILE sets COMPOSE_FILE — remove it; production uses docker-compose.yml alone." >&2
    exit 1
fi

if ! grep -qE '^COMPOSE_PROFILES=.*search' "$ENV_FILE"; then
    echo "Note: $ENV_FILE doesn't set COMPOSE_PROFILES=search — content search's worker and model won't run." >&2
fi

echo "Syncing code to $DEPLOY_HOST:$DEPLOY_DIR..."
ssh "$DEPLOY_HOST" "mkdir -p '$DEPLOY_DIR'"
rsync -az --delete \
    --exclude '.git' --exclude 'node_modules' --exclude 'vendor' --exclude 'public/dist' \
    --exclude 'public/assets/uploads' --exclude 'storage/logs' --exclude 'cache' \
    --exclude '.env' --exclude '.env.*' \
    "$SOURCE_DIR/" "$DEPLOY_HOST:$DEPLOY_DIR/"
rsync -az "$ENV_FILE" "$DEPLOY_HOST:$DEPLOY_DIR/.env"

echo "Building and restarting containers..."
ssh "$DEPLOY_HOST" "cd '$DEPLOY_DIR' && docker compose up -d --build --remove-orphans"

echo "Checking the app answers..."
for _ in $(seq 1 15); do
    if ssh "$DEPLOY_HOST" "cd '$DEPLOY_DIR' && docker compose exec -T app curl -sf http://localhost/ -o /dev/null"; then
        echo "Deployed."
        exit 0
    fi
    sleep 4
done
echo "App did not respond — check: ssh $DEPLOY_HOST 'cd $DEPLOY_DIR && docker compose logs app'" >&2
exit 1
