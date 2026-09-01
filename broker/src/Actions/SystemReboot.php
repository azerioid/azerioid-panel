<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Actions;

use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Runtime;
use AzerioidPanel\Broker\Validator;

final class SystemReboot
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        Validator::typedConfirm((string) ($input['confirm'] ?? ''), 'REBOOT');
        $runtime->exec(['/usr/sbin/reboot'], null, 5);
        return ['accepted' => true];
    }
}
