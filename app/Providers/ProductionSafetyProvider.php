<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Artisan;

class ProductionSafetyProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Block dangerous commands in production
        if (app()->environment('production')) {
            $this->blockDangerousCommands();
        }
    }
    
    /**
     * Block dangerous Artisan commands in production
     */
    protected function blockDangerousCommands(): void
    {
        // List of blocked commands
        $blockedCommands = [
            'migrate:fresh',
            'migrate:refresh',
            'db:wipe',
            'db:seed --force',
        ];
        
        // Intercept Artisan commands
        Artisan::starting(function ($artisan) use ($blockedCommands) {
            $command = request()->server('argv', []);
            $commandString = implode(' ', array_slice($command, 1));
            
            foreach ($blockedCommands as $blocked) {
                if (str_contains($commandString, $blocked)) {
                    fwrite(STDERR, "\n");
                    fwrite(STDERR, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");
                    fwrite(STDERR, "🚨 BLOCKED: '$blocked' is disabled in PRODUCTION\n");
                    fwrite(STDERR, "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");
                    fwrite(STDERR, "\n");
                    fwrite(STDERR, "This command could DESTROY production data.\n");
                    fwrite(STDERR, "Environment: PRODUCTION\n");
                    fwrite(STDERR, "\n");
                    fwrite(STDERR, "Safe alternatives:\n");
                    fwrite(STDERR, "  • php artisan migrate (adds new tables safely)\n");
                    fwrite(STDERR, "  • Contact DBA for database operations\n");
                    fwrite(STDERR, "\n");
                    
                    exit(1);
                }
            }
        });
    }
}
