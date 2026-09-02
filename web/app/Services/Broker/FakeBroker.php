<?php

namespace App\Services\Broker;

/**
 * Local / test stand-in. Mirrors broker JSON shapes so the UI can be
 * developed on a Mac without sudo or an LACMP host.
 */
final class FakeBroker
{
    /** @var array<string, mixed> */
    public array $vhosts;

    /** @var array<int, array<string, mixed>> */
    public array $databases;

    public string $databaseEngine = 'mariadb';

    public bool $postgresqlConfigured = false;

    public bool $refuseReadonlyDeletes = true;

    public bool $failNextValidate = false;

    public bool $failNextDbAdd = false;

    public bool $php82Failed = true;

    /** @var array<string, true> */
    public array $fakeObservedComponents = [];

    public function __construct()
    {
        $this->reset();
    }

    public function reset(): void
    {
        $this->failNextValidate = false;
        $this->failNextDbAdd = false;
        $this->php82Failed = true;
        $this->fakeInstalledComponents = [];
        $this->fakeObservedComponents = [];
        $this->databaseEngine = 'mariadb';
        $this->postgresqlConfigured = false;
        $this->vhosts = [
            [
                'domains' => ['projob.az'],
                'domain' => 'projob.az',
                'root' => '/data/www/projob.az',
                'php_socket' => null,
                'php_version' => null,
                'type' => 'proxy',
                'tls' => true,
                'reverse_proxy' => '127.0.0.1:3000',
                'readonly' => true,
                'enabled' => true,
                'source' => '/etc/caddy/conf.d/projob.az.conf',
            ],
            [
                'domains' => [],
                'domain' => 'default',
                'root' => '/data/www/default',
                'php_socket' => 'unix//run/php/php-fpm.sock',
                'php_version' => '8.4',
                'type' => 'php',
                'tls' => false,
                'reverse_proxy' => null,
                'readonly' => true,
                'enabled' => true,
                'source' => '/etc/caddy/conf.d/default.conf',
            ],
        ];
        $this->databases = [
            ['name' => 'mysql', 'size_bytes' => 1_200_000, 'table_count' => 31, 'users' => [], 'protected' => true],
            ['name' => 'lacmp_panel', 'size_bytes' => 80_000, 'table_count' => 6, 'users' => [['user' => 'lacmp_panel', 'host' => 'localhost']], 'protected' => true],
            ['name' => 'projob', 'size_bytes' => 42_000_000, 'table_count' => 48, 'users' => [['user' => 'projob', 'host' => 'localhost']], 'protected' => false],
        ];
    }

