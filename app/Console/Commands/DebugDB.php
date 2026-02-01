<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DebugDB extends Command
{
    protected $signature = 'debug:db {email}';
    protected $description = 'Check Moodle DB User by Email';

    public function handle()
    {
        $email = $this->argument('email');
        $this->info("🔍 Searching Moodle DB for: $email");

        try {
            // Test Connection
            DB::connection('moodle')->getPdo();
            $this->info("✅ Connection OK");

            // Query
            $user = DB::connection('moodle')->table('user')
                ->where('email', $email)
                ->first();

            if ($user) {
                $this->info("✅ FOUND USER:");
                $this->line("   ID: " . $user->id);
                $this->line("   Username: " . $user->username);
                $this->line("   Email: " . $user->email);
            } else {
                $this->error("❌ User NOT FOUND for email: $email");
                
                // Show similar lines
                $similar = DB::connection('moodle')->table('user')
                    ->select('id', 'username', 'email') // Limit columns
                    ->limit(10)
                    ->get();
                
                $this->warn("   Here are the first 10 users in DB to check structure:");
                foreach ($similar as $u) {
                    $this->line("   - {$u->username} ({$u->email})");
                }
            }

        } catch (\Exception $e) {
            $this->error("❌ EXCEPTION: " . $e->getMessage());
        }
    }
}
