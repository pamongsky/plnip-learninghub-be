<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequests
{
    /**
     * Sensitive fields that should NOT be logged
     */
    protected array $sensitiveFields = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'otp_code',
        'reset_token',
        'token',
        'api_token',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        // Process request
        $response = $next($request);

        $duration = round((microtime(true) - $startTime) * 1000, 2); // in milliseconds

        // Log request details
        $this->logRequest($request, $response, $duration);

        return $response;
    }

    /**
     * Log request details
     */
    protected function logRequest(Request $request, Response $response, float $duration): void
    {
        $logData = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $request->user()?->id,
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $duration,
        ];

        // Add request body for non-GET requests (excluding sensitive fields)
        if (!$request->isMethod('GET') && $request->input()) {
            $logData['input'] = $this->sanitizeInput($request->input());
        }

        // Log based on status code
        $level = $this->getLogLevel($response->getStatusCode());

        Log::channel('daily')->$level('HTTP Request', $logData);

        // Log slow requests separately (>1000ms)
        if ($duration > 1000) {
            Log::channel('daily')->warning('Slow Request Detected', [
                'url' => $request->fullUrl(),
                'duration_ms' => $duration,
                'method' => $request->method(),
            ]);
        }

        // Log failed requests to security channel
        if ($response->getStatusCode() >= 400) {
            Log::channel('security')->warning('Failed HTTP Request', $logData);
        }
    }

    /**
     * Remove sensitive fields from input before logging
     */
    protected function sanitizeInput(array $input): array
    {
        $sanitized = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $this->sensitiveFields, true)) {
                $sanitized[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeInput($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Get appropriate log level based on status code
     */
    protected function getLogLevel(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 500 => 'error',
            $statusCode >= 400 => 'warning',
            default => 'info',
        };
    }
}
