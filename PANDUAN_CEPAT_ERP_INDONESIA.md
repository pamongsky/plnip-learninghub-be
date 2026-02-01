# 🚀 PANDUAN CEPAT INTEGRASI ERP - MULAI DALAM 5 MENIT

## Langkah 1: Konfigurasi Lingkungan (1 menit)
Buka file `.env` di project root:

```bash
# Mulai dengan ERP dinonaktifkan (aman untuk development)
ERP_ENABLED=false

# Arahkan ke API ERP (dapatkan dari PLN IT)
ERP_API_URL=https://erp.plnip.co.id/api/employees
ERP_API_KEY=kunci_api_anda_di_sini

# Sync pada jam 2:00 pagi setiap hari
ERP_SYNC_SCHEDULE=02:00
```

## Langkah 2: Buat User Secara Manual (Development Phase)
Di panel super admin:
1. Buka "Kelola Semua User"
2. Klik "Tambah User"
3. Isi form: nama, email, employee_id, role, dll
4. User dibuat dengan `source=manual`
5. User manual **tidak pernah** ditimpa oleh ERP

## Langkah 3: Aktifkan ERP Saat Siap (Production)
```bash
ERP_ENABLED=true
```

Sekarang:
- Sync otomatis berjalan setiap hari jam 2:00 pagi
- User dari ERP dibuat/diperbarui otomatis
- User manual dari dev tetap aman
- Semua perubahan dicatat dalam audit log

## Langkah 4: Jalankan Sync Manual Kapan Saja
Di panel super admin → tombol "Sync ERP"

Atau via API:
```bash
curl -X POST http://localhost:8000/api/superadmin/sync-erp \
  -H "Authorization: Bearer TOKEN_ANDA"
```

Atau via CLI:
```bash
php artisan erp:sync -v
```

## Langkah 5: Monitor Hasil
Periksa log:
```bash
# Operasi sync terbaru
tail -f storage/logs/audit.log | grep "ERP"

# Error dan peringatan
tail -f storage/logs/security.log | grep "error"
```

---

## ✅ Checklist Pra-Deployment

- [ ] Dapatkan kredensial ERP dari PLN IT
- [ ] Verifikasi endpoint ERP API (HTTP 200 response)
- [ ] Konfigurasi `.env` dengan kredensial
- [ ] Test manual: `php artisan erp:sync -v`
- [ ] Test UI: Klik "Sync ERP" di panel
- [ ] User dibuat/diperbarui dengan benar
- [ ] Audit log menunjukkan operasi sync
- [ ] Tidak ada error di `security.log`

---

## 🔍 Testing Integrasi

### Test Cepat via Command Line
```bash
# Cek konfigurasi
php artisan tinker
> config('erp.enabled')    # Harus false atau true
> config('erp.api_url')    # URL ERP Anda
> config('erp.api_key')    # Tidak boleh kosong

exit
```

### Jalankan Sync dengan Output Detail
```bash
php artisan erp:sync -v

# Output harus menampilkan:
# 🔄 Memulai ERP user sync...
# ✅ ERP Sync Selesai
# ✨ Dibuat: 15
# ♻️  Diperbarui: 8
# ⏭️  Dilewati: 0
# ⚠️  Error: 0
```

### Cek Database
```bash
php artisan tinker

# Hitung user ERP vs manual
> App\Models\User::where('source', 'erp')->count()      # Harus > 0
> App\Models\User::where('source', 'manual')->count()   # User dev

# Lihat log sync terbaru
> App\Models\AuditLog::where('action', 'create')
    ->where('reason', 'like', '%ERP%')
    ->orderBy('created_at', 'desc')
    ->take(10)
    ->get()

exit
```

---

## ⚠️ Masalah Umum & Solusi

### "Error API: 401"
```bash
# API key salah atau expired
# Solusi: Dapatkan key baru dari PLN IT, update ERP_API_KEY di .env
```

### "Connection timeout"
```bash
# Server ERP down atau tidak terjangkau
# Solusi: Cek koneksi VPN, aturan firewall
# Tingkatkan timeout: ERP_SYNC_TIMEOUT=60
```

