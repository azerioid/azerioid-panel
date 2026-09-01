# Stack Manager

Self-contained Linux host control plane. Bootstrap installs **Caddy + PHP 8.4 + SQLite** with zero pre-existing stack.

Adapted from [lacmp_gui](https://github.com/azerioid/lacmp_gui) (v1 LACMP Panel). Install paths remain `/usr/local/lib/lacmp-panel` until a later rename phase.

## Quick start (bootstrap)

```bash
# as root on Ubuntu 24.04, Debian 12, or EL 9
git clone <this-repo> stack-manager
cd stack-manager
chmod +x stack-manager.sh
./stack-manager.sh
```

Default access: SSH tunnel to `http://127.0.0.1:3169`

```bash
ssh -L 3169:127.0.0.1:3169 user@host
```

## What bootstrap installs

| Component | Role |
|-----------|------|
| Caddy | Panel vhost on `127.0.0.1:3169` |
| PHP 8.4 FPM | Dedicated panel pool (`/run/php/lacmp-panel.sock`) |
| SQLite | Panel DB at `/var/lib/lacmp-panel/panel.sqlite` |
| Queue worker | `lacmp-panel-queue.service` |

Bootstrap metadata: `/etc/lacmp-panel/bootstrap.json`

## Project layout

```
docs/           SPEC, decisions, port ownership
registry/       Component schema + detect/distro stubs
broker/         Privileged host broker (PHP)
web/            Laravel 12 + Livewire panel
deploy/         Installer modules, cloud-init, smoke tests
stack-manager.sh Entrypoint
```

## Smoke test

Every phase exit runs against Ubuntu 24.04, Debian 12, and EL 9:

```bash
./deploy/test/smoke-p1.sh
```

Cloud-init templates: `deploy/test/cloud-init/`

## Uninstall

```bash
./deploy/uninstall.sh
./deploy/uninstall.sh --drop-db --remove-bootstrap-pkgs
```

Never touches `/data/www` or user sites.

## Deferred (not in P0/P1)

- Component install/remove (P3+)
- MariaDB panel DB / migrate.sh (P7)
- Adopt flow for legacy lcmp/lamp (P5)

See `docs/SPEC.md` for full architecture.
