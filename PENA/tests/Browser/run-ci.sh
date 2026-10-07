#!/usr/bin/env bash
set -euo pipefail

# Git Bash rewrites /app into its own installation path unless conversion is disabled.
case "$(uname -s)" in
  MINGW*|MSYS*) export MSYS_NO_PATHCONV=1 ;;
esac

# Run only against the synthetic SQLite fixture, never a configured PENA database.
cd "$(dirname "$0")/../../.."
test ! -f PENA/bootstrap/cache/config.php
if [ -n "${PENA_BROWSER_SCREENSHOT_DIR:-}" ]; then
  if [ ! -d "$PENA_BROWSER_SCREENSHOT_DIR" ]; then
    echo 'PENA_BROWSER_SCREENSHOT_DIR must be an existing directory.' >&2
    exit 1
  fi
  screenshot_target="$(cd "$PENA_BROWSER_SCREENSHOT_DIR" && pwd -P)"
  workspace_target="$(pwd -P)"
  case "$screenshot_target" in
    "$workspace_target"|"$workspace_target"/*)
      echo 'PENA_BROWSER_SCREENSHOT_DIR must be outside the repository.' >&2
      exit 1
      ;;
  esac
fi
if ! docker image inspect pena-access-tests >/dev/null 2>&1; then
  echo 'Build the synthetic PHP test image first: docker build -t pena-access-tests PENA' >&2
  exit 1
fi

network=pena-browser-e2e-net-ci
database=pena-browser-e2e-data-ci
storage=pena-browser-e2e-storage-ci
server=pena-browser-e2e-server-ci
for resource in "network $network" "volume $database" "volume $storage" "container $server"; do
  read -r kind name <<< "$resource"
  if docker "$kind" inspect "$name" >/dev/null 2>&1; then
    echo "Refusing to reuse existing Docker $kind: $name" >&2
    exit 1
  fi
done

created_network=false
created_database=false
created_storage=false
created_server=false
cleanup() {
  if [ "$created_server" = true ]; then docker rm -f "$server" >/dev/null 2>&1 || true; fi
  if [ "$created_storage" = true ]; then docker volume rm "$storage" >/dev/null 2>&1 || true; fi
  if [ "$created_database" = true ]; then docker volume rm "$database" >/dev/null 2>&1 || true; fi
  if [ "$created_network" = true ]; then docker network rm "$network" >/dev/null 2>&1 || true; fi
}
trap cleanup EXIT

docker network create --internal "$network" >/dev/null
created_network=true
docker volume create "$database" >/dev/null
created_database=true
docker volume create "$storage" >/dev/null
created_storage=true
docker build -q -t pena-browser-e2e:ci PENA/tests/Browser >/dev/null

app_key="base64:$(openssl rand -base64 32)"
environment=(
  -e APP_ENV=testing -e APP_DEBUG=false -e "APP_KEY=$app_key"
  -e DB_CONNECTION=sqlite -e DB_DATABASE=/tmp/pena-browser-test.sqlite -e DB_URL=
  -e SESSION_DRIVER=cookie -e CACHE_STORE=array -e LOG_CHANNEL=stderr
  -e VIEW_COMPILED_PATH=/tmp/pena-browser-views
  -e PENA_API_HOST=pena-browser-e2e-server
  -e APP_URL=http://pena-browser-e2e-server:8000
  -e PENA_EDITORIAL_WRITES_ENABLED=true
)
mounts=(
  --mount "type=bind,source=$PWD/PENA,target=/app,readonly"
  --mount "type=volume,source=$database,target=/tmp"
  --mount "type=volume,source=$storage,target=/app/storage"
)

docker run --rm --network none "${mounts[@]}" -w /app \
  "${environment[@]}" -e PENA_SYNTHETIC_BROWSER_TEST=1 \
  pena-access-tests php tests/Browser/prepare.php

docker run -d --rm --name "$server" --network "$network" \
  --network-alias pena-browser-e2e-server "${mounts[@]}" -w /app \
  "${environment[@]}" pena-access-tests sh -c \
  'mkdir -p /tmp/pena-browser-views /app/storage/framework/cache /app/storage/framework/sessions /app/storage/framework/views /app/storage/logs && php artisan serve --no-reload --host=0.0.0.0 --port=8000' >/dev/null
created_server=true

ready=false
for attempt in $(seq 1 30); do
  if docker run --rm --network "$network" pena-browser-e2e:ci \
    wget -q -O /dev/null http://pena-browser-e2e-server:8000/admin/login; then
    ready=true
    break
  fi
  sleep 1
done
if [ "$ready" != true ]; then
  docker logs --tail 80 "$server"
  echo 'Synthetic browser server did not become ready.' >&2
  exit 1
fi

browser_mounts=()
browser_environment=()
if [ -n "${PENA_BROWSER_SCREENSHOT_DIR:-}" ]; then
  browser_mounts=(--mount "type=bind,source=$PENA_BROWSER_SCREENSHOT_DIR,target=/screenshots")
  browser_environment=(-e PENA_BROWSER_SCREENSHOT_DIR=/screenshots)
fi

docker run --rm --network "$network" "${browser_mounts[@]}" "${browser_environment[@]}" \
  -e PENA_BROWSER_ORIGIN=http://pena-browser-e2e-server:8000 \
  pena-browser-e2e:ci