    public function handle(string $action, array $args, array $stdin): BrokerResponse
    {
        try {
            $data = match ($action) {
                'status.all' => $this->statusAll(),
                'panel.runtime' => [
                    'php_version' => '8.4',
                    'fpm_socket' => '/run/php/azerioid-panel.sock',
                    'fpm_pool' => 'azerioid-panel',
                    'fpm_service' => 'php8.4-fpm',
                    'queue_unit' => 'azerioid-panel-queue.service',
                    'queue_active' => true,
                    'queue_status' => $this->svc('azerioid-panel-queue'),
                    'system' => true,
                    'removable' => false,
                ],
                'component.list' => $this->componentList(),
                'component.status' => $this->componentStatus((string) ($args[0] ?? '')),
                'component.preflight' => $this->componentPreflight((string) ($args[0] ?? '')),
                'component.install' => $this->componentInstall((string) ($args[0] ?? ''), $stdin),
                'component.adopt' => $this->componentAdopt((string) ($args[0] ?? '')),
                'component.uninstall' => $this->componentUninstall((string) ($args[0] ?? ''), $stdin),
                'component.operation.log' => $this->componentOperationLog((string) ($args[0] ?? '')),
                'version.all' => $this->versionAll(),
                'metrics.system' => $this->metrics(),
                'service.status' => [
                    'parsed' => $this->svc($args[0] ?? 'caddy'),
                    'raw' => "● {$args[0]}.service - fake\n   Active: active (running)",
                    'journal' => 'fake journalctl -xeu '.$args[0],
                ],
                'service.start', 'service.stop', 'service.restart' => ['unit' => $args[0] ?? '', 'action' => explode('.', $action)[1], 'status' => $this->svc($args[0] ?? 'caddy')],
                'vhost.list' => ['vhosts' => $this->vhosts],
                'vhost.add' => $this->vhostAdd($args, $stdin),
                'vhost.edit' => $this->vhostEdit($args, $stdin),
                'vhost.del' => $this->vhostDel($args),
                'db.list' => ['engine' => $this->databaseEngine, 'databases' => $this->databases],
                'db.engine' => $this->dbEngine(),
                'db.dump' => $this->dbDump($args),
                'db.add' => $this->dbAdd($args, $stdin),
                'db.del' => $this->dbDel($args),
                'db.resetpw' => ['user' => $args[0] ?? '', 'reset' => true],
                'logs.tail' => $this->logs($args),
                'php.versions' => $this->phpVersions(),
                'php.ini.get' => ['php_version' => $args[0] ?? '8.4', 'path' => '/etc/php/8.4/fpm/php.ini', 'values' => ['memory_limit' => '128M', 'upload_max_filesize' => '128M', 'post_max_size' => '128M', 'max_execution_time' => '300', 'max_input_time' => '300', 'max_file_uploads' => '20', 'expose_php' => 'Off']],
                'php.ini.set' => ['php_version' => $args[0] ?? '8.4', 'key' => $args[1] ?? '', 'value' => $args[2] ?? ''],
                'mariadb.bind.status' => ['listening_public' => true, 'bind_address_config' => '0.0.0.0', 'config_path' => '/etc/mysql/mariadb.conf.d/50-server.cnf'],
                'mariadb.bind.fix' => ['bind_address' => '127.0.0.1', 'config_path' => '/etc/mysql/mariadb.conf.d/50-server.cnf', 'backup_path' => '/etc/mysql/mariadb.conf.d/50-server.cnf.lacmp-bak-1', 'restarted' => true],
                'mariadb.bind.rollback' => ['config_path' => '/etc/mysql/mariadb.conf.d/50-server.cnf', 'restored_from' => (string) ($args[0] ?? ''), 'restarted' => true],
                'system.reboot-required' => ['required' => true, 'packages' => ['linux-image-6.8']],
                'system.reboot' => $this->requireConfirm($stdin, 'REBOOT', ['accepted' => true]),
                'scheduler.install' => ['path' => '/etc/cron.d/azerioid-panel', 'artisan' => '/usr/local/lib/azerioid-panel/web/artisan', 'user' => 'caddy'],
                'updates.list' => ['total' => 12, 'security' => 3, 'source' => 'apt-check', 'packages' => [
                    ['name' => 'openssl', 'security' => true, 'raw' => 'Inst openssl [3.0] (3.0.1 Ubuntu:24.04/noble-security)'],
                    ['name' => 'curl', 'security' => false, 'raw' => 'Inst curl [8.5] (8.5.1 Ubuntu:24.04/noble-updates)'],
                ]],
                'updates.apply.security' => $this->requireConfirm($stdin, 'APPLY-SECURITY', ['action' => $action, 'exit' => 0, 'output' => 'unattended-upgrade fake ok']),
                'updates.apply.all' => $this->requireConfirm($stdin, 'APPLY-ALL', ['action' => $action, 'exit' => 0, 'output' => 'apt-get upgrade fake ok']),
                'tls.certs' => ['certs' => [[
                    'domain' => 'projob.az', 'ok' => true, 'issuer' => 'C=US, O=Let\'s Encrypt', 'valid_from' => 'Aug  1 00:00:00 2026 GMT',
                    'valid_to' => 'Oct 30 00:00:00 2026 GMT', 'days_remaining' => 63, 'renewal' => 'ok',
                ]]],
                'backup.db', 'backup.files', 'backup.caddy' => ['key' => 'azerioid/db/all/20260828T000000Z.bin', 'size' => 1024, 'kind' => 'db', 'name' => 'all', 'sha256' => str_repeat('a', 64)],
                'backup.list' => ['objects' => [[
                    'key' => 'azerioid/db/all/20260828T000000Z.bin', 'size' => 1024, 'last_modified' => '2026-08-28T00:00:00Z', 'kind' => 'db', 'name' => 'all',
                ]]],
                'backup.prune' => ['deleted' => [], 'keep' => 14],
                'backup.restore.db' => $this->restoreDb($stdin),
                'backup.restore.files' => $this->restoreFiles($stdin),
                'spaces.test' => ['ok' => true, 'bucket' => 'azerioid', 'region' => 'fra1'],
                'auth.audit' => ['path' => '/var/log/auth.log', 'missing' => false, 'success' => [['user' => 'root', 'ip' => '127.0.0.1', 'method' => 'publickey', 'line' => 'Accepted publickey for root from 127.0.0.1']], 'failed' => [], 'failed_count' => 0, 'new_root_ips' => []],
                'firewall.status' => ['ufw' => ['installed' => true, 'status' => "Status: active\nTo 22 ALLOW  Anywhere"], 'fail2ban' => ['installed' => false]],
                'firewall.unban' => ['ip' => $args[0] ?? '', 'jail' => $args[1] ?? 'sshd'],
                'firewall.fail2ban.install' => $this->requireConfirm($stdin, 'INSTALL-FAIL2BAN', ['installed' => true, 'jail' => 'sshd']),
                'logs.search' => ['key' => $args[0] ?? 'caddy', 'path' => '/var/log/caddy/access.log', 'missing' => false, 'needle' => $args[1] ?? '', 'lines' => ['1:match']],
                'php.opcache.stats' => ['php_version' => $args[0] ?? '8.4', 'available' => false, 'error' => 'cachetool is not installed; FPM OPcache cannot be inspected from CLI.'],
                'php.opcache.reset' => ['php_version' => $args[0] ?? '8.4', 'reset' => true, 'available' => true],
                'cron.list' => ['lines' => ['# comment', '0 3 * * * /usr/bin/true'], 'warning' => 'These entries run as root.'],
                'cron.set' => ['updated' => true, 'count' => count($stdin['lines'] ?? [])],
                default => throw new BrokerCallException('Unknown action.', 2),
            };
            return new BrokerResponse(true, $data, null, 0);
        } catch (BrokerCallException $e) {
            return new BrokerResponse(false, null, $e->getMessage(), $e->errorCode);
        }
    }

