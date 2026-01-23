<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureManagementWallboardAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = (bool) config('wallboard.management.ip_allowlist_enabled', false);
        $allowlist = config('wallboard.management.ip_allowlist', []);

        if (!$enabled) {
            return $next($request);
        }

        if (!empty($allowlist)) {
            $ip = $request->ip();
            if (in_array($ip, $allowlist, true)) {
                return $next($request);
            }
        }

        return response('Forbidden', 403);
    }
}
