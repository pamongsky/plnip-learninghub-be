<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_id')->nullable()->after('email');
            $table->unsignedBigInteger('moodle_user_id')->nullable()->after('employee_id');
            $table->string('phone')->nullable()->after('moodle_user_id');
            $table->string('department')->nullable()->after('phone');
            $table->string('position')->nullable()->after('department');
            $table->string('avatar')->nullable()->after('position');
            $table->boolean('is_active')->default(true)->after('avatar');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'employee_id',
                'moodle_user_id',
                'phone',
                'department',
                'position',
                'avatar',
                'is_active'
            ]);
        });
    }
};