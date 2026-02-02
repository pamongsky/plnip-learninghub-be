<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Enterprise-grade FAQ system for AI Assistant
     */
    public function up(): void
    {
        // Main FAQ table
        Schema::create('ai_faqs', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50)->index(); // login, course, technical, general
            $table->string('question', 500);
            $table->text('question_variations')->nullable(); // JSON array
            $table->text('answer'); // Full answer
            $table->string('answer_short', 1000)->nullable(); // Short version
            $table->integer('confidence_score')->default(50); // 0-100
            $table->integer('usage_count')->default(0);
            $table->integer('success_count')->default(0); // Positive feedback
            $table->integer('failure_count')->default(0); // Negative feedback
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false); // Admin approved
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['category', 'is_active']);
            $table->index(['confidence_score', 'is_active']);
            $table->index('usage_count');
        });

        // FAQ Analytics table
        Schema::create('ai_faq_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faq_id')->constrained('ai_faqs')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('user_query', 500);
            $table->decimal('match_score', 5, 4)->nullable(); // 0.0000 - 1.0000
            $table->boolean('was_helpful')->nullable(); // User feedback
            $table->string('response_source', 20); // 'faq', 'gemini', 'cache'
            $table->integer('response_time_ms'); // milliseconds
            $table->timestamps();
            
            $table->index(['faq_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        // FAQ Suggestions (Auto-learning from Gemini responses)
        Schema::create('ai_faq_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('question', 500);
            $table->text('answer');
            $table->integer('occurrence_count')->default(1); // How many times asked
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'occurrence_count']);
        });

        // Insert default FAQs
        DB::table('ai_faqs')->insert([
            [
                'category' => 'login',
                'question' => 'Bagaimana cara login ke sistem?',
                'question_variations' => json_encode([
                    'cara login',
                    'how to login',
                    'gimana masuk',
                    'cara masuk sistem',
                    'login dimana'
                ]),
                'answer' => 'Untuk login ke sistem PLN Learning Hub, buka halaman login di browser Anda, masukkan email dan password yang sudah terdaftar, lalu klik tombol "Login". Jika lupa password, klik "Lupa Password" untuk reset.',
                'answer_short' => 'Buka halaman login, masukkan email & password, klik Login. Lupa password? Klik "Lupa Password".',
                'confidence_score' => 100,
                'is_active' => 1,
                'is_verified' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category' => 'technical',
                'question' => 'Apa yang harus dilakukan jika sistem error?',
                'question_variations' => json_encode([
                    'sistem error',
                    'page not working',
                    'website crash',
                    'tidak bisa diakses',
                    'error 500'
                ]),
                'answer' => 'Jika mengalami error: 1) Refresh halaman (F5), 2) Clear cache browser (Ctrl+Shift+Del), 3) Coba browser lain, 4) Screenshot error dan hubungi admin jika masih berlanjut.',
                'answer_short' => 'Coba: Refresh → Clear cache → Ganti browser. Masih error? Screenshot & hubungi admin.',
                'confidence_score' => 95,
                'is_active' => 1,
                'is_verified' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category' => 'course',
                'question' => 'Bagaimana cara mengakses materi kursus?',
                'question_variations' => json_encode([
                    'akses materi',
                    'buka kursus',
                    'lihat pembelajaran',
                    'dimana materinya',
                    'cara belajar'
                ]),
                'answer' => 'Setelah login, klik menu "Dashboard" atau "My Courses", pilih kursus yang ingin dipelajari, lalu klik untuk membuka materi. Anda bisa mengakses video, dokumen, dan quiz di dalam kursus tersebut.',
                'answer_short' => 'Dashboard → My Courses → Pilih kursus → Akses materi (video, dokumen, quiz).',
                'confidence_score' => 100,
                'is_active' => 1,
                'is_verified' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category' => 'general',
                'question' => 'Apa itu PLN Indonesia Power?',
                'question_variations' => json_encode([
                    'apa itu pln',
                    'indonesia power',
                    'tentang pln',
                    'perusahaan apa ini'
                ]),
                'answer' => 'PLN Indonesia Power adalah anak perusahaan PLN yang fokus pada pembangkitan listrik. Kami mengelola berbagai pembangkit listrik di Indonesia untuk mendukung kelistrikan nasional.',
                'answer_short' => 'PLN Indonesia Power adalah anak perusahaan PLN yang mengelola pembangkit listrik di Indonesia.',
                'confidence_score' => 100,
                'is_active' => 1,
                'is_verified' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_faq_suggestions');
        Schema::dropIfExists('ai_faq_analytics');
        Schema::dropIfExists('ai_faqs');
    }
};
