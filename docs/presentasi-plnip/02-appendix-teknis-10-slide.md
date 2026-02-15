# Appendix Teknis (10 Slide) - Backup untuk Q&A ITD

Gunakan appendix ini saat ada permintaan deep dive teknis setelah deck utama.

## Slide A1 - Peta Endpoint API per Domain
### Isi slide
| Domain | Cakupan Endpoint (agregat) | Contoh Prefix |
|---|---:|---|
| Auth & Profile | 9 | `/api/login`, `/api/register`, `/api/profile/*`, `/api/user` |
| User & Access | 23 | `/api/users`, `/api/superadmin/users/*`, `/api/superadmin/roles/*`, `/api/superadmin/permissions/*` |
| Announcements | 16 | `/api/announcements/*`, `/api/admin/announcements/*`, `/api/instructor/announcements/*` |
| Courses & Certificates | 16 | `/api/courses/*`, `/api/certificates/*`, `/api/admin/certificates/*` |
| Moodle | 8 | `/api/moodle/login-url`, `/api/moodle/sync/*` |
| ERP | 1 | `/api/superadmin/sync-erp` |
| AI Assistant | 14 | `/api/ai-assistant/*`, `/api/course-assistant/*`, `/api/chat/*` |
| Messaging & Realtime | 16 | `/api/messages/*`, `/api/classes/*/chat/*`, `/api/broadcasting/auth` |
| Support & Escalation | 14 | `/api/support/*`, `/api/escalations/*` |
| CMS Landing | 12 | `/api/landing-page`, `/api/cms/*` |
| Dashboard & Activity | 6 | `/api/dashboard/*`, `/api/activity-log/*` |

- Total route API aktif internal: `135`.

## Slide A2 - Matriks Role-Permission Ringkas
### Isi slide
| Area | user/employee | instructor | admin | super-admin |
|---|---|---|---|---|
| Lihat course/progres | Ya | Ya | Ya | Ya |
| Kelola announcement global | Tidak | Tidak | Tidak | Ya |
| Kelola announcement unit/kelas | Tidak | Ya (kelas) | Ya (unit) | Ya |
| Kelola user/role/permission | Tidak | Tidak | Terbatas | Ya |
| Sinkronisasi Moodle | Tidak | Tidak | Ya (sebagian) | Ya (full) |
| Trigger ERP sync | Tidak | Tidak | Tidak | Ya |
| Support/escalation handling | Ticket sendiri | Ticket sendiri + kelas | Ya | Ya |

Catatan:
- Enforcement utama: `auth:sanctum` + `role` middleware + cek tambahan di controller domain tertentu.

## Slide A3 - Skema Data Inti
### Isi slide
- Entitas inti:
  - `users`, `roles/permissions`, `audit_logs`
  - `courses`, `course_enrollments`, `certificates`
  - `announcements`
  - `support_tickets`, `support_replies`, `escalation_tickets`, `escalation_replies`
  - `conversations`, `direct_messages`, `class_messages`
  - `ai_conversations`, `chat_sessions`, `chat_messages`, `chat_attachments`
  - `moodle_sync_logs`, `landing_page_settings` + tabel CMS
- Jumlah migration aktif: `38`.

## Slide A4 - Alur Moodle Sync Detail
### Isi slide
1. Auth role check (`admin/super-admin`).
2. Pemanggilan service sync sesuai domain: users/courses/enrollments/categories/full.
3. Ambil data dari koneksi Moodle (Oracle + prefix `mdl_`).
4. Mapping ke model portal dan simpan ke DB portal.
5. Simpan hasil ke `moodle_sync_logs` untuk jejak operasi.
6. Endpoint status/history dipakai untuk monitoring operasional.

## Slide A5 - Alur ERP Sync Detail
### Isi slide
1. Trigger manual via `/api/superadmin/sync-erp` atau schedule harian.
2. Validasi config `ERP_ENABLED`, URL, API key, timeout.
3. Fetch data employee dari API ERP.
4. Upsert user berdasarkan `employee_id`.
5. Mapping `access_group -> role`.
6. Logging audit/security untuk hasil sync.

## Slide A6 - Alur AI Request-Response Detail
### Isi slide
1. User kirim message ke endpoint AI (`ai-assistant` atau `chat`).
2. System tarik context user (role, fitur tersedia, history percakapan).
3. Untuk pertanyaan materi, tarik context course/Moodle jika relevan.
4. Request ke Gemini API dengan guardrails prompt.
5. Simpan user message + assistant response ke history DB.
6. Kembalikan response ke client dengan session/conversation id.

## Slide A7 - Realtime Events/Channels
### Isi slide
- Event utama (contoh):
  - `NewDirectMessage`, `NewClassMessage`
  - `NewSupportReply`, `SupportTicketStatusUpdated`
  - `NewEscalationReply`, `EscalationStatusUpdated`
- Channel utama:
  - `conversation.{id}`
  - `user.{userId}.messages`
  - `class-chat.{classId}`
  - `support-ticket.{ticketId}`
  - `escalation-ticket.{ticketId}`
- Tujuan: update UI cepat untuk proses kolaborasi dan support.

## Slide A8 - Risiko Teknis Terbuka dan Mitigasi
### Isi slide
| Risiko | Dampak | Mitigasi prioritas |
|---|---|---|
| Konsistensi authz pada endpoint sensitif | Akses tidak sesuai role | Standarisasi middleware + policy + test authz |
| Hardening komunikasi eksternal | Risiko keamanan integrasi | Enforce SSL verify + secret handling terpusat |
| Konsistensi konfigurasi env | Gangguan runtime | Validasi startup checklist + config contract |
| Drift schema/migration | Deploy gagal | Review migration gate + dry-run sebelum release |

## Slide A9 - Testing Gap dan Rencana Hardening
### Isi slide
- Kondisi saat ini:
  - Test feature didominasi legacy web flow.
  - Coverage API domain besar belum merata.
- Rencana:
  - Tahap 1: auth + role enforcement API.
  - Tahap 2: course/moodle/erp sync flows.
  - Tahap 3: support/escalation + realtime auth channels.
  - Tahap 4: regression suite + smoke test deployment.

## Slide A10 - Backlog Prioritas Berikutnya
### Isi slide
- Prioritas P1 (stabilitas dan keamanan):
  - hardening authz, config, migration gate.
- Prioritas P2 (operasional):
  - observability dashboard, alerting, incident runbook.
- Prioritas P3 (produk):
  - peningkatan pengalaman belajar, analitik lanjutan, optimasi AI context.
- Prioritas P4 (scale):
  - performa, kapasitas, dan standarisasi release pipeline.

## Catatan Penggunaan Appendix
- Appendix dibuka hanya jika audiens meminta detail teknis.
- Untuk audiens bisnis, cukup refer ke Slide A1, A2, A8, A10.
