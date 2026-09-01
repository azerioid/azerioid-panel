<?php

use App\Console\Commands\EvaluateAlerts;
use App\Console\Commands\RunScheduledBackup;
use App\Console\Commands\SampleMetrics;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(SampleMetrics::class)->everyMinute()->withoutOverlapping();
Schedule::command(EvaluateAlerts::class)->everyMinute()->withoutOverlapping();
Schedule::command(RunScheduledBackup::class)->hourly()->withoutOverlapping();
