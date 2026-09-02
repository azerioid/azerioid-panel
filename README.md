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

| `/usr/local/lib/azerioid-panel` | Panel install root |
| `/var/lib/azerioid-panel/panel.sqlite` | Panel database |
| `/etc/azerioid-panel/broker.json` | Broker secrets/config |

Upgrading from an older `lacmp-panel` install:

```bash
sudo ./deploy/relocate-from-lacmp.sh
```

## Smoke test

After install on a VM:

```bash
sudo ./deploy/test/smoke-p1.sh
```

## Uninstall

```bash
sudo ./deploy/uninstall.sh --drop-db --remove-bootstrap
```

Full teardown (panel + managed components + repos; still skips `/data/www`):

```bash
sudo ./deploy/uninstall.sh --full
```

## Phase 7 scope

- `sudo ./deploy/migrate.sh` — legacy MariaDB panel DB (`lacmp_panel`) → SQLite
- Run after adopting MariaDB on upgraded hosts; site databases are not modified
- Smoke: `sudo ./deploy/test/smoke-p7.sh`

## Phase 6 scope

- PHP 8.1/8.2/8.3, Node.js (major version picker), Memcached, MongoDB install from Components UI
- Broker repo helpers: Sury/Remi, NodeSource, MongoDB 8.0 apt/yum repos
- `component_operations.options` JSON for install parameters
- Smoke: `sudo ./deploy/test/smoke-p6.sh`

## Phase 3 scope

- Background `RunComponentOperationJob` + `component_operations` table
- Broker: `component.preflight`, `component.install`, `component.uninstall`, `component.operation.log`
- Package mutex (`/var/lib/azerioid-panel/staging/package.lock`), apt lock timeout, dpkg repair
- Redis install/uninstall from Components UI (registry-gated)
- Managed components appear on Services page after install
- Smoke: `sudo ./deploy/test/smoke-p3.sh`

## Phase 2 scope

- Registry-driven `component.list` / `component.status` broker actions
- Read-only Components page (system / managed / observed / broken)
- Registry deployed to `/usr/local/lib/azerioid-panel/registry/`
- Smoke: `sudo ./deploy/test/smoke-p2.sh`

## Phase 1 scope

- Self-contained bootstrap (Caddy + PHP 8.4 + SQLite)
- Queue worker (`azerioid-panel-queue.service`)
- Settings panel runtime section + Components system cards stub
- `panel.runtime` broker action

Not in P1: component install/remove, adopt flow, MariaDB panel DB (use `deploy/migrate.sh` after upgrade).

See `docs/SPEC.md` and `docs/DECISIONS.md` for locked architecture decisions.
