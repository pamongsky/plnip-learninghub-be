# 🎓 SISTEM SERTIFIKAT - FLEXIBLE CRITERIA

## 📋 3 KRITERIA KELULUSAN

### **1. FINAL GRADE (Default)** ⭐

Moodle otomatis hitung nilai akhir dari SEMUA aktivitas:

- Quiz 1, Quiz 2, Quiz 3...
- Assignment 1, Assignment 2...
- Attendance (jika ada)
- Participation (jika ada)

**Setiap aktivitas punya bobot** (di-set di Moodle):

```
Kelas: Dasar Pembangkit Listrik
├── Quiz 1 (Bobot 20%)       → Nilai 80
├── Quiz 2 (Bobot 20%)       → Nilai 85
├── Assignment (Bobot 30%)   → Nilai 90
└── Final Exam (Bobot 30%)   → Nilai 88

Final Grade = (80×0.2) + (85×0.2) + (90×0.3) + (88×0.3)
            = 16 + 17 + 27 + 26.4
            = 86.4%

Passing Grade: 70%
Result: 86.4% ≥ 70% → LULUS ✅
```

**Setting di Portal:**

```json
{
    "certificate_criteria": "final_grade",
    "passing_grade": 70.0
}
```

---

### **2. SPECIFIC QUIZ/EXAM**

Hanya lihat **1 ujian tertentu** (misal hanya Final Exam):

```
Kelas: Dasar Pembangkit Listrik
└── Final Exam (Quiz ID: 123) → Nilai 85%

Passing Grade: 70%
Result: 85% ≥ 70% → LULUS ✅
```

**Cocok untuk:**

- Sertifikasi yang butuh ujian akhir khusus
- Kelas yang hanya punya 1 final test

**Setting di Portal:**

```json
{
    "certificate_criteria": "specific_quiz",
    "certificate_quiz_id": 123,
    "passing_grade": 80.0
}
```

**Cara dapat Quiz ID:**
Masuk Moodle → Edit quiz → Lihat URL:
`/mod/quiz/edit.php?cmid=456` → cmid = 456 (ini quiz ID)

---

### **3. COMPLETION + GRADE**

Harus **2 syarat sekaligus**:

1. ✅ Selesaikan **SEMUA materi** (100% completion di Moodle)
2. ✅ Nilai akhir ≥ passing grade

```
Kelas: Dasar Pembangkit Listrik
├─ Materi 1: ✅ Selesai
├─ Materi 2: ✅ Selesai
├─ Materi 3: ✅ Selesai
├─ Quiz 1: ✅ Selesai
└─ Final Exam: ✅ Selesai

Completion: 100% ✅
Final Grade: 85% ≥ 70% ✅

Result: LULUS ✅
```

**Cocok untuk:**

- Kelas yang wajib baca semua materi
- Training dengan modul lengkap

**Setting di Portal:**

```json
{
    "certificate_criteria": "completion_and_grade",
    "passing_grade": 70.0
}
```

**Note:** Moodle harus enable course completion tracking!

---

## 🕐 SETTING TANGGAL TERBIT

### **Auto-Issue Certificate**

```json
{
    "auto_issue_certificate": true, // Auto generate
    "certificate_issue_delay_days": 3 // Terbit 3 hari setelah lulus
}
```

**Contoh:**

- User lulus: 1 Februari 2026
- Delay: 3 hari
- **Tanggal terbit sertifikat: 4 Februari 2026**

### **Manual Approval**

```json
{
    "auto_issue_certificate": false // Admin harus approve manual
}
```

Command tidak akan generate otomatis. Admin harus approve di dashboard.

---

## 📊 CONTOH KONFIGURASI PER KELAS

### **Kelas Safety (Strict)**

```json
{
    "certificate_criteria": "completion_and_grade",
    "passing_grade": 90.0,
    "auto_issue_certificate": true,
    "certificate_issue_delay_days": 7
}
```

→ Harus selesai semua materi + nilai ≥90%, terbit 7 hari kemudian

### **Kelas Teori (Standard)**

```json
{
    "certificate_criteria": "final_grade",
    "passing_grade": 70.0,
    "auto_issue_certificate": true,
    "certificate_issue_delay_days": 0
}
```

→ Nilai akhir ≥70%, terbit langsung

### **Kelas Sertifikasi (Final Exam Only)**

```json
{
    "certificate_criteria": "specific_quiz",
    "certificate_quiz_id": 456,
    "passing_grade": 80.0,
    "auto_issue_certificate": true,
    "certificate_issue_delay_days": 1
}
```

→ Hanya lihat final exam ≥80%, terbit besok

---

## 🔧 API UNTUK UPDATE SETTING

```http
PUT /api/courses/{id}
{
  "certificate_criteria": "final_grade",
  "passing_grade": 75.00,
  "auto_issue_certificate": true,
  "certificate_issue_delay_days": 2
}
```

---

## 🎯 RINGKASAN

| Kriteria                 | Cek Apa                         | Cocok Untuk                           |
| ------------------------ | ------------------------------- | ------------------------------------- |
| **final_grade**          | Rata-rata semua quiz/assignment | Kelas standar dengan banyak aktivitas |
| **specific_quiz**        | 1 ujian tertentu saja           | Kelas sertifikasi dengan final exam   |
| **completion_and_grade** | Selesai semua materi + nilai    | Training yang wajib baca semua modul  |

**Tanggal terbit:** Bisa diatur delay 0-30 hari setelah lulus

---

**Status:** ✅ PRODUCTION READY dengan 3 opsi flexible!
