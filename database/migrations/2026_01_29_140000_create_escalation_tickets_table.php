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
        Schema::create('escalation_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('superadmin_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('support_ticket_id')->nullable()->constrained('support_tickets')->onDelete('set null');
            $table->enum('type', ['escalation', 'standalone'])->default('standalone');
            $table->string('subject');
            $table->text('description');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->enum('category', ['technical', 'learning', 'certificate', 'payment', 'access', 'moodle', 'feature_request', 'other'])->default('other');
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['admin_id', 'status']);
            $table->index(['superadmin_id', 'status']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escalation_tickets');
    }
};
