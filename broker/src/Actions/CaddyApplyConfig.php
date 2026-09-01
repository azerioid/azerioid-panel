<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Actions;

use AzerioidPanel\Broker\BrokerException;
use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Runtime;
use AzerioidPanel\Broker\Web\WebServers;

final class CaddyApplyConfig
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $mode = strtolower(trim((string) ($input['mode'] ?? $args[0] ?? 'auto')));
        $ports = $input['expect_ports'] ?? [];
        if (!is_array($ports)) {
            throw new BrokerException('expect_ports must be an array of integers.', 2);
        }
        $expect = [];
        foreach ($ports as $p) {
            $expect[] = (int) $p;
        }

        return WebServers::for($config)->reload($runtime, $config, $mode, $expect);
    }
}
