<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Source: manual (dibuat super admin dev) atau erp (dari ERP system)
            $table->enum('source', ['manual', 'erp'])->default('manual')->after('is_active');
            
            // Access group dari ERP (e.g., SUPERADMIN, ADMIN_UNIT, INSTRUCTOR, USER)
            $table->string('access_group')->nullable()->after('source');
            
            // Role override: jika ada, gunakan ini; jika null, gunakan mapping dari access_group
            $table->string('role_override')->nullable()->after('access_group');
            
            // Timestamps untuk tracking
            $table->timestamp('synced_at')->nullable()->after('role_override')->comment('Last sync time dari ERP');
            $table->timestamp('role_changed_at')->nullable()->after('synced_at')->comment('Kapan role terakhir diubah');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'source',
                'access_group',
                'role_override',
                'synced_at',
                'role_changed_at'
            ]);
        });
    }
};