### "Tidak ada data employee dari ERP"
```bash
# API ERP mengembalikan response kosong
# Cek: curl -H "Authorization: Bearer KEY" https://url-erp
# Solusi: Verifikasi endpoint dan auth
```

### User tidak dibuat setelah sync
```bash
# Cek apakah ERP enabled
echo $ERP_ENABLED  # Harus output: true

# Periksa log untuk error
tail -f storage/logs/security.log

# Verifikasi format response ERP API
php artisan erp:sync -v  # Menampilkan detail
```

### Scheduled sync tidak berjalan
```bash
# Cek apakah scheduler aktif
ps aux | grep schedule:run

# Jika tidak berjalan, tambah ke crontab:
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1

# Atau jalankan manual:
php artisan schedule:run
```

---

## 📊 Format API ERP yang Diperlukan

API ERP Anda harus mengembalikan data seperti ini:

```bash
GET https://url-erp-anda/api/employees
Authorization: Bearer API_KEY_ANDA

# Response:
{
  "employees": [
    {
      "employee_id": "EMP001234",
      "name": "John Doe",
      "email": "john.doe@plnip.co.id",
      "phone": "082112345678",
      "department": "Transmisi",
      "position": "Senior Engineer",
      "access_group": "ADMIN_UNIT",
      "is_active": true
    }
  ]
}
```

**Field yang Wajib:**
- `employee_id` (string, unik)
- `name` (string)
- `email` (string)

**Field Opsional:**
- `phone`, `department`, `position`
- `access_group` (maps to role)
- `is_active` (boolean)

---

## 🔐 Pemetaan Role

Field `access_group` dari ERP harus salah satu:

| Nilai ERP | Role Portal | Permissions |
|---|---|---|
| `SUPERADMIN` | super-admin | Full access |
| `ADMIN_UNIT` | admin | Department management |
| `INSTRUCTOR` | instructor | Class management |
| `USER` | user | Learning only |

**Contoh:** Jika ERP mengirim `access_group: "INSTRUCTOR"`, user otomatis dapat role `instructor`.

---

## 🛡️ Catatan Keamanan

- ✅ User manual **tidak pernah** ditimpa
- ✅ Semua perubahan dicatat dalam audit trail
- ✅ Hanya super-admin yang bisa trigger sync manual
- ✅ Super-admin bisa override role apapun (dicatat dengan alasan)
- ✅ Employee_id adalah key permanent (tidak berubah)

---

## 📞 Dapatkan Bantuan

1. **Periksa log terlebih dahulu:**
   ```bash
   tail -f storage/logs/security.log
   tail -f storage/logs/audit.log
   ```

2. **Baca panduan lengkap:**
   - Lihat `ERP_INTEGRATION_GUIDE.md` di project root (Bahasa Inggris)

3. **Detail implementasi:**
   - Lihat `ERP_SYNC_IMPLEMENTATION.md` di project root (Bahasa Inggris)

4. **Hubungi tim PLN IT:**
   - Dapatkan kredensial ERP API
   - Verifikasi endpoint ERP
   - Konfirmasi struktur data employee
   - Nilai access_group yang diperlukan

---

## 📚 Referensi Perintah

```bash
# Sync manual (eksekusi langsung)
php artisan erp:sync

# Output verbose (lihat semua detail)
php artisan erp:sync -v

# Force sync meski disabled
php artisan erp:sync --force

# Jalankan scheduler sekali
php artisan schedule:run

# Test konfigurasi
php artisan tinker
> config('erp')
```

---

## ✅ Anda Siap!

Jika Anda dapat:
1. ✅ Lihat user ERP di database
2. ✅ Lihat tombol "Sync ERP" di UI
3. ✅ Periksa audit log untuk operasi
4. ✅ User manual dari dev phase masih ada

**→ Integrasi berfungsi! 🎉**

Selanjutnya: Konfigurasi JIT validation, webhook, atau fitur lain dari `ERP_INTEGRATION_GUIDE.md`.

---

**Terakhir Diupdate:** Januari 2024
**Versi Panduan Cepat:** 1.0 (Bahasa Indonesia)
