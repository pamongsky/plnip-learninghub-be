# Arsitektur Sistem PLN IP Learning Hub Portal

## KONSEP DASAR SISTEM

Sebelum masuk ke detail teknis, pahami dulu gambaran besar sistem ini:

### Apa Itu PLN IP Learning Hub?

Portal ini adalah sistem pembelajaran online untuk karyawan PLN Indonesia Power. Tujuannya:
- Semua karyawan bisa akses materi pembelajaran dari mana saja
- Admin bisa manage kelas dan peserta dengan mudah
- Sertifikat digital otomatis setelah selesai kelas
- Integrasi dengan sistem yang sudah ada (ERP dan Moodle)

### Analogi Sederhana

Bayangkan sistem ini seperti **sekolah online**:
- **Portal** = gedung sekolah (tempat daftar, lihat jadwal, ambil sertifikat)
- **Moodle** = ruang kelas (tempat belajar, baca materi, kerjakan tugas)
- **ERP** = database karyawan (data siapa aja yang boleh sekolah di sini)
- **AI Assistant** = guru privat (bantu jawab pertanyaan 24/7)

### Kenapa Pakai Banyak Komponen?

**Tidak Pakai 1 Sistem All-in-One Karena:**
1. **Moodle** sudah ada dan banyak materi → tidak perlu bikin LMS dari nol
2. **ERP** sudah punya data karyawan → tidak perlu input manual
3. **Backend terpisah** → bisa di-upgrade tanpa ganggu frontend
4. **Frontend terpisah** → bisa ganti design tanpa ganggu backend

---

## 1. Gambaran Umum

PLN IP Learning Hub Portal terdiri dari beberapa komponen yang bekerja sama:

**Komponen Utama:**
- **Backend REST API** (Laravel 12) - Otak sistem yang atur semua logic
- **Frontend Web Application** (Next.js 14) - Tampilan yang user lihat di browser
- **LMS (Learning Management System)** - Moodle untuk materi pembelajaran
- **Database** - Oracle Database untuk simpan data
- **ERP System** - Sistem ERP PLN IP untuk data karyawan (sync otomatis)
- **AI Assistant** - Google Gemini API untuk chatbot pembelajaran

### 1.1 Diagram Arsitektur High-Level

```
┌─────────────────────────────────────────────────────────────────┐
│                         USERS                                    │
│  (Karyawan, Instructor, Admin, Super Admin)                     │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             │ HTTPS
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                    FRONTEND (Next.js 14)                         │
│  - App Router (React 19)                                        │
│  - Tailwind CSS + Radix UI                                      │
│  - Laravel Echo (WebSocket Client)                              │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             │ REST API + WebSocket
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                    BACKEND (Laravel 12)                          │
│  - REST API Controllers                                         │
│  - Laravel Sanctum (Authentication)                             │
│  - Spatie Permission (Authorization)                            │
│  - Laravel Reverb (WebSocket Server)                            │
└─────┬────────┬────────┬───────────┬──────────────┬─────────────┘
      │        │        │           │              │
      │        │        │           │              │
      ▼        ▼        ▼           ▼              ▼
┌──────────┐ ┌────────────┐ ┌───────────┐ ┌──────────────┐ ┌───────────┐
│  Oracle  │ │   Moodle   │ │    ERP    │ │   Gemini     │ │  Storage  │
│    DB    │ │ Oracle DB  │ │  REST API │ │     API      │ │   (PDF)   │
│ (Portal) │ │   (LMS)    │ │  (Sync)   │ │     (AI)     │ │           │
└──────────┘ └────────────┘ └───────────┘ └──────────────┘ └───────────┘
```

### 1.2 Prinsip Arsitektur

Sistem ini didesain dengan prinsip-prinsip berikut (dan alasannya):

**1. Separation of Concerns (Pemisahan Tanggung Jawab)**
- Backend dan Frontend terpisah
- **Kenapa?** Agar tim bisa kerja parallel (backend developer & frontend developer) tanpa bentrok

