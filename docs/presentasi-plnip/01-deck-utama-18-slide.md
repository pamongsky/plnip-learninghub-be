# Deck Utama (18 Slide) - Presentasi PLN Indonesia Power

Target durasi: `30 menit`.
Format: `business-first`, detail teknis seperlunya.

## Slide 1 - Judul, Identitas, Konteks PKL
### Isi slide
- Judul: `PLN IP Learning Hub - Implementasi Portal Pembelajaran Terintegrasi`
- Nama presenter, unit PKL, periode PKL.
- Audiens: Direktorat MHCA (HCIS/HCD-TLD) + ITD.
- One-line value: `Satu portal untuk pembelajaran, komunikasi, dukungan, dan monitoring berbasis data`.

### Speaker notes (60-90 detik)
- Buka dengan konteks singkat bahwa sistem ini dibangun dari nol selama PKL untuk menjawab kebutuhan pembelajaran internal yang sebelumnya tersebar.
- Tegaskan bahwa presentasi ini fokus pada dua hal: dampak operasional untuk HCIS/HCD dan kesiapan teknis untuk scale bersama ITD.
- Set ekspektasi agenda: masalah awal, solusi yang dibangun, hasil saat ini, dan roadmap lanjutan.

## Slide 2 - Latar Belakang Masalah Sebelum Sistem
### Isi slide
- Proses pembelajaran tersebar di beberapa kanal.
- Monitoring peserta, progres, dan sertifikat tidak terpusat.
- Eskalasi pertanyaan/issue belum terstandar.
- Sinkronisasi data user lintas sistem masih manual.

### Speaker notes (90-120 detik)
- Jelaskan pain point dari sisi operasional: tim harus berpindah tools, data sulit ditarik cepat untuk kebutuhan evaluasi dan tindak lanjut.
- Tekankan dampak langsung: waktu respon lambat, potensi duplikasi, dan kesulitan audit data.
- Hubungkan ke kebutuhan organisasi: dibutuhkan platform tunggal dengan kontrol akses, jejak aktivitas, dan integrasi ke LMS/ERP.

## Slide 3 - Tujuan Proyek dan Ruang Lingkup
### Isi slide
- Tujuan utama:
  - Sentralisasi proses pembelajaran internal.
  - Integrasi LMS Moodle, data ERP, dan AI assistant.
  - Percepatan komunikasi user-admin-instruktur.
- Ruang lingkup versi saat ini:
  - Backend API terintegrasi.
  - Role-based access dan audit log.
  - Dukungan realtime chat/ticket/escalation.

### Speaker notes (60-90 detik)
- Jelaskan bahwa target proyek bukan sekadar portal tampilan, tetapi platform layanan pembelajaran end-to-end.
- Tegaskan batasan versi saat ini: fokus pada fondasi backend dan fitur inti bernilai tinggi.
- Sampaikan bahwa desain sejak awal disiapkan modular agar bisa diteruskan oleh tim internal setelah PKL selesai.

## Slide 4 - Hasil Akhir High-Level (Current State)
### Isi slide
- Platform backend siap operasional untuk domain utama pembelajaran.
- API aktif: `130+ endpoint` (exact internal: 135).
- Cakupan domain: auth, user-role, course, announcement, certificate, support, messaging, AI, CMS.
- Integrasi berjalan: Moodle, ERP, Gemini AI.

### Speaker notes (60-90 detik)
- Berikan pesan bahwa fondasi sistem sudah berjalan sebagai platform, bukan prototype parsial.
- Sebutkan angka disamarkan untuk menegaskan skala implementasi.
- Tekankan kesiapan lintas fungsi: operasional pembelajaran, administrasi, dan dukungan teknis sudah dalam satu ekosistem.

## Slide 5 - Arsitektur Sistem Ringkas
### Isi slide
- Arsitektur terpisah: frontend (repo terpisah) dan backend API (Laravel).
- Komponen backend:
  - Auth + role permission (Sanctum + Spatie).
  - Realtime (Reverb + events/channels).
  - Integrasi Moodle (DB + Web Service).
  - Integrasi ERP (sinkronisasi user).
- Database utama: Oracle.

### Speaker notes (90-120 detik)
- Jelaskan keputusan arsitektur split frontend-backend: memudahkan parallel development, maintainability, dan opsi ekspansi kanal.
- Tekankan alasan memilih API-first: interoperabilitas dengan aplikasi lain ke depan.
- Jelaskan bahwa pemisahan domain service membuat onboarding tim ITD nanti lebih mudah.

## Slide 6 - Fitur Inti untuk User Pembelajar
### Isi slide
- Login dan akses profil terpusat.
- Lihat kelas, progres, dan sertifikat.
- Interaksi pembelajaran lewat class chat.
- Buat support ticket jika ada kendala.
- Akses AI assistant untuk bantuan belajar dan navigasi platform.

