#!/usr/bin/env bash

set -euo pipefail

TASK_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

cd "$TASK_ROOT"

LOCAL_BACKGROUND_PIDS=()

start_worker() {
    php artisan queue:work database "$@" &
    LOCAL_BACKGROUND_PIDS+=("$!")
}

stop_workers() {
    local worker_pid

    for worker_pid in "${LOCAL_BACKGROUND_PIDS[@]}"; do
        if kill -0 "$worker_pid" 2>/dev/null; then
            kill "$worker_pid" 2>/dev/null || true
        fi
    done

    for worker_pid in "${LOCAL_BACKGROUND_PIDS[@]}"; do
        wait "$worker_pid" 2>/dev/null || true
    done
}

trap stop_workers EXIT INT TERM HUP

# Las colas interactivas no comparten proceso con trabajos de larga duracion:
# un analisis pesado no debe bloquear broadcasts ni notificaciones del chat.
start_worker \
    --queue=broadcasts,notifications \
    --tries=3 \
    --timeout=60 \
    --sleep=1

start_worker \
    --queue=default \
    --tries=3 \
    --timeout=60 \
    --sleep=1

start_worker \
    --queue=class-presentations,pedagogical-instruments \
    --tries=3 \
    --timeout=900 \
    --sleep=1

cd "$TASK_ROOT/public"

php \
    -d upload_max_filesize=40M \
    -d post_max_size=128M \
    -d max_file_uploads=30 \
    -d memory_limit=512M \
    -S 127.0.0.1:8000 \
    ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
