<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Conversations table for direct messaging between roles:
     * - Admin <-> User (Peserta)
     * - Instructor <-> Admin
     * - Super Admin <-> Admin
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            
            // Participants - always 2 users
            $table->unsignedBigInteger('user_one_id'); // Initiator
            $table->unsignedBigInteger('user_two_id'); // Recipient
            
            // Conversation type for filtering
            $table->enum('type', [
                'admin_user',      // Admin <-> Peserta
                'instructor_admin', // Instructor <-> Admin
                'superadmin_admin'  // Super Admin <-> Admin
            ]);
            
            // Last message preview for inbox list
            $table->text('last_message')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedBigInteger('last_message_by')->nullable();
            
            // Unread counters for each participant
            $table->integer('user_one_unread')->default(0);
            $table->integer('user_two_unread')->default(0);
            
            // Status
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('user_one_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('user_two_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('last_message_by')->references('id')->on('users')->onDelete('set null');
            
            // Ensure unique conversation between two users
            $table->unique(['user_one_id', 'user_two_id']);
            
            // Indexes for performance
            $table->index(['user_one_id', 'last_message_at']);
            $table->index(['user_two_id', 'last_message_at']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