    private function statusAll(): array
    {
        return [
            'controlled' => [
                $this->svc('caddy') + ['controllable' => true],
                $this->svc('mariadb') + ['controllable' => true],
                $this->svc('php8.4-fpm') + ['controllable' => true],
                array_merge($this->svc('php8.2-fpm'), ['controllable' => true], $this->php82Failed ? [
                    'active_state' => 'failed',
                    'sub_state' => 'failed',
                    'running' => false,
                    'journal' => "Job for php8.2-fpm.service failed.\n--- journalctl -xeu php8.2-fpm ---\nphp-fpm failed to start",
                ] : []),
            ],
            'observed' => [
                $this->svc('redis-server') + ['controllable' => false],
            ],
            'warnings' => [[
                'id' => 'mariadb_public_bind',
                'severity' => 'high',
                'title' => 'MariaDB is listening on 0.0.0.0:3306',
                'body' => 'The database port is reachable on all interfaces. Bind it to 127.0.0.1 unless a remote app genuinely needs network access. The panel will not change this automatically.',
            ]],
        ];
    }

    private function svc(string $unit): array
    {
        return [
            'unit' => $unit,
            'id' => $unit . '.service',
            'active_state' => 'active',
            'sub_state' => 'running',
            'main_pid' => 1000,
            'n_restarts' => 0,
            'active_enter_timestamp' => 'Fri 2026-08-28 07:00:00 UTC',
            'unit_file_state' => 'enabled',
            'description' => $unit,
            'running' => true,
        ];
    }

