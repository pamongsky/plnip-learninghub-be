<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ValidateEnvironment extends Command
{
    protected $signature = 'env:validate';
    protected $description = 'Validate environment configuration for production deployment';

    protected array $requiredEnvVars = [
        'APP_NAME',
        'APP_ENV',
        'APP_KEY',
        'APP_URL',
        'DB_CONNECTION',
        'DB_HOST',
        'DB_PORT',
        'DB_DATABASE',
        'DB_USERNAME',
        'MOODLE_DB_HOST',
        'MOODLE_DB_PORT',
        'MOODLE_DB_DATABASE',
        'MOODLE_DB_USERNAME',
        'MOODLE_URL',
        'CACHE_STORE',
        'SESSION_DRIVER',
        'BROADCAST_CONNECTION',
    ];

    protected array $productionRequirements = [
        'APP_DEBUG' => 'false',
        'APP_ENV' => 'production',
        'SESSION_SECURE_COOKIE' => 'true',
    ];

    public function handle()
    {
        $this->info('🔍 Validating Environment Configuration...');
        $this->newLine();

        $errors = [];
        $warnings = [];

        // Check required variables
        foreach ($this->requiredEnvVars as $var) {
            if (!env($var)) {
                $errors[] = "Missing required environment variable: {$var}";
            }
        }

        // Check APP_KEY
        if (!env('APP_KEY') || env('APP_KEY') === 'base64:') {
            $errors[] = 'APP_KEY is not set. Run: php artisan key:generate';
        }

        // Check MOODLE_URL format
        if (env('MOODLE_URL') && !str_starts_with(env('MOODLE_URL'), 'http')) {
            $warnings[] = 'MOODLE_URL should start with http:// or https://';
        }

        // Production-specific checks
        if (env('APP_ENV') === 'production') {
            foreach ($this->productionRequirements as $var => $expectedValue) {
                if (env($var) !== $expectedValue) {
                    $warnings[] = "Production environment should set {$var}={$expectedValue}";
                }
            }

            if (env('APP_DEBUG') === true || env('APP_DEBUG') === 'true') {
                $errors[] = 'APP_DEBUG must be false in production!';
            }
        }

        // Test database connections
        $this->info('📊 Testing Database Connections...');

        try {
            DB::connection()->getPdo();
            $this->info('✅ Portal database connection successful');
        } catch (\Exception $e) {
            $errors[] = 'Portal database connection failed: ' . $e->getMessage();
        }

        try {
            DB::connection('moodle')->getPdo();
            $this->info('✅ Moodle database connection successful');
        } catch (\Exception $e) {
            $errors[] = 'Moodle database connection failed: ' . $e->getMessage();
        }

        $this->newLine();

        // Display results
        if (empty($errors) && empty($warnings)) {
            $this->info('✅ All environment checks passed!');
            return Command::SUCCESS;
        }

        if (!empty($errors)) {
            $this->error('❌ Errors found:');
            foreach ($errors as $error) {
                $this->line('  • ' . $error);
            }
            $this->newLine();
        }

        if (!empty($warnings)) {
            $this->warn('⚠️  Warnings:');
            foreach ($warnings as $warning) {
                $this->line('  • ' . $warning);
            }
        }

        return empty($errors) ? Command::SUCCESS : Command::FAILURE;
    }
}
