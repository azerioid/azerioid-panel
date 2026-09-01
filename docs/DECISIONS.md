# Architecture Decision Log

ADR-style record of locked decisions for Stack Manager.

## A19 — Clean history

**Status:** Accepted  
**Decision:** New repo `azerioid-panel` with clean git history. Copy/adapt from `lcmp-gui`; do not carry v1 git baggage. Install paths remain `/usr/local/lib/lacmp-panel` until P7 rename.

## A1 — Panel PHP isolation

**Status:** Accepted  
**Decision:** Panel PHP visible in Settings (runtime section) and Components (non-removable system card). Pinned to PHP 8.4. Broker refuses removal while panel depends on it. Dedicated FPM pool at `/run/php/lacmp-panel.sock`, user `caddy`.

## A2 — Migration policy

**Status:** Accepted  
**Decision:** Adopt + `migrate.sh` for legacy installs. P1 targets fresh installs only. `migrate.sh` deferred to P7; adopt flow to P5.

## A3 — SELinux (EL)

**Status:** Accepted (P1 minimum)  
**Decision:** On EL with enforcing SELinux: `semanage port` for 3169, fcontext for `/data/www` and `/var/lib/lacmp-panel`, `httpd_can_network_connect`. Package: `policycoreutils-python-utils`.

## A4 — Distro package names

**Status:** Accepted  
**Decision:** Per-component `distros.{debian,ubuntu,el}` blocks in registry JSON with `packages`, `unit_name`, `detect`, and `repo` metadata.

## A5 — Package manager locks

**Status:** Partial (P1)  
**Decision:** `DPkg::Lock::Timeout=120` on apt. Full mutex deferred to P3.

## A6 — Job system

**Status:** Accepted (P1 foundation)  
**Decision:** `QUEUE_CONNECTION=database`, systemd queue worker unit, `PingJob` placeholder.

## A8 — Security defaults

**Status:** Deferred to P3+  
**Decision:** Component `secure` blocks in registry; applied after install in P3+.

## A9 — Port ownership

**Status:** Accepted  
**Decision:** See `docs/port-ownership.md`. Panel Caddy always single instance; snippet in `/etc/caddy/conf.d/`.

## A10 — GPG pinning

**Status:** Accepted (P1)  
**Decision:** Caddy official repo + Sury (deb) / Remi (EL) with GPG fingerprint verification in `deploy/lib/repos.sh`.

## A15 — RBAC

**Status:** Accepted  
**Decision:** v1 single admin. `users.role` nullable for future.

## A16 — Node scope

**Status:** Accepted  
**Decision:** v1 Node = runtime install only; no PM2.

## Product naming

**Status:** Accepted  
**Decision:** User-facing name "Stack Manager". Entrypoint `stack-manager.sh`. Drop "LACMP Panel" from P1 user-facing copy.

## Panel database

**Status:** Accepted (P1)  
**Decision:** SQLite at `/var/lib/lacmp-panel/panel.sqlite`. No MariaDB panel DB in bootstrap.

## Bootstrap stack

**Status:** Accepted (P1)  
**Decision:** Self-contained installer installs Caddy + PHP 8.4 + SQLite. No `lcmp`/`lamp` prerequisite. Legacy detection becomes adopt path (P5).
