<?php

namespace App\Console\Commands;

use App\Models\MetricSample;
use App\Services\Broker\BrokerClient;
use Illuminate\Console\Command;

class SampleMetrics extends Command
{
    protected $signature = 'azerioid:sample-metrics';

    protected $description = 'Record a rolling metrics sample via the broker';

    public function handle(BrokerClient $broker): int
    {
        $res = $broker->call('metrics.system', [], [], null, false);
        if (! $res->ok) {
            $this->error($res->error ?: 'metrics.system failed');
            return self::FAILURE;
        }
        $d = $res->data;
        $disk = 0;
        foreach ($d['disks'] ?? [] as $row) {
            if (($row['mount'] ?? '') === '/') {
                $disk = (int) rtrim((string) ($row['use_percent'] ?? '0'), '%');
            }
        }
        MetricSample::query()->create([
            'sampled_at' => now(),
            'load1' => (float) ($d['loadavg']['1'] ?? 0),
            'ram_used' => (int) ($d['memory']['used'] ?? 0),
            'ram_total' => (int) ($d['memory']['total'] ?? 0),
            'disk_percent' => $disk,
        ]);
        MetricSample::query()->where('sampled_at', '<', now()->subDays(7))->delete();
        return self::SUCCESS;
    }
}
