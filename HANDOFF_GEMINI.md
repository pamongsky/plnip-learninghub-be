# 🤖 Serah Terima: Claude → Gemini

**Tanggal:** 17 Januari 2026
**Proyek:** PLN IP Learning Hub
**Status:** Hari 2 - Autentikasi & Dashboard Berfungsi ✅

---

## 🔥 DEADLINE KRITIS!

**PART 2 HARUS SELESAI:** 3 HARI LAGI (20 Januari 2026)
**Yang Harus Diselesaikan:** Manajemen kursus, enrollment, katalog, tracking progress
**Kualitas:** TINGGI - Hasil harus memuaskan, no compromises!

---

## 📂 PENTING: Proyek Punya 2 Folder Terpisah

**Backend (Laravel):**
- Lokasi: `c:\laragon\www\plnip-portal\`
- Isi: API, database, admin panel (Filament)

**Frontend (Next.js):**
- Lokasi: `c:\laragon\www\plnip-portal-frontend\`
- Isi: Landing page, login, dashboard, semua UI

**Kalau user bilang:**
- "backend" atau "Laravel" atau "API" → Pakai `c:\laragon\www\plnip-portal\`
- "frontend" atau "Next.js" atau "UI" → Pakai `c:\laragon\www\plnip-portal-frontend\`

**SELALU pakai FULL PATH saat referensi file!**

---

## 🗄️ DATABASE: Oracle (PENTING!)

**Setup Saat Ini:**
- ✅ Menggunakan **Oracle Database** (sudah dikonfigurasi di .env)
- ✅ Package: `yajra/laravel-oci8`
- ✅ Connection: `DB_CONNECTION=oracle`

**Untuk Migrations:**
```php
// ORACLE COMPATIBLE - Laravel otomatis handle syntax Oracle
$table->string('name');        // Jadi VARCHAR2 di Oracle
$table->text('description');   // Jadi CLOB di Oracle
$table->id();                  // Jadi NUMBER dengan sequence
$table->timestamps();          // OK

// HINDARI:
// - MySQL specific (ENUM, JSON columns)
// - Pakai string + validation untuk replace ENUM
```

**Moodle Integration:**
- ❌ Moodle v5 TIDAK support Oracle
- ⏳ User akan presentasi Senin (20 Jan) untuk solusi
- ✅ Untuk sekarang: Build fitur standalone, jangan tunggu Moodle
- ✅ Fokus Part 2, Moodle urusan nanti

---

## ✅ Yang Sudah Jalan

**Backend:**
- Laravel 12 + Oracle DB
- Filament 3.3 admin panel
- Sanctum API authentication
- AnnouncementResource (CRUD)
- AuthController, DashboardController, AnnouncementController

**Frontend:**
- Next.js 16.1.3 (Turbopack)
- Landing page dengan PLN branding
- Login page (redirect berdasarkan role)
- Employee dashboard (tampilkan announcements)
- AuthContext (state management)

**Flow Login:**
1. User buka http://localhost:3000 → Landing page
2. Klik "Login" → Form login
3. Submit → API `/api/login`
4. Redirect berdasarkan role:
   - Admin/Super-admin → http://127.0.0.1:8000/admin (Filament)
   - Employee/Instructor → http://localhost:3000/dashboard (Next.js)

---

## 🎯 TODO Part 2 (3 Hari!)

**Backend Laravel (`c:\laragon\www\plnip-portal\`):**
- [x] Category model + migration (SUDAH MULAI)
- [ ] Course model + migration
- [ ] Lesson model + migration
- [ ] Enrollment model + migration
- [ ] CategoryResource di Filament
- [ ] CourseResource di Filament (CRUD lengkap)
- [ ] Course API endpoints (list, detail, enroll, my-courses)
- [ ] Seeders (minimal 5 kategori, 10 kursus)

**Frontend Next.js (`c:\laragon\www\plnip-portal-frontend\`):**
- [ ] Halaman katalog kursus (grid, filter, search)
- [ ] Halaman detail kursus (info, enroll button)
- [ ] Halaman "Pembelajaran Saya" (enrolled courses + progress)
- [ ] Component filter kategori
- [ ] Component search
- [ ] Loading states & error handling

**Testing:**
- [ ] Responsive (mobile/tablet)
- [ ] Full flow: browse → detail → enroll → my learning
- [ ] Admin bisa CRUD courses via Filament

---

## 🎨 Warna Brand PLN

```javascript
pln: {
  primary: '#035B71',   // Biru utama
  light: '#00A2B9',     // Biru muda
  dark: '#024656',      // Biru gelap
  50: '#E6F4F7',
  100: '#CCE9EF',
  200: '#99D3DF',
  300: '#66BDCF',
  400: '#33A7BF',
  500: '#00A2B9',
  600: '#008294',
  700: '#00616F',
  800: '#00414A',
  900: '#002025',
}
```

**Sudah dikonfigurasi di:** `c:\laragon\www\plnip-portal-frontend\tailwind.config.ts`

---

## 📋 Database Schema (Rencana)

### categories
```
id, name, slug, description, icon, created_at, updated_at
```

### courses
```
id, category_id, instructor_id, title, slug, description,
thumbnail, difficulty (string), duration (integer),
is_published (boolean), created_at, updated_at
```

### lessons
```
id, course_id, title, content, video_url, document_url,
order (integer), duration, created_at, updated_at
```

### enrollments
```
id, user_id, course_id, enrolled_at, completed_at,
progress (decimal 0-100), last_accessed_at,
UNIQUE(user_id, course_id)
```

---

## 🚀 Cara Mulai (Untuk Gemini)

**Baca file-file ini dulu:**
1. `c:\laragon\www\plnip-portal\PROJECT_CONTEXT.md` - Konteks lengkap
2. `c:\laragon\www\plnip-portal\MILESTONE_PART2.md` - Detail tasks Part 2
3. File ini - Quick reference

**Lalu tanya user:** "Apa yang mau dikerjakan dulu?"

**Kalau user bilang "lanjutin dari Claude":**
- Category migration sudah dibuat (belum di-migrate)
- Selanjutnya: Course model + migration
- Lalu: CourseResource Filament
- Prioritas: Backend dulu, frontend menyusul

---

## 🔧 Command Berguna

```bash
# Backend (Laravel)
cd c:\laragon\www\plnip-portal
php artisan serve                    # Start di port 8000
php artisan migrate                  # Jalankan migrations
php artisan make:model Course -m     # Buat model + migration
php artisan make:resource CourseResource --model=Course  # Filament resource
php artisan db:seed                  # Jalankan seeders

