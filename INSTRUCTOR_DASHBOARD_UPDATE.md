# Instructor Dashboard API - Update Log

## Tanggal: 5 Februari 2026

### Perubahan yang Dilakukan:

#### 1. **Fix Query Kelas Instructor**

**Masalah:** Kelas tidak muncul meski admin sudah assign enrollment
**Solusi:**

- Mengubah query dari enrollment-based ke role-based
- Sekarang mengambil kelas berdasarkan role assignment (editingteacher/teacher)
- Filter hanya kelas yang visible

**Query Lama:**

```php
// Mengambil dari user_enrolments (enrollment student)
->join('enrol as e', 'c.id', '=', 'e.courseid')
->join('user_enrolments as ue', 'e.id', '=', 'ue.enrolid')
->where('ue.userid', $moodleUser->id)
```

**Query Baru:**

```php
// Mengambil dari role_assignments (teacher role)
->join('context as ctx', ...) // CONTEXT_COURSE = 50
->join('role_assignments as ra', 'ra.contextid', '=', 'ctx.id')
->join('role as r', 'ra.roleid', '=', 'r.id')
->where('ra.userid', $moodleUser->id)
->whereIn('r.shortname', ['editingteacher', 'teacher'])
```

#### 2. **Rata-rata Kehadiran Real-time**

**Masalah:** average_attendance hardcoded (87%)
**Solusi:** Menghitung dari data Moodle real-time

**Metode Perhitungan:**

1. **Primary:** Course Completion Stats
    - Mengambil dari `course_completions` table
    - Persentase = (Courses Completed / Total Courses) \* 100

2. **Fallback:** Activity Logs
    - Jika tidak ada completion data
    - Mengambil dari `logstore_standard_log`
    - Menghitung course yang pernah diakses
    - Persentase = (Courses Accessed / Total Courses) \* 100

**Code:**

```php
$completionStats = DB::connection('moodle')
    ->table('course_completions as cc')
    ->join('enrol as e', 'cc.course', '=', 'e.courseid')
    ->join('user_enrolments as ue', ...)
    ->whereNotNull('cc.timecompleted')
    ->count();

if ($averageAttendance === 0) {
    // Fallback to activity logs
    $activityCount = DB::connection('moodle')
        ->table('logstore_standard_log')
        ->where('userid', $moodleUser->id)
        ->where('action', 'viewed')
        ->where('target', 'course')
        ->distinct('courseid')
        ->count('courseid');
}
```

### Testing Required:

1. ✅ Login sebagai instructor
2. ✅ Cek apakah kelas muncul di dashboard
3. ✅ Verify average_attendance calculation
4. ✅ Test dengan instructor yang:
    - Punya multiple courses
    - Punya 0 courses
    - Ada completion data
    - Tidak ada completion data

### Endpoint API:

- **URL:** `GET /api/dashboard/instructor`
- **Auth:** Required (Sanctum)
- **Response Structure:**

```json
{
  "success": true,
  "data": {
    "stats": {
      "active_classes": 0,
      "total_participants": 0,
      "completed_classes": 0,
      "average_attendance": 87  // Now real-time!
    },
    "announcements": [...],
    "classes": [...]
  }
}
```

### Notes:

- Pastikan Moodle connection di `.env` sudah benar
- Context level 50 = CONTEXT_COURSE (standar Moodle)
- Role shortname: `editingteacher` atau `teacher`
- Visible = 1 artinya course aktif/published

### Cara Assign Instructor di Moodle:

1. Login Moodle sebagai admin
2. Masuk ke course
3. Participants → Enrol users
4. Pilih user → Assign role "Teacher" atau "Editing Teacher"
5. Atau via Site Administration → Users → Assign system roles
