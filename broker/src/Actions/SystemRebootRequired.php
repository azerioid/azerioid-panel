<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Actions;

use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Runtime;

final class SystemRebootRequired
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $flag = '/var/run/reboot-required';
        $pkgs = '/var/run/reboot-required.pkgs';
        $required = $runtime->fileExists($flag);
        $packages = [];
        if ($required && $runtime->fileExists($pkgs)) {
            foreach (preg_split("/\r?\n/", trim($runtime->readFile($pkgs))) ?: [] as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $packages[] = $line;
                }
            }
        }
        return [
            'required' => $required,
            'packages' => $packages,
        ];
    }
}
