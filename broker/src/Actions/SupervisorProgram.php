<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Actions;

use AzerioidPanel\Broker\BrokerException;
use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Runtime;
use AzerioidPanel\Broker\Supervisor\SupervisorManager;
use AzerioidPanel\Broker\Validator;

final class SupervisorProgram
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $manager = new SupervisorManager($config, $runtime);

        return match ($action) {
            'supervisor.program.list' => $manager->listPrograms(),
            'supervisor.program.create' => $manager->create($input),
            'supervisor.program.update' => $manager->update(
                Validator::supervisorProgramName($args[0] ?? ($input['name'] ?? '')),
                $input
            ),
            'supervisor.program.delete' => $manager->delete(
                Validator::supervisorProgramName($args[0] ?? ($input['name'] ?? '')),
                (bool) ($input['stop_first'] ?? true)
            ),
            'supervisor.program.status' => $manager->status(
                Validator::supervisorProgramName($args[0] ?? ($input['name'] ?? ''))
            ),
            'supervisor.program.start' => $manager->control(
                Validator::supervisorProgramName($args[0] ?? ($input['name'] ?? '')),
                'start'
            ),
            'supervisor.program.stop' => $manager->control(
                Validator::supervisorProgramName($args[0] ?? ($input['name'] ?? '')),
                'stop'
            ),
            'supervisor.program.restart' => $manager->control(
                Validator::supervisorProgramName($args[0] ?? ($input['name'] ?? '')),
                'restart'
            ),
            'supervisor.program.logs' => $manager->logs(
                Validator::supervisorProgramName($args[0] ?? ($input['name'] ?? '')),
                Validator::lineCount($input['lines'] ?? ($args[1] ?? 100))
            ),
            default => throw new BrokerException('Unknown supervisor action.', 2),
        };
    }
}
