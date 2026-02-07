<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop old constraint (Oracle uses BIN$ prefix for dropped constraints)
        $constraints = DB::select("
            SELECT constraint_name
            FROM user_constraints
            WHERE table_name = 'SUPPORT_TICKETS'
            AND constraint_type = 'C'
            AND constraint_name LIKE 'BIN%'
        ");

        foreach ($constraints as $constraint) {
            try {
                DB::statement("ALTER TABLE SUPPORT_TICKETS DROP CONSTRAINT \"{$constraint->constraint_name}\"");
            } catch (\Exception $e) {
                // Constraint might not exist, ignore
            }
        }

        // Add new constraint with all categories (employee + instructor)
        DB::statement("
            ALTER TABLE SUPPORT_TICKETS
            ADD CONSTRAINT support_tickets_category_check
            CHECK (CATEGORY IN (
                'technical', 'learning', 'certificate', 'payment', 'other',
                'schedule', 'content', 'student', 'certification', 'coordination'
            ))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop new constraint
        DB::statement("ALTER TABLE SUPPORT_TICKETS DROP CONSTRAINT support_tickets_category_check");

        // Restore old constraint (employee categories only)
        DB::statement("
            ALTER TABLE SUPPORT_TICKETS
            ADD CONSTRAINT support_tickets_category_check_old
            CHECK (CATEGORY IN ('technical', 'learning', 'certificate', 'payment', 'other'))
        ");
    }
};
