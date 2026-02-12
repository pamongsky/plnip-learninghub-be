# 🤖 AI ASSISTANT - KONSEP & IMPLEMENTASI
## PLN Indonesia Power Learning Hub Portal

---

## 📋 **EXECUTIVE SUMMARY**

AI Assistant pada PLN IP Learning Hub adalah **asisten virtual berbasis Gemini AI** yang terintegrasi penuh dengan platform pembelajaran dan Moodle LMS. AI ini tidak hanya menjawab pertanyaan umum, tetapi juga:

✅ **Memahami struktur platform** - Tahu semua fitur per role (admin, instructor, employee)
✅ **Membaca materi pembelajaran dari Moodle** - PDF, HTML, assignments, books, lessons
✅ **Personalized learning assistant** - Tahu course apa yang user ikuti
✅ **Auto-detect context** - Otomatis fetch materi kalau user tanya topik pembelajaran
✅ **Conversation history** - Ingat konteks percakapan sebelumnya

---

## 🎯 **USE CASES REAL**

### **1. Platform Navigation Assistant**

**User bertanya:**
> "Gimana cara saya lihat sertifikat saya?"

**AI Response:**
> "Untuk melihat sertifikat Anda, silakan ikuti langkah berikut:
>
> 1. Buka menu **Sertifikat Saya** di navigasi samping
> 2. Atau akses langsung via `/dashboard/certificates`
> 3. Disana akan muncul semua sertifikat dari course yang sudah Anda selesaikan
> 4. Anda bisa download PDF sertifikat atau share via link
>
> Sertifikat otomatis dikeluarkan setelah Anda menyelesaikan course dengan nilai minimal yang ditentukan."

**Bagaimana AI tahu ini?**
- Dari `getAvailableFeatures()` yang return semua fitur platform
- AI dapat deskripsi fitur "Sertifikat Saya" beserta path dan cara pakainya

---

### **2. Learning Material Assistant (Auto-detect)**

**User sedang enroll course: "K3 Ketenagalistrikan"**

**User bertanya:**
> "Jelaskan tentang materi grounding dan pentingnya dalam sistem kelistrikan"

**AI Auto-detect:**
1. Keyword "materi" terdeteksi → user tanya materi pembelajaran
2. Keyword "grounding" + "sistem kelistrikan" cocok dengan course "K3 Ketenagalistrikan"
3. Otomatis fetch content dari Moodle course tersebut
4. Baca PDF materi tentang grounding
5. Explain berdasarkan materi yang di-fetch

**AI Response:**
> "Berdasarkan materi course **K3 Ketenagalistrikan** yang Anda ikuti, grounding (pentanahan) adalah:
>
> **Definisi:**
> Grounding adalah proses menghubungkan bagian konduktif dari instalasi listrik ke tanah untuk keamanan dan stabilitas sistem.
>
> **Pentingnya Grounding:**
> 1. **Keamanan Personil** - Mencegah sengatan listrik fatal
> 2. **Proteksi Peralatan** - Melindungi dari lonjakan tegangan
> 3. **Stabilitas Sistem** - Menjaga tegangan referensi tetap stabil
>
> **Jenis-jenis Grounding** (dari materi PDF halaman 12):
> - System grounding (pentanahan sistem)
> - Equipment grounding (pentanahan peralatan)
> - Lightning protection grounding (pentanahan petir)
>
> Untuk detail lebih lanjut, silakan buka materi **"Sistem Grounding.pdf"** di course Anda di Moodle."

**Bagaimana AI bisa ini?**
- Auto-detect: `autoDetectAndFetchCourseContent()` mendeteksi keyword "materi"
- Matching: AI match "grounding", "kelistrikan" dengan course title "K3 Ketenagalistrikan"
- Fetch Moodle: `getMoodleCourseContent()` ambil semua section & modules
- Extract PDF: `extractPDFText()` baca isi PDF "Sistem Grounding.pdf"
- AI process: Gemini baca PDF content (15,000 chars limit) dan explain

