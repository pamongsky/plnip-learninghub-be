# 🔄 Moodle Full Sync - Real-Time Integration

## 📋 Overview

Sistem **Moodle Full Sync** menyediakan sinkronisasi real-time antara **Moodle LMS (Oracle)** dan **Portal PLN IP (Oracle)** menggunakan Direct Database Access Strategy. Sistem ini memungkinkan Super Admin untuk:

✅ Sync Users dari Moodle ke Portal  
✅ Sync Courses dari Moodle ke Portal  
✅ Sync Enrollments (pendaftaran kelas)  
✅ Sync Categories  
✅ **Full Sync** - Sync semua data sekaligus  
✅ Monitor status koneksi Moodle real-time  
✅ View sync history dengan detail lengkap  
✅ Auto-refresh statistics setiap 30 detik

---

## 🏗️ Architecture

### **Strategy: Direct Database Access (Oracle to Oracle)**

```
┌─────────────────────────────────────────────────────────────┐
│                    MOODLE LMS (Oracle)                      │
│  - mdl_user (Users)                                         │
│  - mdl_course (Courses)                                     │
│  - mdl_user_enrolments (Enrollments)                        │
│  - mdl_course_categories (Categories)                       │
└──────────────────┬──────────────────────────────────────────┘
                   │
                   │ Direct DB Read (Oracle Connection)
                   │
                   ▼
┌─────────────────────────────────────────────────────────────┐
│            MoodleSyncService (Laravel)                      │
│  ┌───────────────────────────────────────────────────┐     │
│  │ syncUsers()         - Sync all active users       │     │
│  │ syncCourses()       - Sync all courses            │     │
│  │ syncEnrollments()   - Sync user enrollments       │     │
│  │ syncCategories()    - Sync course categories      │     │
│  │ fullSync()          - Sync everything             │     │
│  │ getConnectionStatus() - Test Moodle connection    │     │
│  │ getSyncStats()      - Get sync statistics         │     │
│  └───────────────────────────────────────────────────┘     │
└──────────────────┬──────────────────────────────────────────┘
                   │
                   │ Write to Portal Database
                   │
                   ▼
┌─────────────────────────────────────────────────────────────┐
│              PORTAL PLN IP (Oracle)                         │
│  - users (Portal users + moodle_user_id)                   │
│  - courses (Courses + moodle_course_id)                    │
│  - course_enrollments (Enrollments)                        │
│  - moodle_sync_logs (Sync history & logs)                  │
└─────────────────────────────────────────────────────────────┘
```

---

## 📦 Backend Components

### **1. MoodleSyncService.php**

`app/Services/MoodleSyncService.php`

**Main Methods:**

- **`fullSync()`** - Sync semua data dari Moodle
- **`syncUsers()`** - Sync users only
    - Exclude deleted, suspended, admin, guest
    - Create new users with default role "user"
    - Update existing users by email
    - Store `moodle_user_id` for mapping
- **`syncCourses()`** - Sync courses only
    - Map Moodle course fields ke Portal
    - Store `moodle_course_id` for mapping
    - Update course visibility, dates, category
- **`syncEnrollments()`** - Sync enrollments
    - Join: `user_enrolments` → `enrol` → `course` → `user`
    - Match by email and moodle_course_id
    - Create/Update enrollment records
- **`syncCategories()`** - Sync course categories (placeholder)
    - TODO: Implement category table di Portal
- **`getConnectionStatus()`** - Test connection & get Moodle stats
- **`getSyncStats()`** - Get Portal statistics

**Logging System:**

- Internal log array stored during sync
- All logs saved to `moodle_sync_logs` table
- Level: `info`, `debug`, `warning`, `error`

---

### **2. MoodleSyncController.php**

`app/Http/Controllers/API/MoodleSyncController.php`

**API Endpoints:**

