<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Actions;

use LacmpPanel\Broker\BrokerException;
use LacmpPanel\Broker\Config;
use LacmpPanel\Broker\Runtime;
use LacmpPanel\Broker\Validator;

final class Fail2banInstall
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        Validator::typedConfirm((string) ($input['confirm'] ?? ''), 'INSTALL-FAIL2BAN');
        $result = $runtime->exec(['/usr/bin/apt-get', '-y', 'install', 'fail2ban'], null, 300);
        if (!$result->ok()) {
            throw new BrokerException(trim($result->stderr) !== '' ? trim($result->stderr) : 'apt-get install fail2ban failed.', 1);
        }
        $jail = "[sshd]\nenabled = true\nbackend = systemd\nmaxretry = 5\nbantime = 1h\n";
        $runtime->writeFile('/etc/fail2ban/jail.d/lacmp-sshd.conf', $jail, 0644);
        $runtime->exec(['/usr/bin/systemctl', 'enable', '--now', 'fail2ban'], null, 30);
        return ['installed' => true, 'jail' => 'sshd'];
    }
}
