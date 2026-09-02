<?php

namespace App\Http\Controllers;

use App\Services\Broker\BrokerClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final class TerminalAuthController
{
    public function __invoke(Request $request, string $sessionId, BrokerClient $broker): Response
    {
        if (! in_array($request->ip(), ['127.0.0.1', '::1'], true)) {
            abort(403);
        }
        if (! Auth::check()) {
            abort(401);
        }

        $res = $broker->call('terminal.session.status', [$sessionId], [], null, false);
        if (! $res->ok) {
            abort(403);
        }

        $session = is_array($res->data) ? $res->data : [];
        if ((string) ($session['admin_user_id'] ?? '') !== (string) Auth::id()) {
            abort(403);
        }

        return response('', 200);
    }
}
