<?php
declare(strict_types=1);

namespace LacmpPanel\Broker;

use LacmpPanel\Broker\Actions\AuthAudit;
use LacmpPanel\Broker\Actions\CaddyApplyConfig;
use LacmpPanel\Broker\Actions\BackupList;
use LacmpPanel\Broker\Actions\BackupPrune;
use LacmpPanel\Broker\Actions\BackupRestore;
use LacmpPanel\Broker\Actions\BackupRun;
use LacmpPanel\Broker\Actions\CronManage;
use LacmpPanel\Broker\Actions\DbAdd;
use LacmpPanel\Broker\Actions\DbDel;
use LacmpPanel\Broker\Actions\DbList;
use LacmpPanel\Broker\Actions\DbResetpw;
use LacmpPanel\Broker\Actions\Fail2banInstall;
use LacmpPanel\Broker\Actions\FirewallStatus;
use LacmpPanel\Broker\Actions\FirewallUnban;
use LacmpPanel\Broker\Actions\LogsSearch;
use LacmpPanel\Broker\Actions\LogsTail;
use LacmpPanel\Broker\Actions\MariadbBindFix;
use LacmpPanel\Broker\Actions\MariadbBindRollback;
use LacmpPanel\Broker\Actions\MariadbBindStatus;
use LacmpPanel\Broker\Actions\MetricsSystem;
use LacmpPanel\Broker\Actions\PanelRuntime;
use LacmpPanel\Broker\Actions\PhpIniGet;
use LacmpPanel\Broker\Actions\PhpIniSet;
use LacmpPanel\Broker\Actions\PhpOpcache;
use LacmpPanel\Broker\Actions\PhpVersions;
use LacmpPanel\Broker\Actions\SchedulerInstall;
use LacmpPanel\Broker\Actions\ServiceControl;
use LacmpPanel\Broker\Actions\ServiceStatus;
use LacmpPanel\Broker\Actions\SpacesTest;
use LacmpPanel\Broker\Actions\StatusAll;
use LacmpPanel\Broker\Actions\SystemReboot;
use LacmpPanel\Broker\Actions\SystemRebootRequired;
use LacmpPanel\Broker\Actions\TlsCerts;
use LacmpPanel\Broker\Actions\UpdatesApply;
use LacmpPanel\Broker\Actions\UpdatesList;
use LacmpPanel\Broker\Actions\VersionAll;
use LacmpPanel\Broker\Actions\VhostAdd;
use LacmpPanel\Broker\Actions\VhostDel;
use LacmpPanel\Broker\Actions\VhostList;

final class Kernel
{
    /** @var array<string, class-string> */
    public const ACTIONS = [
        'status.all' => StatusAll::class,
        'panel.runtime' => PanelRuntime::class,
        'version.all' => VersionAll::class,
        'metrics.system' => MetricsSystem::class,
        'service.status' => ServiceStatus::class,
        'service.start' => ServiceControl::class,
        'service.stop' => ServiceControl::class,
        'service.restart' => ServiceControl::class,
        'vhost.list' => VhostList::class,
        'vhost.add' => VhostAdd::class,
        'vhost.del' => VhostDel::class,
        'caddy.apply' => CaddyApplyConfig::class,
        'web.reload' => CaddyApplyConfig::class,
        'db.list' => DbList::class,
        'db.add' => DbAdd::class,
        'db.del' => DbDel::class,
        'db.resetpw' => DbResetpw::class,
        'logs.tail' => LogsTail::class,
        'logs.search' => LogsSearch::class,
        'php.versions' => PhpVersions::class,
        'php.ini.get' => PhpIniGet::class,
        'php.ini.set' => PhpIniSet::class,
        'php.opcache.stats' => PhpOpcache::class,
        'php.opcache.reset' => PhpOpcache::class,
        'mariadb.bind.status' => MariadbBindStatus::class,
        'mariadb.bind.fix' => MariadbBindFix::class,
        'mariadb.bind.rollback' => MariadbBindRollback::class,
        'system.reboot-required' => SystemRebootRequired::class,
        'system.reboot' => SystemReboot::class,
        'scheduler.install' => SchedulerInstall::class,
        'updates.list' => UpdatesList::class,
        'updates.apply.security' => UpdatesApply::class,
        'updates.apply.all' => UpdatesApply::class,
        'tls.certs' => TlsCerts::class,
        'backup.db' => BackupRun::class,
        'backup.files' => BackupRun::class,
        'backup.caddy' => BackupRun::class,
        'backup.list' => BackupList::class,
        'backup.prune' => BackupPrune::class,
        'backup.restore.db' => BackupRestore::class,
        'backup.restore.files' => BackupRestore::class,
        'spaces.test' => SpacesTest::class,
        'auth.audit' => AuthAudit::class,
        'firewall.status' => FirewallStatus::class,
        'firewall.unban' => FirewallUnban::class,
        'firewall.fail2ban.install' => Fail2banInstall::class,
        'cron.list' => CronManage::class,
        'cron.set' => CronManage::class,
    ];

