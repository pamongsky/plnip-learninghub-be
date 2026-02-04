<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Course;

echo "=== SISTEM HYBRID PLN IP ===\n\n";

echo "1. DATABASE CONNECTIONS:\n";
echo "   - Portal: " . config('database.default') . " (Oracle)\n";
echo "   - Moodle: " . config('database.connections.moodle.driver') . " (Oracle)\n\n";

echo "2. PORTAL ORACLE - USERS:\n";
$portalUsers = User::select('id', 'name', 'email', 'moodle_user_id', 'is_active')->get();
foreach ($portalUsers as $u) {
    echo "   [{$u->id}] {$u->name} | {$u->email} | moodle_id:{$u->moodle_user_id} | active:{$u->is_active}\n";
}
echo "   Total: " . $portalUsers->count() . "\n\n";

echo "3. PORTAL ORACLE - COURSES:\n";
$portalCourses = Course::select('id', 'title', 'moodle_course_id')->get();
foreach ($portalCourses as $c) {
    echo "   [{$c->id}] {$c->title} | moodle_id:{$c->moodle_course_id}\n";
}
echo "   Total: " . $portalCourses->count() . "\n\n";

echo "4. MOODLE ORACLE - USERS (mdl_user):\n";
$moodleUsers = DB::connection('moodle')
    ->table('user')
    ->select('id', 'username', 'email', 'firstname', 'lastname', 'deleted', 'suspended')
    ->where('deleted', 0)
    ->where('suspended', 0)
    ->whereNotIn('id', [1, 2])
    ->get();
foreach ($moodleUsers as $u) {
    echo "   [{$u->id}] {$u->username} | {$u->email} | {$u->firstname} {$u->lastname}\n";
}
echo "   Total: " . $moodleUsers->count() . "\n\n";

echo "5. MOODLE ORACLE - COURSES (mdl_course):\n";
$moodleCourses = DB::connection('moodle')
    ->table('course')
    ->select('id', 'fullname', 'shortname', 'visible')
    ->where('id', '!=', 1)
    ->get();
foreach ($moodleCourses as $c) {
    echo "   [{$c->id}] {$c->fullname} | short:{$c->shortname} | visible:{$c->visible}\n";
}
echo "   Total: " . $moodleCourses->count() . "\n\n";

echo "6. SYNC STATUS:\n";
$syncedUsers = User::whereNotNull('moodle_user_id')->count();
$syncedCourses = Course::whereNotNull('moodle_course_id')->count();
echo "   - Users synced: {$syncedUsers}/{$portalUsers->count()}\n";
echo "   - Courses synced: {$syncedCourses}/{$portalCourses->count()}\n";