**2. RESTful API (Komunikasi Standar)**
- Semua komunikasi pakai REST API (JSON format)
- **Kenapa?** Agar frontend bisa diganti (dari web ke mobile app) tanpa ubah backend

**3. Database Integration (Integrasi Database)**
- Portal punya database sendiri (master data)
- Moodle punya database sendiri (untuk LMS)
- Portal bisa akses database Moodle (read-only)
- **Kenapa?** Portal tidak ganggu struktur Moodle yang sudah jalan, tapi bisa ambil data dari Moodle

**4. Data Synchronization (Sinkronisasi Data)**
- Data karyawan: ERP → Portal (one-way sync)
- Data course: Moodle → Portal (one-way sync)
- **Kenapa one-way?** Supaya ERP dan Moodle tetap jadi master data, Portal hanya baca

**5. Role-Based Access Control (Kontrol Akses Berdasarkan Role)**
- User punya role (super-admin, admin, instructor, user)
- Setiap role punya permission berbeda
- **Kenapa?** Agar super-admin bisa CRUD semua, tapi user biasa hanya bisa lihat kelas mereka sendiri

## 2. Komponen Backend (Laravel 12)

### 2.1 Struktur Direktori Backend

```
plnip-portal/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       └── API/
│   │           ├── AuthController.php
│   │           ├── CourseController.php
│   │           ├── CertificateController.php
│   │           ├── UserController.php
│   │           ├── MoodleSyncController.php
│   │           ├── AIAssistantController.php
│   │           ├── SupportTicketController.php
│   │           ├── AnnouncementController.php
│   │           └── ... (lainnya)
│   ├── Models/
│   │   ├── User.php
│   │   ├── Course.php
│   │   ├── Certificate.php
│   │   ├── CourseEnrollment.php
│   │   ├── SupportTicket.php
│   │   └── ... (lainnya)
│   └── Services/
│       ├── MoodleSyncService.php
│       ├── ERPSyncService.php
│       └── UserService.php
├── config/
│   ├── database.php (Oracle + Moodle connections)
│   ├── permission.php (Spatie config)
│   ├── reverb.php (WebSocket config)
│   ├── erp.php (ERP integration config)
│   └── services.php (Moodle, Gemini API)
├── database/
│   ├── migrations/
│   └── seeders/
│       ├── DatabaseSeeder.php
│       └── RolePermissionSeeder.php
└── routes/
    └── api.php
```

### 2.2 Authentication & Authorization

**Authentication: Laravel Sanctum**
- Token-based authentication untuk SPA
- Token disimpan di cookies (httpOnly untuk security)
- Middleware `auth:sanctum` untuk protected routes

**Authorization: Spatie Laravel Permission**
- Role-based: `super-admin`, `admin`, `instructor`, `user` (learner)
- Permission granular untuk setiap fitur
- Middleware `role:role-name` untuk role checking

### 2.3 Database Schema Overview

**Table Utama:**

1. **users** - Data user/karyawan
   - `id`, `employee_id` (NIP), `name`, `email`, `password`
   - `source` (manual/erp), `access_group`, `role_override`
   - `moodle_user_id` (foreign key ke Moodle)
   - `department`, `position`, `phone`
   - `is_active`, `synced_at`, `created_at`, `updated_at`

2. **courses** - Data kelas/course
   - `id`, `title`, `short_name`, `description`
   - `category_id`, `instructor_id`
   - `moodle_course_id` (foreign key ke Moodle)
   - `start_date`, `end_date`, `is_active`
   - `created_at`, `updated_at`

3. **course_enrollments** - Relasi user-course
   - `id`, `user_id`, `course_id`
   - `moodle_role` (student, editingteacher, teacher, coursecreator, manager)
   - `status` (active, suspended, completed)
   - `enrolled_at`, `completed_at`

