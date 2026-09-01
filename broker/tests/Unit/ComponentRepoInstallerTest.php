<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Tests;

use AzerioidPanel\Broker\Component\ComponentRepoInstaller;
use AzerioidPanel\Broker\Component\OperationLogger;
use AzerioidPanel\Broker\Component\OsRelease;
use AzerioidPanel\Broker\FakeRuntime;
use PHPUnit\Framework\TestCase;

final class ComponentRepoInstallerTest extends TestCase
{
    public function test_node_major_defaults_to_22_when_option_missing(): void
    {
        $rt = new FakeRuntime();
        $rt->files['/etc/os-release'] = "ID=ubuntu\nVERSION_ID=\"24.04\"\nVERSION_CODENAME=noble\n";
        $os = OsRelease::detect($rt);
        $log = new OperationLogger($rt, '/tmp/op.log');

        $installer = new ComponentRepoInstaller($rt);
        $installer->ensureForInstall($os, 'nodejs', [], $log);

        $joined = json_encode($rt->execLog);
        $this->assertIsString($joined);
        $this->assertStringContainsString('setup_22.x', $joined);
    }
}
