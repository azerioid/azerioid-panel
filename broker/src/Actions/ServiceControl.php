<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Actions;

use LacmpPanel\Broker\BrokerException;
use LacmpPanel\Broker\Config;
use LacmpPanel\Broker\Runtime;
use LacmpPanel\Broker\Systemd;
use LacmpPanel\Broker\Validator;

final class ServiceControl
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $verb = match ($action) {
            'service.start' => 'start',
            'service.stop' => 'stop',
            'service.restart' => 'restart',
            default => throw new BrokerException('Unknown service action.', 2),
        };
        $unit = Validator::service($args[0] ?? '', $config->controllableServiceList($runtime));
        return Systemd::control($runtime, $verb, $unit);
    }
}
