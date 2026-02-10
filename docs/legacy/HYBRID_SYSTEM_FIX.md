# HYBRID SYSTEM ANALYSIS

## KONSEP YANG BENAR:

### Master Data: PORTAL (Oracle)
- User dibuat di Portal (manual atau ERP sync)
- Portal punya semua data user lengkap
- Portal adalah single source of truth

### Slave Data: MOODLE (MySQL)
- Moodle cuma untuk e-learning
- User Moodle harus sync dari Portal
- Kalau user di Portal tapi belum di Moodle → AUTO CREATE

## FLOW YANG SALAH SEKARANG:

```
User Manual Create (Portal) → ❌ TIDAK SYNC KE MOODLE
User Klik "Akses Kelas" → Cek Moodle DB → ❌ NOT FOUND → ERROR
```

## FLOW YANG BENAR:

```
User Manual Create (Portal) 
  ↓
✅ Simpan di Oracle (Portal)
  ↓
✅ Auto-sync ke Moodle (create user Moodle via API/DB)
  ↓
User Klik "Akses Kelas"
  ↓
✅ Cek Portal user (sudah login)
  ↓
✅ Cek Moodle user (by email)
  ↓
IF NOT EXISTS → ✅ AUTO CREATE di Moodle
  ↓
✅ Generate Magic Key
  ↓
✅ Redirect ke Moodle (Auto Login)
```

## YANG HARUS DIFIX:

1. **UserService::createUserManual** 
   - Tambah sync ke Moodle setelah create di Portal
   
2. **MoodleAuthController::getLoginUrl**
   - Kalau user di Portal tapi belum di Moodle
   - AUTO CREATE user di Moodle dulu
   - Baru generate key & login

3. **Background Job (optional)**
   - Cron job sync semua Portal users ke Moodle
   - Biar ga perlu real-time sync
