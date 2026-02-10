# Instalasi dan Deployment PLN IP Learning Hub Portal

## KONSEP DASAR DEPLOYMENT

Sebelum masuk ke langkah teknis, pahami dulu apa itu deployment dan mengapa kita perlu melakukannya:

### Apa itu Development vs Production?

**Development (Lokal di Komputer Anda):**
- Ini adalah tempat Anda bekerja membuat kode
- Jalan di komputer/laptop Anda sendiri (contoh: Laragon)
- Hanya Anda yang bisa akses (localhost)
- Kalau ada error, tidak masalah karena masih testing
- Contoh: http://localhost:8000

**Production (Server di Internet):**
- Ini adalah tempat aplikasi yang sudah jadi ditaruh agar bisa diakses semua orang
- Jalan di server khusus (komputer yang nyala 24/7 dan terhubung internet)
- Semua karyawan PLN IP bisa akses dari browser mereka
- Harus stabil, tidak boleh sering error
- Contoh: https://portal.plnip.co.id

### Kenapa Ada 2 Aplikasi (Backend + Frontend)?

Portal ini terdiri dari 2 aplikasi terpisah:

**Backend (Laravel):**
- Ini yang ngurusin data, database, login, permission, dll
- Kayak "otak" dari aplikasi
- User tidak lihat langsung, tapi semua request dari user diproses di sini
- Simpan data user, kelas, sertifikat ke database Oracle
- Contoh endpoint: https://portal.plnip.co.id/api/courses

**Frontend (Next.js):**
- Ini yang user lihat di browser (tampilan web)
- Kayak "wajah" dari aplikasi
- Ambil data dari Backend via API, terus tampilkan ke user
- Contoh: https://plnip.co.id (halaman yang user buka)

### Alur Deployment Sederhana

```
Komputer Developer (Development)
    │
    │ (1) Kode selesai dibuat & ditest
    │
    ▼
Git Repository (GitHub/GitLab)
    │
    │ (2) Push kode ke Git
    │
    ▼
Server Production (Linux)
    │
    │ (3) Pull kode dari Git
    │ (4) Install dependencies (Composer, npm)
    │ (5) Setup database & environment
    │ (6) Build aplikasi
    │ (7) Start service (Nginx, PM2, dll)
    │
    ▼
Aplikasi Live di Internet
    │
    └─► User bisa akses dari browser mereka
```

### Apa yang Dibutuhkan untuk Deployment?

