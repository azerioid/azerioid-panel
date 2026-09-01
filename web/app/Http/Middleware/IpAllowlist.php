<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class IpAllowlist
{
    public function handle(Request $request, Closure $next): Response
    {
        $list = Setting::get('ip_allowlist', []);
        if (! is_array($list) || $list === []) {
            return $next($request);
        }

        $ip = $request->ip();
        foreach ($list as $allowed) {
            if (is_string($allowed) && $allowed !== '' && $ip === $allowed) {
                return $next($request);
            }
        }

        abort(403, 'This IP is not allowlisted for the panel.');
    }
}