    private function versionAll(): array
    {
        return [
            'web' => ['version' => '2.10.0', 'raw' => 'v2.10.0', 'service' => 'caddy', 'label' => 'Caddy', 'stack' => 'lcmp'],
            'caddy' => ['version' => '2.10.0', 'raw' => 'v2.10.0'],
            'mariadb' => ['version' => '11.4.5', 'raw' => 'mariadb from 11.4.5-MariaDB'],
            'php' => ['version' => '8.4.5', 'raw' => 'PHP 8.4.5 (cli)', 'installed' => ['8.4']],
        ];
    }

    private function metrics(): array
    {
        return [
            'loadavg' => ['1' => 0.12, '5' => 0.18, '15' => 0.21],
            'memory' => ['total' => 2_097_152_000, 'available' => 1_048_576_000, 'free' => 524_288_000, 'used' => 1_048_576_000],
            'uptime_seconds' => 345600,
            'disks' => [[
                'filesystem' => '/dev/sda1',
                'size' => 42_000_000_000,
                'used' => 18_000_000_000,
                'available' => 24_000_000_000,
                'use_percent' => '43%',
                'mount' => '/',
            ]],
            'hostname' => 'dream',
            'mariadb_listening_public' => true,
        ];
    }

    /**
     * @param  array<string,mixed>  $stdin
     * @param  array<string,mixed>  $ok
     * @return array<string,mixed>
     */
    private function requireConfirm(array $stdin, string $expected, array $ok): array
    {
        if (($stdin['confirm'] ?? '') !== $expected) {
            throw new BrokerCallException('Confirmation phrase did not match.', 3);
        }
        return $ok;
    }

    /**
     * @param  array<string,mixed>  $stdin
     * @return array<string,mixed>
     */
    private function restoreDb(array $stdin): array
    {
        $target = (string) ($stdin['target'] ?? '');
        $overwrite = (bool) ($stdin['overwrite'] ?? false);
        $existing = in_array($target, ['projob', 'mysql', 'lacmp_panel'], true);
        if ($existing && ! $overwrite) {
            throw new BrokerCallException('Target database exists. Restore into a new name, or send overwrite confirm OVERWRITE.', 3);
        }
        if ($overwrite) {
            $this->requireConfirm($stdin, 'OVERWRITE', []);
        }
        return ['target' => $target, 'overwrite' => $overwrite];
    }

    /**
     * @param  array<string,mixed>  $stdin
     * @return array<string,mixed>
     */
    private function restoreFiles(array $stdin): array
    {
        $site = (string) ($stdin['site'] ?? '');
        $apply = (bool) ($stdin['apply'] ?? false);
        $force = (bool) ($stdin['force'] ?? false);
        $protected = false;
        foreach ($this->vhosts as $v) {
            if (($v['domain'] ?? '') === $site && ! empty($v['readonly'])) {
                $protected = true;
                break;
            }
        }
        $confirmToken = strtoupper($site);
        if ($protected && $apply && ! $force) {
            throw new BrokerCallException(
                'Refusing to restore over a read-only vhost without force + confirm '.$confirmToken.'.',
                3
            );
        }
        if ($protected && $apply && $force) {
            $this->requireConfirm($stdin, $confirmToken, []);
        }
        return [
            'staged' => '/var/lib/azerioid-panel/staging/restore-'.$site,
            'preview' => [$site.'/index.php'],
            'applied' => $apply,
            'forced_readonly' => $protected && $force && $apply,
        ];
    }