### Speaker notes (60-90 detik)
- Posisikan fitur user sebagai pengalaman pembelajaran yang lebih cepat dan jelas.
- Sorot tiga nilai: transparansi progres, kemudahan dukungan, dan bantuan belajar kontekstual.
- Tegaskan bahwa alur dibuat sederhana agar adopsi user non-teknis tetap tinggi.

## Slide 7 - Fitur Inti untuk Admin/Instruktur/Super Admin
### Isi slide
- Manajemen user, role, permission, dan audit aktivitas.
- Manajemen pengumuman (global/admin/instruktur).
- Pengelolaan enrollment, role di course, dan tracking progres.
- Moderasi support ticket dan escalation workflow.
- Pengelolaan konten CMS landing page.

### Speaker notes (90-120 detik)
- Jelaskan diferensiasi per role: siapa melakukan apa, dan kenapa penting untuk governance.
- Tekankan kontrol akses sebagai kunci agar proses cepat tetapi tetap terjaga.
- Tunjukkan bahwa alur escalation membuat penanganan isu tidak berhenti di satu level.

## Slide 8 - Integrasi Moodle: Nilai Bisnis
### Isi slide
- Fitur: login URL/SSO helper, sinkronisasi user-course-enrollment, akses struktur materi.
- Dampak:
  - Mengurangi input manual berulang.
  - Menjaga konsistensi data pembelajaran.
  - Mempercepat readiness laporan progres.

### Speaker notes (90-120 detik)
- Jelaskan bahwa Moodle tetap jadi LMS inti, sementara portal ini menjadi layer orkestrasi dan operasional.
- Sorot manfaat untuk tim HCIS/HCD: monitoring lebih cepat tanpa tarik data manual berulang.
- Sampaikan bahwa strategi integrasi dibuat hybrid agar pragmatis terhadap kondisi sistem existing.

## Slide 9 - Integrasi ERP: Dampak Operasional
### Isi slide
- Sinkronisasi data user berbasis employee record.
- Mapping akses group ke role aplikasi.
- Trigger sinkronisasi terjadwal dan manual.
- Dampak: onboarding user lebih cepat, minim mismatch role.

### Speaker notes (60-90 detik)
- Jelaskan peran ERP sebagai source of truth identitas pegawai.
- Tekankan pengurangan pekerjaan administratif karena role bisa diturunkan dari data sumber.
- Hubungkan ke aspek kontrol: perubahan data lebih mudah diaudit karena jejak sinkronisasi tersedia.

## Slide 10 - AI Assistant: Use Case dan Batasan
### Isi slide
- Use case:
  - Bantuan belajar umum.
  - Bantu navigasi fitur platform.
  - Bantu memahami konteks materi course.
- Batasan:
  - Tidak menampilkan data sensitif internal.
  - Untuk isu teknis kritis tetap lewat support ticket.

### Speaker notes (60-90 detik)
- Posisikan AI sebagai akselerator produktivitas belajar, bukan pengganti proses formal.
- Tegaskan guardrail: AI dibatasi agar aman dan tetap sesuai governance.
- Jelaskan nilai praktis: mempercepat jawaban pertanyaan rutin sehingga tim dapat fokus pada kasus yang bernilai tinggi.

## Slide 11 - Realtime Communication dan Manfaat Proses
### Isi slide
- Channel utama:
  - Direct message antar user sesuai role.
  - Class chat untuk konteks pembelajaran.
  - Support ticket reply dan escalation reply realtime.
- Manfaat:
  - Respon lebih cepat.
  - Jejak komunikasi lebih terstruktur.

### Speaker notes (60-90 detik)
- Jelaskan bahwa realtime bukan fitur kosmetik, tetapi pengurang waktu tunggu operasional.
- Tekankan bahwa komunikasi tertaut ke entitas bisnis (kelas/tiket), sehingga mudah ditelusuri saat evaluasi.
- Hubungkan ke pengalaman user: tidak perlu pindah kanal eksternal untuk tindak lanjut.

## Slide 12 - Keamanan, Kontrol Akses, Audit Trail
### Isi slide
- Autentikasi token-based via Sanctum.
- Role middleware + permission model per domain.
- Audit log untuk aktivitas penting.
- Prinsip presentasi: tidak menampilkan detail kredensial/token/internal endpoint sensitif.

### Speaker notes (90-120 detik)
- Jelaskan bahwa keamanan dijalankan pada level akses dan jejak aktivitas.
- Tekankan pemisahan hak akses untuk mencegah penyalahgunaan dan menjaga kepatuhan operasional.
- Sampaikan bahwa area hardening berkelanjutan sudah teridentifikasi dan dimasukkan ke roadmap.