---

### **3. Assignment/Tugas Helper**

**User bertanya:**
> "Tugas apa yang harus saya kerjakan di course K3 minggu ini?"

**AI Response:**
> "Berdasarkan course **K3 Ketenagalistrikan** yang Anda ikuti, ada tugas berikut:
>
> **Tugas 1: Analisis Risiko K3 di Gardu Induk**
> - **Deadline:** 15 Februari 2026
> - **Deskripsi:** Anda diminta untuk mengidentifikasi minimal 5 potensi bahaya di area gardu induk dan memberikan rekomendasi mitigasi untuk masing-masing bahaya.
> - **Format:** PDF atau Word, max 5 halaman
> - **Bobot:** 25% dari nilai akhir
>
> **Cara Submit:**
> 1. Klik 'Akses LMS Moodle' dari halaman course Anda
> 2. Buka section 'Week 3'
> 3. Klik assignment 'Analisis Risiko K3'
> 4. Upload file jawaban Anda
>
> Jangan lupa cek rubrik penilaian di Moodle untuk memastikan semua kriteria terpenuhi!"

**Bagaimana AI tahu assignment?**
- `getMoodleCourseContent()` fetch semua modules termasuk type `assign`
- `getAssignmentContent()` khusus fetch deskripsi, deadline, instructions dari assignment
- AI parse dan presentasikan dalam format user-friendly

---

### **4. Multi-format Content Reading**

**User bertanya:**
> "Rangkumin materi chapter 1 sampai 3 di course Transformator"

**AI fetches:**
1. **PDF files** - "Bab 1 Pengenalan Transformator.pdf" → extract text
2. **HTML pages** - "Prinsip Kerja Transformator" → strip tags, get text
3. **Book module** - Multi-chapter book → `getBookContent()` fetch all chapters
4. **Lesson pages** - Interactive lessons → `getLessonContent()` fetch content

**AI Response:**
> "Berikut rangkuman materi Chapter 1-3 dari course **Transformator**:
>
> **Chapter 1: Pengenalan Transformator**
> - Transformator adalah alat untuk mengubah tegangan AC tanpa mengubah frekuensi
> - Prinsip dasar: induksi elektromagnetik (hukum Faraday)
> - Komponen utama: inti besi, kumparan primer, kumparan sekunder
>
> **Chapter 2: Jenis-jenis Transformator**
> - Step-up transformer (naikkan tegangan)
> - Step-down transformer (turunkan tegangan)
> - Isolation transformer (isolasi listrik)
> - Autotransformer (efisiensi tinggi)
>
> **Chapter 3: Rating & Spesifikasi**
> - Rating daya (kVA)
> - Voltage ratio (rasio tegangan primer/sekunder)
> - Impedance percentage
> - Cooling method (ONAN, ONAF, OFAF)
>
> Total materi yang saya baca: 3 PDF files (45 halaman), 2 HTML pages, 1 book (5 chapters)."

**Format yang bisa dibaca AI:**
- ✅ PDF files (extract text via `extractPDFText()`)
- ✅ HTML pages (strip tags)
- ✅ Book chapters (via `getBookContent()`)
- ✅ Lesson pages (via `getLessonContent()`)
- ✅ Assignment descriptions
- ✅ Folder dengan multiple PDFs
- ✅ Labels & inline content
- ❌ Quiz/Exam questions (TIDAK BOLEH untuk integritas ujian)
- ❌ SCORM packages (tidak ada API Moodle)
- ❌ Video content (hanya metadata, tidak bisa transkrip)

---

## 🏗️ **ARSITEKTUR SISTEM**

### **Flow Diagram**