    private function vhostAdd(array $args, array $stdin): array
    {
        if ($this->failNextValidate) {
            $this->failNextValidate = false;
            throw new BrokerCallException('Caddy rejected the new vhost; the file was rolled back.', 1);
        }
        $domain = $args[0] ?? '';
        foreach ($this->vhosts as $v) {
            if (! empty($v['readonly']) && ($v['domain'] === $domain || in_array($domain, $v['domains'] ?? [], true))) {
                throw new BrokerCallException($domain.' is managed externally and can\'t be edited.', 3);
            }
            if ($v['domain'] === $domain || in_array($domain, $v['domains'] ?? [], true)) {
                throw new BrokerCallException('A vhost for '.$domain.' already exists.', 3);
            }
        }
        $row = [
            'domains' => [$domain],
            'domain' => $domain,
            'root' => $args[1] ?? '/data/www/' . $domain,
            'php_socket' => ($args[2] ?? 'php') === 'php' ? 'unix//run/php/php' . ($args[3] ?? '8.4') . '-fpm.sock' : null,
            'php_version' => ($args[2] ?? 'php') === 'php' ? ($args[3] ?? '8.4') : null,
            'type' => $args[2] ?? 'php',
            'tls' => true,
            'reverse_proxy' => ($args[2] ?? '') === 'proxy' ? ($args[3] ?? null) : null,
            'readonly' => false,
            'enabled' => true,
            'source' => '/etc/caddy/conf.d/' . $domain . '.conf',
        ];
        $this->vhosts[] = $row;
        return $row;
    }

    /**
     * @param  array<string,mixed>  $stdin
     * @return array<string,mixed>
     */
    private function vhostEdit(array $args, array $stdin): array
    {
        if ($this->failNextValidate) {
            $this->failNextValidate = false;
            throw new BrokerCallException('Caddy rejected the edit; the file was rolled back.', 1);
        }
        $domain = (string) ($args[0] ?? ($stdin['domain'] ?? ''));
        foreach ($this->vhosts as $i => $v) {
            if (($v['domain'] ?? '') !== $domain) {
                continue;
            }
            if (! empty($v['readonly'])) {
                throw new BrokerCallException('This vhost is managed externally and cannot be edited by the panel.', 3);
            }
            $before = [
                'root' => $v['root'] ?? null,
                'php_version' => $v['php_version'] ?? null,
                'tls' => (bool) ($v['tls'] ?? false),
                'type' => $v['type'] ?? 'static',
            ];
            if (isset($stdin['root'])) {
                $v['root'] = (string) $stdin['root'];
            }
            if (isset($stdin['php_version'])) {
                $v['php_version'] = (string) $stdin['php_version'];
                $v['php_socket'] = 'unix//run/php/php'.($stdin['php_version']).'-fpm.sock';
            }
            if (array_key_exists('tls', $stdin)) {
                $v['tls'] = (bool) $stdin['tls'];
            }
            $after = [
                'root' => $v['root'] ?? null,
                'php_version' => $v['php_version'] ?? null,
                'tls' => (bool) ($v['tls'] ?? false),
                'type' => $v['type'] ?? 'static',
            ];
            $this->vhosts[$i] = $v;

            return [
                'domain' => $domain,
                'before' => $before,
                'after' => $after,
                'root' => $v['root'],
                'type' => $v['type'],
                'php_version' => $v['php_version'] ?? null,
                'tls' => (bool) ($v['tls'] ?? false),
                'source' => $v['source'],
                'apply' => ['path' => 'restart', 'address' => '', 'admin_spec' => 'n/a', 'admin_enabled' => false],
            ];
        }
        throw new BrokerCallException('Vhost config does not exist.', 3);
    }

    private function vhostDel(array $args): array
    {
        $domain = $args[0] ?? '';
        foreach ($this->vhosts as $i => $v) {
            if ($v['domain'] !== $domain) {
                continue;
            }
            if ($this->refuseReadonlyDeletes && ! empty($v['readonly'])) {
                throw new BrokerCallException('This vhost is managed externally and cannot be deleted by the panel.', 3);
            }
            unset($this->vhosts[$i]);
            $this->vhosts = array_values($this->vhosts);
            return ['domain' => $domain, 'deleted' => $v['source'], 'web_root_preserved' => $v['root']];
        }
        throw new BrokerCallException('Vhost config does not exist.', 3);
    }

