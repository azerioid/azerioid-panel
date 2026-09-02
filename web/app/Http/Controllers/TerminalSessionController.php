<?php

namespace App\Http\Controllers;

use App\Services\Broker\BrokerClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TerminalSessionController
{
    public function heartbeat(string $sessionId, BrokerClient $broker): JsonResponse
    {
        $res = $broker->call('terminal.session.heartbeat', [$sessionId]);

        return response()->json(['ok' => $res->ok, 'data' => $res->data, 'error' => $res->error]);
    }

    public function stop(string $sessionId, Request $request, BrokerClient $broker): JsonResponse
    {
        $res = $broker->call('terminal.session.stop', [$sessionId]);

        return response()->json(['ok' => $res->ok, 'data' => $res->data, 'error' => $res->error]);
    }
}
