<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Actions;

use LacmpPanel\Broker\Config;
use LacmpPanel\Broker\ProcMetrics;
use LacmpPanel\Broker\Runtime;

final class MetricsSystem
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        return ProcMetrics::collect($runtime);
    }
}
