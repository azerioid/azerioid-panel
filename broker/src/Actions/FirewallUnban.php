<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Actions;

use AzerioidPanel\Broker\BrokerException;
use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Runtime;
use AzerioidPanel\Broker\Validator;

final class FirewallUnban
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $ip = Validator::ipv4((string) ($args[0] ?? $input['ip'] ?? ''));
        $jail = (string) ($args[1] ?? $input['jail'] ?? 'sshd');
        if (!preg_match('/^[a-z][a-z0-9_-]{0,32}$/', $jail)) {
            throw new BrokerException('Invalid fail2ban jail name.', 2);
        }
        if (!$runtime->fileExists('/usr/bin/fail2ban-client')) {
            throw new BrokerException('fail2ban is not installed.', 3);
        }
        $result = $runtime->exec(['/usr/bin/fail2ban-client', 'set', $jail, 'unbanip', $ip], null, 15);
        if (!$result->ok()) {
            throw new BrokerException(trim($result->stderr . ' ' . $result->stdout) ?: 'unban failed.', 1);
        }
        return ['ip' => $ip, 'jail' => $jail];
    }
}
