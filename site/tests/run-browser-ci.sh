#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."

docker image inspect pena-access-tests >/dev/null
docker image inspect pena-browser-e2e:ci >/dev/null
network=pena-site-e2e-net-ci
server=pena-site-e2e-server-ci
if docker network inspect "$network" >/dev/null 2>&1 || docker container inspect "$server" >/dev/null 2>&1; then
  echo 'Refusing to reuse the synthetic site browser-test resources.' >&2
  exit 1
fi

created_network=false
created_server=false
cleanup() {
  if [ "$created_server" = true ]; then docker rm -f "$server" >/dev/null 2>&1 || true; fi
  if [ "$created_network" = true ]; then docker network rm "$network" >/dev/null 2>&1 || true; fi
}
trap cleanup EXIT

docker network create "$network" >/dev/null
created_network=true
docker run -d --rm --name "$server" --network "$network" \
  --network-alias pena-site-e2e-server \
  --mount "type=bind,source=$PWD/site,target=/site,readonly" -w /site \
  -e VENEZA_SITE_TEST_MODE=1 -e VENEZA_PUBLIC_API_ORIGIN=http://127.0.0.1:8091 \
  pena-access-tests sh -c \
  'php -S 127.0.0.1:8091 tests/fake-api.php >/dev/null 2>&1 & VENEZA_PUBLIC_API_ORIGIN=https://api-homolog.example.test php -S 0.0.0.0:8093 -t . tests/router.php >/dev/null 2>&1 & php -S 0.0.0.0:8092 -t . tests/router.php' >/dev/null
created_server=true

ready=false
for attempt in $(seq 1 30); do
  if docker run --rm --network "$network" pena-browser-e2e:ci \
    wget -q -O /dev/null http://pena-site-e2e-server:8092/conhecimento/ && \
    docker run --rm --network "$network" pena-browser-e2e:ci \
    wget -q -O /dev/null http://pena-site-e2e-server:8093/conhecimento/; then
    ready=true
    break
  fi
  sleep 1
done
if [ "$ready" != true ]; then
  echo 'Synthetic site server did not become ready.' >&2
  exit 1
fi

docker run --rm --network "$network" \
  --mount "type=bind,source=$PWD/site/tests,target=/site-tests,readonly" \
  -e SITE_E2E_ORIGIN=http://pena-site-e2e-server:8092 \
  -e SITE_E2E_REMOTE_ORIGIN=http://pena-site-e2e-server:8093 \
  pena-browser-e2e:ci node /site-tests/browser.mjs

# Static responsive checks use an in-process fixture and block external access.
docker run --rm --network none \
  --mount "type=bind,source=$PWD/site,target=/site,readonly" \
  --entrypoint node pena-browser-e2e:ci /site/tests/responsive-smoke.mjs
