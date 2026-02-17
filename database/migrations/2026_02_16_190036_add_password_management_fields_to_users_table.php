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
        Schema::table('users', function (Blueprint $table) {
            // Track if user must change password on next login
            $table->boolean('must_change_password')->default(true)->after('password');

            // Track when password was last changed
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');

            // Track account source (erp sync or manual creation)
            $table->enum('account_source', ['erp', 'manual'])->default('manual')->after('password_changed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['must_change_password', 'password_changed_at', 'account_source']);
        });
    }
};
