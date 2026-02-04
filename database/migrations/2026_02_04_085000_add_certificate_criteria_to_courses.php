<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // Completion criteria: 'final_grade', 'specific_quiz', 'completion_and_grade'
            $table->enum('certificate_criteria', ['final_grade', 'specific_quiz', 'completion_and_grade'])
                ->default('final_grade')
                ->after('passing_grade');

            // Jika criteria = 'specific_quiz', simpan quiz/exam ID dari Moodle
            $table->unsignedBigInteger('certificate_quiz_id')->nullable()->after('certificate_criteria');

            // Auto-issue certificate atau manual approval
            $table->boolean('auto_issue_certificate')->default(true)->after('certificate_quiz_id');

            // Delay berapa hari setelah lulus baru terbit sertifikat (0 = langsung)
            $table->integer('certificate_issue_delay_days')->default(0)->after('auto_issue_certificate');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'certificate_criteria',
                'certificate_quiz_id',
                'auto_issue_certificate',
                'certificate_issue_delay_days'
            ]);
        });
    }
};
