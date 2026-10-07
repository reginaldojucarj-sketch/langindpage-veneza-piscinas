#!/bin/sh
set -eu

export VENEZA_SITE_TEST_MODE=1
export VENEZA_PUBLIC_API_ORIGIN=http://127.0.0.1:8091
php -S 127.0.0.1:8091 tests/fake-api.php >/dev/null 2>&1 &
api_pid=$!
php -S 127.0.0.1:8092 -t . tests/router.php >/dev/null 2>&1 &
site_pid=$!
trap 'kill "$api_pid" "$site_pid" 2>/dev/null || true' EXIT

php tests/knowledge-unit.php
php tests/http-assert.php
