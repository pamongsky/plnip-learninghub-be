# 💾 AUTO BACKUP - PENJELASAN LENGKAP

## 📖 APA ITU AUTO BACKUP?

Auto backup = **Windows Task Scheduler** yang jalankan script backup Oracle **otomatis setiap hari jam 2 pagi**.

### Cara Kerjanya:
```
1. Jam 2 pagi → Windows Task Scheduler terbangun
2. Jalankan script → oracle_backup.ps1
3. Script export database → file .dmp (Oracle Data Pump)
4. Simpan di folder → C:\oracle\backups\
5. Log hasil → backup_YYYYMMDD_HHMMSS.log
6. Hapus backup lama → (lebih dari 7 hari)
7. Selesai → tidur lagi sampai besok jam 2 pagi
```

**Anda tidak perlu ngapa-ngapain. Semua otomatis!**

---

## 🚀 SETUP AUTO BACKUP (Langkah-langkah)

### Step 1: Jalankan Script Setup

```powershell
# 1. Buka PowerShell AS ADMINISTRATOR
#    (Klik kanan PowerShell → Run as Administrator)

# 2. Pindah ke folder scripts
cd C:\laragon\www\plnip-portal\scripts

# 3. Jalankan setup
.\setup_backup.ps1
```

### Step 2: Isi Informasi yang Ditanya

Script akan tanya:
```
Enter Oracle username: system         ← Isi username Oracle
Enter Oracle password: ******         ← Isi password Oracle
Enter Oracle SID: ORCL                ← Isi SID database (biasanya ORCL)
```

### Step 3: Test Backup (Optional)

Script akan tanya:
```
Do you want to run a test backup now? (y/n): y
```

Ketik `y` untuk test backup langsung (recommended).

### Step 4: Selesai!

Output:
```
✅ BACKUP AUTOMATION SETUP COMPLETE!

📋 Summary:
   • Task Name: Oracle_Daily_Backup_PLNIP
   • Schedule: Daily at 2:00 AM
   • Backup Location: C:\oracle\backups
   • Retention: 7 days
```

**DONE!** Sekarang backup akan jalan otomatis setiap hari.

---

## 🔍 CEK BACKUP BERJALAN ATAU TIDAK

### Cara 1: Cek Scheduled Task
```powershell
# Cek status task
Get-ScheduledTask -TaskName "Oracle_Daily_Backup_PLNIP"

# Output:
# TaskName                 State
# --------                 -----
# Oracle_Daily_Backup_PLNIP Ready  ← Artinya siap jalan
```

### Cara 2: Cek File Backup
```powershell
# List semua backup
Get-ChildItem C:\oracle\backups\*.dmp | 
    Select-Object Name, @{Name="Size(MB)";Expression={[math]::Round($_.Length/1MB,2)}}, LastWriteTime

# Output:
# Name                          Size(MB) LastWriteTime
# ----                          -------- -------------
# plnip_backup_20260202_020005.dmp  125.45   2/2/2026 2:05:23 AM
# plnip_backup_20260201_020003.dmp  124.32   2/1/2026 2:04:15 AM
```

### Cara 3: Cek Log Backup
```powershell
# Baca log backup terakhir
Get-Content C:\oracle\backups\backup_*.log | Select-Object -Last 20

# Output akan show:
# [2026-02-02 02:00:05] ━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# [2026-02-02 02:00:05] 🔄 Oracle Backup Started
# [2026-02-02 02:05:23] ✓ Data Pump export completed
# [2026-02-02 02:05:23] ✅ Backup Completed Successfully
```

---

## 🧪 TEST BACKUP MANUAL (Sekarang)

Kalau mau test backup jalan sekarang (tidak tunggu jam 2 pagi):

```powershell
# Jalankan task sekarang
Start-ScheduledTask -TaskName "Oracle_Daily_Backup_PLNIP"

# Tunggu beberapa menit...

# Cek status
Get-ScheduledTaskInfo -TaskName "Oracle_Daily_Backup_PLNIP"

# Output:
# LastRunTime        : 2/2/2026 1:30:45 PM  ← Kapan terakhir jalan
# LastTaskResult     : 0                    ← 0 = success
# NextRunTime        : 2/3/2026 2:00:00 AM  ← Kapan jalan lagi
```

---

## 📂 APA YANG DIBACKUP?

Script backup ini export **SEMUA DATA** Oracle:

### 1. Full Backup (plnip_backup_*.dmp)
- ✓ Semua table (USERS, ROLES, PERMISSIONS, dll)
- ✓ Semua data
- ✓ Semua index & constraint
- ✓ Semua procedure & function
- ✓ Compressed (hemat space)

### 2. Critical Tables Backup (plnip_critical_*.dmp)
- ✓ USERS
- ✓ ROLES
- ✓ PERMISSIONS
- ✓ ANNOUNCEMENTS
- ✓ COURSES
- ✓ SUPPORT_TICKETS

**Kenapa 2 backup?**
- Full backup = restore complete (tapi besar & lama)
- Critical backup = restore cepat (table penting aja)

---

## 🔄 RESTORE DARI BACKUP