1. **Server Production** - Komputer/VM yang nyala 24/7 dengan koneksi internet
2. **Database Oracle** - Tempat simpan data (bisa di server yang sama atau terpisah)
3. **Domain Name** - Alamat web yang mudah diingat (contoh: plnip.co.id)
4. **SSL Certificate** - Supaya website aman (https:// bukan http://)
5. **Konfigurasi .env** - File yang isinya password database, API key, dll (JANGAN PERNAH di-push ke Git!)

### Langkah Besar Deployment

1. **Setup Server** - Install PHP, Node.js, Nginx, Oracle Client
2. **Upload Kode** - Git clone dari repository
3. **Konfigurasi** - Setup .env dengan password production
4. **Database** - Jalankan migration untuk buat tabel
5. **Build** - Compile kode agar siap production
6. **Service** - Jalankan aplikasi dengan PM2, Supervisor, dll
7. **Testing** - Pastikan semua jalan lancar sebelum kasih tau user

Sekarang kita masuk ke detail teknisnya:

---

## 1. Requirement Sistem

Ini adalah software yang HARUS sudah terinstall di server sebelum kita bisa deploy aplikasi.

### 1.1 Software Requirements

**Backend (Laravel 12):**
- PHP 8.2 atau lebih tinggi
- Composer 2.x
- Oracle Instant Client 21c atau lebih tinggi
- Oracle Database 11g atau lebih tinggi

**Frontend (Next.js 14):**
- Node.js 18.x atau lebih tinggi (LTS recommended)
- npm 9.x atau yarn 1.22.x

**Additional Services:**
- Redis (optional, untuk cache dan queue)
- Supervisor (untuk queue worker di production)

### 1.2 PHP Extensions Required

```
php -m | grep -E 'pdo|oci8|mbstring|openssl|curl|json|xml'
```

**Required extensions:**
- `pdo_oci` - Oracle database driver
- `oci8` - Oracle OCI8 extension
- `mbstring` - Multibyte string support
- `openssl` - SSL/TLS support
- `curl` - HTTP client
- `json` - JSON processing
- `xml` - XML processing
- `zip` - ZIP archive support
- `gd` atau `imagick` - Image processing

### 1.3 Server Requirements (Production)

**Minimum:**
- CPU: 4 cores
- RAM: 8 GB
- Storage: 100 GB (SSD recommended)
- Network: 100 Mbps

**Recommended:**
- CPU: 8 cores
- RAM: 16 GB
- Storage: 250 GB SSD
- Network: 1 Gbps

## 2. Instalasi Oracle Instant Client

### Apa itu Oracle Instant Client dan Untuk Apa?

Oracle Instant Client adalah software yang memungkinkan PHP berkomunikasi dengan database Oracle.

**Analogi Sederhana:**
Bayangkan database Oracle sebagai ruangan tertutup. PHP (Laravel) butuh "kunci khusus" untuk bisa masuk dan ambil/simpan data di ruangan itu. Nah, Oracle Instant Client ini adalah "kunci" tersebut.

Tanpa Oracle Instant Client:
- PHP tidak bisa connect ke database Oracle
- Laravel tidak bisa jalanin query (SELECT, INSERT, UPDATE, DELETE)
- Aplikasi akan error: "OCI8 extension not found"

**Catatan Penting:** Oracle Instant Client HARUS diinstall di server tempat PHP jalan (baik development maupun production).

### 2.1 Download Oracle Instant Client

1. Kunjungi: https://www.oracle.com/database/technologies/instant-client/downloads.html
2. Download Oracle Instant Client 21c untuk OS Anda:
   - Windows: `instantclient-basic-windows.x64-21.x.x.x.zip`
   - Linux: `oracle-instantclient-basic-21.x.x.x-1.x86_64.rpm`

**Kenapa versi 21c?** Karena ini versi LTS (Long Term Support) yang stabil dan kompatibel dengan Oracle Database 11g hingga 21c.

### 2.2 Instalasi di Windows

```bash
# Extract ZIP ke C:\oracle\instantclient_21_8
# Atau lokasi lain yang mudah diakses

# Tambahkan ke System PATH
setx PATH "%PATH%;C:\oracle\instantclient_21_8"

# Restart terminal untuk apply PATH
```

### 2.3 Instalasi di Linux (CentOS/RHEL)

```bash
# Install RPM
sudo rpm -ivh oracle-instantclient-basic-21.8.0.0.0-1.x86_64.rpm

# Atau via alien (Debian/Ubuntu)
sudo alien -i oracle-instantclient-basic-21.8.0.0.0-1.x86_64.rpm

# Set LD_LIBRARY_PATH
export LD_LIBRARY_PATH=/usr/lib/oracle/21/client64/lib:$LD_LIBRARY_PATH

# Tambahkan ke ~/.bashrc untuk permanent
echo 'export LD_LIBRARY_PATH=/usr/lib/oracle/21/client64/lib:$LD_LIBRARY_PATH' >> ~/.bashrc
```

### 2.4 Verifikasi Instalasi

```bash
# Test koneksi Oracle
echo 'SELECT 1 FROM DUAL;' | sqlplus username/password@//host:1521/service_name
```

## 3. Setup Development Environment

Bagian ini untuk setup aplikasi di komputer development (lokal).

### 3.1 Clone Repository

Clone artinya download kode dari Git ke komputer Anda.

```bash
# Backend
cd c:\laragon\www
git clone https://github.com/your-org/plnip-portal.git
cd plnip-portal

# Frontend (separate repository)
cd c:\laragon\www
git clone https://github.com/your-org/plnip-portal-frontend.git
cd plnip-portal-frontend
```

**Kenapa ada 2 repository terpisah?**
Karena Backend dan Frontend adalah aplikasi yang berbeda, dipisah agar:
- Lebih mudah di-manage oleh tim yang berbeda
- Frontend bisa di-deploy di server terpisah jika perlu
- Masing-masing punya dependency dan build process sendiri

### 3.2 Setup Backend (Laravel)

**Step 1: Install Dependencies**

Dependencies adalah library pihak ketiga yang aplikasi kita butuhkan untuk jalan (contoh: library untuk connect Oracle, library untuk generate PDF, dll).

```bash
cd c:\laragon\www\plnip-portal

# Install Composer dependencies
composer install
```

**Apa yang dilakukan command ini?**
- Composer akan baca file `composer.json` (daftar library yang dibutuhkan)
- Download semua library tersebut dari internet
- Simpan di folder `vendor/`

**Kalau ada error OCI8:**
Artinya Oracle Instant Client belum terinstall atau belum di-setup dengan benar. Kembali ke bagian 2 untuk install Oracle Instant Client.

**Step 2: Environment Configuration**

File .env adalah file konfigurasi rahasia yang berisi password database, API key, dll.

**Kenapa ada .env.example dan .env?**
- `.env.example` adalah template yang di-commit ke Git (tidak ada password asli)
- `.env` adalah file konfigurasi asli dengan password asli (TIDAK boleh di-commit ke Git)
- Setiap developer copy .env.example, rename jadi .env, lalu isi password sesuai environment masing-masing

```bash
# Copy .env.example ke .env
cp .env.example .env

# Generate application key
php artisan key:generate
```

**Apa itu Application Key?**
- Key unik untuk encrypt data session, cookie, password
- Laravel butuh key ini untuk security
- Command di atas akan auto-generate random key dan simpan di .env

**Step 3: Konfigurasi Database di `.env`**

Sekarang edit file `.env` dan isi dengan konfigurasi database Anda.

```env
# Application
APP_NAME="PLN IP Learning Hub"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Portal Database (Oracle)
DB_CONNECTION=oracle
DB_HOST=192.168.1.100
DB_PORT=1521
DB_DATABASE=PLNIPDB
DB_SERVICE_NAME=XEPDB1
DB_USERNAME=portal_user
DB_PASSWORD=your_password

# Moodle Database (Oracle)
MOODLE_DB_HOST=192.168.1.101
MOODLE_DB_PORT=1521
MOODLE_DB_DATABASE=MOODLEDB
MOODLE_DB_SERVICE_NAME=MOODLEPDB
MOODLE_DB_USERNAME=moodle_user
MOODLE_DB_PASSWORD=moodle_password

# Moodle Web Services
MOODLE_URL=https://moodle.plnip.co.id
MOODLE_WS_TOKEN=your_moodle_webservice_token

# ERP Integration
ERP_ENABLED=false
ERP_API_URL=https://erp.plnip.co.id/api/employees
ERP_API_KEY=your_erp_api_key
ERP_SYNC_TIMEOUT=30
ERP_SYNC_SCHEDULE=02:00

# Gemini AI
GEMINI_API_KEY=your_gemini_api_key

# Laravel Reverb (WebSocket)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# Session & Cache
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

# File Storage
FILESYSTEM_DISK=public
```

**Step 4: Database Migration**

Migration adalah cara Laravel membuat tabel di database secara otomatis (tidak perlu buat manual via SQL).

**Apa itu Migration?**
- Migration adalah file PHP yang berisi instruksi untuk buat tabel, kolom, index, dll
- Setiap migration punya timestamp (contoh: `2026_02_01_create_users_table.php`)
- Laravel track migration mana yang sudah jalan dan mana yang belum

**Kenapa pakai Migration?**
- Tidak perlu buat tabel manual satu-satu via SQL
- Semua developer punya struktur database yang sama
- Gampang rollback kalau ada salah
- Bisa di-version control (track perubahan database di Git)

```bash
# Test koneksi database (pastikan .env sudah benar)
php artisan db:show
```

**Apa yang command ini lakukan?**
Menampilkan info database yang sedang digunakan (nama database, connection type, dll). Kalau error di sini, berarti konfigurasi .env salah atau database tidak bisa diakses.

```bash
# Jalankan migration (buat semua tabel)
php artisan migrate
```

**Apa yang command ini lakukan?**
- Baca semua file migration di folder `database/migrations/`
- Buat tabel sesuai instruksi di migration (users, courses, certificates, dll)
- Simpan log di tabel `migrations` agar tidak jalan 2 kali

```bash
# Seed roles dan permissions (data awal untuk role-based access)
php artisan db:seed --class=RolePermissionSeeder
```

**Apa itu Seeding?**
Seeding adalah mengisi data awal ke database (contoh: role super-admin, admin, instructor, user).

**Kenapa perlu seeding?**
Karena aplikasi butuh data awal untuk jalan:
- Roles (super-admin, admin, instructor, user)
- Permissions (view courses, create users, dll)
- User pertama (super-admin) untuk login pertama kali

```bash
# Seed semua data (termasuk dummy user untuk testing)
php artisan db:seed
```

Command ini akan seed:
- Roles & permissions
- User dummy (superadmin@plnip.co.id, admin@plnip.co.id, dll) untuk testing
- Data dummy lainnya (optional, hanya untuk development)

**Step 5: Storage Setup**

```bash
# Buat symlink untuk public storage
php artisan storage:link

# Pastikan folder storage writable
chmod -R 775 storage bootstrap/cache
```

**Step 6: Install Laravel Reverb**

```bash
# Install Reverb
php artisan reverb:install

# Update .env dengan Reverb config (sudah dilakukan di step 3)
```

**Step 7: Start Development Server**

```bash
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Queue worker
php artisan queue:work

# Terminal 3: Laravel Reverb (WebSocket)
php artisan reverb:start

# Akses di browser: http://localhost:8000
```

### 3.3 Setup Frontend (Next.js)

**Step 1: Install Dependencies**

```bash
cd c:\laragon\www\plnip-portal-frontend

# Install npm packages
npm install

# Atau dengan yarn
yarn install
```

**Step 2: Environment Configuration**

```bash
# Copy .env.example ke .env.local
cp .env.example .env.local
```

**Edit `.env.local`:**

```env
# API Configuration
NEXT_PUBLIC_API_URL=http://localhost:8000

# WebSocket Configuration (Laravel Reverb)
NEXT_PUBLIC_WS_HOST=localhost
NEXT_PUBLIC_WS_PORT=8080
NEXT_PUBLIC_WS_KEY=your-app-key
NEXT_PUBLIC_WS_CLUSTER=mt1

# Application
NEXT_PUBLIC_APP_NAME="PLN IP Learning Hub"
```

**Step 3: Start Development Server**

```bash
# Development mode (with hot reload)
npm run dev

# Akses di browser: http://localhost:3000
```

**Step 4: Verifikasi Koneksi**

1. Buka http://localhost:3000
2. Klik "Masuk" (Login)
3. Test login dengan credentials dari seeder:
   - Email: `superadmin@plnip.co.id`
   - Password: `password`

## 4. Konfigurasi Moodle

### Konsep Integrasi Portal dengan Moodle

Sebelum mulai setup, pahami dulu bagaimana Portal dan Moodle bekerja sama:

**Skenario Normal (Tanpa Portal):**
```
User daftar manual di Moodle → Admin approve → User bisa login ke Moodle → Ikut kelas
```

**Dengan Portal (Sistem Kita):**
```
User sudah ada di Portal (dari ERP sync)
    │
    ▼
User klik "Daftar" pada kelas di Portal
    │
    ▼
Portal otomatis buat user di Moodle + enroll ke kelas
    │
    ▼
User langsung bisa akses materi di Moodle (SSO - tidak perlu login lagi)
```

**Integrasi Portal ke Moodle Butuh 2 Cara:**

1. **Web Services API** - Portal bisa perintah Moodle untuk buat user, enroll ke kelas, ambil konten
2. **Direct Database** - Portal baca langsung database Moodle untuk ambil data grades, progress, dll (lebih cepat)

### 4.1 Enable Web Services di Moodle

Web Services adalah cara aplikasi luar (Portal) untuk "ngomong" dengan Moodle via API.

**Analogi:**
Moodle seperti kantor yang tertutup. Web Services adalah pintu khusus yang dibuka agar Portal bisa masuk dan ngasih perintah ke Moodle (tapi dengan permission terbatas).

**Step 1: Akses Moodle sebagai Admin**

1. Login ke Moodle: https://moodle.plnip.co.id
2. Navigate: Site Administration → Advanced Features
3. Enable "Enable web services" (checkbox)
4. Save changes

**Kenapa harus enable ini?**
Karena default Moodle menutup akses API untuk security. Kita buka akses ini supaya Portal bisa komunikasi dengan Moodle.

**Step 2: Create Web Service User**

1. Navigate: Site Administration → Users → Accounts → Add a new user
2. Isi data:
   - Username: `portal_ws_user`
   - First name: `Portal`
   - Last name: `WebService`
   - Email: `portal-ws@plnip.co.id`
   - Password: (strong password)
3. Save

**Step 3: Create Custom Role untuk Web Service**

1. Navigate: Site Administration → Users → Permissions → Define roles
2. Add a new role:
   - Short name: `webserviceuser`
   - Full name: `Web Service User`
   - Context types: System
3. Assign capabilities:
   - `webservice/rest:use` (Allow)
   - `moodle/webservice:createtoken` (Allow)
   - `moodle/user:viewdetails` (Allow)
   - `moodle/course:view` (Allow)
   - `moodle/course:viewhiddencourses` (Allow)
   - Dan capabilities lain sesuai kebutuhan
4. Create this role

**Step 4: Assign Role ke Web Service User**

1. Navigate: Site Administration → Users → Permissions → Assign system roles
2. Pilih role "Web Service User"
3. Add user `portal_ws_user`

**Step 5: Enable REST Protocol**

1. Navigate: Site Administration → Plugins → Web services → Manage protocols
2. Enable "REST protocol"

**Step 6: Create External Service**

1. Navigate: Site Administration → Plugins → Web services → External services
2. Add new external service:
   - Name: `PLN IP Portal Service`
   - Short name: `plnip_portal`
   - Enabled: Yes
   - Authorized users only: Yes
3. Add functions:
   - `core_course_get_contents`
   - `core_user_create_users`
   - `enrol_manual_enrol_users`
   - `mod_assign_get_assignments`
   - Dan functions lain yang dibutuhkan
4. Save

**Step 7: Add Authorized User**

1. Pada external service yang baru dibuat, klik "Authorised users"
2. Add user `portal_ws_user`

**Step 8: Generate Token**

1. Navigate: Site Administration → Plugins → Web services → Manage tokens
2. Add new token:
   - User: `portal_ws_user`
   - Service: `PLN IP Portal Service`
3. Copy token yang di-generate
4. Paste ke `.env` backend: `MOODLE_WS_TOKEN=...`

### 4.2 Database Connection ke Moodle

**Step 1: Buat Database User untuk Portal**

```sql
-- Login sebagai Moodle DB admin
sqlplus moodle_admin/password@moodledb

-- Buat user baru dengan read-only access
CREATE USER portal_readonly IDENTIFIED BY "strong_password";

-- Grant privileges (read-only)
GRANT CONNECT TO portal_readonly;
GRANT SELECT ON moodle_admin.mdl_user TO portal_readonly;
GRANT SELECT ON moodle_admin.mdl_course TO portal_readonly;
GRANT SELECT ON moodle_admin.mdl_course_categories TO portal_readonly;
GRANT SELECT ON moodle_admin.mdl_enrol TO portal_readonly;
GRANT SELECT ON moodle_admin.mdl_user_enrolments TO portal_readonly;
GRANT SELECT ON moodle_admin.mdl_grade_items TO portal_readonly;
GRANT SELECT ON moodle_admin.mdl_grade_grades TO portal_readonly;
GRANT SELECT ON moodle_admin.mdl_course_modules TO portal_readonly;
-- Dan tabel lain yang diperlukan

-- Test koneksi
SELECT COUNT(*) FROM moodle_admin.mdl_user;
```

**Step 2: Update `.env` Backend**

```env
MOODLE_DB_HOST=192.168.1.101
MOODLE_DB_PORT=1521
MOODLE_DB_DATABASE=MOODLEDB
MOODLE_DB_SERVICE_NAME=MOODLEPDB
MOODLE_DB_USERNAME=portal_readonly
MOODLE_DB_PASSWORD=strong_password
```

**Step 3: Test Koneksi dari Laravel**

```bash
php artisan tinker

# Test query
DB::connection('moodle')->table('user')->count();

# Jika berhasil, akan return jumlah users
```

## 5. Konfigurasi ERP Integration

### Konsep ERP Sync

**Apa itu ERP?**
ERP (Enterprise Resource Planning) adalah sistem PLN IP yang menyimpan data karyawan (NIP, nama, email, department, jabatan, dll).

**Kenapa perlu ERP Sync?**
Bayangkan:
- PLN IP punya 1000+ karyawan
- Data mereka sudah ada di ERP
- Tidak mungkin admin input manual 1000 user ke Portal satu-satu

Solusinya: **ERP Sync** - Portal otomatis tarik data karyawan dari ERP secara berkala (contoh: setiap malam jam 2).

**Alur ERP Sync:**
```
ERP System (Master Data Karyawan)
    │
    │ (1) Portal request data via REST API
    │
    ▼
Portal menerima JSON berisi list karyawan
    │
    │ (2) Loop setiap karyawan:
    │     - Kalau NIP belum ada di Portal → Buat user baru
    │     - Kalau NIP sudah ada → Update data (nama, email, dept, dll)
    │
    ▼
Portal punya data user yang sinkron dengan ERP
    │
    └─► User bisa login ke Portal pakai email corporate mereka
```

**Penting:** Data HANYA mengalir DARI ERP KE Portal (one-way sync). Portal TIDAK mengirim data balik ke ERP.

### 5.1 Requirement ERP API

Portal butuh REST API endpoint dari tim ERP dengan spesifikasi:

**Endpoint:** `GET /api/employees`

**Authentication:** Bearer Token

**Response Format:**
```json
{
  "employees": [
    {
      "employee_id": "12345678",
      "name": "Budi Santoso",
      "email": "budi.santoso@plnip.co.id",
      "phone": "08123456789",
      "department": "Engineering",
      "position": "Senior Engineer",
      "access_group": "USER",
      "is_active": true
    },
    ...
  ]
}
```

**Field Mapping:**

| Field         | Type    | Required | Keterangan                           |
|---------------|---------|----------|--------------------------------------|
| employee_id   | string  | YES      | NIP karyawan (unique identifier)     |
| name          | string  | YES      | Nama lengkap                         |
| email         | string  | YES      | Email corporate (unique)             |
| phone         | string  | NO       | Nomor telepon                        |
| department    | string  | NO       | Departemen/Unit kerja                |
| position      | string  | NO       | Jabatan                              |
| access_group  | string  | NO       | USER, ADMIN, INSTRUCTOR, SUPER-ADMIN |
| is_active     | boolean | NO       | Status aktif (default: true)         |

### 5.2 Setup ERP Connection

**Step 1: Request API Key dari Tim ERP**

Hubungi tim ERP PLN IP untuk mendapatkan:
- API URL
- API Key (Bearer Token)

**Step 2: Konfigurasi di `.env`**

```env
ERP_ENABLED=true
ERP_API_URL=https://erp.plnip.co.id/api/employees
ERP_API_KEY=your_erp_api_key_here
ERP_SYNC_TIMEOUT=30
ERP_SYNC_SCHEDULE=02:00
ERP_MAX_RETRIES=3
ERP_RETRY_DELAY=60
ERP_VERIFY_SSL=true
```

**Step 3: Test ERP Connection**

```bash
php artisan tinker

# Test fetch employees
$service = app(\App\Services\ERPSyncService::class);
$employees = $service->syncUsers();

# Cek hasil
print_r($employees);
```

### 5.3 Setup Scheduled Sync

**Edit `app/Console/Kernel.php`:**

```php
protected function schedule(Schedule $schedule)
{
    // ERP Sync daily at 02:00 (configurable via .env)
    $schedule->call(function () {
        app(\App\Services\ERPSyncService::class)->syncUsers();
    })->dailyAt(config('erp.schedule', '02:00'))
      ->when(config('erp.enabled', false));
}
```

**Setup Cron Job (Linux):**

```bash
# Edit crontab
crontab -e

# Tambahkan line berikut
* * * * * cd /path/to/plnip-portal && php artisan schedule:run >> /dev/null 2>&1
```

**Setup Task Scheduler (Windows):**

```batch
# Buat scheduled task yang menjalankan:
php C:\laragon\www\plnip-portal\artisan schedule:run
```

## 6. Build untuk Production

### Konsep Build Production

**Development vs Production Mode:**

**Development (Lokal):**
- Laravel load config dari file .env setiap request (lambat tapi flexible)
- Error ditampilkan detail ke browser (untuk debugging)
- File view di-compile setiap kali ada perubahan
- Dependency development (contoh: debugbar) ikut ter-install

**Production (Server Live):**
- Laravel load config dari cache (super cepat)
- Error TIDAK ditampilkan detail (hanya log ke file untuk security)
- File view sudah di-compile sekali (tidak compile ulang setiap request)
- Hanya dependency production yang ter-install (hemat storage)

**Kenapa perlu "Build" untuk Production?**
Untuk optimasi performa:
- Aplikasi jalan lebih cepat (response time lebih singkat)
- Memory usage lebih kecil
- User experience lebih baik

### 6.1 Build Backend

```bash
cd c:\laragon\www\plnip-portal

# Install dependencies (production only, tanpa library development)
composer install --no-dev --optimize-autoloader
```

**Apa bedanya dengan `composer install` biasa?**
- `--no-dev` = tidak install library development (contoh: laravel/telescope, phpunit)
- `--optimize-autoloader` = buat autoloader yang lebih cepat untuk production

```bash
# Cache configuration (compile semua file config jadi 1 file)
php artisan config:cache
```

**Untuk apa?**
Tanpa cache: Laravel baca 20+ file config setiap request (lambat).
Dengan cache: Laravel baca 1 file cache (cepat).

**Catatan:** Setelah cache config, Laravel TIDAK baca .env lagi. Jadi kalau ubah .env, harus jalankan `config:cache` lagi.

```bash
# Cache routes (compile semua route jadi 1 file)
php artisan route:cache
```

**Untuk apa?**
Laravel punya ratusan route. Kalau di-cache, Laravel tidak perlu parse file routes setiap request.

```bash
# Cache views (compile semua blade template jadi PHP)
php artisan view:cache
```

**Untuk apa?**
Blade template (.blade.php) perlu di-compile jadi PHP biasa. Dengan cache ini, compile dilakukan sekali aja di awal, tidak setiap request.

```bash
# Optimize autoloader (buat class map untuk autoload yang lebih cepat)
composer dump-autoload --optimize
```

**Untuk apa?**
Autoloader adalah sistem yang load class PHP. Command ini buat "peta" semua class di aplikasi agar load lebih cepat.

### 6.2 Build Frontend

```bash
cd c:\laragon\www\plnip-portal-frontend

# Install dependencies
npm ci --production

# Build static assets
npm run build

# Test production build locally
npm start
```

### 6.3 Environment Production

**Backend `.env`:**

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://portal.plnip.co.id

# Database production
DB_CONNECTION=oracle
DB_HOST=prod-oracle-host
DB_PORT=1521
DB_DATABASE=PLNIP_PROD
DB_SERVICE_NAME=PLNIPPROD
DB_USERNAME=portal_prod
DB_PASSWORD=strong_production_password

# Session & Cache (Redis recommended)
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=redis_password
REDIS_PORT=6379

# Laravel Reverb Production
BROADCAST_CONNECTION=reverb
REVERB_HOST=portal.plnip.co.id
REVERB_PORT=443
REVERB_SCHEME=https

# ERP (enabled in production)
ERP_ENABLED=true
```

**Frontend `.env.production`:**

```env
NEXT_PUBLIC_API_URL=https://portal.plnip.co.id
NEXT_PUBLIC_WS_HOST=portal.plnip.co.id
NEXT_PUBLIC_WS_PORT=443
NEXT_PUBLIC_WS_KEY=production-app-key
```

## 7. Deployment ke Server Production

### Konsep Deployment ke Server

Setelah aplikasi jadi dan ditest di lokal, saatnya taruh di server production agar bisa diakses semua orang.

**Apa yang Terjadi Saat Deployment?**

1. **Upload Kode** - Kirim kode dari komputer developer ke server via Git
2. **Install Software** - Install PHP, Node.js, Oracle Client, dll di server
3. **Setup Environment** - Konfigurasi .env dengan password production
4. **Setup Database** - Jalankan migration untuk buat tabel
5. **Build Aplikasi** - Compile kode untuk optimasi production
6. **Setup Web Server** - Konfigurasi Nginx agar user bisa akses via domain
7. **Setup Services** - Jalankan queue worker, WebSocket, dll sebagai background service

**Server Production Seperti Apa?**

Server production adalah komputer khusus (biasanya Linux) yang:
- Nyala 24/7 dan terhubung ke internet
- Punya IP public atau domain (contoh: portal.plnip.co.id)
- Spesifikasi hardware lebih tinggi (CPU, RAM, Storage)
- Konfigurasi security yang ketat (firewall, SSL, dll)

**Tim IT PLN IP Siapkan:**
- Server Linux (Ubuntu/CentOS)
- Database Oracle (bisa di server yang sama atau terpisah)
- Domain name (plnip.co.id, portal.plnip.co.id)
- SSL Certificate (untuk https)
- Akses SSH ke server

### 7.1 Server Setup (Linux)

**Prasyarat Software yang Harus Diinstall di Server:**

| Software           | Untuk Apa                                   |
|--------------------|---------------------------------------------|
| Ubuntu 22.04 LTS   | Operating System server                     |
| Nginx              | Web server untuk handle HTTP request        |
| PHP 8.2-FPM        | PHP runtime untuk Laravel                   |
| Oracle Instant Client | Driver untuk connect ke database Oracle  |
| Node.js 18.x       | JavaScript runtime untuk Next.js            |
| Supervisor         | Manage queue worker agar jalan terus        |
| PM2                | Manage Next.js app agar jalan terus         |
| SSL Certificate    | Untuk https (Let's Encrypt gratis)          |

**Kenapa butuh semua itu?**
- Nginx = pintu masuk, terima request dari user browser, teruskan ke Laravel/Next.js
- PHP-FPM = jalankan kode Laravel
- Node.js = jalankan kode Next.js
- Supervisor & PM2 = pastikan aplikasi tidak mati kalau ada crash
- SSL = enkripsi data antara browser user dan server (aman dari hacker)

### 7.2 Deploy Backend (Laravel)

**Step 1: Upload Code ke Server**

Ada 2 cara upload kode ke server:

**Cara 1: Via Git (Recommended)**
```bash
# SSH ke server production
ssh user@server

# Masuk ke folder web root
cd /var/www

# Clone repository dari GitHub/GitLab
git clone https://github.com/your-org/plnip-portal.git
cd plnip-portal
```

**Kenapa via Git?**
- Update code tinggal `git pull` (cepat)
- Bisa track version (bisa rollback kalau ada masalah)
- Tim IT bisa lihat history perubahan

**Cara 2: Via rsync (Alternative)**
```bash
# Upload dari komputer lokal ke server via rsync
rsync -avz --exclude 'node_modules' --exclude 'vendor' \
  ./plnip-portal/ user@server:/var/www/plnip-portal/
```

**Kenapa exclude node_modules dan vendor?**
Karena folder ini isinya library dependencies yang besar (ratusan MB). Lebih cepat download ulang di server via `composer install` dan `npm install`.

**Step 2: Install Dependencies**

```bash
cd /var/www/plnip-portal

# Install Composer dependencies
composer install --no-dev --optimize-autoloader

# Set permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

**Step 3: Setup Environment**

```bash
# Copy .env
cp .env.production .env

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Seed roles (first time only)
php artisan db:seed --class=RolePermissionSeeder

# Cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage link
php artisan storage:link
```

**Step 4: Configure Nginx**

Nginx adalah web server yang terima request dari browser user dan teruskan ke Laravel.

**Analogi:**
Browser user → Nginx (resepsionis) → Laravel (backend office)

Nginx perlu dikonfigurasi supaya tau:
- Domain apa yang dilayani (contoh: portal.plnip.co.id)
- Folder mana yang jadi root aplikasi
- Request diteruskan ke PHP-FPM

**File Konfigurasi:**
Buat file `/etc/nginx/sites-available/portal.plnip.co.id`:

```nginx
# HTTP (port 80) - redirect semua request ke HTTPS
server {
    listen 80;
    server_name portal.plnip.co.id;

    # Redirect HTTP ke HTTPS untuk security
    return 301 https://$host$request_uri;
}

# Kenapa harus redirect ke HTTPS?
# Karena data login, password, dll harus dienkripsi agar tidak disadap hacker.

server {
    listen 443 ssl http2;
    server_name portal.plnip.co.id;

    # SSL Configuration
    ssl_certificate /etc/ssl/certs/portal.plnip.co.id.crt;
    ssl_certificate_key /etc/ssl/private/portal.plnip.co.id.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    root /var/www/plnip-portal/public;
    index index.php index.html;

    # Increase upload size for certificate upload
    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}

# WebSocket (Laravel Reverb)
server {
    listen 443 ssl http2;
    server_name ws.portal.plnip.co.id;

    ssl_certificate /etc/ssl/certs/portal.plnip.co.id.crt;
    ssl_certificate_key /etc/ssl/private/portal.plnip.co.id.key;

    location / {
        proxy_pass http://localhost:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

**Step 5: Setup Supervisor untuk Queue Worker**

**Apa itu Queue Worker dan Kenapa Perlu?**

Queue worker adalah background process yang jalankan pekerjaan berat secara asynchronous (tidak langsung).

**Contoh Kasus:**
```
User upload ZIP sertifikat dengan 50 PDF
    │
    ▼
Tanpa Queue: User harus tunggu 2 menit sampai semua PDF diproses (browser loading terus)
    │
    ▼
Dengan Queue: User langsung dapat response "Sedang diproses", PDF diproses di background
```

**Pekerjaan yang Pakai Queue:**
- Upload bulk certificates (ZIP dengan banyak PDF)
- Send email massal
- Generate report besar
- Sync data ERP (1000+ karyawan)

**Kenapa Butuh Supervisor?**

Queue worker harus jalan terus 24/7. Kalau di-jalankan manual (`php artisan queue:work`) lalu terminal ditutup, worker akan mati.

Supervisor adalah "babysitter" yang:
- Auto-start worker saat server boot
- Auto-restart kalau worker crash
- Manage multiple workers (parallel processing)

**Konfigurasi Supervisor:**

```bash
# Buat file konfigurasi
sudo nano /etc/supervisor/conf.d/plnip-portal-worker.conf
```

```ini
[program:plnip-portal-worker]
# Nama process
process_name=%(program_name)s_%(process_num)02d

# Command untuk jalankan worker
command=php /var/www/plnip-portal/artisan queue:work --sleep=3 --tries=3 --max-time=3600

# Auto-start saat server boot
autostart=true

# Auto-restart kalau crash
autorestart=true

# Kill group processes saat stop
stopasgroup=true
killasgroup=true

# User yang jalankan worker (harus sama dengan PHP-FPM user)
user=www-data

# Jumlah worker parallel (2 = bisa proses 2 job sekaligus)
numprocs=2

# Log output ke file
redirect_stderr=true
stdout_logfile=/var/www/plnip-portal/storage/logs/worker.log

# Grace period sebelum kill worker (1 jam)
stopwaitsecs=3600
```

```bash
# Reload supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start plnip-portal-worker:*
```

**Step 6: Setup Laravel Reverb Service**

**Apa itu Laravel Reverb dan Untuk Apa?**

Laravel Reverb adalah WebSocket server untuk fitur real-time (update otomatis tanpa refresh browser).

**Analogi:**
- **HTTP biasa** = seperti WhatsApp Web yang harus refresh browser untuk lihat pesan baru
- **WebSocket** = seperti WhatsApp app yang pesan baru langsung muncul tanpa refresh

**Fitur Real-time di Portal:**
- Chat kelas (pesan baru langsung muncul)
- Notifikasi (bell icon update otomatis kalau ada notif baru)
- Status online user (lihat siapa yang lagi online)
- Progress upload (ZIP sertifikat sedang diproses)

**Kenapa Butuh Service?**

WebSocket server harus jalan terus 24/7. Systemd service memastikan:
- Auto-start saat server boot
- Auto-restart kalau crash
- Log error ke journal

**Konfigurasi Systemd Service:**

```bash
# Buat file service
sudo nano /etc/systemd/system/laravel-reverb.service
```

```ini
[Unit]
# Deskripsi service
Description=Laravel Reverb WebSocket Server

# Tunggu network ready dulu sebelum start
After=network.target

[Service]
# Type simple = process jalan di foreground
Type=simple

# User yang jalankan service
User=www-data

# Working directory
WorkingDirectory=/var/www/plnip-portal

# Command untuk start Reverb
ExecStart=/usr/bin/php /var/www/plnip-portal/artisan reverb:start

# Auto-restart kalau crash
Restart=always

# Tunggu 5 detik sebelum restart (prevent restart loop)
RestartSec=5

[Install]
# Auto-start saat boot
WantedBy=multi-user.target
```

```bash
# Enable and start service
sudo systemctl daemon-reload
sudo systemctl enable laravel-reverb
sudo systemctl start laravel-reverb
```

### 7.3 Deploy Frontend (Next.js)

**Step 1: Upload Code**

```bash
ssh user@server
cd /var/www
git clone https://github.com/your-org/plnip-portal-frontend.git
cd plnip-portal-frontend
```

**Step 2: Build Application**

```bash
# Install dependencies
npm ci --production

# Build
npm run build

# Test locally
npm start
```

**Step 3: Setup PM2 (Process Manager)**

**Apa itu PM2 dan Untuk Apa?**

PM2 adalah process manager untuk aplikasi Node.js (seperti Supervisor untuk PHP).

**Kenapa Next.js Butuh PM2?**

Kalau jalankan Next.js manual (`npm start`) lalu terminal ditutup, aplikasi akan mati.

PM2 memastikan:
- Next.js jalan terus 24/7
- Auto-restart kalau crash
- Auto-start saat server boot
- Monitor memory usage
- Load balancing (kalau butuh multiple instance)

```bash
# Install PM2 secara global
sudo npm install -g pm2

# Start Next.js app dengan PM2
pm2 start npm --name "plnip-frontend" -- start
```

**Apa yang command ini lakukan?**
- `pm2 start npm` = jalankan command npm via PM2
- `--name "plnip-frontend"` = kasih nama process (untuk identify nanti)
- `-- start` = argument yang diteruskan ke npm (jalankan `npm start`)

```bash
# Setup auto-start saat server boot
pm2 startup
```

Command ini akan generate script untuk auto-start PM2 saat server boot. Copy dan jalankan script yang muncul di output.

```bash
# Save konfigurasi PM2 saat ini
pm2 save
```

Simpan list aplikasi yang sedang jalan agar PM2 restart aplikasi yang sama saat server reboot.

**Useful PM2 Commands:**
```bash
pm2 list                # List semua aplikasi yang jalan
pm2 logs plnip-frontend # Lihat logs real-time
pm2 restart plnip-frontend # Restart aplikasi
pm2 stop plnip-frontend # Stop aplikasi
pm2 delete plnip-frontend # Hapus dari PM2
pm2 monit               # Monitor CPU & memory usage
```

**Step 4: Configure Nginx for Frontend**

```nginx
server {
    listen 80;
    server_name plnip.co.id www.plnip.co.id;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name plnip.co.id www.plnip.co.id;

    ssl_certificate /etc/ssl/certs/plnip.co.id.crt;
    ssl_certificate_key /etc/ssl/private/plnip.co.id.key;

    location / {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

## 8. Post-Deployment

Setelah deployment selesai, WAJIB test untuk pastikan semua jalan lancar sebelum inform user.

### 8.1 Verifikasi Deployment

**Checklist Testing Deployment:**

**1. Test Backend API**
```bash
# Test health check endpoint
curl https://portal.plnip.co.id/api/health

# Response yang benar:
# {"status": "ok", "database": "connected"}
```

**Kalau error:**
- 502 Bad Gateway = PHP-FPM tidak jalan atau Nginx salah konfigurasi
- 500 Internal Server Error = Laravel error, cek log di `storage/logs/laravel.log`
- Connection refused = Nginx tidak jalan

**2. Test Frontend**
```bash
# Test frontend homepage
curl https://plnip.co.id

# Harus return HTML (tidak boleh error)
```

**Kalau error:**
- 502 Bad Gateway = Next.js tidak jalan atau PM2 belum start
- Connection refused = Nginx tidak jalan

**3. Test WebSocket**
```bash
# Test Laravel Reverb
curl https://ws.portal.plnip.co.id

# Harus return response dari Reverb (tidak boleh 502)
```

**4. Test Queue Worker**
```bash
# Check status worker
sudo supervisorctl status plnip-portal-worker:*

# Output yang benar:
# plnip-portal-worker:plnip-portal-worker_00   RUNNING   pid 1234, uptime 0:05:00
# plnip-portal-worker:plnip-portal-worker_01   RUNNING   pid 1235, uptime 0:05:00
```

**Kalau status FATAL atau EXITED:**
- Cek log: `tail -f /var/www/plnip-portal/storage/logs/worker.log`
- Biasanya error koneksi database atau permission issue

**5. Test Laravel Reverb**
```bash
# Check status service
sudo systemctl status laravel-reverb

# Output yang benar:
# Active: active (running) since ...
```

**Kalau status failed:**
- Cek log: `sudo journalctl -u laravel-reverb -n 50`
- Biasanya port 8080 sudah dipakai atau config salah

### 8.2 Setup SSL Certificate (Let's Encrypt)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Generate certificate
sudo certbot --nginx -d plnip.co.id -d www.plnip.co.id -d portal.plnip.co.id -d ws.portal.plnip.co.id

# Auto-renewal (cron)
sudo crontab -e
# Add:
0 0 1 * * /usr/bin/certbot renew --quiet
```

### 8.3 Setup Monitoring

**Install monitoring tools:**

```bash
# Application monitoring
composer require laravel/telescope --dev

# Server monitoring
sudo apt install htop iotop
```

### 8.4 Backup Setup

**Kenapa Backup Sangat Penting?**

Bayangkan skenario terburuk:
- Server crash dan hard disk rusak
- Database korup karena bug
- Hacker masuk dan hapus data
- User tidak sengaja delete data penting

Tanpa backup = SEMUA DATA HILANG (database user, sertifikat, dll) dan tidak bisa dikembalikan.

Dengan backup = bisa restore data ke kondisi sebelum masalah terjadi.

**Apa yang Perlu Di-backup?**

1. **Database** - Data user, kelas, enrollment, sertifikat (paling penting)
2. **File Sertifikat** - PDF sertifikat yang di-upload user
3. **Konfigurasi** - File .env dan config (untuk restore cepat)
4. **Kode** - Sudah ada di Git, tidak perlu backup terpisah

**Strategi Backup:**

- **Frequency:** Setiap hari (automated via cron)
- **Time:** Jam 3 pagi (saat traffic rendah)
- **Retention:** Simpan backup 7 hari terakhir (lebih lama = lebih banyak storage)
- **Location:** Simpan di server terpisah (kalau server utama rusak, backup tetap aman)

**Script Backup Otomatis:**

```bash
# Buat script backup
sudo nano /usr/local/bin/backup-plnip.sh
```

```bash
#!/bin/bash
# Script backup otomatis untuk PLN IP Learning Hub

# Timestamp untuk nama file backup
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Folder untuk simpan backup (pastikan ada disk space cukup)
BACKUP_DIR="/backup/plnip"

# Setup Oracle environment
ORACLE_HOME=/usr/lib/oracle/21/client64
export ORACLE_HOME
export PATH=$ORACLE_HOME/bin:$PATH

echo "=== Backup started at $(date) ==="

# 1. Backup Database Oracle (full export)
echo "Backing up database..."
exp userid=portal_prod/password@PLNIP_PROD \
    file=$BACKUP_DIR/db_backup_$TIMESTAMP.dmp \
    log=$BACKUP_DIR/db_backup_$TIMESTAMP.log \
    full=y

# Check kalau backup database berhasil
if [ $? -eq 0 ]; then
    echo "Database backup SUCCESS"
else
    echo "Database backup FAILED!" >&2
    exit 1
fi

# 2. Backup File Sertifikat (compress jadi tar.gz)
echo "Backing up certificates..."
tar -czf $BACKUP_DIR/files_backup_$TIMESTAMP.tar.gz \
    /var/www/plnip-portal/storage/app/public/certificates

# Check kalau backup file berhasil
if [ $? -eq 0 ]; then
    echo "Files backup SUCCESS"
else
    echo "Files backup FAILED!" >&2
fi

# 3. Hapus backup lama (lebih dari 7 hari) untuk hemat storage
echo "Cleaning up old backups (>7 days)..."
find $BACKUP_DIR -name "*.dmp" -mtime +7 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete
find $BACKUP_DIR -name "*.log" -mtime +7 -delete

echo "=== Backup completed at $(date) ==="
echo "Backup files:"
ls -lh $BACKUP_DIR/*$TIMESTAMP*
```

**Apa yang script ini lakukan?**
1. Export semua data dari database Oracle ke file .dmp
2. Compress semua PDF sertifikat ke file .tar.gz
3. Hapus backup yang lebih dari 7 hari (hemat storage)
4. Log semua proses (success atau error)

```bash
# Make executable
sudo chmod +x /usr/local/bin/backup-plnip.sh

# Schedule daily backup at 03:00
sudo crontab -e
# Add:
0 3 * * * /usr/local/bin/backup-plnip.sh >> /var/log/plnip-backup.log 2>&1
```

## 9. Troubleshooting Common Issues

Bagian ini berisi solusi untuk masalah yang sering terjadi saat deployment.

### 9.1 Oracle Connection Error

**Error yang Muncul:**
```
ORA-12154: TNS:could not resolve the connect identifier
```

**Artinya Apa?**
Laravel tidak bisa connect ke database Oracle karena tidak menemukan konfigurasi koneksi.

**Analogi:**
Seperti mau telepon seseorang tapi tidak tau nomor teleponnya.

**Penyebab:**
1. Service name di .env salah
2. tnsnames.ora tidak ada atau salah
3. TNS_ADMIN environment variable tidak di-set

**Cara Cek & Fix:**

**Step 1: Cek environment variable**
```bash
# Check apakah TNS_ADMIN sudah di-set
echo $TNS_ADMIN

# Kalau kosong, berarti belum di-set
# Set manual:
export TNS_ADMIN=/usr/lib/oracle/21/client64/network/admin
```

**Step 2: Cek file tnsnames.ora**
```bash
# Lihat isi file tnsnames.ora
cat $ORACLE_HOME/network/admin/tnsnames.ora

# File ini harus berisi entry untuk service_name yang ada di .env
# Contoh:
# PLNIP_PROD =
#   (DESCRIPTION =
#     (ADDRESS = (PROTOCOL = TCP)(HOST = 192.168.1.100)(PORT = 1521))
#     (CONNECT_DATA =
#       (SERVICE_NAME = PLNIPPROD)
#     )
#   )
```

**Step 3: Test koneksi manual**
```bash
# Test via sqlplus
sqlplus username/password@service_name

# Kalau berhasil connect = konfigurasi benar
# Kalau error = cek kembali host, port, service_name
```

### 9.2 PHP OCI8 Extension Not Found

**Error:** `Call to undefined function oci_connect()`

**Solution:**
```bash
# Install OCI8 via PECL
sudo pecl install oci8

# Add to php.ini
echo "extension=oci8.so" | sudo tee -a /etc/php/8.2/cli/php.ini
echo "extension=oci8.so" | sudo tee -a /etc/php/8.2/fpm/php.ini

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

### 9.3 Permission Denied on Storage

**Error yang Muncul:**
```
file_put_contents(/var/www/plnip-portal/storage/logs/laravel.log): failed to open stream: Permission denied
```

**Artinya Apa?**
Laravel tidak punya izin untuk menulis file ke folder storage (untuk log, cache, upload, dll).

**Analogi:**
Seperti mau nulis di buku tapi tangannya terikat. Laravel mau nulis log tapi tidak punya permission.

**Kenapa Terjadi?**

Di Linux, setiap file/folder punya owner dan permission. PHP-FPM jalan sebagai user `www-data`, jadi folder storage harus:
- Owned by www-data
- Permission 775 (owner+group bisa read/write/execute)

**Cara Fix:**

```bash
cd /var/www/plnip-portal

# Ubah owner folder storage jadi www-data
sudo chown -R www-data:www-data storage bootstrap/cache
```

**Apa yang command ini lakukan?**
- `chown` = change owner
- `-R` = recursive (semua subfolder juga)
- `www-data:www-data` = user:group

```bash
# Ubah permission jadi 775
sudo chmod -R 775 storage bootstrap/cache
```

**Apa arti permission 775?**
- 7 (owner) = read(4) + write(2) + execute(1) = full access
- 7 (group) = read(4) + write(2) + execute(1) = full access
- 5 (others) = read(4) + execute(1) = read only

**Khusus untuk Server dengan SELinux:**
```bash
# SELinux adalah security layer tambahan di CentOS/RHEL
# Kalau enabled, perlu set context type:
sudo chcon -R -t httpd_sys_rw_content_t storage

# Atau disable SELinux (tidak recommended untuk production)
sudo setenforce 0
```

**Cara Cek Kalau Sudah Fix:**
```bash
# Cek owner dan permission
ls -la storage/

# Harus terlihat:
# drwxrwxr-x www-data www-data ... storage/
```

### 9.4 Moodle Connection Timeout

**Error:** `Connection timeout when connecting to Moodle`

**Solution:**
```bash
# Check firewall
sudo ufw status

# Allow outbound connections
sudo ufw allow out 1521/tcp

# Check network connectivity
telnet moodle-db-host 1521

# Increase timeout in .env
MOODLE_DB_TIMEOUT=60
```

### 9.5 Queue Worker Not Processing Jobs

**Solution:**
```bash
# Check supervisor status
sudo supervisorctl status plnip-portal-worker:*

# Restart workers
sudo supervisorctl restart plnip-portal-worker:*

# Check logs
tail -f /var/www/plnip-portal/storage/logs/worker.log

# Clear failed jobs
php artisan queue:flush
```

### 9.6 Laravel Reverb Not Starting

**Solution:**
```bash
# Check port availability
sudo netstat -tuln | grep 8080

# Check service status
sudo systemctl status laravel-reverb

# View logs
sudo journalctl -u laravel-reverb -f

# Restart service
sudo systemctl restart laravel-reverb
```

### 9.7 Next.js Build Error

**Error:** `Error: ENOSPC: System limit for number of file watchers reached`

**Solution:**
```bash
# Increase inotify watchers
echo fs.inotify.max_user_watches=524288 | sudo tee -a /etc/sysctl.conf
sudo sysctl -p

# Restart PM2
pm2 restart plnip-frontend
```

## 10. Update dan Maintenance

### 10.1 Update Application

**Backend:**
```bash
cd /var/www/plnip-portal

# Pull latest code
git pull origin main

# Install new dependencies
composer install --no-dev --optimize-autoloader

# Run new migrations
php artisan migrate --force

# Clear cache
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue workers
sudo supervisorctl restart plnip-portal-worker:*

# Restart Laravel Reverb
sudo systemctl restart laravel-reverb
```

**Frontend:**
```bash
cd /var/www/plnip-portal-frontend

# Pull latest code
git pull origin main

# Install new dependencies
npm ci --production

# Build
npm run build

# Restart PM2
pm2 restart plnip-frontend
```

### 10.2 Rollback Strategy

**Persiapan:**
```bash
# Tag current version sebelum deploy
git tag -a v1.0.0 -m "Release v1.0.0"
git push origin v1.0.0
```

**Rollback:**
```bash
# Checkout previous tag
git checkout v0.9.9

# Install dependencies
composer install --no-dev --optimize-autoloader

# Rollback migration
php artisan migrate:rollback --step=1

# Clear cache
php artisan cache:clear
php artisan config:cache
```

### 10.3 Database Migration Best Practice

```bash
# ALWAYS backup before migrate
php artisan db:backup  # Custom command if available

# Or manual backup
exp userid=portal_prod/password@PLNIP_PROD \
    file=/backup/pre_migration_$(date +%Y%m%d).dmp \
    full=y

# Test migration in staging first
php artisan migrate --pretend

# Run migration
php artisan migrate --force

# If error, rollback immediately
php artisan migrate:rollback --step=1
```

## 11. Checklist Deployment

Gunakan checklist ini setiap kali deployment untuk pastikan tidak ada yang terlewat.

### Pre-Deployment (Sebelum Deploy)

**Penting: WAJIB Backup Dulu!**
- [ ] Backup database production (kalau ada masalah, bisa restore)
- [ ] Backup file sertifikat (storage/app/public/certificates)
- [ ] Backup file .env (untuk referensi)

**Testing di Staging:**
- [ ] Test di staging environment (jangan langsung production!)
- [ ] Test semua fitur kritis (login, enrollment, upload sertifikat, dll)
- [ ] Load testing (simulasi banyak user bersamaan)

**Koordinasi Tim:**
- [ ] Code review approved oleh lead developer
- [ ] Tag release version di Git (contoh: v1.2.0)
- [ ] Update changelog dan documentation
- [ ] Inform user tentang maintenance window (contoh: "Server maintenance besok jam 02:00-04:00")
- [ ] Siapkan rollback plan (kalau ada masalah, bisa kembali ke versi lama)

---

### Deployment (Saat Deploy)

**Update Code:**
- [ ] SSH ke server production
- [ ] Pull latest code dari Git (`git pull origin main`)
- [ ] Check branch dan commit hash (`git log -1`)

**Update Dependencies:**
- [ ] Install backend dependencies (`composer install --no-dev --optimize-autoloader`)
- [ ] Install frontend dependencies (`npm ci --production`)

**Konfigurasi:**
- [ ] Update .env kalau ada perubahan (database, API key, dll)
- [ ] Generate app key kalau fresh install (`php artisan key:generate`)

**Database:**
- [ ] Backup database sekali lagi sebelum migration
- [ ] Dry-run migration (`php artisan migrate --pretend`) untuk cek SQL yang akan jalan
- [ ] Run migration (`php artisan migrate --force`)
- [ ] Check kalau migration success (tidak ada error)

**Build & Cache:**
- [ ] Clear semua cache (`php artisan cache:clear`)
- [ ] Build frontend (`npm run build`)
- [ ] Cache config (`php artisan config:cache`)
- [ ] Cache routes (`php artisan route:cache`)
- [ ] Cache views (`php artisan view:cache`)

**Restart Services:**
- [ ] Restart PHP-FPM (`sudo systemctl restart php8.2-fpm`)
- [ ] Restart Nginx (`sudo systemctl restart nginx`)
- [ ] Restart queue workers (`sudo supervisorctl restart plnip-portal-worker:*`)
- [ ] Restart Laravel Reverb (`sudo systemctl restart laravel-reverb`)
- [ ] Restart Next.js (`pm2 restart plnip-frontend`)

**Smoke Tests (Test Cepat):**
- [ ] Curl backend health check (`curl https://portal.plnip.co.id/api/health`)
- [ ] Curl frontend (`curl https://plnip.co.id`)
- [ ] Check semua services running (supervisor, reverb, pm2)

---

### Post-Deployment (Setelah Deploy)

**Verifikasi Teknis:**
- [ ] Aplikasi bisa diakses dari browser
- [ ] Test login dengan akun test (jangan pakai akun production)
- [ ] Test API endpoints utama (GET /api/courses, GET /api/users, dll)
- [ ] Test upload sertifikat (individual dan bulk)
- [ ] Test download sertifikat

**Verifikasi Real-time:**
- [ ] Test chat kelas (kirim pesan, muncul real-time)
- [ ] Test notifikasi (bell icon update otomatis)
- [ ] Check queue processing (upload ZIP sertifikat, harus diproses)

**Monitoring:**
- [ ] Monitor error logs (`tail -f storage/logs/laravel.log`)
- [ ] Monitor Nginx logs (`tail -f /var/log/nginx/error.log`)
- [ ] Monitor worker logs (`tail -f storage/logs/worker.log`)
- [ ] Check CPU & memory usage (`htop`)

**Testing User:**
- [ ] Minta 2-3 user untuk test login dan akses kelas
- [ ] Confirm tidak ada error atau complain
- [ ] Monitor logs saat user sedang pakai aplikasi

**ERP Sync (Kalau Enabled):**
- [ ] Test manual sync (`php artisan erp:sync` atau via admin panel)
- [ ] Check log ERP sync (created/updated/skipped users)
- [ ] Verify data user ter-sync dengan benar

**Inform Stakeholders:**
- [ ] Kasih tau tim IT bahwa deployment selesai
- [ ] Kasih tau user bahwa maintenance selesai dan aplikasi sudah bisa diakses
- [ ] Update status di monitoring dashboard (kalau ada)

---

### Kalau Ada Masalah (Rollback)

Kalau deployment error dan tidak bisa fix cepat:

1. **Rollback Code:**
   ```bash
   git checkout v1.1.0  # Version sebelumnya
   composer install --no-dev
   npm ci && npm run build
   ```

2. **Rollback Database:**
   ```bash
   php artisan migrate:rollback --step=1
   # Atau restore dari backup
   ```

3. **Restart Services:**
   ```bash
   sudo systemctl restart php8.2-fpm nginx
   sudo supervisorctl restart plnip-portal-worker:*
   pm2 restart plnip-frontend
   ```

4. **Inform Stakeholders:**
   - Kasih tau bahwa ada masalah dan sedang di-rollback
   - Kasih estimate kapan bisa fix dan deploy ulang

## 12. Kesimpulan

### Ringkasan Deployment Flow

```
Development (Lokal)
    │
    │ Kode selesai & test OK
    │
    ▼
Git Repository
    │
    │ Push code
    │
    ▼
Staging Server (Testing)
    │
    │ QA test semua fitur
    │
    ▼
Production Server (Live)
    │
    │ Backup → Deploy → Test → Monitor
    │
    ▼
User bisa akses aplikasi
```

### Tips Penting

1. **Selalu Backup Sebelum Deploy**
   - Database bisa korup
   - Migration bisa error
   - Backup adalah safety net Anda

2. **Test di Staging Dulu**
   - Jangan pernah deploy langsung ke production tanpa test
   - Staging adalah copy production untuk test aman

3. **Deploy di Off-Peak Hours**
   - Deploy jam 2-4 pagi saat user sedikit
   - Kalau ada masalah, tidak banyak user yang terpengaruh

4. **Monitor Setelah Deploy**
   - Pantau logs minimal 1 jam setelah deploy
   - Response cepat kalau ada error

5. **Dokumentasi Perubahan**
   - Catat apa yang di-deploy (fitur baru, bug fix, dll)
   - Buat changelog untuk track history

### Kontak Darurat

Kalau ada masalah kritis saat deployment:

1. **Cek logs dulu** - 90% masalah bisa dideteksi dari logs
2. **Rollback kalau perlu** - Jangan paksa fix kalau sudah stuck
3. **Hubungi tim** - Jangan solve sendiri kalau tidak yakin

**Tim Development PLN IP Learning Hub:**
- Lead Developer: [kontak]
- DevOps Engineer: [kontak]
- Database Admin: [kontak]

### Dokumentasi Lanjutan

Untuk info lebih detail:
- **Arsitektur Sistem:** `docs/01-ARSITEKTUR-SISTEM.md`
- **API Reference:** `docs/03-API-REFERENCE.md`
- **Maintenance Guide:** `docs/04-MAINTENANCE.md`
