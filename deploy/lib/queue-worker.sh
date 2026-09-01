#!/usr/bin/env bash
# systemd queue worker for Laravel jobs.
set -euo pipefail

install_queue_worker() {
    echo "==> Installing lacmp-panel-queue.service"
    install -m 0644 "${ROOT}/deploy/systemd/lacmp-panel-queue.service" \
        /etc/systemd/system/lacmp-panel-queue.service

    sed -i \
        -e "s|@PREFIX@|${PREFIX}|g" \
        -e "s|@WEB_USER@|${WEB_USER}|g" \
        -e "s|@PHP_BIN@|${PHP_BIN}|g" \
        /etc/systemd/system/lacmp-panel-queue.service

    systemctl daemon-reload
    systemctl enable --now lacmp-panel-queue.service
    systemctl restart lacmp-panel-queue.service
}

dispatch_ping_job() {
    echo "==> Dispatching PingJob to verify queue worker"
    run_as_web "${PHP_BIN} artisan tinker --execute=\"App\\\\Jobs\\\\PingJob::dispatch();\"" \
        >/dev/null 2>&1 || true
}
