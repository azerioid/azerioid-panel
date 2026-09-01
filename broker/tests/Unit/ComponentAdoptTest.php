<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Tests;

use AzerioidPanel\Broker\Component\ComponentAdopter;
use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\FakeRuntime;
use PHPUnit\Framework\TestCase;

final class ComponentAdoptTest extends TestCase
{
    private string $registryPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registryPath = dirname(__DIR__, 3) . '/registry/components';
    }

    public function test_adopts_observed_mariadb(): void
    {
        $rt = new FakeRuntime();
        $rt->files['/etc/os-release'] = "ID=ubuntu\nVERSION_ID=\"24.04\"\n";
        $rt->files['/var/lib/azerioid-panel/managed-components.json'] = "{\"components\":{}}\n";
        $rt->files['/run/mysqld/mysqld.sock'] = '';
        $rt->dirs[rtrim($this->registryPath, '/')] = true;
        foreach (glob($this->registryPath . '/*.json') ?: [] as $path) {
            $rt->files[$path] = (string) file_get_contents($path);
        }
        $rt->script(['/usr/bin/dpkg-query', '-W', '-f=${Status}', 'mariadb-server'], 0, 'install ok installed');
        $rt->script(['/usr/bin/dpkg-query', '-W', '-f=${Status}', 'postgresql'], 1, '', 'not installed');
        $rt->script(['psql', '--version'], 1, '', 'not found');
        $rt->script(['/usr/bin/systemctl', 'show', 'mariadb', '--property=LoadState', '--no-pager'], 0, "LoadState=loaded\n");
        $rt->script([
            '/usr/bin/systemctl', 'show', 'mariadb',
            '--property=Id,ActiveState,SubState,MainPID,NRestarts,ActiveEnterTimestamp,UnitFileState,Description',
            '--no-pager',
        ], 0, "Id=mariadb.service\nActiveState=active\nSubState=running\n");
        $rt->script(['/usr/bin/mariadb', '-e', 'CREATE USER'], 0);
        $rt->script(['/bin/df', '-B1', '-P', '/var'], 0, "Filesystem 1B-blocks Used Available Capacity Mounted on\n/dev/sda1 10000000000 1000000000 9000000000 10% /var\n");
        $rt->files['/proc/meminfo'] = "MemAvailable: 2097152 kB\n";

        $cfg = new Config();
        $cfg->registryComponentsPath = $this->registryPath;
        $cfg->managedComponentsPath = '/var/lib/azerioid-panel/managed-components.json';
        $cfg->stagingDir = '/var/lib/azerioid-panel/staging';
        $rt->dirs['/var/lib/azerioid-panel/staging'] = true;

        $result = (new ComponentAdopter($cfg, $rt))->adopt('mariadb');
        $this->assertTrue($result['adopted']);
        $this->assertSame('managed', $result['status']['kind']);
    }
}
