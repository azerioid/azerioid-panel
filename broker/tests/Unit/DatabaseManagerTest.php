<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Tests;

use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Database\DatabaseManager;
use AzerioidPanel\Broker\FakeRuntime;
use PHPUnit\Framework\TestCase;

final class DatabaseManagerTest extends TestCase
{
    public function test_resolves_legacy_mariadb_credentials(): void
    {
        $cfg = new Config();
        $cfg->mysqlPassword = 'abcdefghijklmnopqrst';
        $manager = new DatabaseManager($cfg, new FakeRuntime());
        $this->assertSame('mariadb', $manager->resolveEngine(''));
    }

    public function test_db_engine_lists_configured_engines(): void
    {
        $cfg = new Config();
        $cfg->databaseEngine = 'mariadb';
        $cfg->mysqlPassword = 'abcdefghijklmnopqrst';
        $engines = (new DatabaseManager($cfg, new FakeRuntime()))->engines();
        $mariadb = array_values(array_filter($engines, fn (array $e) => $e['id'] === 'mariadb'))[0];
        $this->assertTrue($mariadb['configured']);
        $this->assertTrue($mariadb['active']);
    }
}
