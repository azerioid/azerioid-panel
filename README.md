# Stack Manager

Self-contained Linux host control plane. Bootstrap installs **Caddy**, **PHP 8.4 FPM**, and a **SQLite**-backed Laravel panel — no pre-installed `lcmp`/`lamp` stack required.

## Quick start

```bash
git clone <repo> stack-manager && cd stack-manager
chmod +x stack-manager.sh
sudo ./stack-manager.sh --non-interactive
```

Panel default: `http://127.0.0.1:3169` (SSH tunnel: `ssh -L 3169:127.0.0.1:3169 user@host`)

## Supported OS

- Ubuntu 24.04
- Debian 12
- Alma/Rocky/RHEL/OL 9+ (SELinux enforcing supported)

## Layout

| Path | Purpose |
|------|---------|
| `stack-manager.sh` | Bootstrap entrypoint |
| `deploy/install.sh` | Modular installer |
| `deploy/lib/` | Installer modules |
| `broker/` | Privileged host broker |
| `web/` | Laravel 12 + Livewire UI |
| `registry/` | Component metadata (P0 stubs) |
| `docs/SPEC.md` | Master specification |

## Smoke test

After install on a VM:

```bash
sudo ./deploy/test/smoke-p1.sh
```

## Uninstall

```bash
sudo ./deploy/uninstall.sh --drop-db --remove-bootstrap
```

## Phase 1 scope

- Self-contained bootstrap (Caddy + PHP 8.4 + SQLite)
- Queue worker (`lacmp-panel-queue.service`)
- Settings panel runtime section + Components system cards stub
- `panel.runtime` broker action

Not in P1: component install/remove, adopt flow, MariaDB panel DB, `migrate.sh`.

See `docs/SPEC.md` and `docs/DECISIONS.md` for locked architecture decisions.
