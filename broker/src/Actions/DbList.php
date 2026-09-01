<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Actions;

use LacmpPanel\Broker\Config;
use LacmpPanel\Broker\Runtime;

final class DbList
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $rows = $runtime->dbQuery(
            'SELECT s.SCHEMA_NAME AS name,
                    COALESCE(SUM(t.DATA_LENGTH + t.INDEX_LENGTH), 0) AS size_bytes,
                    COUNT(t.TABLE_NAME) AS table_count
             FROM information_schema.SCHEMATA s
             LEFT JOIN information_schema.TABLES t
               ON t.TABLE_SCHEMA = s.SCHEMA_NAME
             GROUP BY s.SCHEMA_NAME
             ORDER BY s.SCHEMA_NAME'
        );

        $usersByDb = [];
        $grants = $runtime->dbQuery(
            "SELECT Db, User, Host FROM mysql.db WHERE Db <> ''"
        );
        foreach ($grants as $g) {
            $db = str_replace(['\\_', '\\%'], ['_', '%'], (string) $g['Db']);
            $usersByDb[$db][] = [
                'user' => $g['User'],
                'host' => $g['Host'],
            ];
        }

        $databases = [];
        foreach ($rows as $row) {
            $name = (string) $row['name'];
            $databases[] = [
                'name' => $name,
                'size_bytes' => (int) $row['size_bytes'],
                'table_count' => (int) $row['table_count'],
                'users' => $usersByDb[$name] ?? [],
                'protected' => in_array($name, $config->protectedDatabases, true),
            ];
        }

        return ['databases' => $databases];
    }
}