    private function dbAdd(array $args, array $stdin): array
    {
        if ($this->failNextDbAdd) {
            $this->failNextDbAdd = false;
            throw new BrokerCallException('Database already exists.', 3);
        }
        $name = $args[0] ?? '';
        $user = $args[1] ?? $name;
        $this->databases[] = [
            'name' => $name,
            'size_bytes' => 0,
            'table_count' => 0,
            'users' => [['user' => $user, 'host' => 'localhost']],
            'protected' => false,
        ];
        return ['name' => $name, 'user' => $user, 'hosts' => ['localhost', '127.0.0.1']];
    }

    private function dbDel(array $args): array
    {
        $name = $args[0] ?? '';
        foreach ($this->databases as $i => $db) {
            if ($db['name'] !== $name) {
                continue;
            }
            if ($db['protected']) {
                throw new BrokerCallException('Refusing to mutate a protected system database.', 3);
            }
            unset($this->databases[$i]);
            $this->databases = array_values($this->databases);
            return ['name' => $name, 'user' => $args[1] ?? $name, 'dropped' => true];
        }
        throw new BrokerCallException('Database does not exist.', 3);
    }

    private function logs(array $args): array
    {
        $key = $args[0] ?? 'caddy';
        return [
            'key' => $key,
            'path' => '/var/log/caddy/access.log',
            'missing' => false,
            'lines' => [
                '[2026-08-28 07:00:01] GET / 200',
                '[2026-08-28 07:00:02] GET /health 200',
                'fake log stream for ' . $key,
            ],
        ];
    }

    private function phpVersions(): array
    {
        return ['versions' => [[
            'version' => '8.4',
            'fpm_service' => 'php8.4-fpm',
            'socket' => 'unix//run/php/php8.4-fpm.sock',
            'ini' => '/etc/php/8.4/fpm/php.ini',
            'status' => $this->svc('php8.4-fpm'),
        ]]];
    }

    private function componentList(): array
    {
        $components = $this->fakeRegistryComponents();
        return [
            'distro_key' => 'ubuntu',
            'distro_id' => 'ubuntu',
            'distro_version' => '24',
            'pkg_mgr' => 'apt',
            'registry_path' => base_path('../registry/components'),
            'components' => $components,
            'observed_extras' => [],
        ];
    }

    private function componentStatus(string $id): array
    {
        $id = strtolower(trim($id));
        foreach ($this->fakeRegistryComponents() as $component) {
            if (($component['id'] ?? '') === $id) {
                return $component + [
                    'distro_key' => 'ubuntu',
                    'distro_id' => 'ubuntu',
                ];
            }
        }
        throw new BrokerCallException('Unknown component id.', 2);
    }

