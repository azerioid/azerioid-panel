<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Actions;

use AzerioidPanel\Broker\Component\ComponentInstaller;
use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Runtime;
use AzerioidPanel\Broker\Validator;

final class ComponentOperationLog
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $operationId = Validator::operationId((string) ($args[0] ?? $input['operation_id'] ?? ''));
        return (new ComponentInstaller($config, $runtime))->operationLog($operationId);
    }
}
