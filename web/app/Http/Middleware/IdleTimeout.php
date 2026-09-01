<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class IdleTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $idle = (int) (session('idle_minutes') ?: config('lacmp.session_idle_minutes', 15));
            $last = (int) $request->session()->get('last_activity_at', time());
            if ($idle > 0 && (time() - $last) > ($idle * 60)) {
                auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route('login')->with('status', 'Session expired due to inactivity.');
            }
            $request->session()->put('last_activity_at', time());
        }

        return $next($request);
    }
}
