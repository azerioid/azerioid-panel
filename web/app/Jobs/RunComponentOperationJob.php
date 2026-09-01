<?php

namespace App\Jobs;

use App\Models\ComponentOperation;
use App\Services\Broker\BrokerCallException;
use App\Services\Broker\BrokerClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RunComponentOperationJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public int $operationId)
    {
    }

    public function handle(BrokerClient $broker): void
    {
        $operation = ComponentOperation::query()->find($this->operationId);
        if ($operation === null) {
            return;
        }

        if (ComponentOperation::query()
            ->where('status', 'running')
            ->where('id', '!=', $operation->id)
            ->exists()) {
            $this->release(15);

            return;
        }

        $operation->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        $brokerAction = $operation->action === 'uninstall' ? 'component.uninstall' : 'component.install';
        $operationKey = 'op-' . $operation->id;

        try {
            $stdin = ['operation_id' => $operationKey];
            if (is_array($operation->options) && $operation->options !== []) {
                $stdin['options'] = $operation->options;
            }
            $response = $broker->call(
                $brokerAction,
                [$operation->component_id],
                $stdin,
                900,
            );

            $logResponse = $broker->call('component.operation.log', [$operationKey], [], 30, audit: false);
            $lines = $logResponse->ok ? ($logResponse->data['lines'] ?? []) : [];

            if (!$response->ok) {
                $operation->update([
                    'status' => 'failed',
                    'error' => $response->error,
                    'log' => is_array($lines) ? implode("\n", $lines) : null,
                    'finished_at' => now(),
                ]);

                return;
            }

            $operation->update([
                'status' => 'completed',
                'error' => null,
                'log' => is_array($lines) ? implode("\n", $lines) : null,
                'finished_at' => now(),
            ]);
        } catch (BrokerCallException $e) {
            Log::error('Component operation failed', ['operation' => $operation->id, 'error' => $e->getMessage()]);
            $operation->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }
    }
}
