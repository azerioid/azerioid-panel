<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Actions;

use LacmpPanel\Broker\Config;
use LacmpPanel\Broker\Runtime;
use LacmpPanel\Broker\Systemd;
use LacmpPanel\Broker\Validator;

final class ServiceStatus
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $unit = Validator::service($args[0] ?? '', $config->controllableServiceList($runtime));
        return [
            'parsed' => Systemd::show($runtime, $unit),
            'raw' => Systemd::statusRaw($runtime, $unit),
            'journal' => Systemd::journal($runtime, $unit),
        ];
    }
}
