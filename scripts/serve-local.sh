#!/usr/bin/env bash

set -euo pipefail

TASK_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

cd "$TASK_ROOT"

php artisan queue:work database \
    --queue=class-presentations,pedagogical-instruments \
    --tries=3 \
    --timeout=900 \
    --sleep=1 &
LOCAL_WORKER_PID=$!

stop_worker() {
    if kill -0 "$LOCAL_WORKER_PID" 2>/dev/null; then
        kill "$LOCAL_WORKER_PID" 2>/dev/null || true
        wait "$LOCAL_WORKER_PID" 2>/dev/null || true
    fi
}

trap stop_worker EXIT INT TERM HUP

cd "$TASK_ROOT/public"

php \
    -d upload_max_filesize=40M \
    -d post_max_size=128M \
    -d max_file_uploads=30 \
    -d memory_limit=512M \
    -S 127.0.0.1:8000 \
    ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
