<?php

namespace App\Console\Commands;

use App\Services\Alerts\AlertEvaluator;
use Illuminate\Console\Command;

class EvaluateAlerts extends Command
{
    protected $signature = 'azerioid:evaluate-alerts';

    protected $description = 'Evaluate alert rules and send Telegram on state change';

    public function handle(AlertEvaluator $evaluator): int
    {
        $stats = $evaluator->run();
        $this->info('opened='.$stats['opened'].' resolved='.$stats['resolved'].' notified='.$stats['notified']);
        return self::SUCCESS;
    }
}