4. **certificates** - Sertifikat digital
   - `id`, `user_id`, `course_id`
   - `certificate_number`, `pdf_path`
   - `original_filename`, `is_valid`
   - `notes`, `created_at`, `updated_at`

5. **support_tickets** - Tiket bantuan
   - `id`, `user_id`, `category`, `subject`, `description`
   - `status` (open, in_progress, resolved, closed)
   - `priority`, `attachments`, `is_escalated`
   - `created_at`, `updated_at`

6. **announcements** - Pengumuman
   - `id`, `user_id`, `title`, `content`
   - `scope` (global, department, class), `target_id`
   - `priority`, `image`, `is_active`
   - `created_at`, `updated_at`

7. **activity_logs** - Log aktivitas
   - `id`, `log_name`, `description`
   - `subject_type`, `subject_id`
   - `causer_type`, `causer_id`
   - `properties`, `created_at`

8. **audit_logs** - Audit trail (khusus untuk sensitive operations)
   - `id`, `user_id`, `action`, `entity_type`, `entity_id`
   - `changes`, `reason`, `ip_address`
   - `created_at`

9. **role & permission tables** (Spatie)
   - `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`

### 2.4 Services Layer

**MoodleSyncService**
- `fullSync()` - Sync semua data dari Moodle
- `syncUsers()` - Sync users dari Moodle ke Portal
- `syncCourses()` - Sync courses dari Moodle ke Portal
- `syncEnrollments()` - Sync enrollments dari Moodle ke Portal
- `getConnectionStatus()` - Cek koneksi Moodle

**ERPSyncService**
- `syncUsers()` - Sync data karyawan dari ERP ke Portal
- `fetchEmployees()` - Ambil data dari ERP API
- `createUserFromERP()` - Buat user baru dari data ERP
- `updateUserFromERP()` - Update user existing dari data ERP
- `validateUserStatus()` - Validasi status user di ERP (untuk login)

**UserService**
- `mapAccessGroupToRole()` - Mapping access_group ERP ke role Laravel
- Helper methods untuk user management

## 3. Komponen Frontend (Next.js 14)

### 3.1 Struktur Direktori Frontend

```
plnip-portal-frontend/
├── app/
│   ├── (auth)/
│   │   ├── login/
│   │   └── register/
│   ├── dashboard/          # Employee/User pages
│   │   ├── classes/
│   │   ├── certificates/
│   │   ├── support/
│   │   └── profile/
│   ├── instructor/         # Instructor pages
│   │   ├── classes/
│   │   ├── announcements/
│   │   └── messages/
│   ├── admin/             # Admin pages
│   │   ├── courses/
│   │   ├── users/
│   │   ├── support/
│   │   └── announcements/
│   └── superadmin/        # Super Admin pages
│       ├── users/
│       ├── roles/
│       ├── moodle-sync/
│       ├── home/ (CMS)
│       └── activity-log/
├── components/
│   ├── ui/               # Shadcn/Radix UI components
│   ├── layout/           # Layout components
│   └── features/         # Feature-specific components
├── lib/
│   ├── api.ts           # Axios instance & API helpers
│   ├── auth.ts          # Authentication helpers
│   └── utils.ts         # Utility functions
└── public/
    └── assets/
```

### 3.2 Routing Strategy

- **App Router** (Next.js 14) - File-based routing
- **Route Groups** - `(auth)` untuk public pages, role-based folders
- **Middleware** - Route protection berdasarkan authentication & role
- **Dynamic Routes** - `[id]` untuk detail pages

### 3.3 State Management

- **Server Components** - Default untuk data fetching
- **Client Components** - Untuk interactivity (form, modals)
- **React Context** - User authentication state
- **URL State** - Untuk filtering, pagination, search

## 4. Integrasi Moodle

### 4.1 Strategi Integrasi

PLN IP Portal menggunakan **dua metode** untuk integrasi Moodle:

1. **Direct Database Connection** - Untuk read data (cepat, real-time)
2. **Web Services API** - Untuk write operations & content extraction