| Method | Endpoint                       | Description                   | Role Required      |
| ------ | ------------------------------ | ----------------------------- | ------------------ |
| GET    | `/api/moodle/sync/status`      | Get connection status & stats | Super Admin, Admin |
| GET    | `/api/moodle/sync/history`     | Get sync history (last 20)    | Super Admin, Admin |
| POST   | `/api/moodle/sync/full`        | Run full sync                 | **Super Admin**    |
| POST   | `/api/moodle/sync/users`       | Sync users only               | Super Admin, Admin |
| POST   | `/api/moodle/sync/courses`     | Sync courses only             | Super Admin, Admin |
| POST   | `/api/moodle/sync/enrollments` | Sync enrollments only         | Super Admin, Admin |
| POST   | `/api/moodle/sync/categories`  | Sync categories               | Super Admin, Admin |

**Response Examples:**

**Status Response:**

```json
{
    "connection": {
        "status": "connected",
        "moodle_version": "4.3.2",
        "total_users": 5120,
        "total_courses": 245,
        "database": "ORCL",
        "host": "192.168.1.100"
    },
    "stats": {
        "portal_users": 4850,
        "portal_courses": 240,
        "portal_enrollments": 12840,
        "synced_users": 4850,
        "synced_courses": 240
    },
    "last_sync": {
        "type": "full",
        "started_at": "2026-02-04 10:30:00",
        "completed_at": "2026-02-04 10:30:15",
        "duration": 15,
        "status": "success"
    }
}
```

**Full Sync Response:**

```json
{
    "message": "Full sync berhasil!",
    "results": {
        "started_at": "2026-02-04 11:00:00",
        "completed_at": "2026-02-04 11:00:22",
        "duration": 22,
        "users": {
            "total_moodle": 5120,
            "added": 15,
            "updated": 145,
            "errors": 0,
            "duration_seconds": 8.5
        },
        "courses": {
            "total_moodle": 245,
            "added": 2,
            "updated": 12,
            "errors": 0,
            "duration_seconds": 5.2
        },
        "enrollments": {
            "total_moodle": 12980,
            "added": 85,
            "updated": 340,
            "errors": 0,
            "duration_seconds": 8.3
        },
        "logs": [
            {
                "timestamp": "2026-02-04 11:00:00",
                "level": "info",
                "message": "Starting Full Sync from Moodle"
            }
        ]
    }
}
```

---

### **3. MoodleSyncLog Model**

`app/Models/MoodleSyncLog.php`

**Database Table: `moodle_sync_logs`**

| Column                | Type      | Description                    |
| --------------------- | --------- | ------------------------------ |
| `id`                  | BIGINT    | Primary key                    |
| `type`                | ENUM      | full/users/courses/enrollments |
| `status`              | ENUM      | success/warning/error          |
| `triggered_by`        | BIGINT    | User ID who triggered sync     |
| `started_at`          | TIMESTAMP | Sync start time                |
| `completed_at`        | TIMESTAMP | Sync completion time           |
| `duration_seconds`    | INT       | Total sync duration            |
| `users_added`         | INT       | Users created                  |
| `users_updated`       | INT       | Users updated                  |
| `users_errors`        | INT       | User sync errors               |
| `courses_added`       | INT       | Courses created                |
| `courses_updated`     | INT       | Courses updated                |
| `courses_errors`      | INT       | Course sync errors             |
| `enrollments_added`   | INT       | Enrollments created            |
| `enrollments_updated` | INT       | Enrollments updated            |
| `enrollments_errors`  | INT       | Enrollment sync errors         |
| `error_message`       | TEXT      | Error message if failed        |
| `logs`                | JSON      | Full log array from sync       |

---

## 🎨 Frontend Components

### **SuperAdmin Moodle Page**

`app/superadmin/moodle/page.tsx`

**Features:**

1. **Connection Status Card**
    - Real-time status (Connected/Disconnected)
    - Moodle version info
    - Last sync time & duration
    - Error display if connection fails

2. **Statistics Cards (4 Cards)**
    - Portal Users (with synced count)
    - Portal Courses (with synced count)
    - Portal Enrollments
    - Moodle Users (from Moodle DB)

3. **Quick Sync Actions**
    - Sync Users button
    - Sync Courses button
    - Sync Enrollments button
    - All disabled during sync operation

4. **Full Sync Button**
    - Top-right corner
    - Animated spinner during sync
    - Triggers full data sync

5. **Sync History Table**
    - Last 20 sync operations
    - Columns: Type, Start Time, End Time, Status, Users (+/↻), Courses (+/↻)
    - Color-coded status badges
    - Empty state message

