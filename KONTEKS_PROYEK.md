# PLN Indonesia Power Learning Hub - Konteks Proyek

**Terakhir Update:** 17 Januari 2026
**Developer:** Intern PLN IP
**AI Assistant:** Claude Sonnet 4.5 → Gemini Code Assist

---

## ⚠️ PENTING UNTUK GEMINI: Proyek Punya 2 Folder Terpisah

**Backend (Laravel):**
- Lokasi: `c:\laragon\www\plnip-portal\`
- Isi: API, database Oracle, admin panel Filament

**Frontend (Next.js):**
- Lokasi: `c:\laragon\www\plnip-portal-frontend\`
- Isi: Landing page, login, dashboard, semua UI

**Kalau user bilang:**
- "backend" / "Laravel" / "API" → `c:\laragon\www\plnip-portal\`
- "frontend" / "Next.js" / "UI" → `c:\laragon\www\plnip-portal-frontend\`

**SELALU pakai FULL PATH!**

---

## 🎯 Ringkasan Proyek

**Nama:** PLN IP Learning Hub Portal
**Jenis:** Learning Management System (LMS)
**Arsitektur:** Decoupled (Laravel Backend + Next.js Frontend)
**Status:** Hari 2 - Autentikasi & Dashboard Selesai

**Tech Stack:**
- Backend: Laravel 12 + Oracle DB + Filament 3.3 + Sanctum
- Frontend: Next.js 16.1.3 + TypeScript + Tailwind CSS
- Database: Oracle (yajra/laravel-oci8)

---

## 🗄️ Database: Oracle (KRITIS!)

- ✅ Pakai Oracle Database (sudah dikonfigurasi)
- ✅ Package: yajra/laravel-oci8
- ✅ Connection: `DB_CONNECTION=oracle`

**Migrations Oracle:**
```php
$table->string('name');    // OK - jadi VARCHAR2
$table->text('content');   // OK - jadi CLOB
$table->id();              // OK - jadi NUMBER + sequence
// HINDARI: enum(), json() - pakai string + validation
```

**Moodle:**
- Moodle v5 TIDAK support Oracle
- User presentasi Senin 20 Jan untuk solusi
- Untuk sekarang: Build standalone, ignore Moodle

---

## ✅ Yang Sudah Jalan

**Backend:**
- Laravel 12 + Oracle + Filament + Sanctum API
- Models: User, Announcement
- Controllers: Auth, Dashboard, Announcement
- CORS dikonfigurasi (localhost:3000)

**Frontend:**
- Next.js 16 + Landing page + Login + Dashboard
- AuthContext (state management + role redirect)
- Axios (Bearer token interceptor)

**Flow Login:**
1. Buka localhost:3000 → Landing page
2. Klik Login → Form login
3. Submit → POST /api/login
4. Redirect:
   - Admin → http://127.0.0.1:8000/admin (Filament)
   - Employee → http://localhost:3000/dashboard (Next.js)

---

## 🎯 TODO Part 2 (3 Hari - Deadline 20 Jan!)

**Backend:**
- [x] Category migration (SUDAH DIBUAT)
- [ ] Course model + migration
- [ ] Lesson model + migration
- [ ] Enrollment model + migration
- [ ] CategoryResource Filament
- [ ] CourseResource Filament
- [ ] Course API (list, detail, enroll)
- [ ] Seeders (5 kategori, 10 kursus)

**Frontend:**
- [ ] Katalog kursus (grid, filter, search)
- [ ] Detail kursus
- [ ] Pembelajaran Saya (enrolled + progress)
- [ ] Responsive mobile/tablet

---

## 📋 Schema Database (Rencana)

**categories:** id, name, slug, description, icon
**courses:** id, category_id, instructor_id, title, slug, description, thumbnail, difficulty, duration, is_published
**lessons:** id, course_id, title, content, video_url, order, duration
**enrollments:** id, user_id, course_id, enrolled_at, progress, completed_at

---

## 🎨 Warna PLN (Sudah di Tailwind)

```
primary: #035B71 (biru utama)
light: #00A2B9 (biru muda)
dark: #024656 (biru gelap)
50-900: full scale
```

Pakai: `bg-pln-primary`, `text-pln-light`, dll.

---

## 🔧 Command Penting

```bash
# Backend
cd c:\laragon\www\plnip-portal
php artisan serve          # Port 8000
php artisan migrate        # Jalankan migrations
php artisan make:model Course -m

# Frontend
cd c:\laragon\www\plnip-portal-frontend
npm run dev               # Port 3000
```

---

## 🧪 Test Credentials

```
Super Admin: superadmin@plnip.local / Admin123!
Employee: employee@plnip.local / Employee123!
```

---

## ⚠️ Catatan Penting

1. Selalu READ file dulu sebelum EDIT
2. Pakai FULL PATH untuk referensi file
3. Oracle = pakai string bukan enum
4. Kualitas TINGGI, hasil harus MEMUASKAN!
5. Deadline 3 HARI - no compromises!

---

**File Lengkap:** Baca HANDOFF_GEMINI.md untuk detail lengkap
