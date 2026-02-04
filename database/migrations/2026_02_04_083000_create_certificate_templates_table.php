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
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nama template (e.g., "Template Pembangkit Standar")
            $table->string('category')->nullable(); // Kategori: pembangkit, transmisi, distribusi, dll
            $table->string('file_path'); // Path ke template PDF/image
            $table->string('preview_path')->nullable(); // Preview image
            $table->json('variables')->nullable(); // Available variables: ["nama", "kelas", "tanggal", etc]
            $table->json('settings')->nullable(); // Font, position, color settings
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false); // Default template
            $table->timestamps();
        });

        // Add template_id to courses table
        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedBigInteger('certificate_template_id')->nullable()->after('instructor_id');
            $table->foreign('certificate_template_id')->references('id')->on('certificate_templates')->onDelete('set null');
        });

        // Add template_id to certificates table
        Schema::table('certificates', function (Blueprint $table) {
            $table->unsignedBigInteger('template_id')->nullable()->after('course_id');
            $table->foreign('template_id')->references('id')->on('certificate_templates')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropColumn('template_id');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['certificate_template_id']);
            $table->dropColumn('certificate_template_id');
        });

        Schema::dropIfExists('certificate_templates');
    }
};