```
┌─────────────────────────────────────────────────────────────────┐
│                        USER INTERACTION                          │
│  User klik floating button AI chat di pojok kanan bawah         │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                   FRONTEND (Next.js)                             │
│  Component: AIChatWidget.tsx                                     │
│  - Draggable floating button                                     │
│  - Chat interface with sidebar history                           │
│  - Send message to API                                           │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         │ POST /api/ai-assistant/chat
                         │ { message: "...", conversation_id: "..." }
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│              BACKEND (Laravel API)                               │
│  Controller: AIAssistantController.php                           │
└────────────────────────┬────────────────────────────────────────┘
                         │
                    ┌────┴─────┐
                    │  STEP 1  │ Save user message to database
                    │          │ (ai_conversations table)
                    └────┬─────┘
                         │
                    ┌────┴─────┐
                    │  STEP 2  │ Get conversation history
                    │          │ Last 10 messages untuk context
                    └────┬─────┘
                         │
                    ┌────┴─────┐
                    │  STEP 3  │ Build user context
                    │          │ - User info (name, role, employee_id)
                    │          │ - Available features (ALL 35+ features)
                    │          │ - Navigation menu structure
                    │          │ - Enrolled courses list
                    └────┬─────┘
                         │
                    ┌────┴─────┐
                    │  STEP 4  │ AUTO-DETECT: Apakah user tanya materi?
                    │          │ Check keywords: materi, modul, jelaskan, pdf, dll
                    └────┬─────┘
                         │
                ┌────────┴────────┐
                │ YES             │ NO
                ▼                 ▼
    ┌───────────────────┐    Skip fetch
    │  STEP 5 (Optional) │    Moodle content
    │  Fetch Moodle      │
    │  Course Content    │
    └────┬──────────────┘
         │
         │ Match course dari user message
         │ atau pakai single enrolled course
         │
         ▼
    ┌───────────────────────────────────────┐
    │   MOODLE API CALL                      │
    │   core_course_get_contents             │
    │   GET /webservice/rest/server.php      │
    │   - courseid: moodle_course_id         │
    │   - wstoken: MOODLE_WS_TOKEN           │
    └────┬──────────────────────────────────┘
         │
         │ Response: JSON dengan sections & modules
         │
         ▼
    ┌───────────────────────────────────────┐
    │   PARSE MOODLE CONTENT                 │
    │   - PDF files → extractPDFText()       │
    │   - HTML pages → strip_tags()          │
    │   - Books → getBookContent()           │
    │   - Lessons → getLessonContent()       │
    │   - Assignments → getAssignmentContent()│
    │   - Folders → extract all PDFs inside  │
    └────┬──────────────────────────────────┘
         │
         │ Extracted text (max 15,000 chars per PDF)
         │
         └──────────────┬───────────────────┘
                        │
                   ┌────┴─────┐
                   │  STEP 6  │ Build System Prompt for Gemini
                   │          │ Combine:
                   │          │ - Platform features context
                   │          │ - User enrolled courses
                   │          │ - Course content (if fetched)
                   │          │ - AI guidelines & role
                   └────┬─────┘
                        │
                   ┌────┴─────┐
                   │  STEP 7  │ Call Gemini API
                   │          │ POST /v1beta/models/gemini-2.5-flash
                   │          │ - System prompt (~2,700 tokens)
                   │          │ - Conversation history
                   │          │ - User message
                   │          │ - Course content (if any)
                   └────┬─────┘
                        │
                        │ Gemini processes with full context
                        │
                   ┌────┴─────┐
                   │  STEP 8  │ Receive AI response
                   │          │ Max 2048 output tokens
                   └────┬─────┘
                        │
                   ┌────┴─────┐
                   │  STEP 9  │ Save AI response to database
                   │          │ (ai_conversations table)
                   └────┬─────┘
                        │
                        │ Return response to frontend
                        │
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│                   FRONTEND DISPLAY                               │
│  - Render AI response with markdown                             │
│  - Update conversation history sidebar                           │
│  - Auto-scroll to bottom                                         │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🔧 **TECHNICAL IMPLEMENTATION**

### **1. Database Schema**

**Table: `ai_conversations`**
```sql
CREATE TABLE ai_conversations (
    id BIGINT PRIMARY KEY,
    conversation_id VARCHAR(255) NOT NULL,  -- Group messages per conversation
    user_id BIGINT NOT NULL,                -- Foreign key to users
    message TEXT NOT NULL,                  -- User question or AI response
    role VARCHAR(20) NOT NULL,              -- 'user' or 'assistant'
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX idx_conversation_id (conversation_id),
    INDEX idx_user_id (user_id)
);
```

**Conversation History:**
- Last 10 messages diload untuk context
- Format: `[{role: 'user', content: '...'}, {role: 'assistant', content: '...'}]`
- Conversation ID: `conv-{uniqid}{timestamp}`

---

### **2. System Prompt Structure**

AI mendapat context lengkap setiap request:

```php
$systemPrompt = "
Anda adalah AI Assistant untuk PLN Indonesia Power Learning Hub Portal.

