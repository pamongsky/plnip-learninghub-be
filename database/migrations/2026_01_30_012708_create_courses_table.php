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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('moodle_course_id')->nullable()->unique()->comment('ID from Moodle');
            $table->string('title');
            $table->string('short_name')->unique();
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('instructor_id')->nullable()->comment('Main instructor from Users table');
            $table->integer('category_id')->default(1)->comment('Moodle Category ID');
            $table->string('image')->nullable();
            $table->timestamps();

            // Index FK manually if not using constrained() to avoid issues if users table ID type differs, 
            // but generally constrained() is better. Let's use simple unsignedBigInteger for flexibility 
            // with potential non-constrained users or soft deletes.
            $table->index('instructor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