### 4.2 Moodle Database Connection

**Konfigurasi di `config/database.php`:**

```php
'moodle' => [
    'driver' => 'oracle',
    'host' => env('MOODLE_DB_HOST'),
    'port' => env('MOODLE_DB_PORT', '1521'),
    'database' => env('MOODLE_DB_DATABASE'),
    'service_name' => env('MOODLE_DB_SERVICE_NAME'),
    'username' => env('MOODLE_DB_USERNAME'),
    'password' => env('MOODLE_DB_PASSWORD'),
    'charset' => 'AL32UTF8',
    'prefix' => 'mdl_',
],
```

**Tabel Moodle yang Diakses:**
- `mdl_user` - Data user Moodle
- `mdl_course` - Data course/kelas
- `mdl_course_categories` - Kategori course
- `mdl_enrol` & `mdl_user_enrolments` - Data enrollment
- `mdl_grade_items` & `mdl_grade_grades` - Nilai/grades
- `mdl_course_modules` - Modul pembelajaran
- `mdl_assign`, `mdl_quiz`, `mdl_resource`, dll - Activity modules

### 4.3 Moodle Web Services API

**Endpoint Base:** `https://moodle.plnip.co.id/webservice/rest/server.php`

**Web Service Functions yang Digunakan:**
- `core_course_get_contents` - Ambil konten course untuk AI Assistant
- `mod_assign_get_assignments` - Ambil detail tugas
- `core_user_create_users` - Buat user baru di Moodle (via Portal enrollment)
- `enrol_manual_enrol_users` - Enroll user ke course

**Authentication:** Token-based (`wstoken` parameter)

### 4.4 Enrollment Flow: Portal → Moodle

```
User Enroll di Portal
         │
         ▼
Cek: Apakah user sudah ada di Moodle?
         │
    ┌────┴────┐
    NO        YES
    │         │
    ▼         ▼
Create User   Skip
in Moodle
    │         │
    └────┬────┘
         │
         ▼
Enroll user ke course di Moodle
(Direct DB INSERT ke mdl_user_enrolments)
         │
         ▼
Simpan moodle_user_id & course enrollment
di Portal database
         │
         ▼
Return success ke Frontend
```

### 4.5 Grade Sync & Certificate Generation

**Formula Perhitungan Nilai:**
```
final_grade = (finalgrade / grademax) * 100
```

Moodle menggunakan skala 0-10 dengan `grademax` yang bervariasi. Portal menormalisasi ke skala 0-100.

**Catatan:** Certificate system saat ini MANUAL (admin upload PDF), bukan auto-generate.

## 5. Integrasi ERP

### 5.1 Konsep ERP Sync

**Arah Data Flow:**
```
ERP System → Portal Database
```

Data user (karyawan) di-sync **DARI** ERP PLN IP **KE** Portal. Portal **TIDAK** mengirim data kembali ke ERP.

### 5.2 Data Mapping

**Field Mapping ERP → Portal:**

| ERP Field        | Portal Field   | Keterangan                         |
|------------------|----------------|------------------------------------|
| employee_id      | employee_id    | NIP (PRIMARY KEY untuk matching)   |
| name             | name           | Nama lengkap                       |
| email            | email          | Email corporate                    |
| phone            | phone          | Nomor telepon                      |
| department       | department     | Departemen/Unit kerja              |
| position         | position       | Jabatan                            |
| access_group     | access_group   | Hak akses (USER, ADMIN, dll)       |
| is_active        | is_active      | Status aktif karyawan              |

### 5.3 ERP Sync Flow

