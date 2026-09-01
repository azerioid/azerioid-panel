<?php
declare(strict_types=1);

namespace LacmpPanel\Broker;

final class ExecResult
{
    public function __construct(
        public readonly array $command,
        public readonly int $exitCode,
        public readonly string $stdout,
        public readonly string $stderr,
    ) {
    }

    public function ok(): bool
    {
        return $this->exitCode === 0;
    }
}