## Slide 13 - Stabilitas dan Operasional
### Isi slide
- Praktik operasional yang sudah disiapkan:
  - Command safety untuk mencegah operasi destruktif.
  - Script backup Oracle (operational tooling).
  - Logging dan monitoring dasar.
- Tujuan: mengurangi risiko saat deploy dan maintenance.

### Speaker notes (60-90 detik)
- Tekankan bahwa operasional bukan tahap akhir, tapi bagian desain awal sistem.
- Jelaskan value untuk organisasi: continuity lebih terjaga dan risiko human error lebih rendah.
- Sampaikan bahwa penguatan monitoring lanjutan ada di backlog prioritas.

## Slide 14 - Data dan Metrik Hasil (Disamarkan)
### Isi slide
- Cakupan implementasi saat ini:
  - `130+` API endpoint aktif.
  - `20+` API controller domain bisnis.
  - `20+` model data inti.
  - `3` integrasi strategis (Moodle, ERP, AI).
- KPI operasional (isi final sebelum presentasi):
  - Waktu respon tiket: `[isi angka baseline vs current]`
  - Waktu onboarding user: `[isi angka baseline vs current]`
  - Waktu kompilasi laporan progres: `[isi baseline vs current]`

### Speaker notes (90-120 detik)
- Buka dengan metrik implementasi teknis untuk menunjukkan skala kerja yang sudah dicapai.
- Lalu geser ke KPI bisnis-operasional sebagai bahasa utama audiens HCIS/HCD.
- Tegaskan bahwa angka KPI ditampilkan dalam format agregat/disamarkan sesuai kebijakan internal.

## Slide 15 - Tantangan Utama dan Keputusan Engineering
### Isi slide
- Tantangan:
  - Integrasi multi-sistem dengan model data berbeda.
  - Menjaga kecepatan delivery sambil tetap menjaga kontrol akses.
  - Menyamakan kebutuhan operasional dan kebutuhan teknis.
- Keputusan engineering:
  - API-first modular.
  - Role-based control sejak awal.
  - Real-time pada proses kritikal.

### Speaker notes (90-120 detik)
- Jelaskan trade-off nyata yang dihadapi selama implementasi.
- Berikan contoh keputusan yang diambil karena kebutuhan bisnis, bukan semata preferensi teknis.
- Tutup dengan pesan bahwa keputusan tersebut menurunkan risiko saat sistem di-scale oleh tim internal.

## Slide 16 - Lessons Learned
### Isi slide
- Teknis:
  - Integrasi harus dimulai dari data contract yang jelas.
  - Logging dan observability penting sejak awal.
- Kolaborasi:
  - Validasi rutin dengan user operasional mempercepat ketepatan fitur.
  - Komunikasi lintas fungsi mengurangi rework.

### Speaker notes (60-90 detik)
- Presentasikan lesson learned sebagai aset organisasi, bukan catatan personal.
- Sorot dua hal: disiplin teknis dan disiplin komunikasi.
- Tekankan bahwa pembelajaran ini bisa menjadi template implementasi sistem internal berikutnya.

## Slide 17 - Roadmap 3 Tahap Pasca PKL
### Isi slide
- Tahap 1 (0-1 bulan): hardening akses, validasi konfigurasi, perapihan quality gate.
- Tahap 2 (1-3 bulan): perluasan test API, observability, dan dashboard operasional.
- Tahap 3 (3-6 bulan): optimasi performa, governance data, dan fitur lanjutan pembelajaran.

### Speaker notes (90-120 detik)
- Jelaskan roadmap sebagai transisi ownership dari fase build ke fase institutionalization.
- Tegaskan prioritas berurutan: stabilitas dulu, visibilitas operasional berikutnya, lalu scale dan optimasi.
- Kaitkan dengan kolaborasi HCIS/HCD/ITD agar keberlanjutan sistem tidak bergantung satu orang.

## Slide 18 - Penutup: Value untuk HCIS/HCD/ITD
### Isi slide
- Value untuk HCIS/HCD:
  - Operasional pembelajaran lebih terukur dan cepat ditindaklanjuti.
- Value untuk ITD:
  - Fondasi arsitektur siap dikembangkan lebih lanjut.
- Ajakan kolaborasi:
  - Finalisasi KPI, prioritas backlog, dan ownership pasca PKL.

### Speaker notes (60-90 detik)
- Tutup dengan tiga pesan: sistem sudah punya fondasi nyata, dampak operasional sudah terlihat, dan langkah lanjut sudah terstruktur.
- Minta dukungan lintas fungsi untuk fase berikutnya agar nilai sistem terus naik.
- Arahkan audiens ke sesi tanya jawab dan tawarkan deep dive melalui appendix.

## Alokasi Waktu Per Slide (30 Menit)
- Slide 1-4: 6 menit
- Slide 5-9: 9 menit
- Slide 10-14: 9 menit
- Slide 15-18: 6 menit
