<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureSetupComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if (User::query()->exists()) {
            return $next($request);
        }

        if ($request->is('livewire/*') || $request->routeIs('setup', 'setup.*', 'livewire.*')) {
            return $next($request);
        }

        return redirect()->route('setup');
    }
}