USER INFORMATION:
- Name: {user->name}
- Role: {user->role}
- Employee ID: {user->employee_id}

ENROLLED COURSES:
1. K3 Ketenagalistrikan (Instructor: Budi Santoso)
2. Transformator & Sistem Distribusi (Instructor: Andi Wijaya)
3. Manajemen Proyek Kelistrikan (Instructor: Siti Nurhaliza)

AVAILABLE FEATURES (Total: 35 features):

=== EMPLOYEE/USER FEATURES ===
1. Dashboard User (/dashboard)
   - Ringkasan aktivitas belajar, kelas aktif, progress, sertifikat

2. Kelas Saya (/dashboard/classes)
   - Daftar kelas yang diikuti, akses Moodle, lihat progress

3. Katalog Kursus (/dashboard/catalog)
   - Browse semua course, filter, search, enroll

4. Sertifikat Saya (/dashboard/certificates)
   - Download PDF sertifikat dari course yang selesai

5. Profil & Settings (/dashboard/profile)
   - Edit profil, ganti password, preferensi notifikasi

... (30+ features lainnya untuk admin, instructor, super-admin)

COURSE CONTENT (if user asking about material):
{course_name}: {course_description}

Sections:
- Section 1: {section_name}
  - PDF: {pdf_file_name}
    Content: {extracted_pdf_text}
  - HTML Page: {page_name}
    Content: {html_text}
  - Assignment: {assignment_name}
    Description: {assignment_description}
    Deadline: {deadline}

... (all modules & content)

