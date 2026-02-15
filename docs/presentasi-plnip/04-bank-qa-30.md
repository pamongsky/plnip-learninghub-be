# Bank Q&A (30 Pertanyaan) - Presentasi PLN IP

Format jawaban:
- `Jawaban singkat`: untuk audiens campuran.
- `Jawaban teknis lanjut`: jika diminta deep dive ITD.

## 1) Apa nilai utama portal ini untuk PLN IP?
- Jawaban singkat: Menyatukan proses pembelajaran, komunikasi, dan support dalam satu platform terukur.
- Jawaban teknis lanjut: Backend API mencakup 135 route aktif lintas domain (auth, course, support, messaging, AI, integrasi) sehingga alur operasional tidak lagi terpecah.

## 2) Kenapa tidak langsung pakai LMS saja tanpa portal ini?
- Jawaban singkat: Karena kebutuhan operasional internal lebih luas dari fungsi LMS murni.
- Jawaban teknis lanjut: Portal berfungsi sebagai orchestration layer untuk role governance, support workflow, announcement, direct messaging, dan integrasi ERP.

## 3) Apakah ini sudah siap dipakai?
- Jawaban singkat: Fondasi inti sudah siap untuk alur utama.
- Jawaban teknis lanjut: Domain API utama sudah tersedia; berikutnya fokus ke hardening, test coverage, dan observability untuk scale production penuh.

## 4) Siapa saja yang diuntungkan langsung?
- Jawaban singkat: User pembelajar, admin operasional, instruktur, dan tim teknis.
- Jawaban teknis lanjut: Role model memisahkan hak akses per fungsi agar operasional cepat namun tetap terkontrol.

## 5) Kenapa arsitekturnya dipisah frontend dan backend?
- Jawaban singkat: Agar pengembangan, maintenance, dan scale lebih fleksibel.
- Jawaban teknis lanjut: Pola API-first memudahkan pengembangan frontend terpisah serta membuka peluang integrasi channel lain di masa depan.

## 6) Kenapa pilih Laravel untuk backend?
- Jawaban singkat: Cepat untuk delivery, matang untuk API, dan ekosistem lengkap.
- Jawaban teknis lanjut: Memakai Sanctum, Reverb, Spatie Permission, dan OCI8 untuk kebutuhan auth, realtime, RBAC, dan Oracle compatibility.

## 7) Bagaimana kontrol akses diterapkan?
- Jawaban singkat: Berbasis role dan middleware.
- Jawaban teknis lanjut: `auth:sanctum` + middleware role + validasi domain di controller pada endpoint sensitif.

## 8) Bagaimana sistem menjaga jejak audit?
- Jawaban singkat: Aktivitas penting dicatat untuk akuntabilitas.
- Jawaban teknis lanjut: Aksi seperti login, perubahan user, dan sinkronisasi dapat ditelusuri melalui audit log.

## 9) Bagaimana integrasi Moodle dilakukan?
- Jawaban singkat: Portal terhubung ke Moodle untuk sinkronisasi dan akses konteks pembelajaran.
- Jawaban teknis lanjut: Ada endpoint sync users/courses/enrollments/categories + alur login URL helper.

## 10) Bagaimana integrasi ERP memberi dampak?
- Jawaban singkat: Onboarding user dan pemetaan role jadi lebih cepat dan konsisten.
- Jawaban teknis lanjut: Sync user berbasis employee data, lalu mapping access group ke role aplikasi.

## 11) Apakah AI assistant aman digunakan?
- Jawaban singkat: Ya, dengan batasan penggunaan dan guardrails.
- Jawaban teknis lanjut: AI difokuskan untuk bantuan belajar/navigasi; data sensitif tetap dibatasi dan kasus teknis formal diarahkan ke ticketing.

## 12) Apakah AI bisa menggantikan instruktur/admin?
- Jawaban singkat: Tidak, AI hanya asisten percepatan.
- Jawaban teknis lanjut: Workflow otoritatif tetap ada di role admin/instruktur serta proses support resmi.

## 13) Apa kelebihan fitur realtime di sistem ini?
- Jawaban singkat: Mempercepat respon dan mengurangi komunikasi tercecer.
- Jawaban teknis lanjut: Event-channel untuk DM, class chat, support, dan escalation memberi update langsung pada pihak terkait.

## 14) Bagaimana jika integrasi eksternal sedang down?
- Jawaban singkat: Operasional inti portal tetap bisa berjalan terbatas.
- Jawaban teknis lanjut: Integrasi diperlakukan sebagai dependency eksternal; alur fallback dipersiapkan melalui graceful handling dan logging.

## 15) Apakah data personal aman?
- Jawaban singkat: Prinsipnya minimum exposure dan akses berbasis role.
- Jawaban teknis lanjut: Endpoint dilindungi auth, akses dibatasi role, dan penyajian data mengikuti kebutuhan fungsi.

