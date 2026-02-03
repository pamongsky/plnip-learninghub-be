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
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->string('certificate_number')->unique();
            $table->string('course_name');
            $table->string('student_name');
            $table->date('completion_date');
            $table->date('issue_date');
            $table->decimal('final_score', 5, 2)->nullable();
            $table->string('grade', 5)->nullable(); // A, B, C, etc
            $table->integer('total_hours')->nullable();
            $table->string('instructor_name')->nullable();
            $table->string('certificate_url')->nullable(); // PDF download URL
            $table->string('verification_code', 50)->unique();
            $table->boolean('is_valid')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');

            // Indexes
            $table->index(['user_id', 'course_id']);
            $table->index('certificate_number');
            $table->index('verification_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
