<?php

namespace App\Console\Commands;

use App\Services\Panel\MariadbPanelImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportPanelFromMariadb extends Command
{
    protected $signature = 'panel:import-from-mariadb
        {--driver=mysql : Legacy driver (mysql or mariadb)}
        {--host=127.0.0.1 : Legacy database host}
        {--port=3306 : Legacy database port}
        {--socket= : Legacy unix socket}
        {--database=lacmp_panel : Legacy database name}
        {--username=lacmp_panel : Legacy database user}
        {--password= : Legacy database password}
        {--dry-run : Count rows only; do not write to SQLite}';

    protected $description = 'Copy panel tables from a legacy MariaDB/MySQL database into the active SQLite panel database';

    public function handle(MariadbPanelImporter $importer): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! in_array((string) config('database.default'), ['sqlite'], true)) {
            $this->error('Default DB_CONNECTION must be sqlite before importing.');

            return self::FAILURE;
        }

        MariadbPanelImporter::configureLegacyConnection([
            'driver' => (string) $this->option('driver'),
            'host' => (string) $this->option('host'),
            'port' => (string) $this->option('port'),
            'unix_socket' => (string) $this->option('socket'),
            'database' => (string) $this->option('database'),
            'username' => (string) $this->option('username'),
            'password' => (string) $this->option('password'),
        ]);

        try {
            DB::connection('legacy_panel')->getPdo();
        } catch (Throwable $e) {
            $this->error('Could not connect to legacy panel database: '.$e->getMessage());

            return self::FAILURE;
        }

        $result = $importer->import(
            DB::connection('legacy_panel'),
            DB::connection(),
            $dryRun,
        );

        foreach ($result['tables'] as $table => $count) {
            $this->line(sprintf('  %-24s %d', $table.':', $count));
        }

        if ($result['skipped'] !== []) {
            $this->warn('Skipped (missing on source or target): '.implode(', ', $result['skipped']));
        }

        if ($dryRun) {
            $this->info('Dry run complete — no rows copied.');
        } else {
            $this->info('Panel data imported into SQLite.');
        }

        return self::SUCCESS;
    }
}