## 16) Bagaimana mekanisme sertifikat berjalan?
- Jawaban singkat: Sertifikat dapat dikelola dan didistribusikan melalui modul certificate.
- Jawaban teknis lanjut: Ada endpoint user certificate, download, revoke, serta upload single/bulk oleh role tertentu.

## 17) Bagaimana manajemen pengumuman berjalan?
- Jawaban singkat: Pengumuman bisa ditargetkan sesuai peran dan fungsi.
- Jawaban teknis lanjut: Tersedia domain super-admin/admin/instruktur dengan kontrol akses terpisah.

## 18) Apa saja indikator kematangan teknis saat ini?
- Jawaban singkat: Cakupan domain luas dan struktur modular sudah terbentuk.
- Jawaban teknis lanjut: 22 API controller, 24 model, 38 migration, 9 event realtime, dan 3 integrasi strategis aktif.

## 19) Kenapa masih perlu roadmap lanjutan?
- Jawaban singkat: Agar dari "berjalan" naik menjadi "stabil, terukur, dan scalable".
- Jawaban teknis lanjut: Fase berikutnya fokus hardening, coverage test API, observability, dan optimasi performa.

## 20) Risiko terbesar saat handover pasca PKL apa?
- Jawaban singkat: Risiko knowledge gap jika transisi tidak terstruktur.
- Jawaban teknis lanjut: Perlu dokumentasi operasional, ownership per domain, dan backlog prioritas yang disepakati lintas fungsi.

## 21) Bagaimana strategi testing ke depan?
- Jawaban singkat: Prioritaskan API kritikal dulu, lalu perluas regression.
- Jawaban teknis lanjut: Tahap awal fokus authz, sync flow, support/escalation, lalu smoke test release.

## 22) Bagaimana sistem dipantau di operasional harian?
- Jawaban singkat: Melalui logging, status endpoint, dan history proses.
- Jawaban teknis lanjut: Domain sync menyediakan status/history; penguatan alerting dashboard ada pada tahap roadmap berikutnya.

## 23) Apa target jangka pendek paling penting?
- Jawaban singkat: Stabilitas dan keamanan akses.
- Jawaban teknis lanjut: Standardisasi authz, validasi konfigurasi, migration safety gate, dan test coverage dasar.

## 24) Apakah ini bisa diintegrasikan ke sistem lain ke depan?
- Jawaban singkat: Bisa, karena pendekatan API-first.
- Jawaban teknis lanjut: Struktur domain dan service sudah dipisah sehingga lebih mudah menambah adaptor/integrasi baru.

## 25) Apakah deployment dan maintenance sudah dipikirkan?
- Jawaban singkat: Ya, termasuk aspek safety operasional.
- Jawaban teknis lanjut: Ada command safety dan script backup operasional; tahap lanjut menambahkan standar release dan observability lebih ketat.

## 26) Bagaimana memastikan perubahan role tidak semrawut?
- Jawaban singkat: Semua berbasis role governance dan audit.
- Jawaban teknis lanjut: Role/permission management terpusat pada domain super-admin dengan endpoint dedicated.

## 27) Apa bukti bahwa ini bukan sekadar prototype?
- Jawaban singkat: Domain fitur yang terhubung sudah end-to-end dan lintas fungsi.
- Jawaban teknis lanjut: Bukan hanya UI, tetapi API, data model, integrasi eksternal, realtime, dan workflow support sudah berjalan.

## 28) Bagaimana pendekatan keamanan saat presentasi data?
- Jawaban singkat: Pakai angka agregat/disamarkan, tanpa menampilkan data sensitif.
- Jawaban teknis lanjut: Main deck hanya expose metrik level organisasi; detail sensitif tetap di lingkungan internal terbatas.

## 29) Jika harus pilih 1 prioritas kolaborasi setelah presentasi, apa itu?
- Jawaban singkat: Penyelarasan backlog prioritas lintas HCIS/HCD/ITD.
- Jawaban teknis lanjut: Kunci keberlanjutan adalah owner per domain + milestone hardening 3 tahap yang disepakati bersama.

## 30) Apa pesan akhir untuk manajemen?
- Jawaban singkat: Fondasi digital learning operasional sudah terbentuk dan siap ditingkatkan menjadi standar internal.
- Jawaban teknis lanjut: Nilai sistem akan naik signifikan jika fase berikutnya fokus pada stabilitas, observability, dan scale governance.

## Quick Tip Menjawab Saat Q&A
- Jawab bisnis dulu, teknis setelah diminta.
- Hindari istilah terlalu teknis di kalimat pertama.
- Tutup setiap jawaban dengan dampak ke operasional PLN IP.