```
┌──────────────────────────────────────────────────────────┐
│              TRIGGER SYNC (Manual/Scheduled)             │
└──────────────────────┬───────────────────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────────────────┐
│    ERPSyncService::syncUsers()                           │
│    - Fetch data dari ERP API (GET /api/employees)       │
│    - Dengan Authorization Bearer token                   │
└──────────────────────┬───────────────────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────────────────┐
│    Loop setiap employee data:                            │
│                                                           │
│    1. Cari user berdasarkan employee_id (NIP)           │
│       ├─ User NOT FOUND → Create new user                │
│       │   - Set source = 'erp'                           │
│       │   - Assign role dari access_group                │
│       │   - Generate random password                     │
│       │   - Log audit: user created                      │
│       │                                                   │
│       └─ User FOUND → Update existing user               │
│           - Cek source = 'erp' ?                         │
│               YES → Update data (nama, email, dept, dll) │
│                   - Update role jika tidak ada override  │
│                   - Log audit: user updated              │
│               NO  → Skip (manual user, jangan di-update) │
└──────────────────────┬───────────────────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────────────────┐
│    Return statistics:                                     │
│    - created: jumlah user baru dibuat                    │
│    - updated: jumlah user di-update                      │
│    - skipped: jumlah user manual (tidak di-update)       │
│    - errors: jumlah error                                │
└──────────────────────────────────────────────────────────┘
```

### 5.4 ERP Sync Scheduling

**Manual Trigger:**
- Super Admin → Kelola User → Tombol "Sync ERP"
- Endpoint: `POST /api/superadmin/sync-erp`

**Automatic Scheduling (via Laravel Scheduler):**
```php
// app/Console/Kernel.php
$schedule->call(function () {
    app(ERPSyncService::class)->syncUsers();
})->dailyAt(config('erp.schedule')); // Default: 02:00
```

**Environment Variable:**
```env
ERP_ENABLED=true
ERP_API_URL=https://erp.plnip.co.id/api/employees
ERP_API_KEY=your-api-key
ERP_SYNC_SCHEDULE=02:00
```

### 5.5 User Source Management

**Field: `source`**
- `erp` - User dari ERP sync (akan di-update otomatis saat sync)
- `manual` - User dibuat manual oleh admin (tidak akan di-update oleh ERP sync)

**Role Override:**
- Super Admin bisa override role user ERP dengan `role_override = true`
- Jika `role_override = true`, role tidak akan di-update meskipun access_group berubah di ERP

## 6. Certificate System

### 6.1 Konsep Certificate Management

**Certificate system** di PLN IP Portal menggunakan pendekatan **manual upload only**:

1. **TIDAK ada auto-generate** - Sertifikat dibuat di luar sistem (Word/Canva/Adobe)
2. **Upload oleh Admin/Instructor** - Via web interface
3. **Individual atau Bulk** - Support upload per user atau ZIP untuk banyak user
4. **Auto-matching** - Bulk ZIP matching berdasarkan NIP atau nama file

### 6.2 Certificate Upload Flow

**Individual Upload:**
```
Admin → Course Detail → Tab "Peserta"
  → Klik 3-dot menu pada user
    → "Upload Sertifikat"
      → Pilih PDF file
        → Upload ke server
          → Simpan record di DB
            → User bisa download
```

**Bulk Upload (ZIP):**
```
Admin → Course Detail → Tab "Peserta"
  → Tombol "Upload Sertifikat Bulk (ZIP)"
    → Upload ZIP berisi banyak PDF
      → Extract ZIP di server
        → Loop setiap PDF file:
           ├─ Match by NIP (exact) →  Found? → Upload
           ├─ Match by Name (exact) →  Found? → Upload
           ├─ Match by Name (partial) → Found? → Upload
           └─ Not matched → Skip (log as unmatched)
        → Return result: matched & unmatched
```

**File Naming Convention untuk Bulk:**
- `12345678.pdf` (NIP exact)
- `Budi Santoso.pdf` (nama exact)
- `Budi.pdf` (nama partial)

### 6.3 Certificate Model