    /** @return list<array<string, mixed>> */
    private function fakeRegistryComponents(): array
    {
        $defs = [
            ['id' => 'caddy', 'display_name' => 'Caddy', 'category' => 'web', 'system' => true, 'status' => 'active', 'installable' => false],
            ['id' => 'php-8.4', 'display_name' => 'PHP 8.4 (panel runtime)', 'category' => 'runtime', 'system' => true, 'status' => 'active', 'installable' => false],
            ['id' => 'nginx', 'display_name' => 'Nginx', 'category' => 'web', 'system' => false, 'status' => 'not_installed', 'installable' => true, 'unit' => 'nginx'],
            ['id' => 'apache', 'display_name' => 'Apache', 'category' => 'web', 'system' => false, 'status' => 'not_installed', 'installable' => true, 'unit' => 'apache2'],
            ['id' => 'php-8.1', 'display_name' => 'PHP 8.1', 'category' => 'runtime', 'system' => false, 'status' => 'not_installed', 'installable' => true, 'unit' => 'php8.1-fpm'],
            ['id' => 'php-8.2', 'display_name' => 'PHP 8.2', 'category' => 'runtime', 'system' => false, 'status' => 'not_installed', 'installable' => true, 'unit' => 'php8.2-fpm'],
            ['id' => 'php-8.3', 'display_name' => 'PHP 8.3', 'category' => 'runtime', 'system' => false, 'status' => 'not_installed', 'installable' => true, 'unit' => 'php8.3-fpm'],
            ['id' => 'mariadb', 'display_name' => 'MariaDB', 'category' => 'database', 'system' => false, 'status' => 'not_installed', 'installable' => true, 'unit' => 'mariadb'],
            ['id' => 'postgresql', 'display_name' => 'PostgreSQL', 'category' => 'database', 'system' => false, 'status' => 'not_installed', 'installable' => true, 'unit' => 'postgresql'],
            ['id' => 'mongodb', 'display_name' => 'MongoDB', 'category' => 'database', 'system' => false, 'status' => 'not_installed', 'installable' => true, 'unit' => 'mongod'],
            ['id' => 'redis', 'display_name' => 'Redis', 'category' => 'cache', 'system' => false, 'status' => 'not_installed', 'installable' => true, 'unit' => 'redis-server'],
            ['id' => 'memcached', 'display_name' => 'Memcached', 'category' => 'cache', 'system' => false, 'status' => 'not_installed', 'installable' => true, 'unit' => 'memcached'],
            [
                'id' => 'nodejs',
                'display_name' => 'Node.js',
                'category' => 'runtime',
                'system' => false,
                'status' => 'not_installed',
                'installable' => true,
                'install_options' => ['node_major' => ['default' => '22', 'choices' => ['20', '22', '24']]],
            ],
        ];

        return array_map(function (array $row): array {
            $system = (bool) ($row['system'] ?? false);
            $id = (string) $row['id'];
            $installed = isset($this->fakeInstalledComponents[$id]);
            $observed = isset($this->fakeObservedComponents[$id]);
            $status = $installed ? 'active' : ($observed ? 'installed' : (string) ($row['status'] ?? 'not_installed'));
            $kind = $system ? 'system' : ($installed ? 'managed' : ($observed ? 'observed' : 'managed'));
            $adoptable = $observed && !$installed;

            return [
                'id' => $id,
                'display_name' => $row['display_name'],
                'category' => $row['category'],
                'description' => $system ? 'Panel runtime component' : 'Registry catalog entry',
                'managed' => true,
                'system' => $system,
                'kind' => $kind,
                'status' => $status,
                'status_detail' => $installed
                    ? 'Installed by panel (fake).'
                    : ($observed ? 'Detected on host; adopt to manage.' : 'Package not detected on this host.'),
                'unit' => $row['unit'] ?? ($id === 'caddy' ? 'caddy' : ($id === 'php-8.4' ? 'php8.4-fpm' : null)),
                'removable' => !$system,
                'installable' => (bool) ($row['installable'] ?? false) && !$observed,
                'adoptable' => $adoptable,
                'conflicts' => [],
                'ports' => [],
                'install_options' => is_array($row['install_options'] ?? null) ? $row['install_options'] : [],
            ];
        }, $defs);
    }

    /** @return list<string> */
    private function installableComponentIds(): array
    {
        return [
            'redis', 'mariadb', 'postgresql', 'nginx', 'apache',
            'memcached', 'mongodb', 'nodejs', 'php-8.1', 'php-8.2', 'php-8.3',
        ];
    }

    private function componentPreflight(string $id): array
    {
        if (!in_array($id, $this->installableComponentIds(), true)) {
            throw new BrokerCallException('Component is not installable from the panel (not in registry allowlist).', 3);
        }
        if ($id === 'postgresql' && isset($this->fakeInstalledComponents['mariadb'])) {
            return ['component_id' => $id, 'ok' => false, 'issues' => ['Conflicts with mariadb, which is already present on this host.']];
        }
        if ($id === 'mariadb' && isset($this->fakeInstalledComponents['postgresql'])) {
            return ['component_id' => $id, 'ok' => false, 'issues' => ['Conflicts with postgresql, which is already present on this host.']];
        }
        return ['component_id' => $id, 'ok' => true, 'issues' => []];
    }