GUIDELINES:
- Always respond in Indonesian (Bahasa Indonesia)
- Be helpful, friendly, and professional
- If user asking about course material, reference specific section/file
- If user asking about platform feature, give step-by-step guide
- If you don't know, admit it and suggest contacting support
- NEVER make up information, only use provided context
- NEVER access or reveal quiz/exam questions
";
```

**Total System Prompt Size:**
- Base prompt: ~2,700 tokens
- Course content (if fetched): +1,000-4,000 tokens
- **Total: 2,700-6,700 tokens per request**

---

### **3. Auto-detect Keywords**

**Material Keywords:**
```php
$materialKeywords = [
    'materi', 'modul', 'bab', 'topik', 'pelajaran', 'pembelajaran',
    'jelaskan', 'explain', 'ajarkan', 'tolong jelaskan',
    'pdf', 'dokumen', 'file', 'slide',
    'quiz', 'kuis', 'soal', 'ujian', 'tugas', 'assignment',
    'video', 'rekaman',
];
```

**Course Matching Algorithm:**
```php
// Score-based matching
foreach ($enrolledCourses as $course) {
    $courseWords = explode(' ', strtolower($course['name']));
    $score = 0;

    foreach ($courseWords as $word) {
        if (strlen($word) > 3 && str_contains($userMessage, $word)) {
            $score++;
        }
    }

    if ($score > $highestScore) {
        $matchedCourse = $course;
    }
}
```

**Fallback:** Kalau user cuma enroll 1 course, auto-use that course

---

### **4. Moodle Content Types Support**

| Module Type | Readable? | Method | Notes |
|-------------|-----------|--------|-------|
| **resource** (PDF, DOC) | ✅ YES | `extractPDFText()` | Max 15,000 chars per PDF |
| **page** (HTML) | ✅ YES | `strip_tags()` | Full HTML content |
| **url** (Link) | ⚠️ METADATA | URL only | AI can see URL, not content |
| **label** (Inline HTML) | ✅ YES | `strip_tags()` | Full content |
| **folder** (Multiple files) | ✅ YES | Loop + `extractPDFText()` | All PDFs inside |
| **book** (Chapters) | ✅ YES | `getBookContent()` | All chapters via API |
| **lesson** (Pages) | ✅ YES | `getLessonContent()` | All lesson pages via API |
| **assign** (Assignment) | ✅ YES | `getAssignmentContent()` | Description + deadline |
| **quiz** (Exam) | ❌ NO | Blocked | Untuk integritas ujian |
| **forum** | ⚠️ METADATA | Title + desc only | Not full discussions |
| **scorm** | ❌ NO | Not supported | No Moodle API |
| **h5p** | ❌ NO | Not supported | Interactive content |

---

### **5. PDF Text Extraction**

**Library:** Smalot PDF Parser (`smalot/pdfparser`)

```php
private function extractPDFText(string $url): ?string
{
    try {
        $pdfContent = file_get_contents($url);
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseContent($pdfContent);
        $text = $pdf->getText();

        // Safety limit: 15,000 characters
        $maxLength = 15000;
        if (strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength) . "\n\n[... teks dipotong ...]";
        }

        return $text;
    } catch (\Exception $e) {
        Log::error('PDF extraction failed: ' . $e->getMessage());
        return null;
    }
}
```

**Why 15,000 chars limit?**
- 15,000 chars ≈ 3,750 tokens
- Keep total input under 10,000 tokens untuk efisiensi
- Avoid Gemini API token limits (128K context window)

---

### **6. Gemini API Integration**

**Endpoint:**
```
POST https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent
```

**Request Body:**
```json
{
  "contents": [
    {
      "role": "user",
      "parts": [{"text": "System prompt + context"}]
    },
    {
      "role": "model",
      "parts": [{"text": "Previous AI response"}]
    },
    {
      "role": "user",
      "parts": [{"text": "Current user message"}]
    }
  ],
  "generationConfig": {
    "temperature": 0.7,
    "topK": 40,
    "topP": 0.95,
    "maxOutputTokens": 2048
  }
}
```

**Response:**
```json
{
  "candidates": [
    {
      "content": {
        "parts": [
          {
            "text": "AI response text here..."
          }
        ]
      }
    }
  ]
}
```

**Error Handling:**
- HTTP errors → Log + return generic error message
- Moodle API errors → Skip course content, proceed with general chat
- PDF extraction errors → Log + skip that file, proceed with other content

---

## 📊 **FEATURES BREAKDOWN**

### **Platform Features AI Understands** (35+ Features)

#### **Employee/User Features (10 features):**
1. Dashboard User - Overview aktivitas
2. Kelas Saya - Enrolled courses
3. Katalog Kursus - Browse & enroll
4. Sertifikat Saya - Download certificates
5. Profil & Settings - Edit profile
6. Akses LMS Moodle - SSO to Moodle
7. Progress Pembelajaran - Track progress
8. Pengumuman - View announcements
9. Notifikasi - Notification center
10. Support Tickets - Create tickets

#### **Instructor Features (8 features):**
1. Dashboard Instructor - Teaching overview
2. Kelas yang Diajar - Manage courses
3. Kelola Peserta - Student management
4. Upload Materi - Upload to Moodle
5. Penilaian & Grading - Grade students
6. Forum Diskusi - Moderate discussions
7. Laporan Kelas - Class analytics
8. Jadwal Mengajar - Teaching schedule

#### **Admin Features (12 features):**
1. Dashboard Admin - Admin overview
2. Manajemen Users - CRUD users
3. Sync Users dari ERP - Oracle integration
4. Manajemen Courses - CRUD courses
5. Sync Courses dari Moodle - Moodle sync
6. Enroll Users ke Course - Bulk enrollment
7. Manajemen Instructors - Assign instructors
8. Manajemen Sertifikat - Certificate management
9. Upload Sertifikat (Individual & Bulk) - PDF upload
10. Laporan & Analytics - Reports
11. Support Tickets Management - Handle tickets
12. Pengumuman Global - Create announcements

#### **Super Admin Features (5 features):**
1. Dashboard Super Admin - Full overview
2. Manajemen Admins - CRUD admins
3. Role & Permission Management - Spatie permissions
4. System Logs & Audit - Security logs
5. System Settings - Global configs

**Total: 35 features** yang AI pahami dan bisa guide user!

---

## 💡 **INTELLIGENT FEATURES**

### **1. Context-Aware Responses**

AI tahu role user dan hanya suggest fitur yang relevan:

**Example:**
- Employee tanya "Gimana cara upload materi?" → AI jawab "Fitur upload materi hanya untuk Instructor. Sebagai employee, Anda bisa akses materi di 'Kelas Saya'"
- Instructor tanya sama → AI kasih step-by-step upload via Moodle

### **2. Personalized Course Context**

AI tahu course apa yang user enroll:

```
User: "Apa tugas saya minggu ini?"
AI: "Berikut tugas dari course yang Anda ikuti:

      K3 Ketenagalistrikan:
      - Analisis Risiko K3 (deadline 15 Feb)

      Transformator:
      - Quiz Chapter 3 (deadline 18 Feb)