```php
Certificate
├── id
├── user_id (foreign key to users)
├── course_id (foreign key to courses)
├── certificate_number (unique, generated: CERT-XXXXXXXX)
├── pdf_path (storage path: certificates/{number}.pdf)
├── original_filename (nama file asli saat upload)
├── is_valid (boolean: true/false untuk revoke)
├── notes (catatan jika di-revoke)
├── created_at
└── updated_at
```

### 6.4 Certificate Storage

**Storage Disk:** `public`

**Path:** `storage/app/public/certificates/`

**Symlink:** `php artisan storage:link` untuk akses public

**Download URL:** `/api/certificates/{id}/download`

## 7. AI Assistant (Gemini API)

### 7.1 Konsep AI Assistant

AI Assistant di PLN IP Portal adalah asisten pembelajaran berbasis Google Gemini API dengan kemampuan:

1. **General AI** - Menjawab pertanyaan umum (matematika, sains, bahasa, dll)
2. **Platform Navigation** - Membantu navigasi fitur platform
3. **Learning Assistant** - Membantu memahami materi pembelajaran dari Moodle
4. **Quiz Helper** - Menjelaskan konsep soal (TANPA memberikan jawaban langsung)

### 7.2 AI Assistant Architecture

```
User Input
    │
    ▼
Frontend → API: POST /api/ai-assistant/chat
    │       {
    │         message: "...",
    │         conversation_id: "...",
    │         course_id: 123 (optional)
    │       }
    │
    ▼
Backend: AIAssistantController
    │
    ├─ Get user context (role, features, enrolled courses)
    ├─ Auto-detect if asking about course material
    │   └─ If yes → Fetch Moodle course content
    │       ├─ Resources: PDF, Page, File, Folder, Book, Lesson
    │       ├─ Activities: Assignment (YES), Quiz (NO)
    │       └─ Extract PDF text (smalot/pdfparser)
    │
    ├─ Build system prompt
    │   ├─ General AI capabilities
    │   ├─ Platform features context
    │   ├─ User's enrolled courses
    │   └─ Course material content (if available)
    │
    ├─ Get conversation history (last 10 messages)
    │
    ▼
Call Gemini API
  POST https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent
  {
    contents: [
      { role: 'user', parts: [{ text: system_prompt }] },
      { role: 'model', parts: [{ text: initial_response }] },
      ... history messages ...,
      { role: 'user', parts: [{ text: user_message }] }
    ],
    generationConfig: {
      temperature: 0.7,
      maxOutputTokens: 2048
    }
  }
    │
    ▼
Gemini Response
    │
    ├─ Save conversation to DB (user message + assistant response)
    │
    ▼
Return to Frontend
```

### 7.3 Moodle Content Extraction

**Module Types yang Bisa Dibaca AI:**

| Module Type | Bisa Dibaca? | Keterangan                                   |
|-------------|--------------|----------------------------------------------|
| resource    | YES          | File (PDF di-extract text, DOC skip)         |
| page        | YES          | HTML page (strip tags)                       |
| url         | YES          | External link (URL only)                     |
| label       | YES          | Inline HTML content                          |
| folder      | YES          | Folder dengan files (PDF di-extract)         |
| book        | YES          | Book chapters                                |
| lesson      | YES          | Lesson pages                                 |
| assign      | YES          | Assignment description (TIDAK jawaban)       |
| forum       | NO           | Forum discussions (metadata only)            |
| quiz        | NO           | Quiz/Exam content (integrity protection)     |
| glossary    | NO           | Metadata only                                |
| wiki        | NO           | Metadata only                                |

**Quiz/Exam Protection:**
- AI **TIDAK BOLEH** membaca soal quiz/exam untuk menjaga integritas ujian
- Hanya metadata (judul, deskripsi umum) yang tersedia

### 7.4 PDF Text Extraction

**Library:** `smalot/pdfparser`

**Process:**
1. Download PDF dari Moodle via URL + token
2. Save ke temporary file
3. Parse dengan PDFParser
4. Extract semua text dari pages
5. Clean text (remove excessive whitespace, fix formatting)
6. Limit text length (max 15,000 chars untuk prevent token overflow)
7. Return cleaned text

