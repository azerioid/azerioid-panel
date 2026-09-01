<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Tests;

use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\FakeRuntime;
use AzerioidPanel\Broker\Kernel;
use PHPUnit\Framework\TestCase;

final class ComponentInstallTest extends TestCase
{
    private string $registryPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registryPath = dirname(__DIR__, 3) . '/registry/components';
    }

    private function kernel(FakeRuntime $rt): Kernel
    {
        $cfg = new Config();
        $cfg->registryComponentsPath = $this->registryPath;
        $cfg->stagingDir = '/var/lib/azerioid-panel/staging';
        $cfg->managedComponentsPath = '/var/lib/azerioid-panel/managed-components.json';
        $rt->dirs['/var/lib/azerioid-panel/staging'] = true;
        $rt->dirs['/var/lib/azerioid-panel/staging/operations'] = true;
        return new Kernel($cfg, $rt);
    }

    /** @return array{0:int,1:array} */
    private function capture(Kernel $kernel, array $argv, array $stdin = []): array
    {
        ob_start();
        $code = $kernel->run($argv, $stdin);
        $out = ob_get_clean();
        return [$code, json_decode(trim((string) $out), true)];
    }

    private function ubuntuRuntime(): FakeRuntime
    {
        $rt = new FakeRuntime();
        $rt->files['/etc/os-release'] = "ID=ubuntu\nVERSION_ID=\"24.04\"\n";
        $rt->files['/proc/meminfo'] = "MemAvailable: 2097152 kB\n";
        $rt->dirs[rtrim($this->registryPath, '/')] = true;
        foreach (glob($this->registryPath . '/*.json') ?: [] as $path) {
            $rt->files[$path] = (string) file_get_contents($path);
        }
        $rt->script(['/bin/df', '-B1', '-P', '/var'], 0, "Filesystem 1B-blocks Used Available Capacity Mounted on\n/dev/sda1 10000000000 1000000000 9000000000 10% /var\n");
        return $rt;
    }

    public function test_rejects_non_installable_component(): void
    {
        [$code, $json] = $this->capture(
            $this->kernel($this->ubuntuRuntime()),
            ['broker', 'component.install', 'caddy'],
            ['operation_id' => 'op-1']
        );
        $this->assertNotSame(0, $code);
        $this->assertStringContainsString('install', strtolower((string) $json['error']));
    }

    public function test_rejects_unknown_component(): void
    {
        [$code] = $this->capture(
            $this->kernel($this->ubuntuRuntime()),
            ['broker', 'component.install', 'not-real'],
            ['operation_id' => 'op-1']
        );
        $this->assertNotSame(0, $code);
    }

    public function test_preflight_ok_for_redis(): void
    {
        [$code, $json] = $this->capture(
            $this->kernel($this->ubuntuRuntime()),
            ['broker', 'component.preflight', 'redis']
        );
        $this->assertSame(0, $code);
        $this->assertTrue($json['data']['ok']);
    }
}
