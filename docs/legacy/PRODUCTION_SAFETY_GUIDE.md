# PRODUCTION SAFETY GUIDE

# Panduan Keamanan untuk Mencegah Data Loss

## 🚨 ATURAN EMAS - WAJIB DIIKUTI

### 1. JANGAN PERNAH gunakan perintah ini di production:

```bash
❌ php artisan migrate:fresh    # DELETES ALL DATA
❌ php artisan migrate:refresh  # DELETES ALL DATA
❌ php artisan db:wipe          # DELETES ALL DATA
❌ DROP TABLE ...               # Sangat berbahaya
```

### 2. SELALU gunakan perintah aman:

```bash
✅ php artisan migrate           # Hanya tambah table baru (SAFE)
✅ php artisan migrate:rollback  # Undo migration terakhir
✅ php artisan migrate:status    # Cek status migration
```

---

## 🔒 PROTEKSI OTOMATIS YANG SUDAH DIIMPLEMENTASIKAN

### 1. Environment Protection

File: `app/Providers/ProductionSafetyProvider.php`

**Apa yang dilakukan:**

- Otomatis BLOCK perintah berbahaya di production
- migrate:fresh akan GAGAL jika APP_ENV=production
- Muncul warning merah jika coba jalankan

**Cara mengecek:**

```bash
php artisan env        # Cek environment sekarang
```

### 2. Safe Migration Command

File: `app/Console/Commands/SafeMigrate.php`

**Cara pakai:**

```bash
# Migration biasa (SAFE)
php artisan migrate:safe

# Fresh migration dengan konfirmasi ganda
php artisan migrate:safe --fresh
```

**Fitur:**

- Konfirmasi ganda untuk operasi berbahaya
- Cek jumlah user sebelum delete
- Block otomatis di production

---

## 📦 BACKUP OTOMATIS

### Setup Backup Harian

#### Windows (Task Scheduler):

```powershell
# 1. Buka Task Scheduler
# 2. Create Basic Task
#    Name: Oracle Daily Backup
#    Trigger: Daily at 2:00 AM
#    Action: Start a program
#    Program: powershell.exe
#    Arguments: -File "C:\laragon\www\plnip-portal\scripts\oracle_backup.ps1"

# 3. Set environment variables (untuk keamanan):
[System.Environment]::SetEnvironmentVariable('DB_USERNAME', 'system', 'Machine')
[System.Environment]::SetEnvironmentVariable('DB_PASSWORD', 'your_password', 'Machine')
[System.Environment]::SetEnvironmentVariable('DB_SID', 'ORCL', 'Machine')
```

#### Linux/Oracle Server (Cron):

```bash
# Edit crontab
crontab -e

# Tambahkan line ini (backup tiap jam 2 pagi)
0 2 * * * /path/to/plnip-portal/scripts/oracle_backup.sh
```

### Manual Backup (Sebelum operasi berisiko):

```bash
# Full backup
expdp system/password@ORCL DIRECTORY=DATA_PUMP_DIR DUMPFILE=before_migration.dmp FULL=Y

# Critical tables only (lebih cepat)
expdp system/password@ORCL DIRECTORY=DATA_PUMP_DIR DUMPFILE=critical_backup.dmp \
  TABLES=USERS,ROLES,PERMISSIONS,ANNOUNCEMENTS,COURSES,SUPPORT_TICKETS
```

### Restore dari Backup:

```bash
# Full restore
impdp system/password@ORCL DIRECTORY=DATA_PUMP_DIR DUMPFILE=backup.dmp FULL=Y

# Restore specific tables
impdp system/password@ORCL DIRECTORY=DATA_PUMP_DIR DUMPFILE=backup.dmp \
  TABLES=USERS,ROLES,PERMISSIONS TABLE_EXISTS_ACTION=REPLACE
```

---

## ✅ CHECKLIST SEBELUM MIGRATION

Sebelum menjalankan migration di production, WAJIB cek:

```
□ 1. Environment sudah benar?
     php artisan env (harus production)

□ 2. Backup sudah dibuat?
     Cek file backup di C:\oracle\backups

□ 3. Migration sudah ditest di staging?
     Jalankan dulu di staging environment

□ 4. Pakai perintah yang SAFE?
     Gunakan: php artisan migrate (bukan migrate:fresh)

□ 5. Ada rollback plan?
     Simpan backup sebelum migration

□ 6. Database connection benar?
     Cek config/database.php

□ 7. Notifikasi team?
     Inform team sebelum downtime

□ 8. Maintenance mode ON?
     php artisan down
```

---

## 🛡️ ADDITIONAL SAFEGUARDS

### 1. Enable Oracle Flashback Database

```sql
-- Login as SYSDBA
sqlplus / as sysdba

-- Enable Flashback
ALTER DATABASE FLASHBACK ON;

-- Set retention to 24 hours
ALTER SYSTEM SET DB_FLASHBACK_RETENTION_TARGET=1440;

-- Verify
SELECT FLASHBACK_ON FROM V$DATABASE;
```

### 2. Create Restore Points (Sebelum operasi besar)

```sql
-- Create restore point
CREATE RESTORE POINT before_migration GUARANTEE FLASHBACK DATABASE;

-- Jika ada masalah, restore:
SHUTDOWN IMMEDIATE;
STARTUP MOUNT;
FLASHBACK DATABASE TO RESTORE POINT before_migration;
ALTER DATABASE OPEN RESETLOGS;

-- Drop restore point setelah selesai
DROP RESTORE POINT before_migration;
```

### 3. Increase Undo Retention

```sql
-- Set undo retention to 24 hours (for flashback query)
ALTER SYSTEM SET UNDO_RETENTION=86400;
```

---

## 🚀 DEPLOYMENT WORKFLOW (SAFE)

### Workflow yang BENAR:

```bash
# 1. Backup dulu
php scripts/backup_before_deploy.php

# 2. Enable maintenance mode
php artisan down --message="Updating system, back in 5 minutes"

# 3. Pull code
git pull origin main

# 4. Install dependencies
composer install --no-dev
npm install

# 5. Migration (SAFE - hanya tambah table baru)
php artisan migrate --force

# 6. Clear cache
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# 7. Build frontend
npm run build

# 8. Disable maintenance
php artisan up

# 9. Verify
php artisan health:check
```

---

## 📞 EMERGENCY CONTACTS

Jika terjadi incident:

1. **Stop semua operasi** - Jangan panik
2. **Cek backup terakhir** - C:\oracle\backups
3. **Hubungi DBA** - [contact info]
4. **Inform management** - [contact info]
5. **Document incident** - Catat semua yang terjadi

---

## 📚 TRAINING & DOCUMENTATION

### Mandatory Reading untuk IT Team:

- [ ] Production Safety Guide (dokumen ini)
- [ ] Oracle Backup & Recovery Guide
- [ ] Laravel Migration Best Practices
- [ ] Incident Response Procedure

### Regular Training:

- Monthly: Backup restoration drill
- Quarterly: Incident simulation
- Annually: Full disaster recovery test

---

## 🎯 KESIMPULAN

**3 Aturan Utama:**

1. ✅ **BACKUP** setiap hari otomatis
2. ✅ **JANGAN** gunakan migrate:fresh di production
3. ✅ **CEK** environment sebelum operasi berbahaya

**Ingat:**

- Code bisa diperbaiki
- Server bisa diganti
- **DATA YANG HILANG TIDAK BISA KEMBALI**

---

Dibuat tanggal: 2026-02-02
Setelah incident: migrate:fresh di production Oracle
Untuk mencegah: Human error serupa di masa depan
