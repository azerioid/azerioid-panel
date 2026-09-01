<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Tests;

use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\FakeRuntime;
use AzerioidPanel\Broker\Kernel;
use AzerioidPanel\Broker\PosixRuntime;
use PDOException;
use PHPUnit\Framework\TestCase;

final class DbAddTest extends TestCase
{
    public function test_rolls_back_database_when_create_user_fails(): void
    {
        $rt = new FakeRuntime();
        $rt->dbExecFailAt = 2;
        ob_start();
        $cfg = new Config();
        $cfg->databaseEngine = 'mariadb';
        $cfg->mysqlPassword = 'abcdefghijklmnopqrst';
        $code = (new Kernel($cfg, $rt))->run(
            ['broker', 'db.add', 'shopdb', 'shopuser'],
            ['password' => 'abcdefghijklmnopqrst']
        );
        $out = ob_get_clean();
        $json = json_decode($out, true);

        $this->assertNotSame(0, $code);
        $this->assertFalse($json['ok']);
        $this->assertStringContainsString('simulated MariaDB failure', $json['error']);
        $this->assertStringNotContainsString('Internal broker error', $json['error']);
        $sql = implode("\n", $rt->dbExecLog);
        $this->assertStringContainsString('CREATE DATABASE `shopdb`', $sql);
        $this->assertStringContainsString('DROP DATABASE IF EXISTS `shopdb`', $sql);
        $this->assertStringNotContainsString('abcdefghijklmnopqrst', $sql);
        $this->assertStringNotContainsString('abcdefghijklmnopqrst', $out);
    }

    public function test_pdo_message_redacts_identified_by(): void
    {
        $e = new PDOException("SQLSTATE[42000]: Syntax error: IDENTIFIED BY 'supersecret-password-xx'");
        $msg = PosixRuntime::describePdo($e);
        $this->assertStringNotContainsString('supersecret-password-xx', $msg);
        $this->assertStringContainsString('[redacted]', $msg);
    }

    public function test_unexpected_throwable_is_not_internal_broker_error(): void
    {
        $rt = new FakeRuntime();
        $rt->dbExecFailAt = 1;
        ob_start();
        $cfg = new Config();
        $cfg->databaseEngine = 'mariadb';
        $cfg->mysqlPassword = 'abcdefghijklmnopqrst';
        $code = (new Kernel($cfg, $rt))->run(
            ['broker', 'db.add', 'shopdb', 'shopuser'],
            ['password' => 'abcdefghijklmnopqrst']
        );
        $out = ob_get_clean();
        $json = json_decode($out, true);
        $this->assertNotSame(0, $code);
        $this->assertStringNotContainsString('Internal broker error', $json['error']);
        $this->assertStringContainsString('simulated MariaDB failure', $json['error']);
    }
}
