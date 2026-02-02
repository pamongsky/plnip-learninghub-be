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
            $table->enum('scope', ['global', 'unit'])->default('unit')->after('priority');
            $table->string('broadcast_to')->nullable()->after('scope');
            $table->timestamp('expires_at')->nullable()->after('published_at');
            $table->unsignedBigInteger('views_count')->default(0)->after('is_active');

            $table->index('scope');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex(['scope']);
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['scope', 'broadcast_to', 'expires_at', 'views_count']);
        });
    }
};
