# 🎓 SISTEM SERTIFIKAT - DOKUMENTASI LENGKAP

## ✅ STATUS: READY TO USE

Sistem sertifikat sudah lengkap dengan:

- ✅ Template management (upload, edit, delete)
- ✅ Passing grade per kelas (customizable)
- ✅ Auto-generate PDF dari template
- ✅ Command untuk generate sertifikat otomatis
- ✅ API untuk download sertifikat
- ✅ Verifikasi sertifikat publik

---

## 🎯 FITUR UTAMA

### **1. PASSING GRADE CUSTOM PER KELAS**

Setiap kelas bisa punya passing grade berbeda:

- Kelas A: Passing grade 70%
- Kelas B: Passing grade 80%
- Kelas C: Passing grade 60%

**Cara setting:**

```
PUT /api/courses/{id}
{
  "passing_grade": 75.00
}
```

Default: 70.00

---

### **2. AUTO-GENERATE SERTIFIKAT**

**Via Command (Cron Job):**

```bash
# Generate semua sertifikat untuk semua kelas
php artisan certificates:generate

# Generate untuk kelas tertentu
php artisan certificates:generate --course_id=2

# Generate untuk user tertentu
php artisan certificates:generate --user_id=5

# Generate untuk user di kelas tertentu
php artisan certificates:generate --course_id=2 --user_id=5
```

**Setup Cron Job** (jalankan setiap hari jam 2 pagi):

```bash
# Edit crontab
crontab -e

# Tambahkan:
0 2 * * * cd /path/to/plnip-portal && php artisan certificates:generate >> /dev/null 2>&1
```

**Atau di Laravel Task Scheduler** (app/Console/Kernel.php):

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('certificates:generate')
        ->dailyAt('02:00')
        ->emailOutputOnFailure('admin@plnip.co.id');
}
```

---

### **3. FLOW LENGKAP**

```
1. Admin Upload Template
   ↓
2. Admin Assign Template ke Kelas (+ Set Passing Grade)
   ↓
3. User Enroll ke Kelas
   ↓
4. User Ikut Ujian di Moodle
   ↓
5. Moodle Simpan Nilai
   ↓
6. Cron Job Run (setiap hari):
   ├── Cek nilai user dari Moodle
   ├── Jika nilai ≥ passing grade:
   │   ├── Ambil template kelas
   │   ├── Replace variable ({{nama}}, {{kelas}}, dll)
   │   ├── Generate PDF
   │   ├── Simpan ke database + storage
   │   └── User bisa download
   └── Jika nilai < passing grade: Skip
```

---

## 📡 API ENDPOINTS

### **User - My Certificates**

```http
GET /api/certificates
Authorization: Bearer {token}

Response:
[
  {
    "id": 1,
    "certificate_number": "PLN-CERT-2026-02-0001",
    "course_name": "Dasar Pembangkit Listrik",
    "student_name": "Ahmad Fauzi",
    "issue_date": "2026-02-04",
    "final_score": 85.50,
    "grade": "B",
    "certificate_url": "/storage/certificates/PLN-CERT-2026-02-0001.pdf",
    "verification_code": "A1B2C3D4E5F6G7H8"
  }
]
```

### **Download Certificate**

```http
GET /api/certificates/{id}/download
Authorization: Bearer {token}

Response: PDF file download
```

### **Verify Certificate (Public)**

```http
GET /api/certificates/verify?verification_code=A1B2C3D4E5F6G7H8

Response:
{
  "valid": true,
  "message": "Certificate is valid",
  "certificate": {
    "certificate_number": "PLN-CERT-2026-02-0001",
    "student_name": "Ahmad Fauzi",
    "course_name": "Dasar Pembangkit Listrik",
    "issue_date": "2026-02-04"
  }
}
```

### **Admin - All Certificates**

```http
GET /api/admin/certificates
GET /api/admin/certificates?course_id=2
GET /api/admin/certificates?user_id=5
GET /api/admin/certificates?search=Ahmad
Authorization: Bearer {token} + Role: admin|super-admin
```

### **Admin - Statistics**

```http
GET /api/admin/certificates/stats
Authorization: Bearer {token} + Role: admin|super-admin

Response:
{
  "total": 150,
  "valid": 145,
  "revoked": 5,
  "this_month": 25,
  "by_course": [
    {"course_name": "Dasar Pembangkit", "total": 45},
    {"course_name": "Transmisi Listrik", "total": 30}
  ]
}
```

### **Admin - Revoke Certificate**

```http
PATCH /api/admin/certificates/{id}/revoke
{
  "notes": "Data tidak valid"
}
```

### **Admin - Restore Certificate**

```http
PATCH /api/admin/certificates/{id}/restore
```

---

## 🎨 TEMPLATE SYSTEM

### **Supported Formats:**

- PDF (recommended)
- JPG/JPEG
- PNG

### **Variable Replacement:**

Template bisa pakai placeholder ini:

```
{{nama}}              → Ahmad Fauzi
{{employee_id}}       → PLN-2024-001
{{kelas}}             → Dasar Pembangkit Listrik
{{tanggal_selesai}}   → 04 Februari 2026
{{tanggal_terbit}}    → 04 Februari 2026
{{nilai}}             → 85.50
{{grade}}             → B
{{jam}}               → 40
{{instructor}}        → Ir. Budi Santoso
{{nomor_sertifikat}}  → PLN-CERT-2026-02-0001
{{kode_verifikasi}}   → A1B2C3D4E5F6G7H8
{{department}}        → Pembangkitan
{{position}}          → Engineer
```

### **Contoh Template:**

```
===========================================
       SERTIFIKAT PELATIHAN PLN IP
===========================================

Diberikan kepada:
{{nama}} ({{employee_id}})

Telah menyelesaikan pelatihan:
{{kelas}}

Dengan nilai: {{nilai}} ({{grade}})
Total jam: {{jam}} jam
Instruktur: {{instructor}}

Tanggal: {{tanggal_terbit}}

Nomor Sertifikat: {{nomor_sertifikat}}
Kode Verifikasi: {{kode_verifikasi}}

Departemen: {{department}}
Jabatan: {{position}}
===========================================
```

---

## 🔧 CUSTOMIZE PASSING GRADE

**Edit Kelas:**

```http
PUT /api/courses/{id}
{
  "passing_grade": 80.00
}
```

**Contoh:**

- Kelas Safety → Passing grade 90% (strict)
- Kelas Teori → Passing grade 70% (standard)
- Kelas Praktek → Passing grade 75% (medium)

---

## 📊 DATABASE

**certificates table:**

- user_id, course_id, template_id
- certificate_number (unique)
- verification_code (unique)
- final_score, grade
- certificate_url (PDF path)
- is_valid (untuk revoke)

**courses table:**

- certificate_template_id (FK)
- passing_grade (default 70.00)

---

## 🚀 NEXT STEPS

1. **Upload Template:**
    - Admin masuk menu "Template Sertifikat"
    - Upload template PDF/JPG dengan placeholder
    - Set kategori (opsional)

2. **Set Passing Grade:**
    - Edit kelas
    - Atur passing grade sesuai kebutuhan

3. **Setup Cron:**
    - Tambahkan cron job untuk auto-generate
    - Atau run manual: `php artisan certificates:generate`

4. **User Download:**
    - User login → Dashboard → Sertifikat
    - Download PDF

---

**Status:** ✅ PRODUCTION READY
**Dependencies:** ✅ FPDI installed
**Migration:** ✅ Done
**Routes:** ✅ Registered
**Command:** ✅ Ready