**Real-Time Features:**

- ✅ Auto-refresh every 30 seconds (status + history)
- ✅ Pause auto-refresh during active sync
- ✅ Toast notifications for all operations
- ✅ Loading states with skeleton UI
- ✅ Error handling dengan fallback UI

---

### **API Client**

`lib/api/moodleSync.ts`

**TypeScript Interfaces:**

```typescript
interface SyncStatus {
    connection: {
        status: "connected" | "disconnected";
        moodle_version?: string;
        total_users?: number;
        total_courses?: number;
        error?: string;
    };
    stats: {
        portal_users: number;
        portal_courses: number;
        portal_enrollments: number;
        synced_users: number;
        synced_courses: number;
    };
    last_sync: {
        type: string;
        started_at: string;
        completed_at: string;
        duration: number;
        status: string;
    } | null;
}

interface SyncResult {
    total_moodle?: number;
    added: number;
    updated: number;
    errors?: number;
    duration_seconds: number;
}
```

**Functions:**

- `getMoodleSyncStatus()` - Get status & stats
- `runFullSync()` - Trigger full sync
- `syncUsers()` - Sync users only
- `syncCourses()` - Sync courses only
- `syncEnrollments()` - Sync enrollments only
- `getSyncHistory()` - Get sync history

---

## 🔧 Configuration

### **Database Connection (Moodle)**

File: `config/database.php`

```php
'moodle' => [
    'driver' => 'oracle',
    'tns' => env('MOODLE_TNS', ''),
    'host' => env('MOODLE_DB_HOST', ''),
    'port' => env('MOODLE_DB_PORT', '1521'),
    'database' => env('MOODLE_DB_DATABASE', ''),
    'username' => env('MOODLE_DB_USERNAME', ''),
    'password' => env('MOODLE_DB_PASSWORD', ''),
    'charset' => 'AL32UTF8',
    'prefix' => '',
    'prefix_schema' => '',
],
```

### **Environment Variables**

File: `.env`

```env
# Moodle Database Connection (Oracle)
MOODLE_DB_HOST=192.168.1.100
MOODLE_DB_PORT=1521
MOODLE_DB_DATABASE=ORCL
MOODLE_DB_USERNAME=moodle_user
MOODLE_DB_PASSWORD=secure_password
MOODLE_TNS="(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=192.168.1.100)(PORT=1521))(CONNECT_DATA=(SID=ORCL)))"
```

---

## 🚀 Usage Guide

### **1. Access Moodle Sync Page**

```
URL: https://portal.plnip.co.id/superadmin/moodle
Role: Super Admin only
```

### **2. Check Connection Status**

- Green badge = Connected ✅
- Red badge = Disconnected ❌
- View Moodle version, last sync time, duration

### **3. Run Sync Operations**

**Option A: Full Sync (Recommended)**

```
Click "Sync Now" button (top-right)
→ Syncs Users, Courses, Enrollments sekaligus
→ Duration: ~15-30 seconds
→ Toast notification akan muncul
```

**Option B: Partial Sync**

```
Click salah satu quick action button:
- "Sync Users" → Users only
- "Sync Courses" → Courses only
- "Sync Enrollments" → Enrollments only
```

### **4. Monitor Progress**

- Button shows "Syncing..." dengan spinner
- Toast notification menampilkan progress
- Setelah selesai, stats cards auto-refresh
- History table auto-update

### **5. View Sync History**

- Scroll ke bawah ke tabel "Sync History"
- Lihat 20 sync terakhir
- Status badge: Success (green), Warning (yellow), Error (red)
- Kolom Users: `+15 / ↻145` = 15 added, 145 updated
- Kolom Courses: `+2 / ↻12` = 2 added, 12 updated

---

## 📊 Data Mapping

### **Users Mapping**

| Moodle Field | Portal Field     | Notes                     |
| ------------ | ---------------- | ------------------------- |
| `id`         | `moodle_user_id` | Foreign key reference     |
| `firstname`  | `name` (part)    | Combined with lastname    |
| `lastname`   | `name` (part)    | Combined with firstname   |
| `email`      | `email`          | Unique identifier         |
| `deleted`    | -                | Excluded if deleted = 1   |
| `suspended`  | -                | Excluded if suspended = 1 |

