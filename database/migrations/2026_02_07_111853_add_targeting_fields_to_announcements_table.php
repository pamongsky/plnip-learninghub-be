<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            // JSON field for instructor to select multiple classes
            $table->json('target_classes')->nullable()->after('broadcast_to');
            
            // Enum field for admin to target specific roles
            $table->enum('target_role', ['all', 'user', 'instructor'])->default('all')->after('target_classes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['target_classes', 'target_role']);
        });
    }
};
