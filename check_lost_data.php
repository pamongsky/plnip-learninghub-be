<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "📊 Checking Lost Data...\n\n";

$tables = [
    'users' => 'Users (employees, instructors, admins)',
    'roles' => 'Roles',
    'permissions' => 'Permissions',
    'model_has_roles' => 'User-Role Assignments',
    'announcements' => 'Announcements',
    'courses' => 'Courses',
    'course_enrollments' => 'Course Enrollments',
    'support_tickets' => 'Support Tickets',
    'support_replies' => 'Support Ticket Replies',
    'escalation_tickets' => 'Escalation Tickets',
    'escalation_replies' => 'Escalation Replies',
    'class_messages' => 'Class Messages',
    'conversations' => 'Conversations',
    'direct_messages' => 'Direct Messages',
    'chat_sessions' => 'AI Chat Sessions',
    'chat_messages' => 'AI Chat Messages',
    'chat_attachments' => 'Chat Attachments',
    'activity_logs' => 'Activity Logs',
    'audit_logs' => 'Audit Logs',
    'cms_pages' => 'CMS Pages',
    'cms_sections' => 'CMS Sections',
];

$lostData = [];

foreach ($tables as $table => $description) {
    try {
        $count = DB::table($table)->count();
        $status = $count === 0 ? '❌ EMPTY' : "✓ {$count} records";
        echo str_pad($description, 35) . " : {$status}\n";
        
        if ($count === 0) {
            $lostData[] = $description;
        }
    } catch (\Exception $e) {
        echo str_pad($description, 35) . " : ⚠️  Table not found\n";
    }
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n🔥 DATA YANG HILANG:\n";
foreach ($lostData as $item) {
    echo "  • {$item}\n";
}

echo "\n⚠️  YANG PERLU DILAKUKAN:\n";
echo "  1. Restore dari backup Oracle (jika ada)\n";
echo "  2. Atau input ulang data manual\n";
echo "  3. Hubungi tim IT untuk cek backup database\n\n";
