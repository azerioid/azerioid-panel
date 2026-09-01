<?php

namespace App\Console\Commands;

use App\Jobs\PingJob;
use Illuminate\Console\Command;

class DispatchPingJob extends Command
{
    protected $signature = 'queue:dispatch-ping';

    protected $description = 'Dispatch PingJob to verify queue worker (install smoke)';

    public function handle(): int
    {
        PingJob::dispatch();
        $this->info('PingJob dispatched.');

        return self::SUCCESS;
    }
}
