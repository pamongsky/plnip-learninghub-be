<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanOrphanEnrollments extends Command
{
    protected $signature   = 'enrollments:clean-orphans {--dry-run : Preview without deleting}';
    protected $description = 'Remove course_enrollments that reference deleted users.';

    public function handle(): int
    {
        $orphans = DB::table('course_enrollments as ce')
            ->leftJoin('users as u', 'ce.user_id', '=', 'u.id')
            ->whereNull('u.id')
            ->select('ce.id', 'ce.user_id', 'ce.course_id', 'ce.status')
            ->get();

        if ($orphans->isEmpty()) {
            $this->info('✅ No orphaned enrollments found. Database is clean.');
            return 0;
        }

        $this->table(
            ['Enrollment ID', 'User ID (Missing)', 'Course ID', 'Status'],
            $orphans->map(fn ($o) => [$o->id, $o->user_id, $o->course_id, $o->status])->toArray()
        );

        $count = $orphans->count();

        if ($this->option('dry-run')) {
            $this->warn("🔍 DRY RUN — {$count} orphan(s) found. No changes made.");
            return 0;
        }

        if (!$this->confirm("Delete {$count} orphaned enrollment(s)? This cannot be undone.")) {
            $this->info('Aborted.');
            return 0;
        }

        $ids     = $orphans->pluck('id')->toArray();
        $deleted = DB::table('course_enrollments')->whereIn('id', $ids)->delete();

        $this->info("✅ Deleted {$deleted} orphaned enrollment(s).");

        return 0;
    }
}
