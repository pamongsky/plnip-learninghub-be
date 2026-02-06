<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Remove unique constraint from conversation_id because one conversation can have multiple messages
     */
    public function up(): void
    {
        // For Oracle: Drop the unique constraint directly
        // The constraint name was: AI_CONVERSATI_CONVERSAT_ID_UK
        try {
            DB::statement('ALTER TABLE ai_conversations DROP CONSTRAINT ai_conversati_conversat_id_uk');
        } catch (\Exception $e) {
            // Constraint might not exist or have different name, try alternative
            try {
                // Try dropping unique index instead (MySQL/PostgreSQL style)
                Schema::table('ai_conversations', function (Blueprint $table) {
                    $table->dropUnique(['conversation_id']);
                });
            } catch (\Exception $e2) {
                // Ignore if already dropped
            }
        }

        // Add regular index for performance (if not exists)
        try {
            Schema::table('ai_conversations', function (Blueprint $table) {
                $table->index('conversation_id', 'ai_conv_conversation_id_idx');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add unique constraint (not recommended)
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->unique('conversation_id');
        });
    }
};
