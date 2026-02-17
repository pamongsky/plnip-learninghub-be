<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CleanupExpiredTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sanctum:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired Sanctum tokens from database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Cleaning up expired Sanctum tokens...');

        $expirationMinutes = (int) config('sanctum.expiration', 1440);
        $expiryTime = Carbon::now()->subMinutes($expirationMinutes);

        // Delete tokens that haven't been used since expiry time
        $deleted = DB::table('personal_access_tokens')
            ->where('last_used_at', '<', $expiryTime)
            ->delete();

        $this->info("Deleted {$deleted} expired token(s).");

        return Command::SUCCESS;
    }
}
