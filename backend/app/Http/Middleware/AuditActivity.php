<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuditActivity
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log mutating actions if successful (2xx status codes)
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']) && $response->isSuccessful()) {
            try {
                $user = $request->user();
                $cleanInput = $request->except(['password', 'password_confirmation', 'two_factor_secret', 'token']);

                AuditLog::create([
                    'user_id' => $user?->id,
                    'event_type' => strtolower($request->method()).':'.$request->path(),
                    'model_type' => null,
                    'model_id' => null,
                    'old_values' => null,
                    'new_values' => ! empty($cleanInput) ? $cleanInput : null,
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 500),
                    'created_at' => now(),
                ]);
            } catch (\Throwable) {
                // Non-blocking: audit log failure should never crash the user request
            }
        }

        return $response;
    }
}
