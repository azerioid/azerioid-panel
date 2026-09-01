<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Actions;

use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\ProcMetrics;
use AzerioidPanel\Broker\Runtime;

final class MetricsSystem
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        return ProcMetrics::collect($runtime);
    }
}