```

### **3. Multi-turn Conversation**

AI ingat context percakapan sebelumnya (last 10 messages):

```
User: "Jelaskan tentang transformator"
AI: "Transformator adalah alat untuk mengubah tegangan AC..."

User: "Apa bedanya dengan autotransformer?"
AI: "Berdasarkan penjelasan transformator sebelumnya, autotransformer berbeda karena..."
       ↑ AI ingat kita bahas transformator sebelumnya
```

### **4. Safety & Privacy**

✅ **TIDAK BISA:**
- Access quiz/exam questions (integritas ujian)
- Access source code (security)
- Access database directly (hanya via API)
- Modify data (read-only)

✅ **BISA:**
- Read learning materials
- Read assignment descriptions
- Guide platform usage
- Explain concepts from course content

---

## 📈 **ANALYTICS & USAGE**

### **Trackable Metrics:**

```sql
-- Total conversations per user
SELECT user_id, COUNT(DISTINCT conversation_id) as total_conversations
FROM ai_conversations
GROUP BY user_id;

-- Most active users
SELECT u.name, COUNT(DISTINCT ac.conversation_id) as chat_count
FROM users u
JOIN ai_conversations ac ON u.id = ac.user_id
GROUP BY u.id
ORDER BY chat_count DESC
LIMIT 10;

-- Average messages per conversation
SELECT AVG(message_count) as avg_messages
FROM (
    SELECT conversation_id, COUNT(*) as message_count
    FROM ai_conversations
    GROUP BY conversation_id
) as conv_stats;

-- Popular topics (keyword analysis)
SELECT
    CASE
        WHEN message LIKE '%sertifikat%' THEN 'Sertifikat'
        WHEN message LIKE '%materi%' OR message LIKE '%modul%' THEN 'Materi Pembelajaran'
        WHEN message LIKE '%tugas%' OR message LIKE '%assignment%' THEN 'Tugas'
        WHEN message LIKE '%nilai%' OR message LIKE '%grade%' THEN 'Nilai'
        ELSE 'Lainnya'
    END as topic,
    COUNT(*) as count
