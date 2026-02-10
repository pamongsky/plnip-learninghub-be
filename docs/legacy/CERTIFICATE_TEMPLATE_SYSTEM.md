# 🎨 SISTEM TEMPLATE SERTIFIKAT - DOKUMENTASI

## 📋 KONSEP SEDERHANA

### **1 KELAS = 1 TEMPLATE SERTIFIKAT**

**Prinsip:**

- Setiap kelas di Moodle punya 1 template sertifikat
- Tidak peduli ada berapa quiz/ujian di kelas tersebut
- Semua user yang lulus kelas → dapat sertifikat dengan template yang sama

**Contoh:**

```
Kelas: "Dasar Pembangkit Listrik"
Template: "Template Pembangkit Hijau"

User A:
├── Quiz 1: 80
├── Quiz 2: 85
└── Final: 90 → Rata-rata: 85 → LULUS ✅
    → Generate sertifikat pakai "Template Pembangkit Hijau"

User B:
├── Quiz 1: 70
├── Quiz 2: 75
└── Final: 80 → Rata-rata: 75 → LULUS ✅
    → Generate sertifikat pakai "Template Pembangkit Hijau"
```

**SEMUA user di kelas yang sama = template sertifikat yang sama!**

---

## 🔧 CARA KERJA

### **Step 1: Admin Upload Template**

1. Masuk menu "Kelola Template Sertifikat"
2. Upload file PDF/JPG/PNG (contoh: `template_pembangkit.pdf`)
3. Isi nama: "Template Pembangkit Hijau"
4. Pilih kategori: "Pembangkit" (opsional, untuk grouping aja)
5. Save

### **Step 2: Assign Template ke Kelas**

1. Admin sync kelas dari Moodle atau edit kelas existing
2. Di form edit kelas, ada dropdown:
    ```
    Template Sertifikat: [v] Template Pembangkit Hijau
    ```
3. Pilih template yang sesuai
4. Save → Template ter-assign ke kelas

### **Step 3: User Ikut Kelas & Ujian**

1. User enroll ke kelas di portal
2. User ikut ujian di Moodle (bisa banyak quiz)
3. Moodle simpan nilai

### **Step 4: Auto-Generate Sertifikat**

1. Sistem cron job cek nilai dari Moodle
2. Hitung nilai final (rata-rata atau nilai akhir)
3. Jika nilai ≥ 70 (passing grade):
    - Ambil template yang di-assign ke kelas
    - Replace variable: `{{nama}}`, `{{kelas}}`, `{{nilai}}`, dll
    - Generate PDF sertifikat
    - Simpan ke database + storage
4. User bisa download dari dashboard

---

## 💡 TENTANG KATEGORI

**"Kategori" itu BUKAN dari Moodle!**

Kategori cuma untuk **grouping template** biar mudah cari:

```
Template List:
├─ Kategori: Pembangkit
│  ├─ Template Pembangkit Hijau
│  ├─ Template Pembangkit Biru
│  └─ Template Pembangkit Standar
│
├─ Kategori: Transmisi
│  ├─ Template Transmisi Merah
│  └─ Template Transmisi Kuning
│
└─ Kategori: Safety
   └─ Template Safety Orange
```

Admin bebas kasih kategori apapun saat upload template.

---

## 🔧 VARIABLE YANG TERSEDIA

Template bisa pakai variable ini (akan di-replace otomatis):

```
{{nama}}              → Nama lengkap peserta (e.g., "Ahmad Fauzi")
{{employee_id}}       → ID Karyawan (e.g., "PLN-2024-001")
{{kelas}}             → Nama kelas (e.g., "Dasar Pembangkit Listrik")
{{tanggal_selesai}}   → Tanggal selesai kelas (e.g., "15 Januari 2026")
{{tanggal_terbit}}    → Tanggal sertifikat diterbitkan (e.g., "16 Januari 2026")
{{nilai}}             → Nilai akhir (e.g., "85.50")
{{grade}}             → Grade huruf (e.g., "B")
{{jam}}               → Total jam pelatihan (e.g., "40 jam")
{{instructor}}        → Nama instruktur (e.g., "Ir. Budi Santoso")
{{nomor_sertifikat}}  → Nomor unik sertifikat (e.g., "PLN-CERT-2026-02-0001")
{{kode_verifikasi}}   → Kode verifikasi (e.g., "A1B2C3D4E5F6G7H8")
{{department}}        → Departemen peserta (e.g., "Pembangkitan")
{{position}}          → Jabatan peserta (e.g., "Engineer")
```