    /** @param array<string, mixed> $stdin */
    private function componentInstall(string $id, array $stdin): array
    {
        if (!in_array($id, $this->installableComponentIds(), true)) {
            throw new BrokerCallException('Component is not installable from the panel (not in registry allowlist).', 3);
        }
        if (in_array($id, ['php-8.4'], true)) {
            throw new BrokerCallException('Panel PHP runtime cannot be installed from the Components page.', 3);
        }
        $this->fakeInstalledComponents[$id] = true;
        if ($id === 'mariadb') {
            $this->databaseEngine = 'mariadb';
        }
        if ($id === 'postgresql') {
            $this->databaseEngine = 'postgresql';
            $this->postgresqlConfigured = true;
        }
        $op = (string) ($stdin['operation_id'] ?? 'op-fake');
        return [
            'component_id' => $id,
            'operation_id' => $op,
            'log_path' => '/var/lib/azerioid-panel/staging/operations/'.$op.'.log',
            'status' => $this->componentStatus($id),
        ];
    }

    private function componentAdopt(string $id): array
    {
        if (!isset($this->fakeObservedComponents[$id])) {
            throw new BrokerCallException('Only observed (pre-existing) components can be adopted.', 3);
        }
        unset($this->fakeObservedComponents[$id]);
        $this->fakeInstalledComponents[$id] = true;
        if ($id === 'mariadb') {
            $this->databaseEngine = 'mariadb';
        }
        if ($id === 'postgresql') {
            $this->databaseEngine = 'postgresql';
            $this->postgresqlConfigured = true;
        }

        return [
            'component_id' => $id,
            'adopted' => true,
            'status' => $this->componentStatus($id),
            'migration_note' => $id === 'mariadb'
                ? 'Run sudo ./deploy/migrate.sh to copy the legacy lacmp_panel database into SQLite. Site databases are unchanged.'
                : null,
        ];
    }

    /** @param array<string, mixed> $stdin */
    private function componentUninstall(string $id, array $stdin): array
    {
        if (!isset($this->fakeInstalledComponents[$id])) {
            throw new BrokerCallException('Component was not installed by the panel.', 3);
        }
        unset($this->fakeInstalledComponents[$id]);
        if ($id === 'mariadb' && $this->databaseEngine === 'mariadb') {
            $this->databaseEngine = $this->postgresqlConfigured ? 'postgresql' : 'mariadb';
        }
        if ($id === 'postgresql') {
            $this->postgresqlConfigured = false;
            if ($this->databaseEngine === 'postgresql') {
                $this->databaseEngine = 'mariadb';
            }
        }
        return [
            'component_id' => $id,
            'operation_id' => (string) ($stdin['operation_id'] ?? 'op-fake'),
            'log_path' => '/var/lib/azerioid-panel/staging/operations/op-fake.log',
        ];
    }

    private function dbEngine(): array
    {
        return [
            'active' => $this->databaseEngine,
            'engines' => [
                ['id' => 'mariadb', 'label' => 'MariaDB', 'configured' => true, 'active' => $this->databaseEngine === 'mariadb'],
                ['id' => 'postgresql', 'label' => 'PostgreSQL', 'configured' => $this->postgresqlConfigured, 'active' => $this->databaseEngine === 'postgresql'],
            ],
        ];
    }

    /** @param list<string> $args */
    private function dbDump(array $args): array
    {
        $name = $args[0] ?? 'all';
        $engine = $this->databaseEngine;

        return [
            'path' => '/var/lib/azerioid-panel/staging/dumps/'.$engine.'-'.$name.'-fake.sql.gz',
            'size_bytes' => 1024,
            'engine' => $engine,
            'name' => $name,
        ];
    }

    private function componentOperationLog(string $opKey): array
    {
        return [
            'operation_id' => $opKey,
            'path' => '/var/lib/azerioid-panel/staging/operations/'.$opKey.'.log',
            'lines' => ['INFO fake install log line'],
            'missing' => false,
        ];
    }
}
