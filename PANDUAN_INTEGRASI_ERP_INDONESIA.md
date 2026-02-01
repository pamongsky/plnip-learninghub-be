# 📖 PANDUAN INTEGRASI ERP - REFERENSI LENGKAP

## Daftar Isi
1. [Ringkasan](#ringkasan)
2. [Arsitektur](#arsitektur)
3. [Konfigurasi](#konfigurasi)
4. [Penggunaan](#penggunaan)
5. [Manajemen User](#manajemen-user)
6. [Audit Log](#audit-log)
7. [Penanganan Error](#penanganan-error)
8. [Troubleshooting](#troubleshooting)

---

## Ringkasan

Sistem integrasi ERP PLN IP Learning Hub memungkinkan:

- **Sinkronisasi otomatis** user dari database ERP
- **Pemetaan role** dari access_group ERP ke role portal
- **Update data user** (email, department, position, dll)
- **Sinkronisasi terjadwal** via console command
- **Trigger manual** via API endpoint
- **Pencatatan audit** semua operasi sinkronisasi
- **Validasi JIT** status user saat login (opsional)

---

## Arsitektur

### Strategi Sinkronisasi

#### 1. Scheduled Sync (UTAMA)
- Berjalan otomatis setiap hari (default: jam 2:00 pagi)
- Terjadwal via Laravel scheduler
- Dapat dikonfigurasi via `ERP_SYNC_SCHEDULE`
- **Cocok untuk:** Update data user yang predictable

#### 2. Just-In-Time (JIT) Validation
- Cek status real-time saat login
- Validasi user masih aktif di ERP
- Requires `ERP_JIT_VALIDATION=true`
- **Cocok untuk:** Keamanan, deaktivasi user langsung

#### 3. Webhook (Masa Depan)
- ERP push updates ke portal
- Sinkronisasi real-time saat user berubah
- Requires `ERP_WEBHOOK_ENABLED=true` dan webhook token
- **Cocok untuk:** User management mission-critical

### Identifikasi User

**Employee ID** adalah primary key untuk semua operasi:
- Permanent dan tidak pernah berubah
- Unik di seluruh organisasi
- Tidak bisa duplikat
- Lebih reliable daripada email atau nama

### Alur Data

```
Database ERP
     ↓
ERP API (/api/employees)
     ↓
ERPSyncService (fetch & process)
     ↓
User Model (create/update)
     ↓
Role Assignment (via access_group mapping)
     ↓
AuditLog (track semua perubahan)
```

### Pemetaan Role

Access group di ERP dipetakan ke role portal:

| ERP access_group | Portal Role | Tingkat Izin |
|---|---|---|
| `SUPERADMIN` | `super-admin` | Full system access |
| `ADMIN_UNIT` | `admin` | Department admin |
| `INSTRUCTOR` | `instructor` | Class management |
| `USER` | `user` | Learning access only |

---

## Konfigurasi

### Variabel Environment

Tambahkan ke file `.env`:

```bash
# Aktifkan integrasi ERP
ERP_ENABLED=false  # Ubah ke true saat siap

# Konfigurasi ERP API
ERP_API_URL=https://erp.plnip.co.id/api/employees
ERP_API_KEY=kunci_api_anda_di_sini

# Konfigurasi Sinkronisasi
ERP_SYNC_TIMEOUT=30              # Timeout API dalam detik
ERP_SYNC_SCHEDULE=02:00          # Waktu sync harian (HH:MM)
ERP_MAX_RETRIES=3                # Percobaan retry saat gagal
ERP_RETRY_DELAY=60               # Delay antar retry (detik)

# Keamanan
ERP_VERIFY_SSL=true              # Validasi sertifikat SSL
ERP_JIT_VALIDATION=false         # Cek status saat login

# Webhook (penggunaan masa depan)
ERP_WEBHOOK_ENABLED=false
ERP_WEBHOOK_TOKEN=token_anda
```

### File: `config/erp.php`

File konfigurasi sentral untuk semua pengaturan ERP:

```php
config('erp.enabled')        // ERP integration aktif?
config('erp.api_url')        // Endpoint ERP API
config('erp.api_key')        // Authentication key
config('erp.schedule')       // Waktu scheduled sync
config('erp.jit_validation') // JIT validation enabled?
```

---

## Penggunaan

### 1. Sinkronisasi Terjadwal (Otomatis)

Setelah `ERP_ENABLED=true`, sync berjalan otomatis setiap hari pada waktu yang dikonfigurasi.

**Monitor log:**
```bash
# Lihat operasi sync
tail -f storage/logs/audit.log

# Lihat error dan warning
tail -f storage/logs/security.log
```

### 2. Sinkronisasi Manual via API

**Endpoint:** `POST /api/superadmin/sync-erp`
**Authentication:** Super-admin role required
**Headers:** `Authorization: Bearer {sanctum_token}`

**Response:**
```json
{
  "message": "Sinkronisasi ERP berhasil diselesaikan",
  "stats": {
    "created": 15,
    "updated": 8,
    "skipped": 2,
    "errors": 0
  },
  "timestamp": "2024-01-15T10:30:45Z"
}
```

### 3. Sinkronisasi Manual via CLI

Jalankan dari server command line:

```bash
# Sync standar
php artisan erp:sync

# Dengan output detail
php artisan erp:sync -v

# Force sync meski disabled
php artisan erp:sync --force
```

**Output:**
```
🔄 Memulai sinkronisasi ERP user...

✅ Sinkronisasi ERP Selesai
┌─────────────────┬───────┐
│ Status          │ Count │
├─────────────────┼───────┤
│ ✨ Dibuat       │    15 │
│ ♻️  Diperbarui  │     8 │
│ ⏭️  Dilewati    │     2 │
│ ⚠️  Error       │     0 │
└─────────────────┴───────┘
```

---

## Manajemen User

### Field Source (Source Field)

Setiap user memiliki field `source` untuk melacak asal data:

| Source | Keterangan | Editable | Sync |
|--------|-----------|----------|------|
| `manual` | Dibuat fase dev | ✅ Ya | ❌ Tidak Pernah |
| `erp` | Dibuat dari sync ERP | ❌ Tidak | ✅ Auto-sync |

**Transisi Dev ke Production:**
- Fase dev: Buat user manual (`source=manual`)
- Production: Aktifkan ERP sync (`ERP_ENABLED=true`)
- User manual tetap ada, tidak ditimpa ERP sync
- Super admin bisa manual hapus user manual jika perlu

### Manajemen Role

#### Assign Role Otomatis

Saat user sync dari ERP:
1. Baca `access_group` dari data ERP
2. Petakan ke role portal (lihat tabel mapping)
3. Assign role otomatis

#### Override Role Manual

Super-admin bisa override role user manapun:

**Via API:**
```
POST /api/superadmin/users/{user}/override-role
{
  "role": "super-admin",
  "reason": "Promosi ke administrator karena..."
}
```

**Efek:**
- Role asli user disimpan di field `role_override`
- Override role tetap ada meski data ERP berubah
- Perubahan dicatat dalam audit log dengan alasan
- Super-admin bisa revert kapan saja

---

## Audit Log

Setiap operasi ERP sync dicatat:

```php
AuditLog::create([
    'user_id' => null,  // System sync
    'action' => 'create|update|erp_sync_manual',
    'entity_type' => 'User',
    'entity_id' => $user->id,
    'changes' => [...],
    'reason' => 'User dibuat/diupdate dari ERP sync',
    'ip_address' => $request->ip(),
]);
```

**Lihat audit log:**
```bash
# Operasi sync terbaru
SELECT * FROM audit_logs 
WHERE action = 'erp_sync_manual' 
ORDER BY created_at DESC LIMIT 20;

# Riwayat perubahan user
SELECT * FROM audit_logs 
WHERE entity_type = 'User' AND entity_id = {user_id}
ORDER BY created_at DESC;
```

---

## Penanganan Error

### Error Umum

| Error | Penyebab | Solusi |
|-------|---------|--------|
| `ERP API error: 401` | API key tidak valid | Cek `ERP_API_KEY` di .env |
| `ERP API error: 404` | Endpoint salah | Verifikasi `ERP_API_URL` |
| `Connection timeout` | Server ERP down | Cek status server ERP |
| `Invalid employee data` | Field required hilang | Update format data ERP |

### Logging

Semua error dicatat ke channel yang sesuai:

```bash
# Security issues (auth, validation)
storage/logs/security.log

# Audit trail (operasi sync)
storage/logs/audit.log

# General errors
storage/logs/laravel.log
```

---

## Troubleshooting

### Sync Tidak Berjalan?

1. Cek apakah enabled:
   ```bash
   php artisan tinker
   > config('erp.enabled')
   ```

2. Cek Laravel scheduler:
   ```bash
   # Tambah ke crontab jika belum ada
   * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
   ```

3. Jalankan manual untuk debug:
   ```bash
   php artisan erp:sync -v
   ```

### User Tidak Dibuat?

1. Cek format response API:
   ```bash
   curl -H "Authorization: Bearer YOUR_KEY" https://erp.plnip.co.id/api/employees
   ```

2. Cek log:
   ```bash
   tail -f storage/logs/security.log
   ```

3. Verifikasi employee_id unik di database

### Role Tidak Ter-assign?

1. Cek pemetaan access_group:
   ```php
   UserService::mapAccessGroupToRole('YOUR_GROUP_NAME')
   ```

2. Cek apakah role override aktif
3. Jalankan sync lagi setelah perbaikan role assignment

---

## Fitur Siap Pakai

### Primary: Scheduled Sync ✅
- Eksekusi harian pada waktu terjadwal
- Otomatis via Laravel scheduler
- Sempurna untuk update data predictable

### Secondary: JIT Validation ⚙️
- Cek status ERP saat login
- Enable: `ERP_JIT_VALIDATION=true`
- Deaktivasi user removed segera

### Future: Webhook Support 🔮
- ERP push updates ke portal
- Sinkronisasi real-time
- Enable: `ERP_WEBHOOK_ENABLED=true`

---

## Performance

### Durasi Sync
- Organisasi kecil (< 100 user): ~5-10 detik
- Organisasi menengah (100-500 user): ~30-60 detik
- Organisasi besar (500+ user): 1-3 menit

### Optimisasi
- Jalankan sync pada jam off-peak (default 2:00 pagi)
- Disable JIT validation jika tidak diperlukan
- Archive audit log lama secara berkala
- Gunakan database indexes pada employee_id, email

---

## Support

Untuk pertanyaan:
1. Periksa log di `storage/logs/`
2. Review audit trail di database
3. Jalankan `php artisan erp:sync -v` untuk output detail
4. Hubungi tim DevOps PLN IP

---

**Terakhir Diupdate:** Januari 2024
**Versi:** 1.0 (Bahasa Indonesia)