---

## 📝 CONTOH PENGGUNAAN

### **Scenario 1: Template Pembangkit**

1. Admin upload `template_pembangkit.pdf` dengan placeholder:

    ```
    SERTIFIKAT PELATIHAN
    Diberikan kepada: {{nama}}
    ID Karyawan: {{employee_id}}
    Telah menyelesaikan: {{kelas}}
    Nilai: {{nilai}} ({{grade}})
    Tanggal: {{tanggal_terbit}}
    No. Sertifikat: {{nomor_sertifikat}}
    ```

2. User "Ahmad Fauzi" lulus kelas "Dasar Pembangkit"

3. Sistem generate PDF dengan data real:
    ```
    SERTIFIKAT PELATIHAN
    Diberikan kepada: Ahmad Fauzi
    ID Karyawan: PLN-2024-001
    Telah menyelesaikan: Dasar Pembangkit Listrik
    Nilai: 85.50 (B)
    Tanggal: 04 Februari 2026
    No. Sertifikat: PLN-CERT-2026-02-0001
    ```

### **Scenario 2: Multiple Template**

- **Kelas Pembangkit** → Pakai `template_pembangkit.pdf` (background hijau)
- **Kelas Transmisi** → Pakai `template_transmisi.pdf` (background biru)
- **Kelas Safety** → Pakai `template_safety.pdf` (background merah)

Setiap kelas bisa punya desain template berbeda!

---

## 🎯 API ENDPOINTS

### **Get All Templates**

```
GET /api/certificate-templates
GET /api/certificate-templates?category=pembangkit
GET /api/certificate-templates?active_only=true
```

### **Get Available Variables**

```
GET /api/certificate-templates/variables
```

### **Create Template**

```
POST /api/certificate-templates
Content-Type: multipart/form-data

{
  "name": "Template Pembangkit Standar",
  "category": "pembangkit",
  "template_file": (file),
  "preview_file": (file),
  "description": "Template untuk kelas pembangkit",
  "is_default": false
}
```

### **Update Template**

```
POST /api/certificate-templates/{id}
Content-Type: multipart/form-data

{
  "name": "Template Updated",
  "is_active": true
}
```

### **Delete Template**

```
DELETE /api/certificate-templates/{id}
```

---

## 🔄 NEXT STEP: AUTO-GENERATE

Setelah template siap, kita perlu:

1. **CertificateGenerator Service** - Logic generate PDF dari template
2. **Artisan Command** - Cron job cek nilai dari Moodle
3. **Certificate Controller** - API untuk user download sertifikat

Mau saya implementasikan sekarang?

---

## 📊 DATABASE STRUCTURE

**Table: certificate_templates**

- id, name, category, file_path, preview_path
- variables (JSON), settings (JSON)
- is_active, is_default

**Table: certificates**

- id, user_id, course_id, template_id
- certificate_number, verification_code
- final_score, grade, certificate_url
- is_valid, notes

**Table: courses**

- ...existing fields...
- certificate_template_id (FK)

---

## 💡 TIPS UNTUK DESAIN TEMPLATE

1. **Gunakan placeholder yang jelas**: `{{nama}}` lebih baik dari `{{n}}`
2. **Test dulu dengan dummy data**: Pastikan semua variable muncul dengan baik
3. **Simpan source file**: Kalau mau edit template, buka source (PSD/AI/Word) lalu export ulang
4. **Format konsisten**: Semua template sebaiknya ukuran A4 landscape
5. **Preview image**: Upload preview biar admin tahu template mana yang mau dipilih

---

**Status:** ✅ Migration done, Models & Controllers ready, Routes registered
**Next:** Generate PDF service + Auto-sync command
