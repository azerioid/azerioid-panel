<?php

namespace App\Console\Commands;

use App\Models\BackupJob;
use App\Models\Setting;
use App\Services\Broker\BrokerClient;
use Illuminate\Console\Command;

class RunScheduledBackup extends Command
{
    protected $signature = 'azerioid:backup-scheduled';

    protected $description = 'Run the configured backup job if due';

    public function handle(BrokerClient $broker): int
    {
        $cfg = Setting::get('backup.schedule', []);
        if (! is_array($cfg) || ! ($cfg['enabled'] ?? false)) {
            return self::SUCCESS;
        }
        $hour = (int) ($cfg['hour'] ?? 3);
        if ((int) now()->format('G') !== $hour) {
            return self::SUCCESS;
        }
        $cadence = (string) ($cfg['cadence'] ?? 'daily');
        if ($cadence === 'weekly' && now()->dayOfWeek !== (int) ($cfg['weekday'] ?? 0)) {
            return self::SUCCESS;
        }
        $last = BackupJob::query()->where('status', 'ok')->latest()->first();
        if ($last && $last->created_at->isToday()) {
            return self::SUCCESS;
        }
        $spaces = self::spacesStdin();
        $pass = Setting::getSecret('backup.passphrase');
        if ($spaces === null || $pass === null) {
            $this->error('backup secrets missing');
            return self::FAILURE;
        }
        $stdin = ['spaces' => $spaces, 'passphrase' => $pass];
        foreach (['backup.caddy' => ['caddy', 'caddy']] as $action => $meta) {
            $this->runOne($broker, $action, [], $stdin, $meta[0], $meta[1]);
        }
        $targets = $cfg['databases'] ?? ['all'];
        foreach ($targets as $db) {
            $this->runOne($broker, 'backup.db', [(string) $db], $stdin, 'db', (string) $db);
        }
        $sites = $cfg['sites'] ?? [];
        foreach ($sites as $site) {
            $this->runOne($broker, 'backup.files', [(string) $site], $stdin, 'files', (string) $site);
        }
        $keep = (int) ($cfg['keep'] ?? 14);
        $broker->call('backup.prune', [], $stdin + ['keep' => $keep], 120, true);
        return self::SUCCESS;
    }

    /** @param array<int,string> $args */
    private function runOne(BrokerClient $broker, string $action, array $args, array $stdin, string $kind, string $name): void
    {
        $job = BackupJob::query()->create(['kind' => $kind, 'name' => $name, 'status' => 'running']);
        $start = microtime(true);
        $res = $broker->call($action, $args, $stdin, 900, true);
        $job->forceFill([
            'status' => $res->ok ? 'ok' : 'failed',
            'object_key' => $res->ok ? ($res->data['key'] ?? null) : null,
            'size' => $res->ok ? ($res->data['size'] ?? null) : null,
            'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            'error' => $res->ok ? null : $res->error,
        ])->save();
    }

    /** @return array<string,string>|null */
    public static function spacesStdin(): ?array
    {
        $key = Setting::getSecret('spaces.access_key');
        $secret = Setting::getSecret('spaces.secret');
        $endpoint = (string) Setting::get('spaces.endpoint', '');
        $region = (string) Setting::get('spaces.region', '');
        $bucket = (string) Setting::get('spaces.bucket', '');
        if ($key === null || $secret === null || $endpoint === '' || $region === '' || $bucket === '') {
            return null;
        }
        return [
            'endpoint' => $endpoint,
            'region' => $region,
            'bucket' => $bucket,
            'access_key' => $key,
            'secret' => $secret,
        ];
    }
}
