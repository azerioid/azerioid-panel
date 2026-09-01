<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Actions;

use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Database\DatabaseManager;
use AzerioidPanel\Broker\Runtime;

final class DbDump
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $manager = new DatabaseManager($config, $runtime);
        $engine = $manager->resolveEngine((string) ($input['engine'] ?? ''));
        $name = (string) ($args[0] ?? ($input['name'] ?? 'all'));
        $outputPath = $manager->dumpPath($engine, $name);
        $dir = dirname($outputPath);
        if (!$runtime->isDir($dir)) {
            $runtime->mkdir($dir, 0750);
        }

        return $manager->driver($engine)->dump($name, $outputPath);
    }
}
