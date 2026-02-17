<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Safely create an index - skip if already exists (Oracle safe)
     */
    private function safeIndex(string $table, array|string $columns, ?string $name = null): void
    {
        try {
            Schema::table($table, function (Blueprint $t) use ($columns, $name) {
                if ($name) {
                    $t->index($columns, $name);
                } else {
                    $t->index($columns);
                }
            });
        } catch (\Exception $e) {
            // ORA-01408: column list already indexed — skip
            // ORA-00955: name already used by existing object — skip
            // ORA-00942: table/view does not exist — skip
            if (str_contains($e->getMessage(), '1408') ||
                str_contains($e->getMessage(), '955') ||
                str_contains($e->getMessage(), '942') ||
                str_contains($e->getMessage(), 'already indexed') ||
                str_contains($e->getMessage(), 'already used') ||
                str_contains($e->getMessage(), 'does not exist')) {
                return;
            }
            throw $e;
        }
    }

    public function up(): void
    {
        // Users table indexes
        $this->safeIndex('users', 'email');
        $this->safeIndex('users', 'employee_id');
        $this->safeIndex('users', 'moodle_user_id');
        $this->safeIndex('users', ['is_active', 'created_at']);

        // Course enrollments indexes
        $this->safeIndex('course_enrollments', ['user_id', 'status']);
        $this->safeIndex('course_enrollments', ['course_id', 'status']);
        $this->safeIndex('course_enrollments', 'enrolled_at');

        // Courses indexes
        $this->safeIndex('courses', 'is_active');
        $this->safeIndex('courses', 'moodle_course_id');
        $this->safeIndex('courses', 'instructor_id');
        $this->safeIndex('courses', ['is_active', 'start_date']);

        // Announcements indexes
        $this->safeIndex('announcements', ['is_active', 'published_at']);
        $this->safeIndex('announcements', 'priority');
        $this->safeIndex('announcements', 'created_by');
        $this->safeIndex('announcements', ['scope', 'target_role']);

        // Support tickets indexes
        $this->safeIndex('support_tickets', ['user_id', 'status']);
        $this->safeIndex('support_tickets', ['status', 'priority']);
        $this->safeIndex('support_tickets', 'created_at');

        // Certificates indexes
        $this->safeIndex('certificates', ['user_id', 'is_valid']);
        $this->safeIndex('certificates', ['course_id', 'is_valid']);
        $this->safeIndex('certificates', 'certificate_number');

        // AI chat messages indexes
        $this->safeIndex('ai_chat_messages', ['user_id', 'conversation_id']);
        $this->safeIndex('ai_chat_messages', 'created_at');

        // Class chat messages indexes
        $this->safeIndex('class_chat_messages', ['class_id', 'created_at']);
        $this->safeIndex('class_chat_messages', 'user_id');
    }

    public function down(): void
    {
        // Users
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['employee_id']);
            $table->dropIndex(['moodle_user_id']);
            $table->dropIndex(['is_active', 'created_at']);
        });

        // Course enrollments
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['course_id', 'status']);
            $table->dropIndex(['enrolled_at']);
        });

        // Courses
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['moodle_course_id']);
            $table->dropIndex(['instructor_id']);
            $table->dropIndex(['is_active', 'start_date']);
        });

        // Announcements
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'published_at']);
            $table->dropIndex(['priority']);
            $table->dropIndex(['created_by']);
            $table->dropIndex(['scope', 'target_role']);
        });

        // Support tickets
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['status', 'priority']);
            $table->dropIndex(['created_at']);
        });

        // Certificates
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_valid']);
            $table->dropIndex(['course_id', 'is_valid']);
            $table->dropIndex(['certificate_number']);
        });

        // AI chat messages
        Schema::table('ai_chat_messages', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'conversation_id']);
            $table->dropIndex(['created_at']);
        });

        // Class chat messages
        Schema::table('class_chat_messages', function (Blueprint $table) {
            $table->dropIndex(['class_id', 'created_at']);
            $table->dropIndex(['user_id']);
        });
    }
};
