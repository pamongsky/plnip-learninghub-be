<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Sync users dari ERP setiap hari pada jam yang dikonfigurasi
        if (config('erp.enabled')) {
            $syncTime = config('erp.schedule', '02:00');
            [$hour, $minute] = explode(':', $syncTime);

            $schedule->command('erp:sync')
                ->dailyAt($syncTime)
                ->withoutOverlapping()
                ->onSuccess(function () {
                    \Illuminate\Support\Facades\Log::channel('audit')->info('ERP sync scheduled task completed successfully');
                })
                ->onFailure(function () {
                    \Illuminate\Support\Facades\Log::channel('security')->error('ERP sync scheduled task failed');
                });
        }

        // Clean up expired password reset OTPs every hour
        $schedule->command('otp:cleanup')
            ->hourly()
            ->withoutOverlapping();

        // Clean up expired Sanctum tokens daily
        $schedule->command('sanctum:cleanup')
            ->daily()
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