**Limitations:**
- PDF dengan image-only (scanned) tidak bisa di-extract text
- PDF dengan proteksi/encryption mungkin gagal
- Formatting bisa hilang (tables, columns)

## 8. Role & Permission System (Spatie)

### 8.1 Roles

| Role         | Deskripsi                                        |
|--------------|--------------------------------------------------|
| super-admin  | Full access ke semua fitur dan data              |
| admin        | Kelola kelas, user (read-only), support ticket   |
| instructor   | Kelola kelas yang diajar, upload sertifikat      |
| user         | Peserta pembelajaran, akses kelas enrolled       |

### 8.2 Permission Categories

**User Management:**
- `view users`, `create users`, `edit users`, `delete users`
- `override user role`, `view user audit`

**Course Management:**
- `view courses`, `create courses`, `edit courses`, `delete courses`
- `sync moodle`, `enroll users`, `unenroll users`, `manage enrollments`

**Certificate Management:**
- `view certificates`, `upload certificates`, `revoke certificates`

**Support Management:**
- `view tickets`, `create tickets`, `reply tickets`, `update ticket status`, `escalate tickets`

**Announcement Management:**
- `view announcements`, `create announcements`, `edit announcements`, `delete announcements`

**System Management:**
- `view roles`, `create roles`, `edit roles`, `delete roles`
- `view permissions`, `create permissions`, `edit permissions`, `delete permissions`
- `sync erp`, `view activity log`, `manage cms`

### 8.3 Permission Assignment

**Default Permission per Role:**

```php
// Super Admin
- ALL permissions (wildcard)

// Admin
- view/create/edit users (read-only mode)
- view/create/edit/delete courses
- sync moodle courses
- enroll/unenroll users
- upload certificates
- view/create/reply/update tickets
- escalate tickets
- view/create/edit/delete announcements (department scope)

// Instructor
- view courses (own courses only)
- view enrollments (own courses only)
- upload certificates (own courses only)
- view/create/edit announcements (class scope)
- reply class chat

// User (Employee/Learner)
- view courses (enrolled only)
- view certificates (own only)
- create/view tickets (own only)
- view announcements (targeted)
```

## 9. Real-time Features (Laravel Reverb)

### 9.1 WebSocket Architecture

```
┌────────────┐
│  Frontend  │
│ (Pusher JS)│
└─────┬──────┘
      │
      │ WebSocket
      │
      ▼
┌────────────────┐      ┌──────────────┐
│ Laravel Reverb │◄────►│  Redis       │
│   (WS Server)  │      │  (Optional)  │
└────────┬───────┘      └──────────────┘
         │
         │
         ▼
┌───────────────────────┐
│   Laravel Backend     │
│  (Broadcast Events)   │
└───────────────────────┘
```

### 9.2 Real-time Events

**Chat & Messaging:**
- `ClassMessageSent` - Class group chat
- `DirectMessageSent` - Private messaging
- `MessageRead` - Read receipt

**Notifications:**
- `AnnouncementPublished` - Pengumuman baru
- `TicketReplied` - Reply pada support ticket
- `CertificateIssued` - Sertifikat baru tersedia
- `CourseEnrolled` - User di-enroll ke course

**System Events:**
- `UserOnlineStatus` - User online/offline status

### 9.3 Broadcasting Configuration

