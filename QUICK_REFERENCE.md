# ⚠️ QUICK REFERENCE - PRODUCTION SAFETY

## 🚨 EMERGENCY: Data Loss Terjadi?

### LANGKAH PERTAMA (JANGAN PANIK):
1. **STOP** semua operasi database
2. **JANGAN** jalankan command apapun lagi
3. Hubungi DBA segera
4. Cek backup terakhir

### Recovery Steps:
```bash
# 1. Cek recycle bin
php scripts/check_recycle_bin.php

# 2. Restore dari backup
impdp system/password@ORCL DIRECTORY=DATA_PUMP_DIR \
  DUMPFILE=latest_backup.dmp FULL=Y

# 3. Atau gunakan Flashback (jika enabled)
FLASHBACK TABLE users TO BEFORE DROP;
```

---

## ✅ ATURAN EMAS - HAFAL INI!

### ❌ JANGAN PERNAH di Production:
```bash
❌ php artisan migrate:fresh     # DELETES ALL DATA
❌ php artisan migrate:refresh   # DELETES ALL DATA
❌ php artisan db:wipe           # DELETES ALL DATA
❌ DROP TABLE ...                # Bahaya!
❌ TRUNCATE TABLE ...            # Hapus semua data!
```

### ✅ SELALU Gunakan Ini:
```bash
✅ php artisan migrate            # SAFE - hanya tambah table
✅ php artisan migrate:safe       # EXTRA SAFE dengan konfirmasi
✅ php artisan migrate:rollback   # Undo last migration
```

---

## 📋 CHECKLIST SEBELUM MIGRATION

**Print dan tempel di monitor!**

```
□ Environment = production? (php artisan env)
□ Backup sudah dibuat? (cek C:\oracle\backups)
□ Test di staging dulu?
□ Pakai command SAFE? (migrate, bukan migrate:fresh)
□ Maintenance mode ON? (php artisan down)
□ Team sudah diinform?
□ Rollback plan ready?
```

---

## 🔧 SETUP PERTAMA KALI

### 1. Setup Backup Otomatis (WAJIB):
```powershell
# Run as Administrator
cd C:\laragon\www\plnip-portal\scripts
.\setup_backup.ps1
```

### 2. Enable Flashback Database:
```sql
sqlplus / as sysdba
ALTER DATABASE FLASHBACK ON;
ALTER SYSTEM SET DB_FLASHBACK_RETENTION_TARGET=1440;
```

### 3. Verify Protections:
```bash
php scripts/verify_safety.php
```

---

## 🚀 DEPLOYMENT WORKFLOW

### Workflow AMAN (Copy ini):
```bash
# 1. BACKUP DULU!
expdp system/password@ORCL DIRECTORY=DATA_PUMP_DIR \
  DUMPFILE=before_deploy_$(date +%Y%m%d).dmp FULL=Y

# 2. Maintenance mode
php artisan down --message="Updating, back in 5 min"

# 3. Pull code
git pull origin main

# 4. Dependencies
composer install --no-dev
npm ci

# 5. SAFE Migration
php artisan migrate --force

# 6. Cache clear
php artisan optimize:clear

# 7. Build
npm run build

# 8. Done
php artisan up
```

---

## 💾 BACKUP COMMANDS

### Manual Backup (Sebelum operasi berisiko):
```bash
# Full backup (semua data)
expdp system/password@ORCL \
  DIRECTORY=DATA_PUMP_DIR \
  DUMPFILE=emergency_backup.dmp \
  FULL=Y

# Quick backup (table penting aja)
expdp system/password@ORCL \
  DIRECTORY=DATA_PUMP_DIR \
  DUMPFILE=critical_$(date +%Y%m%d).dmp \
  TABLES=USERS,ROLES,PERMISSIONS,ANNOUNCEMENTS,COURSES
```

### Restore dari Backup:
```bash
# Full restore
impdp system/password@ORCL \
  DIRECTORY=DATA_PUMP_DIR \
  DUMPFILE=backup.dmp \
  FULL=Y

# Restore specific table
impdp system/password@ORCL \
  DIRECTORY=DATA_PUMP_DIR \
  DUMPFILE=backup.dmp \
  TABLES=USERS \
  TABLE_EXISTS_ACTION=REPLACE
```

---

## 🔍 MONITORING

### Cek Status Backup:
```powershell
# Cek scheduled task
Get-ScheduledTask -TaskName "Oracle_Daily_Backup_PLNIP"

# Run manual
Start-ScheduledTask -TaskName "Oracle_Daily_Backup_PLNIP"

# Cek log terakhir
Get-Content C:\oracle\backups\backup_*.log | Select-Object -Last 50
```

### Cek Backup Files:
```powershell
# List backups
Get-ChildItem "C:\oracle\backups\*.dmp" | 
  Select-Object Name, Length, LastWriteTime | 
  Sort-Object LastWriteTime -Descending

# Size total
(Get-ChildItem "C:\oracle\backups\*.dmp" | 
  Measure-Object -Property Length -Sum).Sum / 1GB
```

---

## 📞 CONTACTS

**Jika ada masalah:**
- DBA Oracle: [phone/email]
- IT Manager: [phone/email]  
- DevOps Team: [phone/email]
- Emergency Line: [phone]

**Dokumentasi:**
- Full Guide: `PRODUCTION_SAFETY_GUIDE.md`
- Backup Scripts: `scripts/oracle_backup.ps1`
- Verification: `scripts/verify_safety.php`

---

## 🎯 REMEMBER

### 3 Aturan Utama:
1. ✅ **BACKUP SETIAP HARI** (automated)
2. ✅ **JANGAN migrate:fresh di production** (BLOCKED)
3. ✅ **CEK ENVIRONMENT** sebelum operasi (php artisan env)

### Jika Ragu:
- ❓ Tidak yakin? → **JANGAN lakukan!**
- ❓ Butuh reset database? → **Hubungi DBA**
- ❓ Ada error? → **Backup dulu baru fix**

---

**Print halaman ini dan tempel di meja kerja!**

Last Updated: 2026-02-02
After Incident: migrate:fresh in production
Never Again! 🛡️
