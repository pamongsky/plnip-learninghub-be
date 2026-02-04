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
        Schema::create('moodle_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['full', 'users', 'courses', 'enrollments', 'categories'])->default('full');
            $table->enum('status', ['success', 'warning', 'error'])->default('success');
            $table->foreignId('triggered_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();

            // Sync results
            $table->integer('users_added')->default(0);
            $table->integer('users_updated')->default(0);
            $table->integer('users_errors')->default(0);
            $table->integer('courses_added')->default(0);
            $table->integer('courses_updated')->default(0);
            $table->integer('courses_errors')->default(0);
            $table->integer('enrollments_added')->default(0);
            $table->integer('enrollments_updated')->default(0);
            $table->integer('enrollments_errors')->default(0);

            // Logs & errors
            $table->text('error_message')->nullable();
            $table->json('logs')->nullable(); // Full log array

            $table->timestamps();

            // Indexes
            $table->index('type');
            $table->index('status');
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moodle_sync_logs');
    }
};
