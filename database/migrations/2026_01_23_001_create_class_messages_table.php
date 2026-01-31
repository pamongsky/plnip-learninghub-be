<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Table for group chat messages within a class
     */
    public function up(): void
    {
        Schema::create('class_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('user_id');
            $table->text('message');
            $table->enum('message_type', ['discussion', 'question'])->default('discussion');
            $table->boolean('is_answered')->default(false);
            $table->unsignedBigInteger('answered_by')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['class_id', 'created_at']);
            $table->index(['class_id', 'message_type']);
            $table->index(['class_id', 'message_type', 'is_answered']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_messages');
    }
};
