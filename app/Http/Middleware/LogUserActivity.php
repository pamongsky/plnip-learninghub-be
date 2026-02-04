<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class LogUserActivity
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Only log for authenticated users
        if (Auth::check()) {
            $user = Auth::user();

            // Don't log every request, only important ones
            $importantRoutes = [
                'login' => 'Login',
                'logout' => 'Logout',
                'dashboard/stats' => 'Akses Dashboard',
                'superadmin/users' => 'Akses User Management',
                'moodle/sync/full' => 'Sync Moodle',
                'moodle/login-url' => 'Akses LMS Moodle',
            ];

            $path = $request->path();
            $method = $request->method();

            // Log important actions
            foreach ($importantRoutes as $route => $label) {
                if (str_contains($path, $route)) {
                    // Only log GET for views, POST/PUT/DELETE for actions
                    if ($method === 'GET' && !in_array($route, ['login', 'logout'])) {
                        // Skip logging GET requests to avoid too many logs
                        continue;
                    }

                    AuditLog::create([
                        'user_id' => $user->id,
                        'action' => $label,
                        'entity_type' => 'System',
                        'entity_id' => $user->id,
                        'changes' => [
                            'path' => $path,
                            'method' => $method,
                        ],
                        'reason' => null,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]);

                    break;
                }
            }
        }

        return $next($request);
    }
}