**Backend (`.env`):**
```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

**Frontend (`.env.local`):**
```env
NEXT_PUBLIC_WS_HOST=localhost
NEXT_PUBLIC_WS_PORT=8080
NEXT_PUBLIC_WS_KEY=your-app-key
```

**Laravel Echo Client:**
```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: process.env.NEXT_PUBLIC_WS_KEY,
    wsHost: process.env.NEXT_PUBLIC_WS_HOST,
    wsPort: process.env.NEXT_PUBLIC_WS_PORT,
    forceTLS: false,
    disableStats: true,
});
```

## 10. Security Considerations

### 10.1 Authentication Security

- Password hashing: bcrypt (Laravel default)
- Session: httpOnly cookies untuk prevent XSS
- CSRF protection: Laravel Sanctum built-in
- Token expiration: 2 hours (configurable)

### 10.2 Authorization Security

- Role-based access control (Spatie Permission)
- Middleware protection pada semua protected routes
- Input validation pada semua endpoints
- SQL injection prevention: Eloquent ORM

### 10.3 Data Security

- Oracle database connection encryption
- Environment variables untuk sensitive data (`.env` never committed)
- File upload validation (type, size, extension)
- PDF storage dengan permission control

### 10.4 API Security

- Rate limiting pada API endpoints
- CORS configuration untuk frontend domain only
- API token authentication (Sanctum)
- Request validation & sanitization

### 10.5 Audit & Logging

- Activity logs untuk semua user actions (Spatie Activity Log)
- Audit logs untuk sensitive operations (user CRUD, role changes)
- Security logs untuk login attempts, failed authentication
- Error logs dengan stack traces (Laravel Log)

## 11. Scalability & Performance

### 11.1 Database Optimization

- Indexes pada foreign keys dan sering di-query columns
- Eager loading untuk prevent N+1 queries
- Database connection pooling (Oracle native)
- Query caching untuk static data

### 11.2 API Performance

- Response caching (Redis)
- Pagination untuk large datasets
- Lazy loading untuk resources
- API response compression

### 11.3 Frontend Performance

- Next.js Server Components untuk SSR
- Static generation untuk public pages
- Image optimization (Next.js Image)
- Code splitting & lazy loading

### 11.4 File Storage

- Public storage untuk certificates (with symlink)
- CDN untuk static assets (optional)
- File compression untuk PDF storage
- Cleanup jobs untuk orphaned files

## 12. Monitoring & Maintenance

### 12.1 System Health Checks

- Database connection status
- Moodle connection status
- ERP API availability
- Gemini API availability
- Laravel Reverb status

### 12.2 Monitoring Metrics

- API response times
- Database query performance
- User active sessions
- Certificate generation rate
- Support ticket volume

### 12.3 Backup Strategy

- Daily database backup (Oracle RMAN)
- Weekly full backup
- Transaction log backup (continuous)
- File storage backup (certificates)
- Configuration backup (`.env`, configs)

### 12.4 Update & Maintenance

- Laravel updates (minor versions)
- Security patches (priority)
- Package updates (monthly review)
- Database migrations (tested in staging)
- Moodle version compatibility

## 13. Development Workflow

### 13.1 Git Workflow

```
main (production)
  └─ develop (staging)
      └─ feature/* (development)
```

- Feature branches untuk new features
- Pull request & code review sebelum merge
- Automated tests di CI/CD pipeline

### 13.2 Environment Setup

1. **Local Development** - Laragon/XAMPP + Oracle Express
2. **Staging** - Mirror production environment
3. **Production** - PLN IP internal servers

### 13.3 Deployment Process

1. Merge ke `develop` → Deploy ke staging
2. QA testing di staging
3. Merge ke `main` → Deploy ke production
4. Database migration (with backup)
5. Clear cache & restart services

## 14. Kesimpulan

Arsitektur PLN IP Learning Hub Portal dirancang dengan prinsip:

1. **Modular** - Setiap komponen bisa di-upgrade/replace tanpa affect others
2. **Scalable** - Bisa handle peningkatan users dan data
3. **Secure** - Multi-layer security dari authentication sampai authorization
4. **Maintainable** - Code structure yang jelas dan dokumentasi lengkap
5. **Extensible** - Mudah menambahkan fitur baru

Sistem ini siap untuk mendukung program pembelajaran PLN IP dalam jangka panjang dengan fleksibilitas untuk beradaptasi dengan kebutuhan bisnis yang berubah.
