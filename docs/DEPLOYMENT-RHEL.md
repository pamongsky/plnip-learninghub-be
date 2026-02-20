# Deployment Guide: PLN IP Learning Hub Portal
## RHEL (Red Hat Enterprise Linux) — Laravel + Next.js + Moodle

> **Target Server:** RHEL 8/9 dengan Oracle DB, Nginx, PHP 8.2, Node.js 18
> **Arsitektur:** Satu server untuk Backend (Laravel) + Frontend (Next.js) + Moodle
> **Database:** Oracle (sudah terinstall terpisah atau di server yang sama)

---

## Daftar Isi

1. [Persiapan Server](#1-persiapan-server)
2. [Install PHP 8.2 + Extensions](#2-install-php-82--extensions)
3. [Install Oracle Instant Client + OCI8](#3-install-oracle-instant-client--oci8)
4. [Install Nginx](#4-install-nginx)
5. [Install Node.js 18 + PM2](#5-install-nodejs-18--pm2)
6. [Install Composer](#6-install-composer)
7. [Deploy Backend Laravel](#7-deploy-backend-laravel)
8. [Deploy Frontend Next.js](#8-deploy-frontend-nextjs)
9. [Deploy Moodle](#9-deploy-moodle)
10. [Konfigurasi Nginx (Semua Service)](#10-konfigurasi-nginx-semua-service)
11. [SELinux Configuration](#11-selinux-configuration)
12. [Firewall (firewalld)](#12-firewall-firewalld)
13. [SSL Certificate](#13-ssl-certificate)
14. [Services: Queue Worker + WebSocket](#14-services-queue-worker--websocket)
15. [Cron Jobs](#15-cron-jobs)
16. [Verifikasi Deployment](#16-verifikasi-deployment)
17. [Update & Maintenance](#17-update--maintenance)
18. [Troubleshooting](#18-troubleshooting)
19. [Checklist Deploy](#19-checklist-deploy)

---

## 1. Persiapan Server

### 1.1 Update System

```bash
# Login ke server sebagai root
ssh root@SERVER_IP

# Update semua package
dnf update -y

# Install tools dasar
dnf install -y wget curl git unzip zip tar vim nano htop
```

### 1.2 Setup User untuk Aplikasi

```bash
# Buat user khusus untuk deploy (opsional, bisa pakai root)
useradd -m -s /bin/bash deployer
usermod -aG wheel deployer  # sudo access

# Set password
passwd deployer
```

### 1.3 Cek Spesifikasi Server

```bash
# CPU
nproc

# RAM
free -h

# Storage
df -h

# OS Version
cat /etc/redhat-release
```

---

## 2. Install PHP 8.2 + Extensions

### 2.1 Enable Remi Repository

RHEL tidak punya PHP 8.2 di repo default. Gunakan Remi:

```bash
# Install EPEL dulu
dnf install -y epel-release

# Install Remi repo
dnf install -y https://rpms.remirepo.net/enterprise/remi-release-8.rpm
# Atau RHEL 9:
dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm

# Enable PHP 8.2 module
dnf module reset php -y
dnf module enable php:remi-8.2 -y
```

### 2.2 Install PHP 8.2 + Semua Extensions

```bash
dnf install -y \
  php \
  php-fpm \
  php-cli \
  php-common \
  php-mbstring \
  php-xml \
  php-curl \
  php-zip \
  php-gd \
  php-intl \
  php-soap \
  php-bcmath \
  php-json \
  php-opcache \
  php-redis \
  php-pdo

# Verifikasi
php -v
# PHP 8.2.x (cli)
```

### 2.3 Konfigurasi PHP-FPM

```bash
# Edit PHP-FPM pool config
nano /etc/php-fpm.d/www.conf
```

Ubah bagian berikut:

```ini
; Ganti user dan group dari apache ke nginx
user = nginx
group = nginx

; Socket mode
listen = /run/php-fpm/www.sock
listen.owner = nginx
listen.group = nginx
listen.mode = 0660

; Performance (sesuaikan dengan RAM server)
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

```bash
# Edit php.ini untuk production
nano /etc/php.ini
```

```ini
; Production settings
display_errors = Off
log_errors = On
error_log = /var/log/php-error.log

; Upload limits (untuk ZIP sertifikat)
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
memory_limit = 256M

; Timezone
date.timezone = Asia/Jakarta
```

```bash
# Start dan enable PHP-FPM
systemctl start php-fpm
systemctl enable php-fpm
systemctl status php-fpm
```

---

## 3. Install Oracle Instant Client + OCI8

> **PENTING:** Ini bagian paling kritis. PHP perlu Oracle Instant Client untuk connect ke Oracle DB.

### 3.1 Download Oracle Instant Client

Download dari: https://www.oracle.com/database/technologies/instant-client/linux-x86-64-downloads.html

Download 2 file (versi 21c):
- `oracle-instantclient-basic-21.x.x.x-1.x86_64.rpm`
- `oracle-instantclient-devel-21.x.x.x-1.x86_64.rpm`

Upload ke server:
```bash
# Dari komputer lokal
scp oracle-instantclient-basic-21.*.rpm root@SERVER_IP:/tmp/
scp oracle-instantclient-devel-21.*.rpm root@SERVER_IP:/tmp/
```

### 3.2 Install Oracle Instant Client

```bash
cd /tmp

# Install RPM
dnf install -y oracle-instantclient-basic-21.*.rpm
dnf install -y oracle-instantclient-devel-21.*.rpm

# Atau manual via rpm
rpm -ivh oracle-instantclient-basic-21.*.rpm
rpm -ivh oracle-instantclient-devel-21.*.rpm

# Cek path instalasi
ls /usr/lib/oracle/21/client64/lib/
```

### 3.3 Set Environment Variables

```bash
# Set permanent environment variables
cat >> /etc/environment << 'EOF'
ORACLE_HOME=/usr/lib/oracle/21/client64
LD_LIBRARY_PATH=/usr/lib/oracle/21/client64/lib
TNS_ADMIN=/usr/lib/oracle/21/client64/network/admin
EOF

# Atau via /etc/profile.d/
cat > /etc/profile.d/oracle.sh << 'EOF'
export ORACLE_HOME=/usr/lib/oracle/21/client64
export LD_LIBRARY_PATH=/usr/lib/oracle/21/client64/lib:$LD_LIBRARY_PATH
export PATH=$ORACLE_HOME/bin:$PATH
export TNS_ADMIN=/ORACLE_HOME/network/admin
EOF

source /etc/profile.d/oracle.sh

# Buat folder network/admin untuk tnsnames.ora
mkdir -p /usr/lib/oracle/21/client64/network/admin
```

### 3.4 Konfigurasi ldconfig

```bash
# Register Oracle libs ke ldconfig
echo /usr/lib/oracle/21/client64/lib > /etc/ld.so.conf.d/oracle.conf
ldconfig

# Verifikasi
ldconfig -p | grep libclntsh
```

### 3.5 Install PHP OCI8 Extension

```bash
# Install PECL + build tools
dnf install -y php-pear php-devel gcc libnsl

# Install oci8 via PECL
# Ketik "instantclient,/usr/lib/oracle/21/client64/lib" saat diminta
echo "instantclient,/usr/lib/oracle/21/client64/lib" | pecl install oci8

# Enable extension
echo "extension=oci8.so" > /etc/php.d/20-oci8.ini

# Restart PHP-FPM
systemctl restart php-fpm

# Verifikasi
php -m | grep oci8
# Harus muncul: oci8
```

### 3.6 Test Koneksi Oracle

```bash
# Test via PHP
php -r "
\$conn = oci_connect('DB_USER', 'DB_PASS', '127.0.0.1:1521/XEPDB1');
if (\$conn) {
    echo 'Oracle connected OK' . PHP_EOL;
    oci_close(\$conn);
} else {
    \$e = oci_error();
    echo 'Error: ' . \$e['message'] . PHP_EOL;
}
"
```

---

## 4. Install Nginx

```bash
# Install Nginx
dnf install -y nginx

# Start dan enable
systemctl start nginx
systemctl enable nginx
systemctl status nginx

# Test
curl http://localhost
# Harus return HTML welcome page nginx
```

---

## 5. Install Node.js 18 + PM2

```bash
# Install Node.js 18 via NodeSource
curl -fsSL https://rpm.nodesource.com/setup_18.x | bash -
dnf install -y nodejs

# Verifikasi
node -v  # v18.x.x
npm -v   # 9.x.x

# Install PM2 secara global
npm install -g pm2

# Verifikasi
pm2 -v
```

---

## 6. Install Composer

```bash
# Download Composer installer
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"

# Install Composer
php composer-setup.php --install-dir=/usr/local/bin --filename=composer

# Cleanup
php -r "unlink('composer-setup.php');"

# Verifikasi
composer --version
```

---

## 7. Deploy Backend Laravel

### 7.1 Upload Kode ke Server

Ada **3 cara** upload kode ke server, pilih salah satu sesuai situasi:

---

#### Cara A: Git Clone (jika sudah punya remote repo di GitHub/GitLab)

**Setup SSH Key di Server (untuk private repo):**

```bash
# Generate SSH key di server
ssh-keygen -t ed25519 -C "deploy@plnip.co.id" -f ~/.ssh/id_ed25519 -N ""

# Tampilkan public key → copy ke GitHub/GitLab Deploy Keys
cat ~/.ssh/id_ed25519.pub
```

Di GitHub: Settings → Deploy Keys → Add deploy key → paste public key → Allow write access: OFF

```bash
# Test koneksi ke GitHub
ssh -T git@github.com
# Hi YOUR_ORG! You've successfully authenticated...

# Buat folder dan clone
mkdir -p /var/www
cd /var/www
git clone git@github.com:YOUR_ORG/plnip-portal.git
cd plnip-portal
```

**Atau via HTTPS dengan Personal Access Token:**

```bash
# Clone dengan token (ganti YOUR_TOKEN)
git clone https://YOUR_TOKEN@github.com/YOUR_ORG/plnip-portal.git
```

---

#### Cara B: SCP (upload langsung dari komputer lokal ke server) ← Paling Praktis Sementara

Jalankan dari **komputer lokal** (Windows PowerShell atau Git Bash):

```bash
# Upload backend
scp -r C:/laragon/www/plnip-portal root@SERVER_IP:/var/www/plnip-portal

# Upload frontend
scp -r C:/laragon/www/plnip-portal-frontend root@SERVER_IP:/var/www/plnip-portal-frontend
```

> **Catatan:** SCP akan upload semua file termasuk `vendor/` dan `node_modules/` yang besar.
> Lebih baik exclude dulu:

```bash
# Compress dulu di lokal (exclude vendor & node_modules)
# Di Git Bash:
tar -czf plnip-portal.tar.gz \
  --exclude='plnip-portal/vendor' \
  --exclude='plnip-portal/.git' \
  plnip-portal/

# Upload archive
scp plnip-portal.tar.gz root@SERVER_IP:/var/www/

# Di server: extract
cd /var/www
tar -xzf plnip-portal.tar.gz
rm plnip-portal.tar.gz
```

---

#### Cara C: rsync (sync file yang berubah saja, cocok untuk update)

```bash
# Dari komputer lokal
rsync -avz --progress \
  --exclude='vendor/' \
  --exclude='node_modules/' \
  --exclude='.git/' \
  --exclude='storage/logs/' \
  C:/laragon/www/plnip-portal/ \
  root@SERVER_IP:/var/www/plnip-portal/
```

---

**Setelah kode terupload:**

```bash
# Set ownership ke nginx
chown -R nginx:nginx /var/www/plnip-portal
```

### 7.2 Install Dependencies

```bash
cd /var/www/plnip-portal

# Install Composer dependencies (production mode)
composer install --no-dev --optimize-autoloader
```

### 7.3 Konfigurasi Environment

```bash
# Copy env
cp .env.production.example .env
# Atau buat manual
nano .env
```

**Isi `.env` untuk production:**

```env
APP_NAME="PLN IP Learning Hub"
APP_ENV=production
APP_KEY=                         # Generate nanti
APP_DEBUG=false
APP_URL=https://api.plnip.co.id

APP_LOCALE=id
APP_FALLBACK_LOCALE=id

LOG_CHANNEL=daily
LOG_LEVEL=error

# Portal Database (Oracle)
DB_CONNECTION=oracle
DB_HOST=127.0.0.1
DB_PORT=1521
DB_DATABASE=
DB_SERVICE_NAME=XEPDB1           # Ganti sesuai Oracle service name
DB_USERNAME=plnip
DB_PASSWORD=GANTI_PASSWORD_KUAT

# Moodle Database (Oracle)
MOODLE_DB_CONNECTION=oracle
MOODLE_DB_HOST=127.0.0.1
MOODLE_DB_PORT=1521
MOODLE_DB_SERVICE_NAME=XEPDB1
MOODLE_DB_USERNAME=moodle_user
MOODLE_DB_PASSWORD=GANTI_PASSWORD_KUAT

# Moodle Web Services
MOODLE_URL=https://moodle.plnip.co.id
MOODLE_WS_TOKEN=TOKEN_DARI_MOODLE

# Session & Cache
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_DOMAIN=.plnip.co.id

SANCTUM_STATEFUL_DOMAINS=plnip.co.id,www.plnip.co.id

# CORS
CORS_ALLOWED_ORIGINS=https://plnip.co.id,https://www.plnip.co.id

# Queue
QUEUE_CONNECTION=database

# Cache
CACHE_STORE=file

# Storage
FILESYSTEM_DISK=public

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=EMAIL_GMAIL
MAIL_PASSWORD=APP_PASSWORD_GMAIL
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@plnip.co.id
MAIL_FROM_NAME="PLN IP Learning Hub"

# Gemini AI
GEMINI_API_KEY=YOUR_GEMINI_API_KEY

# Laravel Reverb (WebSocket)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=YOUR_APP_ID
REVERB_APP_KEY=YOUR_APP_KEY
REVERB_APP_SECRET=YOUR_APP_SECRET
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=https

# ERP (aktifkan setelah dapat akses API)
ERP_ENABLED=false
ERP_API_URL=https://erp.plnip.co.id/api/employees
ERP_API_KEY=YOUR_ERP_API_KEY
```

### 7.4 Generate App Key & Setup

```bash
cd /var/www/plnip-portal

# Generate app key
php artisan key:generate

# Buat storage symlink
php artisan storage:link

# Set permissions
chown -R nginx:nginx storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

### 7.5 Database Migration

```bash
# Test koneksi dulu
php artisan db:show

# Jalankan migration
php artisan migrate --force

# Seed roles & permissions (hanya pertama kali)
php artisan db:seed --class=RolePermissionSeeder
```

### 7.6 Cache Production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer dump-autoload --optimize
```

---

## 8. Deploy Frontend Next.js

### 8.1 Upload Kode Frontend ke Server

Sama seperti backend, pilih salah satu cara:

**Cara A: Git Clone**
```bash
cd /var/www
git clone git@github.com:YOUR_ORG/plnip-portal-frontend.git
# atau HTTPS:
# git clone https://YOUR_TOKEN@github.com/YOUR_ORG/plnip-portal-frontend.git
```

**Cara B: SCP dari lokal (paling praktis)**
```bash
# Di komputer lokal (Git Bash)
tar -czf plnip-portal-frontend.tar.gz \
  --exclude='plnip-portal-frontend/node_modules' \
  --exclude='plnip-portal-frontend/.next' \
  --exclude='plnip-portal-frontend/.git' \
  plnip-portal-frontend/

scp plnip-portal-frontend.tar.gz root@SERVER_IP:/var/www/

# Di server
cd /var/www
tar -xzf plnip-portal-frontend.tar.gz
rm plnip-portal-frontend.tar.gz
```

```bash
# Set ownership
chown -R nginx:nginx /var/www/plnip-portal-frontend
```

### 8.2 Environment Configuration

```bash
nano /var/www/plnip-portal-frontend/.env.production
```

```env
NEXT_PUBLIC_API_URL=https://api.plnip.co.id
NEXT_PUBLIC_APP_NAME="PLN IP Learning Hub"

# WebSocket (Laravel Reverb)
NEXT_PUBLIC_REVERB_APP_KEY=YOUR_APP_KEY
NEXT_PUBLIC_REVERB_HOST=api.plnip.co.id
NEXT_PUBLIC_REVERB_PORT=443
NEXT_PUBLIC_REVERB_SCHEME=https
```

### 8.3 Install & Build

```bash
cd /var/www/plnip-portal-frontend

# Install dependencies
npm ci

# Build production
npm run build
```

### 8.4 Jalankan dengan PM2

```bash
# Start dengan PM2
pm2 start npm --name "plnip-frontend" -- start

# Atau gunakan ecosystem.config.js yang sudah ada:
pm2 start ecosystem.config.js

# Setup auto-start saat server boot
pm2 startup
# Copy dan jalankan command yang muncul

# Save konfigurasi PM2
pm2 save

# Cek status
pm2 list
pm2 logs plnip-frontend
```

---

## 9. Deploy Moodle

### 9.1 Install PHP Extensions Tambahan untuk Moodle

```bash
# Extensions tambahan yang Moodle butuhkan
dnf install -y \
  php-intl \
  php-soap \
  php-zip \
  php-xmlrpc \
  php-sodium

# Restart PHP-FPM
systemctl restart php-fpm
```

### 9.2 Download Moodle

```bash
cd /var/www

# Download Moodle 4.5
wget https://download.moodle.org/download.php/direct/stable405/moodle-latest-405.tgz

# Extract
tar -xzf moodle-latest-405.tgz

# Set ownership
chown -R nginx:nginx /var/www/moodle
chmod -R 755 /var/www/moodle

# Hapus installer
rm moodle-latest-405.tgz
```

> **Alternatif:** Copy dari lokal via scp:
> ```bash
> scp -r /path/to/moodle root@SERVER_IP:/var/www/moodle
> ```

### 9.3 Buat Folder moodledata

```bash
# WAJIB di luar webroot untuk keamanan
mkdir -p /var/moodledata
chown -R nginx:nginx /var/moodledata
chmod -R 770 /var/moodledata
```

### 9.4 Setup Oracle untuk Moodle

```bash
# Login ke Oracle sebagai admin
sqlplus sys/SYS_PASSWORD@XEPDB1 as sysdba
```

```sql
-- Buat user Moodle (jika belum ada)
CREATE USER moodle_user IDENTIFIED BY "MoodlePLN123!";

-- Grant privileges yang dibutuhkan Moodle
GRANT CREATE SESSION TO moodle_user;
GRANT CREATE TABLE TO moodle_user;
GRANT CREATE SEQUENCE TO moodle_user;
GRANT CREATE PROCEDURE TO moodle_user;
GRANT CREATE TRIGGER TO moodle_user;
GRANT CREATE VIEW TO moodle_user;
GRANT CREATE SYNONYM TO moodle_user;
GRANT ALTER SESSION TO moodle_user;
GRANT UNLIMITED TABLESPACE TO moodle_user;

EXIT;
```

### 9.5 Buat config.php Moodle

```bash
nano /var/www/moodle/config.php
```

```php
<?php
unset($CFG);
global $CFG;
$CFG = new stdClass();

// Database Oracle
$CFG->dbtype    = 'oci';
$CFG->dblibrary = 'native';
$CFG->dbhost    = '127.0.0.1:1521/XEPDB1';   // format Oracle: host:port/service
$CFG->dbname    = 'moodle_user';               // schema = username di Oracle
$CFG->dbuser    = 'moodle_user';
$CFG->dbpass    = 'MoodlePLN123!';             // ganti dengan password kuat
$CFG->prefix    = 'mdl_';

// URL dan paths
$CFG->wwwroot   = 'https://moodle.plnip.co.id';
$CFG->dataroot  = '/var/moodledata';
$CFG->admin     = 'admin';

$CFG->directorypermissions = 0777;

// Performance
$CFG->cachetype = 'file';

// Security
$CFG->cookiesecure = true;

require_once(__DIR__ . '/lib/setup.php');
```

```bash
# Set permission config.php
chown nginx:nginx /var/www/moodle/config.php
chmod 640 /var/www/moodle/config.php
```

### 9.6 Install Moodle via CLI

```bash
# Jalankan installer
php /var/www/moodle/admin/cli/install.php \
  --wwwroot="https://moodle.plnip.co.id" \
  --dataroot="/var/moodledata" \
  --dbtype="oci" \
  --dbhost="127.0.0.1:1521/XEPDB1" \
  --dbname="moodle_user" \
  --dbuser="moodle_user" \
  --dbpass="MoodlePLN123!" \
  --prefix="mdl_" \
  --fullname="PLN IP Learning Hub - LMS" \
  --shortname="PLNIP" \
  --adminuser="admin" \
  --adminpass="Admin@PLN2024!" \
  --adminemail="admin@plnip.co.id" \
  --agree-license \
  --non-interactive
```

> **Proses ini lama (5-20 menit)**. Biarkan jalan sampai selesai.

### 9.7 Setup Moodle Web Services (untuk integrasi Portal)

Setelah Moodle terinstall, setup Web Services agar Portal bisa integrasi:

```bash
# Login ke Moodle Admin via browser:
# https://moodle.plnip.co.id/login
# Username: admin / Password: Admin@PLN2024!
```

Langkah via Moodle Admin UI:
1. **Enable Web Services:** Site Administration → Advanced Features → Enable web services ✓
2. **Enable REST Protocol:** Site Administration → Plugins → Web services → Manage protocols → REST ✓
3. **Create Service:** Site Administration → Plugins → Web services → External services → Add
   - Name: `PLN IP Portal`
   - Short name: `plnip_portal`
   - Enabled: Yes
   - Add functions: `core_user_create_users`, `enrol_manual_enrol_users`, `core_course_get_contents`, `gradereport_user_get_grades_table`
4. **Generate Token:** Site Administration → Plugins → Web services → Manage tokens → Add
5. Copy token → masukkan ke `.env` Laravel: `MOODLE_WS_TOKEN=...`

### 9.8 Setup Moodle Cron

```bash
# Tambahkan cron untuk Moodle (wajib)
crontab -u nginx -e
```

```cron
* * * * * /usr/bin/php /var/www/moodle/admin/cli/cron.php > /dev/null 2>&1
```

---

## 10. Konfigurasi Nginx (Semua Service)

### 10.1 Backend Laravel (API)

```bash
nano /etc/nginx/conf.d/api.plnip.co.id.conf
```

```nginx
# HTTP → redirect ke HTTPS
server {
    listen 80;
    server_name api.plnip.co.id;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name api.plnip.co.id;

    ssl_certificate     /etc/ssl/certs/plnip.co.id.crt;
    ssl_certificate_key /etc/ssl/private/plnip.co.id.key;
    ssl_protocols       TLSv1.2 TLSv1.3;

    root /var/www/plnip-portal/public;
    index index.php;

    # Upload limit untuk sertifikat ZIP
    client_max_body_size 100M;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # WebSocket upgrade untuk Reverb
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_read_timeout 60s;
    }
}
```

### 10.2 Frontend Next.js

```bash
nano /etc/nginx/conf.d/plnip.co.id.conf
```

```nginx
server {
    listen 80;
    server_name plnip.co.id www.plnip.co.id;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name plnip.co.id www.plnip.co.id;

    ssl_certificate     /etc/ssl/certs/plnip.co.id.crt;
    ssl_certificate_key /etc/ssl/private/plnip.co.id.key;
    ssl_protocols       TLSv1.2 TLSv1.3;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }

    # Cache static assets Next.js
    location /_next/static/ {
        proxy_pass http://127.0.0.1:3000;
        proxy_cache_valid 200 1d;
        add_header Cache-Control "public, max-age=86400, immutable";
    }
}
```

### 10.3 Moodle LMS

```bash
nano /etc/nginx/conf.d/moodle.plnip.co.id.conf
```

```nginx
server {
    listen 80;
    server_name moodle.plnip.co.id;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name moodle.plnip.co.id;

    ssl_certificate     /etc/ssl/certs/plnip.co.id.crt;
    ssl_certificate_key /etc/ssl/private/plnip.co.id.key;
    ssl_protocols       TLSv1.2 TLSv1.3;

    root /var/www/moodle;
    index index.php;

    # Upload limit untuk Moodle (file materi, tugas, dll)
    client_max_body_size 200M;

    # Gzip compression
    gzip on;
    gzip_types text/plain text/css application/json application/javascript;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ [^/]\.php(/|$) {
        fastcgi_split_path_info ^(.+?\.php)(/.*)$;
        if (!-f $document_root$fastcgi_script_name) {
            return 404;
        }
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        include fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    # Block akses ke folder sensitif
    location ~* ^/(config\.php|version\.php|composer\.json|\.git) {
        deny all;
        return 404;
    }

    # Block akses ke moodledata
    location /dataroot/ {
        deny all;
    }

    # Cache static assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }
}
```

### 10.4 Test & Reload Nginx

```bash
# Test konfigurasi
nginx -t

# Reload
systemctl reload nginx
```

---

## 11. SELinux Configuration

> **RHEL mengaktifkan SELinux secara default.** Ini security layer yang perlu dikonfigurasi agar Nginx/PHP bisa akses file aplikasi.

### 11.1 Cek Status SELinux

```bash
getenforce
# Enforcing = aktif (normal di RHEL)
# Permissive = log tapi tidak block
# Disabled = mati
```

### 11.2 Allow Nginx & PHP-FPM

```bash
# Allow Nginx koneksi ke database dan network
setsebool -P httpd_can_network_connect 1
setsebool -P httpd_can_network_connect_db 1

# Allow Nginx koneksi ke upstream (PM2 Next.js di port 3000)
setsebool -P httpd_can_network_relay 1

# Allow Nginx kirim email
setsebool -P httpd_can_sendmail 1
```

### 11.3 Set Context untuk Folder Aplikasi

```bash
# Laravel backend - folder public (read)
chcon -R -t httpd_sys_content_t /var/www/plnip-portal/public

# Laravel storage - read/write
chcon -R -t httpd_sys_rw_content_t /var/www/plnip-portal/storage
chcon -R -t httpd_sys_rw_content_t /var/www/plnip-portal/bootstrap/cache

# Moodle - webroot (read)
chcon -R -t httpd_sys_content_t /var/www/moodle

# Moodle data folder - read/write
chcon -R -t httpd_sys_rw_content_t /var/moodledata
```

### 11.4 Allow Koneksi ke Oracle (Port 1521)

```bash
# Allow PHP koneksi ke Oracle
semanage port -a -t http_port_t -p tcp 1521

# Atau dengan policy module (jika semanage tidak ada)
dnf install -y policycoreutils-python-utils
semanage port -a -t http_port_t -p tcp 1521
```

### 11.5 Verifikasi SELinux

```bash
# Cek AVC denials (kalau ada error SELinux)
ausearch -m avc -ts recent | audit2why

# Generate policy dari denials (auto-fix)
ausearch -m avc -ts recent | audit2allow -M plnip_policy
semodule -i plnip_policy.pp
```

---

## 12. Firewall (firewalld)

```bash
# Start firewalld
systemctl start firewalld
systemctl enable firewalld

# Allow HTTP dan HTTPS
firewall-cmd --permanent --add-service=http
firewall-cmd --permanent --add-service=https

# Allow SSH (jangan sampai lupa ini atau tidak bisa masuk server)
firewall-cmd --permanent --add-service=ssh

# Jangan expose port internal (3000, 8080) ke publik
# Hanya Nginx yang perlu diakses dari luar

# Reload firewall
firewall-cmd --reload

# Verifikasi
firewall-cmd --list-all
```

---

## 13. SSL Certificate

### 13.1 Install Certbot (Let's Encrypt - Gratis)

```bash
# Install Certbot untuk Nginx
dnf install -y certbot python3-certbot-nginx

# Generate certificate untuk semua domain
certbot --nginx \
  -d plnip.co.id \
  -d www.plnip.co.id \
  -d api.plnip.co.id \
  -d moodle.plnip.co.id \
  --email admin@plnip.co.id \
  --agree-tos \
  --non-interactive

# Auto-renewal (certificate expired setiap 90 hari)
systemctl enable certbot-renew.timer
systemctl start certbot-renew.timer
```

### 13.2 Atau Pakai SSL dari IT PLN IP

Kalau IT PLN IP kasih file `.crt` dan `.key`:

```bash
# Copy file SSL
cp your-cert.crt /etc/ssl/certs/plnip.co.id.crt
cp your-key.key /etc/ssl/private/plnip.co.id.key

# Set permission
chmod 644 /etc/ssl/certs/plnip.co.id.crt
chmod 600 /etc/ssl/private/plnip.co.id.key
```

---

## 14. Services: Queue Worker + WebSocket

### 14.1 Queue Worker (Supervisor)

```bash
# Install Supervisor
dnf install -y supervisor

# Buat konfigurasi
nano /etc/supervisord.d/plnip-worker.conf
```

```ini
[program:plnip-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/plnip-portal/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=nginx
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/plnip-portal/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
# Start Supervisor
systemctl start supervisord
systemctl enable supervisord

# Load konfigurasi baru
supervisorctl reread
supervisorctl update
supervisorctl start plnip-worker:*

# Cek status
supervisorctl status
```

### 14.2 Laravel Reverb (WebSocket)

```bash
nano /etc/systemd/system/laravel-reverb.service
```

```ini
[Unit]
Description=Laravel Reverb WebSocket Server
After=network.target

[Service]
Type=simple
User=nginx
WorkingDirectory=/var/www/plnip-portal
ExecStart=/usr/bin/php /var/www/plnip-portal/artisan reverb:start --host=0.0.0.0 --port=8080
Restart=always
RestartSec=5
StandardOutput=append:/var/www/plnip-portal/storage/logs/reverb.log
StandardError=append:/var/www/plnip-portal/storage/logs/reverb-error.log

[Install]
WantedBy=multi-user.target
```

```bash
systemctl daemon-reload
systemctl enable laravel-reverb
systemctl start laravel-reverb
systemctl status laravel-reverb
```

---

## 15. Cron Jobs

```bash
# Edit crontab untuk user nginx
crontab -u nginx -e
```

```cron
# Laravel Scheduler (setiap menit)
* * * * * cd /var/www/plnip-portal && /usr/bin/php artisan schedule:run >> /dev/null 2>&1

# Moodle Cron (setiap menit - WAJIB untuk Moodle)
* * * * * /usr/bin/php /var/www/moodle/admin/cli/cron.php >> /dev/null 2>&1

# Cleanup Laravel sessions & tokens (setiap hari jam 3 pagi)
0 3 * * * cd /var/www/plnip-portal && /usr/bin/php artisan auth:clear-resets >> /dev/null 2>&1

# Backup database (setiap hari jam 2 pagi)
0 2 * * * /usr/local/bin/backup-plnip.sh >> /var/log/plnip-backup.log 2>&1
```

### Script Backup Database

```bash
nano /usr/local/bin/backup-plnip.sh
```

```bash
#!/bin/bash
set -euo pipefail

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backup/plnip"
RETENTION_DAYS=7

# Load Oracle env
export ORACLE_HOME=/usr/lib/oracle/21/client64
export LD_LIBRARY_PATH=/usr/lib/oracle/21/client64/lib:$LD_LIBRARY_PATH
export PATH=$ORACLE_HOME/bin:$PATH

mkdir -p "$BACKUP_DIR"

echo "[$(date)] Backup started"

# 1. Backup Portal DB
expdp plnip/PASSWORD@//127.0.0.1:1521/XEPDB1 \
  dumpfile=portal_${TIMESTAMP}.dmp \
  logfile=portal_${TIMESTAMP}.log \
  directory=DATA_PUMP_DIR \
  schemas=plnip

# 2. Backup Moodle DB
expdp moodle_user/PASSWORD@//127.0.0.1:1521/XEPDB1 \
  dumpfile=moodle_${TIMESTAMP}.dmp \
  logfile=moodle_${TIMESTAMP}.log \
  directory=DATA_PUMP_DIR \
  schemas=moodle_user

# 3. Backup file sertifikat
tar -czf "$BACKUP_DIR/certificates_${TIMESTAMP}.tar.gz" \
  /var/www/plnip-portal/storage/app/public/certificates/

# 4. Hapus backup lama
find "$BACKUP_DIR" -name "*.tar.gz" -mtime +"$RETENTION_DAYS" -delete

echo "[$(date)] Backup completed"
```

```bash
chmod +x /usr/local/bin/backup-plnip.sh
```

---

## 16. Verifikasi Deployment

### 16.1 Cek Semua Service

```bash
# PHP-FPM
systemctl status php-fpm

# Nginx
systemctl status nginx

# Supervisor (queue worker)
supervisorctl status

# Laravel Reverb
systemctl status laravel-reverb

# PM2 (Next.js)
pm2 list
```

### 16.2 Test Endpoint

```bash
# Backend health check
curl -I https://api.plnip.co.id/api/health
# HTTP/2 200 ✓

# Frontend
curl -I https://plnip.co.id
# HTTP/2 200 ✓

# Moodle
curl -I https://moodle.plnip.co.id
# HTTP/2 200 ✓
```

### 16.3 Test Oracle Connection dari Laravel

```bash
cd /var/www/plnip-portal
php artisan tinker

# Di dalam tinker:
DB::select('SELECT 1 FROM DUAL');
# [[1]] ✓

DB::connection('moodle')->select('SELECT 1 FROM DUAL');
# [[1]] ✓
```

### 16.4 Test Login

1. Buka https://plnip.co.id
2. Login dengan akun super-admin
3. Pastikan dashboard load
4. Test fitur enrollment, upload sertifikat, chat

---

## 17. Update & Maintenance

### 17.1 Update Backend Laravel

```bash
cd /var/www/plnip-portal

# Pull kode terbaru
git pull origin main

# Update dependencies
composer install --no-dev --optimize-autoloader

# Jalankan migration baru (kalau ada)
php artisan migrate --force

# Clear dan rebuild cache
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart workers
supervisorctl restart plnip-worker:*
systemctl restart laravel-reverb
```

### 17.2 Update Frontend Next.js

```bash
cd /var/www/plnip-portal-frontend

# Pull kode terbaru
git pull origin main

# Install dependencies baru (kalau ada)
npm ci

# Build ulang
npm run build

# Restart PM2
pm2 restart plnip-frontend
```

### 17.3 Update Moodle

```bash
cd /var/www/moodle

# Put Moodle in maintenance mode dulu
php admin/cli/maintenance.php --enable

# Backup DB dulu!
# (jalankan script backup manual)

# Update Moodle (via CLI atau copy file baru)
php admin/cli/upgrade.php --non-interactive

# Disable maintenance mode
php admin/cli/maintenance.php --disable
```

---

## 18. Troubleshooting

### Error: ORA-12154 (TNS could not resolve)

```bash
# Cek TNS_ADMIN
echo $TNS_ADMIN

# Test koneksi manual
sqlplus plnip/PASSWORD@//127.0.0.1:1521/XEPDB1

# Cek listener Oracle
lsnrctl status

# Fix: set env secara explicit di .env
# Tidak perlu tnsnames.ora jika pakai format: host:port/service
```

### Error: OCI8 extension not found

```bash
# Cek apakah extension terload
php -m | grep oci8

# Cek file .ini
cat /etc/php.d/20-oci8.ini

# Pastikan library ada
ldconfig -p | grep libclntsh

# Re-install jika perlu
pecl install oci8
```

### Error: 502 Bad Gateway (Laravel)

```bash
# Cek PHP-FPM jalan
systemctl status php-fpm

# Cek socket ada
ls -la /run/php-fpm/www.sock

# Cek Nginx error log
tail -f /var/log/nginx/error.log

# Cek SELinux denials
ausearch -m avc -ts recent
```

### Error: 502 Bad Gateway (Next.js)

```bash
# Cek PM2
pm2 list
pm2 logs plnip-frontend

# Restart jika crash
pm2 restart plnip-frontend

# Cek port 3000 aktif
ss -tlnp | grep 3000
```

### Error: Permission Denied (storage)

```bash
cd /var/www/plnip-portal

# Fix ownership
chown -R nginx:nginx storage bootstrap/cache

# Fix permissions
chmod -R 775 storage bootstrap/cache

# Fix SELinux context
chcon -R -t httpd_sys_rw_content_t storage
chcon -R -t httpd_sys_rw_content_t bootstrap/cache
```

### Moodle: Cron tidak jalan

```bash
# Test manual
sudo -u nginx php /var/www/moodle/admin/cli/cron.php

# Cek crontab
crontab -u nginx -l

# Cek error Moodle
tail -f /var/moodledata/moodle_error.log
```

### Queue Worker tidak proses job

```bash
# Cek status
supervisorctl status plnip-worker:*

# Lihat log
tail -f /var/www/plnip-portal/storage/logs/worker.log

# Restart
supervisorctl restart plnip-worker:*

# Lihat failed jobs
php artisan queue:failed
```

### SELinux blocking akses

```bash
# Lihat denials terbaru
ausearch -m avc -ts recent | tail -20

# Auto-generate dan apply fix
ausearch -m avc -ts recent | audit2allow -M plnip_fix
semodule -i plnip_fix.pp

# Atau temporary disable SELinux untuk diagnosa
setenforce 0
# Test apakah error hilang → berarti SELinux yang block
setenforce 1  # Aktifkan lagi
```

---

## 19. Checklist Deploy

### Pre-Deploy
- [ ] Backup database Portal Oracle
- [ ] Backup database Moodle Oracle
- [ ] Backup file sertifikat di storage
- [ ] Backup file `.env`
- [ ] Test di staging environment terlebih dahulu

### Server Setup (Hanya Pertama Kali)
- [ ] PHP 8.2 + extensions terinstall
- [ ] Oracle Instant Client terinstall
- [ ] OCI8 PHP extension aktif
- [ ] Nginx terinstall dan jalan
- [ ] Node.js 18 + PM2 terinstall
- [ ] Composer terinstall
- [ ] SELinux dikonfigurasi
- [ ] Firewall dikonfigurasi (http/https/ssh)
- [ ] SSL certificate terpasang

### Deploy Backend
- [ ] Upload kode ke server (pilih salah satu):
  - [ ] `git clone` (jika ada remote repo + SSH key sudah setup) — **atau**
  - [ ] `scp`/`tar+scp` dari lokal (jika belum ada remote repo) — **atau**
  - [ ] `rsync` untuk update file yang berubah saja
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `.env` dikonfigurasi (DB, MOODLE, GEMINI, dll)
- [ ] `php artisan key:generate`
- [ ] `php artisan storage:link`
- [ ] `chmod -R 775 storage bootstrap/cache`
- [ ] `php artisan migrate --force`
- [ ] `php artisan db:seed --class=RolePermissionSeeder` (hanya pertama kali)
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] SELinux context di-set untuk storage
- [ ] Nginx config untuk API aktif
- [ ] Queue worker (Supervisor) jalan
- [ ] Laravel Reverb (WebSocket) jalan

### Deploy Frontend
- [ ] Upload kode frontend ke server (git clone / scp / rsync)
- [ ] `.env.production` dikonfigurasi
- [ ] `npm ci`
- [ ] `npm run build`
- [ ] PM2 start/restart
- [ ] `pm2 startup && pm2 save`
- [ ] Nginx config untuk frontend aktif

### Deploy Moodle
- [ ] Moodle files di `/var/www/moodle`
- [ ] `/var/moodledata` dibuat dengan permission nginx
- [ ] Oracle user `moodle_user` punya grant yang cukup
- [ ] `config.php` dikonfigurasi
- [ ] CLI installer selesai (atau upgrade)
- [ ] Web Services diaktifkan di Moodle admin
- [ ] Token Moodle WS di-copy ke `.env` Laravel
- [ ] Cron Moodle dipasang
- [ ] SELinux context di-set untuk moodledata
- [ ] Nginx config untuk Moodle aktif

### Post-Deploy Verification
- [ ] `curl https://api.plnip.co.id/api/health` → 200
- [ ] `curl https://plnip.co.id` → 200
- [ ] `curl https://moodle.plnip.co.id` → 200
- [ ] Test login di browser
- [ ] Test enrolling kelas
- [ ] Test upload sertifikat
- [ ] Test chat realtime
- [ ] Monitor logs 1 jam setelah deploy

---

## Ringkasan Domain & Port

| Service | Domain | Port Internal |
|---------|--------|---------------|
| Frontend (Next.js) | plnip.co.id | 3000 (via PM2) |
| Backend (Laravel) | api.plnip.co.id | PHP-FPM socket |
| LMS (Moodle) | moodle.plnip.co.id | PHP-FPM socket |
| WebSocket (Reverb) | api.plnip.co.id/app | 8080 (via Nginx proxy) |

---

## Ringkasan Services

| Service | Tool | Auto-Start |
|---------|------|------------|
| PHP-FPM | systemd | ✅ `systemctl enable php-fpm` |
| Nginx | systemd | ✅ `systemctl enable nginx` |
| Queue Worker | Supervisor | ✅ `systemctl enable supervisord` |
| WebSocket | systemd | ✅ `systemctl enable laravel-reverb` |
| Next.js | PM2 | ✅ `pm2 startup && pm2 save` |

---

*Dokumentasi ini dibuat untuk deployment PLN IP Learning Hub Portal ke server RHEL.*
*Untuk referensi tambahan: `docs/02-INSTALASI-DEPLOYMENT.md` (Ubuntu), `docs/03-API-REFERENCE.md`, `docs/04-MAINTENANCE.md`*
