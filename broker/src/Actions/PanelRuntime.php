<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Actions;

use LacmpPanel\Broker\Config;
use LacmpPanel\Broker\Runtime;
use LacmpPanel\Broker\Systemd;

final class PanelRuntime
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $queueUnit = $config->panelRuntimeQueueUnit;
        $queueStatus = Systemd::show($runtime, $queueUnit);

        return [
            'php_version' => $config->panelPhpVersion,
            'fpm_socket' => $config->panelFpmSocket,
            'fpm_pool' => $config->panelFpmPool,
            'fpm_service' => $config->phpFpmService($config->panelPhpVersion),
            'queue_unit' => $queueUnit,
            'queue_active' => ($queueStatus['active'] ?? '') === 'active',
            'queue_status' => $queueStatus,
            'system' => true,
            'removable' => false,
        ];
    }
}
