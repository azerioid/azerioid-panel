<?php

namespace App\Services\Broker;

final class BrokerResponse
{
    public function __construct(
        public readonly bool $ok,
        public readonly mixed $data,
        public readonly ?string $error,
        public readonly int $code,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            (bool) ($payload['ok'] ?? false),
            $payload['data'] ?? null,
            isset($payload['error']) ? (string) $payload['error'] : null,
            (int) ($payload['code'] ?? 1),
        );
    }

    public function dataOrFail(): mixed
    {
        if (! $this->ok) {
            throw new BrokerCallException($this->error ?: 'Broker call failed.', $this->code);
        }
        return $this->data;
    }
}
