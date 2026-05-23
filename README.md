# Learning Management System

Portal pembelajaran terintegra dengan sistem manajemen pembelajaran (LMS) berbasis Moodle, sinkronisasi data ERP, dan AI Assistant bertenaga Gemini.

## Tentang Sistem

LMS adalah platform pembelajaran digital yang dirancang khusus untuk mendukung program pelatihan dan pengembangan karyawan. Sistem ini mengintegrasikan:

- **Backend REST API** - Laravel 12 dengan Oracle Database
- **Frontend Modern** - Next.js 14 dengan App Router
- **LMS Integration** - Moodle dengan direct database connection
- **ERP Sync** - Sinkronisasi otomatis data karyawan dari sistem ERP PLN IP
- **AI Assistant** - Asisten pembelajaran berbasis Gemini API
- **Real-time Updates** - Laravel Reverb untuk notifikasi dan chat

## Fitur Utama

### Untuk Learner (Karyawan)
- Dashboard pembelajaran dengan progress tracking
- Akses kelas dari Moodle LMS dengan SSO
- Download sertifikat digital
- Support ticket system
- AI Assistant untuk bantuan pembelajaran
- Direct messaging antar user

### Untuk Instructor
- Manajemen kelas dan learner
- Upload sertifikat (individual/bulk ZIP)
- Monitoring progress learner
- Class group chat
- Pengumuman khusus kelas

### Untuk Admin
- Manajemen kelas dan enrollment
- Sinkronisasi data dari Moodle
- Support ticket handling
- Eskalasi ke Super Admin
- Pengumuman untuk department

### Untuk Super Admin
- Manajemen user dan role (CRUD)
- Sinkronisasi data ERP
- Role & Permission management (Spatie)
- Full Moodle sync control
- CMS landing page
- Activity log dan audit trail
- Eskalasi ticket handling

## Quick Links

- [Arsitektur Sistem](docs/01-ARSITEKTUR-SISTEM.md) - Penjelasan lengkap arsitektur dan alur data
- [Instalasi & Deployment (Ubuntu)](docs/02-INSTALASI-DEPLOYMENT.md) - Panduan setup development dan production (Ubuntu/Debian)
- [Deployment RHEL](docs/DEPLOYMENT-RHEL.md) - **Panduan deployment lengkap ke server RHEL (Laravel + Next.js + Moodle)**
- [API Reference](docs/03-API-REFERENCE.md) - Dokumentasi lengkap semua endpoint API
- [Maintenance Guide](docs/04-MAINTENANCE.md) - Panduan troubleshooting dan maintenance

## Quick Start Development

### Prasyarat
- PHP 8.2 atau lebih tinggi
- Oracle Instant Client 21c
- Composer 2.x
- Node.js 18.x atau lebih tinggi
- Oracle Database 11g atau lebih tinggi

### Setup Backend (Laravel)

```bash
# Clone repository
cd c:\laragon\www\plnip-portal

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Konfigurasi database Oracle di .env
# Jalankan migration
php artisan migrate

# Seed roles dan permissions
php artisan db:seed

# Install Laravel Reverb untuk WebSocket
php artisan reverb:install

# Jalankan development server
php artisan serve
```

### Setup Frontend (Next.js)

```bash
# Pindah ke folder frontend
cd c:\laragon\www\plnip-portal-frontend

# Install dependencies
npm install

# Copy environment file
cp .env.example .env.local

# Konfigurasi API URL di .env.local
# NEXT_PUBLIC_API_URL=http://localhost:8000

# Jalankan development server
npm run dev
```

### Akses Aplikasi

- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8000
- **API Documentation**: http://localhost:8000/api/documentation

## Tech Stack

### Backend
- **Framework**: Laravel 12
- **Database**: Oracle (yajra/laravel-oci8)
- **Authentication**: Laravel Sanctum
- **Authorization**: Spatie Laravel Permission
- **WebSocket**: Laravel Reverb
- **PDF Processing**: smalot/pdfparser, setasign/fpdi

### Frontend
- **Framework**: Next.js 14 (App Router)
- **UI Library**: React 19, Radix UI
- **Styling**: Tailwind CSS
- **Rich Text Editor**: TipTap
- **Real-time**: Pusher JS + Laravel Echo

### Integrasi
- **LMS**: Moodle (Direct DB + Web Services API)
- **ERP**: REST API Integration
- **AI**: Google Gemini API

## Project Structure

```
plnip-portal/                    # Backend Laravel
├── app/
│   ├── Http/Controllers/API/    # API Controllers
│   ├── Models/                  # Eloquent Models
│   └── Services/                # Business Logic Services
├── config/                      # Configuration files
├── database/
│   ├── migrations/              # Database migrations
│   └── seeders/                 # Database seeders
├── routes/
│   └── api.php                  # API routes
└── docs/                        # Dokumentasi lengkap

plnip-portal-frontend/           # Frontend Next.js
├── app/                         # Next.js App Router
│   ├── (auth)/                  # Auth pages (login, register)
│   ├── dashboard/               # User/Employee pages
│   ├── instructor/              # Instructor pages
│   ├── admin/                   # Admin pages
│   └── superadmin/              # Super Admin pages
├── components/                  # React components
├── lib/                         # Utilities dan helpers
└── public/                      # Static assets
```


## Development Workflow

1. **Backend Development**
   ```bash
   # Terminal 1: Laravel server
   php artisan serve

   # Terminal 2: Queue worker
   php artisan queue:work

   # Terminal 3: Laravel Reverb (WebSocket)
   php artisan reverb:start
   ```

2. **Frontend Development**
   ```bash
   npm run dev
   ```

3. **Database Changes**
   ```bash
   # Buat migration baru
   php artisan make:migration create_table_name

   # Jalankan migration
   php artisan migrate

   # Rollback jika ada error
   php artisan migrate:rollback
   ```

## Testing

```bash
# Backend unit tests
php artisan test

# Frontend tests
cd plnip-portal-frontend
npm run test
```

## Deployment

- **Server RHEL (PLN IP):** Lihat [docs/DEPLOYMENT-RHEL.md](docs/DEPLOYMENT-RHEL.md) — panduan lengkap deploy Laravel + Next.js + Moodle di RHEL dengan Oracle DB.
- **Ubuntu/Debian:** Lihat [docs/02-INSTALASI-DEPLOYMENT.md](docs/02-INSTALASI-DEPLOYMENT.md) untuk setup development dan server Ubuntu.

## Support dan Dokumentasi

Untuk informasi lengkap, lihat dokumentasi di folder `docs/`:

1. **Arsitektur Sistem** - Penjelasan mendalam tentang arsitektur dan komponen sistem
2. **Instalasi & Deployment** - Panduan lengkap setup dan deployment
3. **API Reference** - Dokumentasi semua endpoint API
4. **Maintenance Guide** - Troubleshooting dan maintenance

## Lisensi

Proprietary - PLN Indonesia Power © 2026