# Frontend (Next.js)
cd c:\laragon\www\plnip-portal-frontend
npm run dev                          # Start di port 3000
npm install                          # Install dependencies

# Test API
curl -X POST http://127.0.0.1:8000/api/login \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"employee@plnip.local\",\"password\":\"Employee123!\"}"
```

---

## 🧪 Test Credentials

```
Super Admin:
- Email: superadmin@plnip.local
- Password: Admin123!

Employee:
- Email: employee@plnip.local
- Password: Employee123!
```

---

## ⚠️ Hal Penting untuk Gemini

1. **Selalu baca file dulu** sebelum edit (pakai Read tool)
2. **Pakai FULL PATH** saat referensi file
3. **Oracle DB** = pakai string untuk enum, hindari MySQL-specific syntax
4. **CORS sudah dikonfigurasi** di `config/cors.php` (localhost:3000)
5. **Warna PLN** sudah di tailwind config, tinggal pakai
6. **Kualitas tinggi** = no shortcuts, hasil harus memuaskan!

---

## 🐛 Kalau Ada Masalah

**Login failed:**
- Cek CORS config
- Cek Sanctum stateful domains
- Test API dengan curl

**Migration error:**
- Oracle syntax berbeda, pakai `string()` bukan `enum()`
- Clear cache: `php artisan config:clear`

**Frontend error:**
- Cek backend jalan di port 8000
- Cek .env.local punya NEXT_PUBLIC_API_URL

---

## 📞 Komunikasi dengan User

**Format Laporan Harian:**
```
SELESAI Kemarin:
- [Task 1]
- [Task 2]

DIKERJAKAN Hari Ini:
- [Task 3]
- [Task 4]

BLOCKER:
- Tidak ada / [Deskripsi masalah]
```

**Kalau stuck > 30 menit:**
- LANGSUNG tanya user
- Kasih konteks (sudah coba apa, error apa)

---

## ✅ Kriteria Sukses Part 2

**Part 2 SELESAI kalau:**
- [x] Admin bisa buat 10+ courses via Filament
- [x] Employee bisa browse catalog dengan filter/search
- [x] Employee bisa lihat detail kursus
- [x] Employee bisa enroll kursus
- [x] Employee bisa lihat kursus di "Pembelajaran Saya"
- [x] Progress tracking jalan
- [x] Responsive semua device
- [x] No critical bugs
- [x] UI terlihat profesional
- [x] Stakeholder bilang "WOW!" 🎉

---

**Mindset:** FIGHT FOR QUALITY! 🔥
**Deadline:** 3 HARI
**Target:** HASIL MEMUASKAN!

Mari kita buat PLN bangga! 💪
