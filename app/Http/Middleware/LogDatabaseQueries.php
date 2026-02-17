<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogDatabaseQueries
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only enable in development or when explicitly enabled
        if (!config('app.debug') && !config('database.log_queries', false)) {
            return $next($request);
        }

        // Enable query logging
        DB::enableQueryLog();

        $response = $next($request);

        // Get executed queries
        $queries = DB::getQueryLog();
        $totalQueries = count($queries);
        $totalTime = collect($queries)->sum('time');

        // Log slow queries (>100ms)
        $slowQueries = collect($queries)->filter(function ($query) {
            return $query['time'] > 100;
        });

        if ($slowQueries->isNotEmpty()) {
            Log::channel('daily')->warning('Slow Database Queries Detected', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'total_queries' => $totalQueries,
                'total_time_ms' => $totalTime,
                'slow_queries' => $slowQueries->map(function ($query) {
                    return [
                        'sql' => $query['query'],
                        'bindings' => $query['bindings'],
                        'time_ms' => $query['time'],
                    ];
                })->toArray(),
            ]);
        }

        // Log if too many queries (>50 queries = potential N+1 problem)
        if ($totalQueries > 50) {
            Log::channel('daily')->warning('High Query Count Detected', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'total_queries' => $totalQueries,
                'total_time_ms' => $totalTime,
            ]);
        }

        // Add query stats to response headers (development only)
        if (config('app.debug')) {
            $response->headers->set('X-Database-Queries', $totalQueries);
            $response->headers->set('X-Database-Time', round($totalTime, 2) . 'ms');
        }

        return $response;
    }
}
