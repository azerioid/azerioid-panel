<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Actions;

use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Runtime;

final class FirewallStatus
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $ufw = ['installed' => $runtime->fileExists('/usr/sbin/ufw')];
        if ($ufw['installed']) {
            $st = $runtime->exec(['/usr/sbin/ufw', 'status', 'verbose'], null, 15);
            $ufw['status'] = trim($st->stdout . "\n" . $st->stderr);
        }

        $ban = ['installed' => $runtime->fileExists('/usr/bin/fail2ban-client')];
        if ($ban['installed']) {
            $st = $runtime->exec(['/usr/bin/fail2ban-client', 'status'], null, 15);
            $ban['status'] = trim($st->stdout);
            $jails = [];
            if (preg_match('/Jail list:\s*(.+)$/m', $st->stdout, $m)) {
                foreach (preg_split('/,\s*/', trim($m[1])) ?: [] as $jail) {
                    $jail = trim($jail);
                    if ($jail === '') {
                        continue;
                    }
                    $js = $runtime->exec(['/usr/bin/fail2ban-client', 'status', $jail], null, 15);
                    $banned = [];
                    if (preg_match('/Banned IP list:\s*(.*)$/m', $js->stdout, $bm)) {
                        $banned = array_values(array_filter(preg_split('/\s+/', trim($bm[1])) ?: []));
                    }
                    $jails[] = ['jail' => $jail, 'raw' => trim($js->stdout), 'banned' => $banned];
                }
            }
            $ban['jails'] = $jails;
        }

        return ['ufw' => $ufw, 'fail2ban' => $ban];
    }
}
