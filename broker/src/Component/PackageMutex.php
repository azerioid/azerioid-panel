<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Component;

use AzerioidPanel\Broker\BrokerException;

/** Exclusive package-manager lock (global mutex for apt/dnf). */
final class PackageMutex
{
    private mixed $handle = null;

    public function __construct(private readonly string $lockPath)
    {
    }

    public function acquire(int $waitSeconds = 120): void
    {
        $dir = dirname($this->lockPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $this->handle = @fopen($this->lockPath, 'c+');
        if ($this->handle === false) {
            throw new BrokerException('Could not open package lock file.', 1);
        }
        $deadline = microtime(true) + $waitSeconds;
        while (true) {
            if (flock($this->handle, LOCK_EX | LOCK_NB)) {
                ftruncate($this->handle, 0);
                fwrite($this->handle, (string) getmypid());
                fflush($this->handle);
                return;
            }
            if (microtime(true) >= $deadline) {
                throw new BrokerException(
                    'Another package manager operation is running; timed out waiting for the lock.',
                    1
                );
            }
            usleep(500_000);
        }
    }

    public function release(): void
    {
        if (!is_resource($this->handle)) {
            return;
        }
        flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;
    }

    public function __destruct()
    {
        $this->release();
    }
}
