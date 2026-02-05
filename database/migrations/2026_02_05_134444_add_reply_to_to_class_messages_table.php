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
        Schema::table('class_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('reply_to')->nullable()->after('message_type');
            $table->unsignedBigInteger('mentioned_user_id')->nullable()->after('reply_to');

            $table->foreign('reply_to')->references('id')->on('class_messages')->onDelete('set null');
            $table->foreign('mentioned_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_messages', function (Blueprint $table) {
            $table->dropForeign(['reply_to']);
            $table->dropForeign(['mentioned_user_id']);
            $table->dropColumn(['reply_to', 'mentioned_user_id']);
        });
    }
};
