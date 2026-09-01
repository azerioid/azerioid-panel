<?php

namespace App\Services\Panel;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class MariadbPanelImporter
{
    /** @var list<string> */
    public const TABLE_ORDER = [
        'users',
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'settings',
        'audit_logs',
        'alert_incidents',
        'metric_samples',
        'backup_jobs',
        'component_operations',
    ];

    /**
     * @return array{tables: array<string, int>, skipped: list<string>}
     */
    public function import(Connection $source, Connection $target, bool $dryRun = false): array
    {
        $counts = [];
        $skipped = [];

        if ($dryRun) {
            foreach (self::TABLE_ORDER as $table) {
                if (! Schema::connection($source->getName())->hasTable($table)) {
                    $skipped[] = $table;

                    continue;
                }

                $counts[$table] = (int) $source->table($table)->count();
            }

            return ['tables' => $counts, 'skipped' => $skipped];
        }

        $target->statement('PRAGMA foreign_keys = OFF');

        try {
            foreach (self::TABLE_ORDER as $table) {
                if (! Schema::connection($source->getName())->hasTable($table)) {
                    $skipped[] = $table;

                    continue;
                }

                if (! Schema::connection($target->getName())->hasTable($table)) {
                    $skipped[] = $table;

                    continue;
                }

                $columns = $this->sharedColumns($source, $target, $table);
                if ($columns === []) {
                    $skipped[] = $table;

                    continue;
                }

                $total = (int) $source->table($table)->count();
                $counts[$table] = $total;

                if ($total === 0) {
                    continue;
                }

                $target->table($table)->delete();
                $this->resetSqliteSequence($target, $table);

                $sourceQuery = $source->table($table)->select($columns);
                if (in_array('id', $columns, true)) {
                    $sourceQuery->orderBy('id');
                } elseif ($columns !== []) {
                    $sourceQuery->orderBy($columns[0]);
                }

                $sourceQuery->chunk(250, function ($rows) use ($target, $table): void {
                        $payload = [];
                        foreach ($rows as $row) {
                            $payload[] = $this->normalizeRow((array) $row);
                        }
                        if ($payload !== []) {
                            $target->table($table)->insert($payload);
                        }
                    });

                $this->resetSqliteSequence($target, $table);
            }
        } finally {
            $target->statement('PRAGMA foreign_keys = ON');
        }

        return ['tables' => $counts, 'skipped' => $skipped];
    }

    /** @return list<string> */
    private function sharedColumns(Connection $source, Connection $target, string $table): array
    {
        $sourceColumns = Schema::connection($source->getName())->getColumnListing($table);
        $targetColumns = Schema::connection($target->getName())->getColumnListing($table);

        return array_values(array_intersect($targetColumns, $sourceColumns));
    }

    /** @param array<string, mixed> $row */
    private function normalizeRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $row[$key] = $value->format('Y-m-d H:i:s');
            }
        }

        return $row;
    }

    private function resetSqliteSequence(Connection $target, string $table): void
    {
        if (! str_contains((string) $target->getDriverName(), 'sqlite')) {
            return;
        }

        $maxId = $target->table($table)->max('id');
        if ($maxId === null) {
            $target->statement('DELETE FROM sqlite_sequence WHERE name = ?', [$table]);

            return;
        }

        $existing = $target->selectOne('SELECT seq FROM sqlite_sequence WHERE name = ?', [$table]);
        if ($existing === null) {
            $target->insert('INSERT INTO sqlite_sequence (name, seq) VALUES (?, ?)', [$table, $maxId]);
        } else {
            $target->update('UPDATE sqlite_sequence SET seq = ? WHERE name = ?', [$maxId, $table]);
        }
    }

    public static function configureLegacyConnection(array $config): void
    {
        if (($config['database'] ?? '') === '') {
            throw new RuntimeException('Legacy panel database name is required.');
        }

        config([
            'database.connections.legacy_panel' => [
                'driver' => $config['driver'] ?? 'mysql',
                'host' => $config['host'] ?? '127.0.0.1',
                'port' => $config['port'] ?? '3306',
                'database' => $config['database'],
                'username' => $config['username'] ?? '',
                'password' => $config['password'] ?? '',
                'unix_socket' => $config['unix_socket'] ?? '',
                'charset' => $config['charset'] ?? 'utf8mb4',
                'collation' => $config['collation'] ?? 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
            ],
        ]);

        DB::purge('legacy_panel');
    }
}
