<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuditLogger
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if ($this->shouldLog($request)) {

            Log::channel('soc')->info('api.request', [
                'event_type' => 'api.request',
                'timestamp' => now()->toIso8601String(),
                'user_id' => Auth::id(),
                'ip' => $request->ip(),
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'status' => $response->status(),
                'session_id' => substr(session()->getId(), 0, 8),
                'user_agent' => $request->userAgent()
            ]);
        }

        return $response;
    }

    private function shouldLog($request)
    {
        return str_contains($request->path(), 'login')
            || str_contains($request->path(), 'transfer')
            || str_contains($request->path(), 'token')
            || str_contains($request->path(), 'me')
            || str_contains($request->path(), 'account');
    }
}