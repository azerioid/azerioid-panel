<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Actions;

use AzerioidPanel\Broker\BrokerException;
use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Runtime;
use AzerioidPanel\Broker\Supervisor\SupervisorManager;
use AzerioidPanel\Broker\Validator;
use AzerioidPanel\Broker\Web\WebServers;

final class VhostDel
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $domain = Validator::domain($args[0] ?? ($input['domain'] ?? ''));
        $manager = new SupervisorManager($config, $runtime);
        $linked = [];
        try {
            $manager->assertInstalled();
            $linked = $manager->programsForVhost($domain);
        } catch (BrokerException $e) {
            if ($e->errorCode !== 3 || !str_contains($e->getMessage(), 'not installed')) {
                throw $e;
            }
        }

        if ($linked !== [] && !self::boolInput($input['remove_supervisor_programs'] ?? false)) {
            $names = implode(', ', array_column($linked, 'name'));
            throw new BrokerException(
                "Vhost {$domain} has supervisor process(es): {$names}. "
                . 'Remove them first, or pass remove_supervisor_programs=true to delete with the vhost.',
                3
            );
        }

        foreach ($linked as $program) {
            $manager->delete((string) $program['name']);
        }

        return WebServers::for($config)->removeVhost($runtime, $config, $domain);
    }

    private static function boolInput(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