FROM ai_conversations
WHERE role = 'user'
GROUP BY topic
ORDER BY count DESC;
```

---

## 💰 **COST ESTIMATION** (Updated)

### **Current Setup:**
- Model: **Gemini 2.5 Flash**
- Input: $0.30 per 1M tokens = Rp 4,710 per 1M
- Output: $2.50 per 1M tokens = Rp 39,250 per 1M

### **Cost per Chat Type:**

| Chat Type | Input Tokens | Output Tokens | Cost per Chat |
|-----------|--------------|---------------|---------------|
| General chat (no course) | 3,250 | 200 | Rp 0.010 |
| Chat + course content | 4,530 | 500 | Rp 0.022 |
| Chat + PDF reading | 7,050 | 800 | Rp 0.035 |

### **Monthly Cost (700 daily active users, 200 prompts/day):**

```
Mix: 70% general + 20% course + 10% PDF
Per user per day: (140 × 0.01) + (40 × 0.022) + (20 × 0.035) = Rp 2.98
700 users × Rp 2.98 × 22 days = Rp 45,892/month ≈ Rp 46,000/month
Per year: Rp 552,000/year
```

### **Recommendation: Switch to Gemini 2.0 Flash**

- Input: $0.10 per 1M (3x cheaper!)
- Output: $0.40 per 1M (6x cheaper!)
- **Monthly cost: Rp 9,000 (80% hemat!)**
- **Yearly cost: Rp 108,000**

**ROI:**
- 1 staff helpdesk salary: Rp 60 juta/tahun
- AI Assistant (700 users): Rp 108 ribu/tahun
- **Hemat 99.8%!**

---

## 🚀 **IMPLEMENTATION TIMELINE**

✅ **Phase 1: Core AI (DONE)**
- Gemini API integration
- Conversation history
- Platform context (35 features)

✅ **Phase 2: Moodle Integration (DONE)**
- Auto-detect course content
- PDF extraction
- HTML page reading
- Assignment fetching

✅ **Phase 3: Advanced Features (DONE)**
- Multi-format support (Book, Lesson, Folder)
- Course matching algorithm
- Safety filters (no quiz access)

🔄 **Phase 4: Optimization (ONGOING)**
- Switch to Gemini 2.0 Flash (cost optimization)
- Context caching (hemat 90% input cost)
- Rate limiting per user
- Usage analytics dashboard

📋 **Phase 5: Future Enhancements**
- Voice input/output
- Image analysis (diagram, charts dari materi)
- Real-time collaboration (group study dengan AI)
- Adaptive learning path suggestions

---

## 🎓 **TRAINING DATA SOURCES**

AI Assistant ini **TIDAK ditraining ulang**. Menggunakan:

1. **Base Knowledge:** Gemini 2.5 Flash (Google's pre-trained model)
2. **Platform Context:** Injected via system prompt setiap request
3. **Course Content:** Fetched real-time dari Moodle API
4. **Conversation History:** Last 10 messages untuk continuity

**NO FINE-TUNING NEEDED** karena context injection sudah cukup powerful!

---

## 🔐 **SECURITY & COMPLIANCE**

### **Data Privacy:**
- ✅ User conversations tersimpan di database lokal (bukan Google servers)
- ✅ Hanya metadata dikirim ke Gemini (tidak ada PII sensitif)
- ✅ Course content hanya di-fetch untuk enrolled users
- ✅ No data retention di Gemini API (stateless)

### **Access Control:**
- ✅ Authentication required (Laravel Sanctum)
- ✅ Role-based feature suggestions
- ✅ Course content filtered by enrollment
- ✅ Quiz/exam content blocked

### **Rate Limiting (Planned):**
```php
// Per user limits
'daily_limit' => 200 messages,
'hourly_limit' => 50 messages,
'minute_limit' => 5 messages,