**Default Values for New Users:**

- `password`: bcrypt('password123') - temporary
- `is_active`: true
- `role`: 'user' (peserta)

---

### **Courses Mapping**

| Moodle Field | Portal Field       | Notes                    |
| ------------ | ------------------ | ------------------------ |
| `id`         | `moodle_course_id` | Foreign key reference    |
| `fullname`   | `title`            | Course title             |
| `shortname`  | `short_name`       | Course code              |
| `summary`    | `description`      | HTML stripped            |
| `category`   | `category_id`      | Category reference       |
| `startdate`  | `start_date`       | Unix timestamp → Y-m-d   |
| `enddate`    | `end_date`         | Unix timestamp → Y-m-d   |
| `visible`    | `is_active`        | 1 = active, 0 = inactive |

**Excluded:**

- Course ID = 1 (Site root)

---

### **Enrollments Mapping**

| Moodle Table      | Portal Table         | Join Logic                |
| ----------------- | -------------------- | ------------------------- |
| `user_enrolments` | `course_enrollments` | Main enrollment data      |
| `enrol`           | -                    | Bridge table              |
| `course`          | `courses`            | Match by moodle_course_id |
| `user`            | `users`              | Match by email            |

**Enrollment Status:**

- `status = 0` → active
- `status = 1` → suspended

---

## 🔍 Troubleshooting

### **Problem: Connection Disconnected**

**Symptoms:**

- Red badge "Tidak Terhubung"
- Error message di status card

**Solutions:**

1. Check `.env` variables:

    ```bash
    MOODLE_DB_HOST=...
    MOODLE_DB_PORT=1521
    MOODLE_DB_DATABASE=ORCL
    ```

2. Test connection manually:

    ```php
    php artisan tinker
    >>> DB::connection('moodle')->select('SELECT 1 FROM DUAL');
    ```

3. Check Oracle TNS listener:

    ```bash
    lsnrctl status
    ```

4. Verify firewall allows port 1521

---

### **Problem: Sync Fails with Errors**

**Symptoms:**

- Toast notification shows "Gagal melakukan sync"
- History shows status "error"

**Solutions:**

1. **Check Laravel logs:**

    ```bash
    tail -f storage/logs/laravel.log
    ```

2. **Common errors:**
    - **ORA-12154: TNS:could not resolve the connect identifier**
      → Fix TNS connection string di `.env`

    - **Duplicate key error**
      → Email/moodle_user_id sudah ada
      → Service akan auto-update instead of create

    - **Foreign key constraint**
      → Course/User not found in Portal
      → Sync users & courses terlebih dahulu

3. **Verify Moodle data:**
    ```sql
    SELECT COUNT(*) FROM mdl_user WHERE deleted = 0;
    SELECT COUNT(*) FROM mdl_course WHERE id != 1;
    ```

---

### **Problem: Slow Sync Performance**

**Symptoms:**

- Sync duration > 60 seconds
- Timeout errors

**Solutions:**

1. **Check network latency:**

    ```bash
    ping 192.168.1.100
    ```

2. **Optimize queries:**
    - Add indexes on Moodle tables:
        ```sql
        CREATE INDEX idx_user_email ON mdl_user(email);
        CREATE INDEX idx_course_visible ON mdl_course(visible);
        ```

3. **Increase PHP timeout:**

    ```php
    // config/database.php
    'moodle' => [
        'options' => [
            PDO::ATTR_TIMEOUT => 60,
        ],
    ],
    ```

4. **Run sync during off-peak hours**
    - Schedule via cron job:
        ```bash
        0 2 * * * php /path/to/artisan moodle:sync full
        ```

---

## 🎯 Best Practices

### **1. Regular Sync Schedule**

**Recommended:**

- **Full Sync**: 1x per day (2 AM)
- **User Sync**: Every 4 hours
- **Course Sync**: Every 6 hours
- **Enrollment Sync**: Every 2 hours

