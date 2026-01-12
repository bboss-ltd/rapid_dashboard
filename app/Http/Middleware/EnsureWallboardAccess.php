<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWallboardAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowlist = config('wallboard.ip_allowlist', []);
        $basicEnabled = (bool) config('wallboard.basic_auth_enabled', false);

        // If nothing configured, allow public access
        if (!$basicEnabled && empty($allowlist)) {
            return $next($request);
        }

        // 1) Allow if IP is in allowlist
        if (!empty($allowlist)) {
            $ip = $request->ip(); // be sure TrustProxies is set correctly if behind a proxy
            if (in_array($ip, $allowlist, true)) {
                return $next($request);
            }
        }

        // 2) Allow if basic auth passes
        if ($basicEnabled) {
            $expectedUser = (string) config('wallboard.basic_auth_user');
            $expectedPass = (string) config('wallboard.basic_auth_pass');

            $user = $request->getUser();
            $pass = $request->getPassword();

            if ($user === $expectedUser && $pass === $expectedPass && $expectedPass !== '') {
                return $next($request);
            }

            return response('Unauthorized', 401, [
                'WWW-Authenticate' => 'Basic realm="Wallboard"',
            ]);
        }

        // If only allowlist is configured and IP failed
        return response('Forbidden', 403);
    }
}