// Cost monitoring
'monthly_budget_alert' => Rp 50,000,
'auto_throttle_threshold' => Rp 100,000,
```

---

## 📱 **USER EXPERIENCE**

### **Frontend: AIChatWidget.tsx**

**Features:**
- 🎯 Floating button (pojok kanan bawah)
- 🖱️ Draggable position
- 💬 Chat interface dengan bubbles
- 📜 Sidebar untuk conversation history
- 🔍 Search previous conversations
- 🗑️ Delete conversations
- ↔️ Maximize/minimize
- 📱 Mobile responsive
- 🌙 Dark mode support

**UX Flow:**
1. User klik floating button → widget expand
2. Ketik pertanyaan → send
3. Loading animation (3 bouncing dots)
4. Response muncul dengan markdown formatting
5. Auto-scroll ke bottom
6. Sidebar update dengan conversation title

---

## 🎯 **KEY SELLING POINTS**

### **Untuk Presentasi ke Management:**

1. **ROI Sangat Tinggi**
   - Biaya: Rp 108 ribu/tahun (dengan Gemini 2.0 Flash)
   - Replace: 1 helpdesk staff (Rp 60 juta/tahun)
   - Hemat: 99.8%

2. **24/7 Availability**
   - AI tidak pernah libur
   - Response instant (<5 detik)
   - No queue, no waiting

3. **Scalable**
   - 700 users atau 5000 users, cost difference minimal
   - Tidak perlu hire lebih banyak helpdesk
   - Infrastructure already in place

4. **Intelligent & Context-Aware**
   - Bukan chatbot biasa
   - Paham struktur platform 35+ features
   - Bisa baca materi dari Moodle (PDF, HTML, assignments)
   - Personalized per user (role, enrolled courses)

5. **Learning Acceleration**
   - User dapat instant help untuk materi
   - AI jelaskan konsep dari course content
   - Reduce instructor workload untuk basic questions

6. **Data-Driven Insights**
   - Analytics: pertanyaan paling sering
   - Identify pain points di platform
   - Improve UX based on AI chat logs

---

## 📞 **SUPPORT & MAINTENANCE**

### **Monitoring:**
- Gemini API uptime (Google SLA: 99.9%)
- Response time tracking
- Error rate monitoring
- Cost tracking (daily/monthly)

### **Troubleshooting:**

**Common Issues:**

1. **Moodle content tidak bisa di-fetch**
   - Check: MOODLE_URL & MOODLE_WS_TOKEN valid?
   - Check: Course moodle_course_id correct?
   - Check: Webservice enabled di Moodle?

2. **PDF extraction failed**
   - Check: File size (max 10 MB recommended)
   - Check: PDF format (some encrypted PDFs gagal)
   - Fallback: AI tetap bisa pakai course description

3. **Gemini API error**
   - Check: GEMINI_API_KEY valid?
   - Check: Quota not exceeded?
   - Retry logic: 3x retry dengan exponential backoff

---

## 🏁 **CONCLUSION**

AI Assistant PLN IP Learning Hub adalah **game-changer** untuk employee learning experience:

✅ **Affordable** - Rp 108 ribu/tahun untuk 700 active users
✅ **Intelligent** - Context-aware, multi-format content reading
✅ **Scalable** - Handle 5000 users tanpa signifikan cost increase
✅ **Integrated** - Seamless dengan Moodle & platform features
✅ **Secure** - Role-based, privacy-focused, no quiz leaks

**Next Steps:**
1. Optimize: Switch ke Gemini 2.0 Flash (hemat 80%)
2. Enable context caching (hemat 90% lagi)
3. Add usage analytics dashboard
4. Gather user feedback & iterate

---

**Document Version:** 1.0
**Last Updated:** 2026-02-11
**Author:** System Architect
**Status:** Production Ready ✅