Kalau suatu saat perlu restore (semoga ga perlu lagi!):

### Restore Full Database:
```bash
impdp system/password@ORCL \
  DIRECTORY=DATA_PUMP_DIR \
  DUMPFILE=plnip_backup_20260202_020005.dmp \
  FULL=Y
```

### Restore Table Tertentu:
```bash
impdp system/password@ORCL \
  DIRECTORY=DATA_PUMP_DIR \
  DUMPFILE=plnip_backup_20260202_020005.dmp \
  TABLES=USERS,ROLES,PERMISSIONS \
  TABLE_EXISTS_ACTION=REPLACE
```

---

## ⚙️ KONFIGURASI (Optional)

Kalau mau ubah setting backup, edit file `scripts/oracle_backup.ps1`:

```powershell
# Ubah jadwal backup (default: 2 AM)
# Edit di Windows Task Scheduler:
# 1. Buka taskschd.msc
# 2. Cari "Oracle_Daily_Backup_PLNIP"
# 3. Klik kanan → Properties → Triggers → Edit

# Ubah retention (default: 7 hari)
# Edit di oracle_backup.ps1 line 7:
$RETENTION_DAYS = 14  # Simpan 14 hari

# Ubah lokasi backup (default: C:\oracle\backups)
# Edit di oracle_backup.ps1 line 6:
$BACKUP_DIR = "D:\backups\oracle"
```

---

## 🎯 ANALOGI SEDERHANA

**Auto Backup itu kayak alarm HP:**

1. **Set alarm** = Setup script (sekali aja)
2. **Jam berbunyi** = Windows Task Scheduler terbangun jam 2 pagi
3. **Bangun & foto** = Export database ke file .dmp
4. **Simpan foto** = Save di folder backups
5. **Tidur lagi** = Tunggu besok jam 2 pagi
6. **Hapus foto lama** = Delete backup >7 hari (biar ga penuh disk)

**Anda cuma perlu set alarm sekali. Sisanya otomatis!**

---

## 📊 MONITORING BACKUP

### Setiap Minggu, Cek:
```powershell
# 1. Cek backup berjalan normal
Get-ScheduledTaskInfo -TaskName "Oracle_Daily_Backup_PLNIP"

# 2. Cek file backup ada (7 file = 7 hari)
(Get-ChildItem C:\oracle\backups\plnip_backup_*.dmp).Count

# 3. Cek size backup normal (tidak 0 KB)
Get-ChildItem C:\oracle\backups\plnip_backup_*.dmp | 
    Measure-Object -Property Length -Sum |
    Select-Object @{Name="TotalSize(GB)";Expression={$_.Sum/1GB}}
```

### Setiap Bulan, Test:
```powershell
# Test restore backup ke database test
# (Jangan restore ke production!)
impdp system/password@TESTDB \
  DIRECTORY=DATA_PUMP_DIR \
  DUMPFILE=plnip_backup_latest.dmp \
  REMAP_SCHEMA=original:test
```

---

## ⚠️ TROUBLESHOOTING

### Problem 1: Task tidak jalan
```powershell
# Cek status task
Get-ScheduledTask -TaskName "Oracle_Daily_Backup_PLNIP"

# Kalau "Disabled", enable:
Enable-ScheduledTask -TaskName "Oracle_Daily_Backup_PLNIP"
```

### Problem 2: Backup gagal (LastTaskResult ≠ 0)
```powershell
# Cek log error
Get-Content C:\oracle\backups\backup_*.log | Select-Object -Last 50

# Cek credential Oracle masih valid
echo $env:DB_USERNAME
echo $env:DB_SID
```

### Problem 3: Disk penuh
```powershell
# Cek space disk
Get-PSDrive C | Select-Object Used,Free

# Kurangi retention (hapus backup lebih cepat)
# Edit oracle_backup.ps1:
$RETENTION_DAYS = 3  # Simpan cuma 3 hari
```

---

## 📞 SUPPORT

**Kalau ada masalah:**
1. Cek log: `C:\oracle\backups\backup_*.log`
2. Test manual: `Start-ScheduledTask -TaskName "Oracle_Daily_Backup_PLNIP"`
3. Verify script: `php scripts/verify_safety.php`

**Emergency restore:**
1. Cari backup terakhir: `C:\oracle\backups\`
2. Restore dengan impdp (command di atas)
3. Inform DBA/team

---

## ✅ CHECKLIST SETUP

Pastikan semua ini sudah:
```
□ Script setup sudah dijalankan (setup_backup.ps1)
□ Scheduled task terbuat (cek dengan Get-ScheduledTask)
□ Environment variables set (DB_USERNAME, DB_PASSWORD, DB_SID)
□ Test backup berhasil (ada file .dmp di C:\oracle\backups)
□ Log tidak ada error (cek backup_*.log)
□ Task jalan next time 2 AM besok (cek NextRunTime)
```

---

**Intinya:** 
1. **Setup sekali** → Jalankan `setup_backup.ps1`
2. **Lupa aja** → Backup jalan otomatis tiap hari
3. **Cek kadang-kadang** → Pastikan file backup bertambah

**Simple!** 🎉
