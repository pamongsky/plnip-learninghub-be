<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SafeMigrate extends Command
{
    protected $signature = 'migrate:safe {--fresh : Dangerous: Drop all tables}';
    protected $description = 'Safe migration command with production protection';

    public function handle()
    {
        $environment = app()->environment();
        $isFresh = $this->option('fresh');
        
        // CRITICAL: Block migrate:fresh in production
        if ($isFresh && $environment === 'production') {
            $this->error('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->error('🚨 BLOCKED: migrate:fresh is DISABLED in production');
            $this->error('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->newLine();
            $this->warn('This command would DELETE ALL DATA in production database.');
            $this->warn('It has been permanently blocked for safety.');
            $this->newLine();
            $this->info('Safe alternatives:');
            $this->line('  • php artisan migrate           - Add new tables only');
            $this->line('  • php artisan migrate:rollback  - Undo last migration');
            $this->line('  • php artisan migrate:reset     - Rollback all (safer)');
            $this->newLine();
            $this->error('If you REALLY need to reset production, contact DBA.');
            
            return Command::FAILURE;
        }
        
        // Warning for staging
        if ($isFresh && $environment === 'staging') {
            $this->warn('⚠️  WARNING: You are about to DROP ALL TABLES in STAGING');
            $this->warn('Environment: ' . strtoupper($environment));
            $this->newLine();
            
            if (!$this->confirm('Type YES to confirm you want to delete all data', false)) {
                $this->info('Operation cancelled.');
                return Command::FAILURE;
            }
            
            // Double confirmation
            $confirmation = $this->ask('Type the word "DELETE" to proceed');
            if ($confirmation !== 'DELETE') {
                $this->error('Confirmation failed. Operation cancelled.');
                return Command::FAILURE;
            }
        }
        
        // Show current database info
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('Migration Safety Check');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('Environment: ' . strtoupper($environment));
        $this->line('Database: ' . config('database.default'));
        $this->line('Host: ' . config('database.connections.oracle.host'));
        
        if ($isFresh) {
            $this->line('Operation: FRESH (DROP ALL TABLES)');
        } else {
            $this->line('Operation: MIGRATE (SAFE)');
        }
        
        $this->newLine();
        
        // Count current tables/data
        try {
            $userCount = DB::table('USERS')->count();
            $this->line("Current users: $userCount");
            
            if ($isFresh && $userCount > 10) {
                $this->error("⚠️  HIGH USER COUNT: $userCount users will be DELETED");
                if (!$this->confirm('Are you ABSOLUTELY SURE?', false)) {
                    $this->info('Operation cancelled.');
                    return Command::FAILURE;
                }
            }
        } catch (\Exception $e) {
            // Table might not exist yet
        }
        
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();
        
        // Execute migration
        if ($isFresh) {
            $this->call('migrate:fresh');
        } else {
            $this->call('migrate');
        }
        
        $this->newLine();
        $this->info('✓ Migration completed safely');
        
        return Command::SUCCESS;
    }
}
