<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Oracle: drop old check constraint and add new one that includes 'learner'
        DB::statement('ALTER TABLE announcements DROP CONSTRAINT CHK_ANNOUNCEMENTS_TARGET_ROLE');
        DB::statement("ALTER TABLE announcements ADD CONSTRAINT CHK_ANNOUNCEMENTS_TARGET_ROLE CHECK (TARGET_ROLE IN ('all', 'user', 'learner', 'instructor'))");

        // Migrate existing 'user' values to 'learner'
        DB::statement("UPDATE announcements SET target_role = 'learner' WHERE target_role = 'user'");
    }

    public function down(): void
    {
        // Restore original constraint (requires no 'learner' rows)
        DB::statement('ALTER TABLE announcements DROP CONSTRAINT CHK_ANNOUNCEMENTS_TARGET_ROLE');
        DB::statement("ALTER TABLE announcements ADD CONSTRAINT CHK_ANNOUNCEMENTS_TARGET_ROLE CHECK (TARGET_ROLE IN ('all', 'user', 'instructor'))");
    }
};
