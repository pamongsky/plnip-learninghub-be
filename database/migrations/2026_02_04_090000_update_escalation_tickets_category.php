<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Manually update category constraint for Oracle
        // First, we need to find and drop the old category constraint

        // For Oracle, we'll use a more direct approach
        // Get all constraints and find the category one
        $result = DB::select("
            SELECT constraint_name
            FROM user_cons_columns
            WHERE table_name = 'ESCALATION_TICKETS'
            AND column_name = 'CATEGORY'
            AND constraint_name LIKE '%CHK%'
        ");

        if (!empty($result)) {
            foreach ($result as $row) {
                try {
                    DB::statement("ALTER TABLE escalation_tickets DROP CONSTRAINT " . $row->constraint_name);
                    echo "Dropped constraint: " . $row->constraint_name . "\n";
                } catch (\Exception $e) {
                    // Continue if fail
                }
            }
        }

        // Add new constraint
        DB::statement("
            ALTER TABLE escalation_tickets
            ADD CONSTRAINT esc_tickets_category_ck
            CHECK (category IN ('technical', 'learning', 'certificate', 'payment', 'access', 'moodle', 'feature_request', 'other'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop new constraint
        try {
            DB::statement("ALTER TABLE escalation_tickets DROP CONSTRAINT esc_tickets_category_ck");
        } catch (\Exception $e) {
            // Ignore
        }

        // Restore old constraint
        DB::statement("
            ALTER TABLE escalation_tickets
            ADD CONSTRAINT esc_tickets_category_old_ck
            CHECK (category IN ('technical', 'access', 'moodle', 'feature_request', 'other'))
        ");
    }
};
