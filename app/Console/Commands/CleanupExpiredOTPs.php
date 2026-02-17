<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CleanupExpiredOTPs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'otp:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired password reset OTPs from database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Cleaning up expired password reset OTPs...');

        // Delete expired OTPs (older than 1 hour to be safe)
        $deleted = DB::table('password_reset_otps')
            ->where('expires_at', '<', Carbon::now()->subHour())
            ->delete();

        $this->info("Deleted {$deleted} expired OTP record(s).");

        return Command::SUCCESS;
    }
}
