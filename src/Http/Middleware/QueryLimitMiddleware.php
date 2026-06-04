<?php

declare(strict_types=1);

namespace Mostafax\ReportingEngine\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies per-user rate limiting and max-query-time enforcement headers.
 * Pairs with the server-side DB timeout set via ReportEngine options.
 */
final class QueryLimitMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $config = config('reporting-engine.rate_limiting', []);

        if ($config['enabled'] ?? true) {
            $key      = 'dhr:' . ($request->user()?->getKey() ?? $request->ip());
            $maxAttempts = (int) ($config['max_attempts'] ?? 60);
            $decay       = (int) ($config['decay_seconds'] ?? 60);

            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                $retryAfter = RateLimiter::availableIn($key);
                return response()->json([
                    'message'     => 'Too many requests.',
                    'retry_after' => $retryAfter,
                ], 429)->withHeaders([
                    'Retry-After'   => $retryAfter,
                    'X-RateLimit-Limit' => $maxAttempts,
                ]);
            }

            RateLimiter::hit($key, $decay);
        }

        $response = $next($request);

        // Expose remaining budget in response headers
        if ($config['enabled'] ?? true) {
            $key          = 'dhr:' . ($request->user()?->getKey() ?? $request->ip());
            $maxAttempts  = (int) ($config['max_attempts'] ?? 60);
            $remaining    = RateLimiter::remaining($key, $maxAttempts);

            $response->headers->set('X-RateLimit-Limit',     (string) $maxAttempts);
            $response->headers->set('X-RateLimit-Remaining', (string) $remaining);
        }

        return $response;
    }
}
