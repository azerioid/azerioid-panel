<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Tests;

use LacmpPanel\Broker\PosixRuntime;
use PHPUnit\Framework\TestCase;

final class PosixRuntimeIoFailureTest extends TestCase
{
    public function test_erofs_message_points_at_fpm_sandbox(): void
    {
        $msg = PosixRuntime::describeIoFailure('write', '/etc/caddy/conf.d/abc.com.conf.lacmp-tmp', [
            'message' => 'file_put_contents(/etc/caddy/conf.d/abc.com.conf.lacmp-tmp): Failed to open stream: Read-only file system',
        ]);
        $this->assertStringContainsString('read-only for the broker context', $msg);
        $this->assertStringContainsString('ProtectSystem', $msg);
        $this->assertStringContainsString('ReadWritePaths', $msg);
    }

    public function test_child_env_has_home_and_path(): void
    {
        $method = new \ReflectionMethod(PosixRuntime::class, 'childEnv');
        $env = $method->invoke(null);
        $this->assertNotSame('', $env['HOME'] ?? '');
        $this->assertArrayHasKey('PATH', $env);
        $this->assertArrayHasKey('XDG_CONFIG_HOME', $env);
        if (getenv('HOME') !== false && getenv('HOME') !== '') {
            $this->assertSame(getenv('HOME'), $env['HOME']);
        }
    }

    public function test_open_basedir_stays_specific(): void
    {
        $msg = PosixRuntime::describeIoFailure('write', '/etc/caddy/conf.d/x.conf', [
            'message' => 'file_put_contents(): open_basedir restriction in effect',
        ]);
        $this->assertStringContainsString('open_basedir', $msg);
    }
}
