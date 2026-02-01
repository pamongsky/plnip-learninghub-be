<?php

namespace App\Console\Commands;

use App\Services\ERPSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncERPUsers extends Command
{
    protected $signature = 'erp:sync {--force : Force sync even if disabled}';
    protected $description = 'Sync users from ERP system';

    public function handle()
    {
        $force = $this->option('force');
        $erpEnabled = config('erp.enabled', false);

        if (!$erpEnabled && !$force) {
            $this->warn('⚠️  ERP sync is disabled. Use --force to override.');
            return Command::FAILURE;
        }

        try {
            $this->info('🔄 Starting ERP user sync...');

            $syncService = new ERPSyncService();
            $stats = $syncService->syncUsers();

            if (isset($stats['error'])) {
                $this->error('❌ Sync failed: ' . $stats['error']);
                Log::error('ERP sync error', $stats);
                return Command::FAILURE;
            }

            // Display results
            $this->newLine();
            $this->info('✅ ERP Sync Completed');
            $this->table(
                ['Status', 'Count'],
                [
                    ['✨ Created', $stats['created'] ?? 0],
                    ['♻️  Updated', $stats['updated'] ?? 0],
                    ['⏭️  Skipped', $stats['skipped'] ?? 0],
                    ['⚠️  Errors', $stats['errors'] ?? 0],
                ]
            );

            Log::channel('audit')->info('ERP sync command completed', $stats);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Command failed: ' . $e->getMessage());
            Log::error('ERP sync command error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }
}