**Cron Setup (Laravel Scheduler):**

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    // Full sync daily at 2 AM
    $schedule->call(function () {
        app(MoodleSyncService::class)->fullSync();
    })->dailyAt('02:00');

    // User sync every 4 hours
    $schedule->call(function () {
        app(MoodleSyncService::class)->syncUsers();
    })->everyFourHours();
}
```

---

### **2. Monitor Sync Health**

**Key Metrics:**

- Sync success rate (target: > 99%)
- Average sync duration (target: < 30s)
- Error count per sync (target: 0)
- Data completeness (synced vs Moodle total)

**Dashboard Query:**

```sql
SELECT
    type,
    status,
    AVG(duration_seconds) as avg_duration,
    COUNT(*) as total_syncs,
    SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors
FROM moodle_sync_logs
WHERE started_at >= NOW() - INTERVAL 7 DAY
GROUP BY type, status;
```

---

### **3. Handle Large Datasets**

**For > 10,000 records:**

1. **Use chunk processing:**

    ```php
    $moodleUsers = DB::connection('moodle')
        ->table('user')
        ->where('deleted', 0)
        ->cursor(); // Use cursor instead of get()

    foreach ($moodleUsers as $mUser) {
        // Process one by one
    }
    ```

2. **Implement batch inserts:**

    ```php
    User::upsert($batchData, ['email'], ['name', 'moodle_user_id']);
    ```

3. **Add progress reporting:**
    ```php
    $this->log('info', "Processed {$processed}/{$total} users");
    ```

---

## 📝 Changelog

### **Version 1.0.0 (2026-02-04)**

**✨ Features:**

- ✅ Full sync dari Moodle ke Portal (Users, Courses, Enrollments)
- ✅ Direct Database Access Strategy (Oracle to Oracle)
- ✅ Real-time connection status monitoring
- ✅ Auto-refresh statistics every 30 seconds
- ✅ Comprehensive sync history logging
- ✅ Toast notifications untuk semua operasi
- ✅ Loading states & error handling
- ✅ SuperAdmin dashboard integration

**🗄️ Database:**

- ✅ Migration: `moodle_sync_logs` table
- ✅ Model: `MoodleSyncLog` with relationships
- ✅ Indexes untuk performance optimization

**🔧 Backend:**

- ✅ Service: `MoodleSyncService` (400+ lines)
- ✅ Controller: `MoodleSyncController` dengan 7 endpoints
- ✅ API Routes dengan role-based middleware

**🎨 Frontend:**

- ✅ Page: `/superadmin/moodle` dengan 4 sections
- ✅ API Client: TypeScript interfaces & functions
- ✅ Real-time updates dengan polling
- ✅ Responsive design (mobile-friendly)

---

## 🚧 Future Enhancements

### **Planned Features:**

1. **Progress Tracking via SSE (Server-Sent Events)**
    - Real-time progress bar during sync
    - Live log streaming
    - Estimated time remaining

2. **Selective Sync Filters**
    - Sync by date range
    - Sync specific courses only
    - Sync by user role

3. **Conflict Resolution UI**
    - Detect duplicates before sync
    - Manual merge options
    - Rollback failed syncs

4. **Performance Optimization**
    - Queue-based sync untuk large datasets
    - Parallel processing workers
    - Incremental sync (only changed data)

5. **Notification System**
    - Email notification on sync completion
    - Slack/Teams integration
    - Alert on sync failures

6. **API Rate Limiting**
    - Prevent concurrent syncs
    - Queue sync requests
    - Priority-based scheduling

---

## 📚 Related Documentation

- [ERP Integration Guide](./ERP_INTEGRATION_GUIDE.md)
- [User Management Guide](./AKSI_USER_MANAGEMENT_IMPROVEMENTS.md)
- [Certificate System](./IMPLEMENTATION_COMPLETE.md)
- [API Specification](./ERP_API_SPECIFICATION.md)

---

## 👥 Support

**For Questions:**

- Slack: #plnip-portal-dev
- Email: dev-team@plnip.co.id

**For Bug Reports:**

- GitHub Issues: [plnip-portal/issues](https://github.com/plnip/portal/issues)

---

**Status:** ✅ **PRODUCTION READY**  
**Last Updated:** 2026-02-04  
**Version:** 1.0.0
