<?php

namespace App\Http\Middleware;

use App\Utils\InputSanitizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInput
{
    /**
     * Fields that should NOT be sanitized (e.g., passwords)
     */
    protected array $except = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Sanitize all input except excluded fields
        $input = $request->all();
        $sanitized = $this->sanitize($input);

        $request->merge($sanitized);

        return $next($request);
    }

    /**
     * Recursively sanitize input
     */
    protected function sanitize(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            // Skip excluded fields
            if (in_array($key, $this->except)) {
                $sanitized[$key] = $value;
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = InputSanitizer::sanitizeString($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