    public function __construct(
        private readonly Config $config,
        private Runtime $runtime,
    ) {
        $this->runtime = $this->config->runtimeWithDb($this->runtime);
    }

    /**
     * @param  array<int,string>  $argv
     * @param  array<string,mixed>|null  $stdin  Pre-parsed JSON. When null, read STDIN.
     */
    public function run(array $argv, ?array $stdin = null): int
    {
        $action = $argv[1] ?? '';
        $args = array_values(array_slice($argv, 2));
        $input = $stdin ?? $this->readStdinJson();

        try {
            $action = Validator::action($action);
            if (!isset(self::ACTIONS[$action])) {
                throw new BrokerException('Unknown action.', 2);
            }
            $class = self::ACTIONS[$action];
            $handler = new $class();
            $data = $handler->handle($action, $args, $input, $this->runtime, $this->config);
            $this->audit($action, array_merge($args, $input), true, 0, null);
            $this->emit(true, $data, null, 0);
            return 0;
        } catch (BrokerException $e) {
            $this->audit($action !== '' ? $action : 'invalid', array_merge($args, $input), false, $e->errorCode, $e->getMessage());
            $this->emit(false, null, $e->getMessage(), $e->errorCode);
            return $e->errorCode;
        } catch (\Throwable $e) {
            $safe = self::publicError($e);
            fwrite(STDERR, 'broker: ' . $e::class . "\n");
            $this->audit($action !== '' ? $action : 'crash', array_merge($args, $input), false, 1, $safe);
            $this->emit(false, null, $safe, 1);
            return 1;
        }
    }

    /** @return array<string,mixed> */
    private function readStdinJson(): array
    {
        if (!defined('STDIN') || !is_resource(STDIN)) {
            return [];
        }
        $raw = stream_get_contents(STDIN);
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new BrokerException('Stdin must be a JSON object when provided.', 2);
        }
        return $decoded;
    }

    private function audit(string $action, array $args, bool $ok, int $code, ?string $error): void
    {
        try {
            (new AuditLog($this->config, $this->runtime))->write($action, $args, $ok, $code, $error);
        } catch (\Throwable) {
            fwrite(STDERR, "broker: failed to write audit log\n");
        }
    }

    private function emit(bool $ok, mixed $data, ?string $error, int $code): void
    {
        echo json_encode([
            'ok' => $ok,
            'data' => $data,
            'error' => $error,
            'code' => $code,
        ], JSON_UNESCAPED_SLASHES) . "\n";
    }

    private static function publicError(\Throwable $e): string
    {
        if ($e instanceof \PDOException) {
            return PosixRuntime::describePdo($e);
        }
        $msg = $e->getMessage();
        if (preg_match('/IDENTIFIED BY|passwd\\s*=|password\\s*=/i', $msg) === 1) {
            return $e::class . ' during broker action (details redacted).';
        }
        $msg = trim($msg);
        return $msg !== '' ? $e::class . ': ' . $msg : $e::class . ' during broker action.';
    }
}
