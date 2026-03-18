<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PerformanceMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        
        // Add security headers
        $response = $next($request);
        
        // Calculate execution time
        $executionTime = microtime(true) - $startTime;
        
        // Add performance headers
        $response->headers->set('X-Execution-Time', number_format($executionTime * 1000, 2) . 'ms');
        $response->headers->set('X-Memory-Usage', number_format(memory_get_usage(true) / 1024 / 1024, 2) . 'MB');
        
        // Log slow requests
        if ($executionTime > 2.0) {
            Log::warning('Slow request detected', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'execution_time' => $executionTime,
                'memory_usage' => memory_get_usage(true),
                'user_id' => $request->user()?->id,
            ]);
        }
        
        // Cache control headers for API responses
        if ($request->is('api/*')) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }
        
        return $response;
    }
}
