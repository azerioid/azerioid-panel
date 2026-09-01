<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Component;

use AzerioidPanel\Broker\Runtime;

final class OperationLogger
{
    public function __construct(
        private readonly Runtime $runtime,
        private readonly string $path,
    ) {
    }

    public function info(string $message): void
    {
        $this->append('INFO', $message);
    }

    public function warn(string $message): void
    {
        $this->append('WARN', $message);
    }

    public function path(): string
    {
        return $this->path;
    }

    private function append(string $level, string $message): void
    {
        $line = gmdate('Y-m-d\TH:i:s\Z') . " [{$level}] {$message}\n";
        $prev = $this->runtime->fileExists($this->path) ? $this->runtime->readFile($this->path) : '';
        $this->runtime->writeFile($this->path, $prev . $line, 0640);
    }
}
