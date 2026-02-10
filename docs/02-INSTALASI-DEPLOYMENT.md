# Instalasi dan Deployment PLN IP Learning Hub Portal

## 1. Requirement Sistem

### 1.1 Software Requirements

**Backend (Laravel 12):**
- PHP 8.2 atau lebih tinggi
- Composer 2.x
- Oracle Instant Client 21c atau lebih tinggi
- Oracle Database 11g atau lebih tinggi

**Frontend (Next.js 14):**
- Node.js 18.x atau lebih tinggi (LTS recommended)
- npm 9.x atau yarn 1.22.x

**Additional Services:**
- Redis (optional, untuk cache dan queue)
- Supervisor (untuk queue worker di production)

### 1.2 PHP Extensions Required

```
php -m | grep -E 'pdo|oci8|mbstring|openssl|curl|json|xml'
```

**Required extensions:**
- `pdo_oci` - Oracle database driver
- `oci8` - Oracle OCI8 extension
- `mbstring` - Multibyte string support
- `openssl` - SSL/TLS support
- `curl` - HTTP client
- `json` - JSON processing
- `xml` - XML processing
- `zip` - ZIP archive support
- `gd` atau `imagick` - Image processing

### 1.3 Server Requirements (Production)

**Minimum:**
- CPU: 4 cores
- RAM: 8 GB
- Storage: 100 GB (SSD recommended)
- Network: 100 Mbps

**Recommended:**
- CPU: 8 cores
- RAM: 16 GB
- Storage: 250 GB SSD
- Network: 1 Gbps

## 2. Instalasi Oracle Instant Client

### 2.1 Download Oracle Instant Client

1. Kunjungi: https://www.oracle.com/database/technologies/instant-client/downloads.html
2. Download Oracle Instant Client 21c untuk OS Anda:
   - Windows: `instantclient-basic-windows.x64-21.x.x.x.zip`
   - Linux: `oracle-instantclient-basic-21.x.x.x-1.x86_64.rpm`

### 2.2 Instalasi di Windows

```bash
# Extract ZIP ke C:\oracle\instantclient_21_8
# Atau lokasi lain yang mudah diakses

# Tambahkan ke System PATH
setx PATH "%PATH%;C:\oracle\instantclient_21_8"

# Restart terminal untuk apply PATH
```

### 2.3 Instalasi di Linux (CentOS/RHEL)

```bash
# Install RPM
sudo rpm -ivh oracle-instantclient-basic-21.8.0.0.0-1.x86_64.rpm

# Atau via alien (Debian/Ubuntu)
sudo alien -i oracle-instantclient-basic-21.8.0.0.0-1.x86_64.rpm

# Set LD_LIBRARY_PATH
export LD_LIBRARY_PATH=/usr/lib/oracle/21/client64/lib:$LD_LIBRARY_PATH

# Tambahkan ke ~/.bashrc untuk permanent
echo 'export LD_LIBRARY_PATH=/usr/lib/oracle/21/client64/lib:$LD_LIBRARY_PATH' >> ~/.bashrc
```

### 2.4 Verifikasi Instalasi

```bash
# Test koneksi Oracle
echo 'SELECT 1 FROM DUAL;' | sqlplus username/password@//host:1521/service_name
```

## 3. Setup Development Environment

### 3.1 Clone Repository

```bash
# Backend
cd c:\laragon\www
git clone https://github.com/your-org/plnip-portal.git
cd plnip-portal

# Frontend (separate repository)
cd c:\laragon\www
git clone https://github.com/your-org/plnip-portal-frontend.git
cd plnip-portal-frontend
```

### 3.2 Setup Backend (Laravel)

**Step 1: Install Dependencies**

```bash
cd c:\laragon\www\plnip-portal

# Install Composer dependencies
composer install

# Jika ada error OCI8, pastikan Oracle Instant Client sudah terinstall
```

**Step 2: Environment Configuration**

```bash
# Copy .env.example ke .env
cp .env.example .env

# Generate application key
php artisan key:generate
```

**Step 3: Konfigurasi Database di `.env`**

```env
# Application
APP_NAME="PLN IP Learning Hub"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Portal Database (Oracle)
DB_CONNECTION=oracle
DB_HOST=192.168.1.100
DB_PORT=1521
DB_DATABASE=PLNIPDB
DB_SERVICE_NAME=XEPDB1
DB_USERNAME=portal_user
DB_PASSWORD=your_password

# Moodle Database (Oracle)
MOODLE_DB_HOST=192.168.1.101
MOODLE_DB_PORT=1521
MOODLE_DB_DATABASE=MOODLEDB
MOODLE_DB_SERVICE_NAME=MOODLEPDB
MOODLE_DB_USERNAME=moodle_user
MOODLE_DB_PASSWORD=moodle_password

# Moodle Web Services
MOODLE_URL=https://moodle.plnip.co.id
MOODLE_WS_TOKEN=your_moodle_webservice_token

# ERP Integration
ERP_ENABLED=false
ERP_API_URL=https://erp.plnip.co.id/api/employees
ERP_API_KEY=your_erp_api_key
ERP_SYNC_TIMEOUT=30
ERP_SYNC_SCHEDULE=02:00

# Gemini AI
GEMINI_API_KEY=your_gemini_api_key

# Laravel Reverb (WebSocket)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# Session & Cache
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

# File Storage
FILESYSTEM_DISK=public
```

**Step 4: Database Migration**

```bash
# Test koneksi database
php artisan db:show

# Jalankan migration
php artisan migrate

# Seed roles dan permissions
php artisan db:seed --class=RolePermissionSeeder

# Atau seed semua (termasuk dummy data untuk development)
php artisan db:seed
```

**Step 5: Storage Setup**

```bash
# Buat symlink untuk public storage
php artisan storage:link

# Pastikan folder storage writable
chmod -R 775 storage bootstrap/cache
```

**Step 6: Install Laravel Reverb**

```bash
# Install Reverb
php artisan reverb:install

# Update .env dengan Reverb config (sudah dilakukan di step 3)
```

**Step 7: Start Development Server**

```bash
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Queue worker
php artisan queue:work

# Terminal 3: Laravel Reverb (WebSocket)
php artisan reverb:start

# Akses di browser: http://localhost:8000
```

### 3.3 Setup Frontend (Next.js)

**Step 1: Install Dependencies**

```bash
cd c:\laragon\www\plnip-portal-frontend

# Install npm packages
npm install

# Atau dengan yarn
yarn install
```

**Step 2: Environment Configuration**

```bash
# Copy .env.example ke .env.local
cp .env.example .env.local
```

**Edit `.env.local`:**

```env
# API Configuration
NEXT_PUBLIC_API_URL=http://localhost:8000

# WebSocket Configuration (Laravel Reverb)
NEXT_PUBLIC_WS_HOST=localhost
NEXT_PUBLIC_WS_PORT=8080
NEXT_PUBLIC_WS_KEY=your-app-key
NEXT_PUBLIC_WS_CLUSTER=mt1

# Application
NEXT_PUBLIC_APP_NAME="PLN IP Learning Hub"
```

**Step 3: Start Development Server**

```bash
# Development mode (with hot reload)
npm run dev

# Akses di browser: http://localhost:3000
```

**Step 4: Verifikasi Koneksi**

1. Buka http://localhost:3000
2. Klik "Masuk" (Login)
3. Test login dengan credentials dari seeder:
   - Email: `superadmin@plnip.co.id`
   - Password: `password`

## 4. Konfigurasi Moodle

### 4.1 Enable Web Services di Moodle

**Step 1: Akses Moodle sebagai Admin**

1. Login ke Moodle: https://moodle.plnip.co.id
2. Navigate: Site Administration → Advanced Features
3. Enable "Enable web services" (checkbox)
4. Save changes

**Step 2: Create Web Service User**

1. Navigate: Site Administration → Users → Accounts → Add a new user
2. Isi data:
   - Username: `portal_ws_user`
   - First name: `Portal`
   - Last name: `WebService`
   - Email: `portal-ws@plnip.co.id`
   - Password: (strong password)
3. Save

**Step 3: Create Custom Role untuk Web Service**

1. Navigate: Site Administration → Users → Permissions → Define roles
2. Add a new role:
   - Short name: `webserviceuser`
   - Full name: `Web Service User`
   - Context types: System
3. Assign capabilities:
   - `webservice/rest:use` (Allow)
   - `moodle/webservice:createtoken` (Allow)
   - `moodle/user:viewdetails` (Allow)
   - `moodle/course:view` (Allow)
   - `moodle/course:viewhiddencourses` (Allow)
   - Dan capabilities lain sesuai kebutuhan
4. Create this role

**Step 4: Assign Role ke Web Service User**

1. Navigate: Site Administration → Users → Permissions → Assign system roles
2. Pilih role "Web Service User"
3. Add user `portal_ws_user`

**Step 5: Enable REST Protocol**

1. Navigate: Site Administration → Plugins → Web services → Manage protocols
2. Enable "REST protocol"

**Step 6: Create External Service**

1. Navigate: Site Administration → Plugins → Web services → External services
2. Add new external service:
   - Name: `PLN IP Portal Service`
   - Short name: `plnip_portal`
   - Enabled: Yes
   - Authorized users only: Yes
3. Add functions:
   - `core_course_get_contents`
   - `core_user_create_users`
   - `enrol_manual_enrol_users`
   - `mod_assign_get_assignments`
   - Dan functions lain yang dibutuhkan
4. Save

**Step 7: Add Authorized User**

1. Pada external service yang baru dibuat, klik "Authorised users"
2. Add user `portal_ws_user`

**Step 8: Generate Token**

1. Navigate: Site Administration → Plugins → Web services → Manage tokens
2. Add new token:
   - User: `portal_ws_user`
   - Service: `PLN IP Portal Service`
3. Copy token yang di-generate
4. Paste ke `.env` backend: `MOODLE_WS_TOKEN=...`

### 4.2 Database Connection ke Moodle

**Step 1: Buat Database User untuk Portal**

```sql
-- Login sebagai Moodle DB admin
sqlplus moodle_admin/password@moodledb

-- Buat user baru dengan read-only access
CREATE USER portal_readonly IDENTIFIED BY "strong_password";

-- Grant privileges (read-only)
GRANT CONNECT TO portal_readonly;
GRANT SELECT ON moodle_admin.mdl_user TO portal_readonly;
GRANT SELECT ON moodle_admin.mdl_course TO portal_readonly;
GRANT SELECT ON moodle_admin.mdl_course_categories TO portal_readonly;
GRANT SELECT ON moodle_admin.mdl_enrol TO portal_readonly;
GRANT SELECT ON moodle_admin.mdl_user_enrolments TO portal_readonly;
GRANT SELECT ON moodle_admin.mdl_grade_items TO portal_readonly;
GRANT SELECT ON moodle_admin.mdl_grade_grades TO portal_readonly;
GRANT SELECT ON moodle_admin.mdl_course_modules TO portal_readonly;
-- Dan tabel lain yang diperlukan

-- Test koneksi
SELECT COUNT(*) FROM moodle_admin.mdl_user;
```

**Step 2: Update `.env` Backend**

```env
MOODLE_DB_HOST=192.168.1.101
MOODLE_DB_PORT=1521
MOODLE_DB_DATABASE=MOODLEDB
MOODLE_DB_SERVICE_NAME=MOODLEPDB
MOODLE_DB_USERNAME=portal_readonly
MOODLE_DB_PASSWORD=strong_password
```

**Step 3: Test Koneksi dari Laravel**

```bash
php artisan tinker

# Test query
DB::connection('moodle')->table('user')->count();

# Jika berhasil, akan return jumlah users
```

## 5. Konfigurasi ERP Integration

### 5.1 Requirement ERP API

Portal membutuhkan REST API endpoint dari ERP dengan format:

**Endpoint:** `GET /api/employees`

**Authentication:** Bearer Token

**Response Format:**
```json
{
  "employees": [
    {
      "employee_id": "12345678",
      "name": "Budi Santoso",
      "email": "budi.santoso@plnip.co.id",
      "phone": "08123456789",
      "department": "Engineering",
      "position": "Senior Engineer",
      "access_group": "USER",
      "is_active": true
    },
    ...
  ]
}
```

**Field Mapping:**

| Field         | Type    | Required | Keterangan                           |
|---------------|---------|----------|--------------------------------------|
| employee_id   | string  | YES      | NIP karyawan (unique identifier)     |
| name          | string  | YES      | Nama lengkap                         |
| email         | string  | YES      | Email corporate (unique)             |
| phone         | string  | NO       | Nomor telepon                        |
| department    | string  | NO       | Departemen/Unit kerja                |
| position      | string  | NO       | Jabatan                              |
| access_group  | string  | NO       | USER, ADMIN, INSTRUCTOR, SUPER-ADMIN |
| is_active     | boolean | NO       | Status aktif (default: true)         |

### 5.2 Setup ERP Connection

**Step 1: Request API Key dari Tim ERP**

Hubungi tim ERP PLN IP untuk mendapatkan:
- API URL
- API Key (Bearer Token)

**Step 2: Konfigurasi di `.env`**

```env
ERP_ENABLED=true
ERP_API_URL=https://erp.plnip.co.id/api/employees
ERP_API_KEY=your_erp_api_key_here
ERP_SYNC_TIMEOUT=30
ERP_SYNC_SCHEDULE=02:00
ERP_MAX_RETRIES=3
ERP_RETRY_DELAY=60
ERP_VERIFY_SSL=true
```

**Step 3: Test ERP Connection**

```bash
php artisan tinker

# Test fetch employees
$service = app(\App\Services\ERPSyncService::class);
$employees = $service->syncUsers();

# Cek hasil
print_r($employees);
```

### 5.3 Setup Scheduled Sync

**Edit `app/Console/Kernel.php`:**

```php
protected function schedule(Schedule $schedule)
{
    // ERP Sync daily at 02:00 (configurable via .env)
    $schedule->call(function () {
        app(\App\Services\ERPSyncService::class)->syncUsers();
    })->dailyAt(config('erp.schedule', '02:00'))
      ->when(config('erp.enabled', false));
}
```

**Setup Cron Job (Linux):**

```bash
# Edit crontab
crontab -e

# Tambahkan line berikut
* * * * * cd /path/to/plnip-portal && php artisan schedule:run >> /dev/null 2>&1
```

**Setup Task Scheduler (Windows):**

```batch
# Buat scheduled task yang menjalankan:
php C:\laragon\www\plnip-portal\artisan schedule:run
```

## 6. Build untuk Production

### 6.1 Build Backend

```bash
cd c:\laragon\www\plnip-portal

# Install dependencies (production only)
composer install --no-dev --optimize-autoloader

# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer dump-autoload --optimize
```

### 6.2 Build Frontend

```bash
cd c:\laragon\www\plnip-portal-frontend

# Install dependencies
npm ci --production

# Build static assets
npm run build

# Test production build locally
npm start
```

### 6.3 Environment Production

**Backend `.env`:**

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://portal.plnip.co.id

# Database production
DB_CONNECTION=oracle
DB_HOST=prod-oracle-host
DB_PORT=1521
DB_DATABASE=PLNIP_PROD
DB_SERVICE_NAME=PLNIPPROD
DB_USERNAME=portal_prod
DB_PASSWORD=strong_production_password

# Session & Cache (Redis recommended)
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=redis_password
REDIS_PORT=6379

# Laravel Reverb Production
BROADCAST_CONNECTION=reverb
REVERB_HOST=portal.plnip.co.id
REVERB_PORT=443
REVERB_SCHEME=https

# ERP (enabled in production)
ERP_ENABLED=true
```

**Frontend `.env.production`:**

```env
NEXT_PUBLIC_API_URL=https://portal.plnip.co.id
NEXT_PUBLIC_WS_HOST=portal.plnip.co.id
NEXT_PUBLIC_WS_PORT=443
NEXT_PUBLIC_WS_KEY=production-app-key
```

## 7. Deployment ke Server Production

### 7.1 Server Setup (Linux)

**Prasyarat:**
- Ubuntu 22.04 LTS atau CentOS 8
- Nginx atau Apache
- PHP 8.2-FPM
- Oracle Instant Client
- Node.js 18.x
- Supervisor (untuk queue worker)
- SSL Certificate (Let's Encrypt atau corporate CA)

### 7.2 Deploy Backend (Laravel)

**Step 1: Upload Code ke Server**

```bash
# Via Git
ssh user@server
cd /var/www
git clone https://github.com/your-org/plnip-portal.git
cd plnip-portal

# Atau via rsync
rsync -avz --exclude 'node_modules' --exclude 'vendor' \
  ./plnip-portal/ user@server:/var/www/plnip-portal/
```

**Step 2: Install Dependencies**

```bash
cd /var/www/plnip-portal

# Install Composer dependencies
composer install --no-dev --optimize-autoloader

# Set permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

**Step 3: Setup Environment**

```bash
# Copy .env
cp .env.production .env

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Seed roles (first time only)
php artisan db:seed --class=RolePermissionSeeder

# Cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage link
php artisan storage:link
```

**Step 4: Configure Nginx**

```nginx
server {
    listen 80;
    server_name portal.plnip.co.id;

    # Redirect HTTP to HTTPS
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name portal.plnip.co.id;

    # SSL Configuration
    ssl_certificate /etc/ssl/certs/portal.plnip.co.id.crt;
    ssl_certificate_key /etc/ssl/private/portal.plnip.co.id.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    root /var/www/plnip-portal/public;
    index index.php index.html;

    # Increase upload size for certificate upload
    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}

# WebSocket (Laravel Reverb)
server {
    listen 443 ssl http2;
    server_name ws.portal.plnip.co.id;

    ssl_certificate /etc/ssl/certs/portal.plnip.co.id.crt;
    ssl_certificate_key /etc/ssl/private/portal.plnip.co.id.key;

    location / {
        proxy_pass http://localhost:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

**Step 5: Setup Supervisor untuk Queue Worker**

```bash
# Create supervisor config
sudo nano /etc/supervisor/conf.d/plnip-portal-worker.conf
```

```ini
[program:plnip-portal-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/plnip-portal/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/plnip-portal/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
# Reload supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start plnip-portal-worker:*
```

**Step 6: Setup Laravel Reverb Service**

```bash
# Create systemd service
sudo nano /etc/systemd/system/laravel-reverb.service
```

```ini
[Unit]
Description=Laravel Reverb WebSocket Server
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/plnip-portal
ExecStart=/usr/bin/php /var/www/plnip-portal/artisan reverb:start
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
# Enable and start service
sudo systemctl daemon-reload
sudo systemctl enable laravel-reverb
sudo systemctl start laravel-reverb
```

### 7.3 Deploy Frontend (Next.js)

**Step 1: Upload Code**

```bash
ssh user@server
cd /var/www
git clone https://github.com/your-org/plnip-portal-frontend.git
cd plnip-portal-frontend
```

**Step 2: Build Application**

```bash
# Install dependencies
npm ci --production

# Build
npm run build

# Test locally
npm start
```

**Step 3: Setup PM2 (Process Manager)**

```bash
# Install PM2 globally
sudo npm install -g pm2

# Start application
pm2 start npm --name "plnip-frontend" -- start

# Auto-restart on reboot
pm2 startup
pm2 save
```

**Step 4: Configure Nginx for Frontend**

```nginx
server {
    listen 80;
    server_name plnip.co.id www.plnip.co.id;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name plnip.co.id www.plnip.co.id;

    ssl_certificate /etc/ssl/certs/plnip.co.id.crt;
    ssl_certificate_key /etc/ssl/private/plnip.co.id.key;

    location / {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

## 8. Post-Deployment

### 8.1 Verifikasi Deployment

```bash
# Check backend health
curl https://portal.plnip.co.id/api/health

# Check frontend
curl https://plnip.co.id

# Check WebSocket
curl https://ws.portal.plnip.co.id

# Check queue worker
sudo supervisorctl status plnip-portal-worker:*

# Check Laravel Reverb
sudo systemctl status laravel-reverb
```

### 8.2 Setup SSL Certificate (Let's Encrypt)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Generate certificate
sudo certbot --nginx -d plnip.co.id -d www.plnip.co.id -d portal.plnip.co.id -d ws.portal.plnip.co.id

# Auto-renewal (cron)
sudo crontab -e
# Add:
0 0 1 * * /usr/bin/certbot renew --quiet
```

### 8.3 Setup Monitoring

**Install monitoring tools:**

```bash
# Application monitoring
composer require laravel/telescope --dev

# Server monitoring
sudo apt install htop iotop
```

### 8.4 Backup Setup

```bash
# Create backup script
sudo nano /usr/local/bin/backup-plnip.sh
```

```bash
#!/bin/bash
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backup/plnip"

# Database backup
ORACLE_HOME=/usr/lib/oracle/21/client64
export ORACLE_HOME
export PATH=$ORACLE_HOME/bin:$PATH

exp userid=portal_prod/password@PLNIP_PROD \
    file=$BACKUP_DIR/db_backup_$TIMESTAMP.dmp \
    log=$BACKUP_DIR/db_backup_$TIMESTAMP.log \
    full=y

# File backup (certificates)
tar -czf $BACKUP_DIR/files_backup_$TIMESTAMP.tar.gz \
    /var/www/plnip-portal/storage/app/public/certificates

# Keep last 7 days only
find $BACKUP_DIR -name "*.dmp" -mtime +7 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete

echo "Backup completed: $TIMESTAMP"
```

```bash
# Make executable
sudo chmod +x /usr/local/bin/backup-plnip.sh

# Schedule daily backup at 03:00
sudo crontab -e
# Add:
0 3 * * * /usr/local/bin/backup-plnip.sh >> /var/log/plnip-backup.log 2>&1
```

## 9. Troubleshooting Common Issues

### 9.1 Oracle Connection Error

**Error:** `ORA-12154: TNS:could not resolve the connect identifier`

**Solution:**
```bash
# Check TNS_ADMIN environment variable
echo $TNS_ADMIN

# Check tnsnames.ora
cat $ORACLE_HOME/network/admin/tnsnames.ora

# Test connection
sqlplus username/password@service_name
```

### 9.2 PHP OCI8 Extension Not Found

**Error:** `Call to undefined function oci_connect()`

**Solution:**
```bash
# Install OCI8 via PECL
sudo pecl install oci8

# Add to php.ini
echo "extension=oci8.so" | sudo tee -a /etc/php/8.2/cli/php.ini
echo "extension=oci8.so" | sudo tee -a /etc/php/8.2/fpm/php.ini

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

### 9.3 Permission Denied on Storage

**Error:** `file_put_contents(...): failed to open stream: Permission denied`

**Solution:**
```bash
cd /var/www/plnip-portal

# Fix ownership
sudo chown -R www-data:www-data storage bootstrap/cache

# Fix permissions
sudo chmod -R 775 storage bootstrap/cache

# SELinux (if enabled)
sudo chcon -R -t httpd_sys_rw_content_t storage
```

### 9.4 Moodle Connection Timeout

**Error:** `Connection timeout when connecting to Moodle`

**Solution:**
```bash
# Check firewall
sudo ufw status

# Allow outbound connections
sudo ufw allow out 1521/tcp

# Check network connectivity
telnet moodle-db-host 1521

# Increase timeout in .env
MOODLE_DB_TIMEOUT=60
```

### 9.5 Queue Worker Not Processing Jobs

**Solution:**
```bash
# Check supervisor status
sudo supervisorctl status plnip-portal-worker:*

# Restart workers
sudo supervisorctl restart plnip-portal-worker:*

# Check logs
tail -f /var/www/plnip-portal/storage/logs/worker.log

# Clear failed jobs
php artisan queue:flush
```

### 9.6 Laravel Reverb Not Starting

**Solution:**
```bash
# Check port availability
sudo netstat -tuln | grep 8080

# Check service status
sudo systemctl status laravel-reverb

# View logs
sudo journalctl -u laravel-reverb -f

# Restart service
sudo systemctl restart laravel-reverb
```

### 9.7 Next.js Build Error

**Error:** `Error: ENOSPC: System limit for number of file watchers reached`

**Solution:**
```bash
# Increase inotify watchers
echo fs.inotify.max_user_watches=524288 | sudo tee -a /etc/sysctl.conf
sudo sysctl -p

# Restart PM2
pm2 restart plnip-frontend
```

## 10. Update dan Maintenance

### 10.1 Update Application

**Backend:**
```bash
cd /var/www/plnip-portal

# Pull latest code
git pull origin main

# Install new dependencies
composer install --no-dev --optimize-autoloader

# Run new migrations
php artisan migrate --force

# Clear cache
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue workers
sudo supervisorctl restart plnip-portal-worker:*

# Restart Laravel Reverb
sudo systemctl restart laravel-reverb
```

**Frontend:**
```bash
cd /var/www/plnip-portal-frontend

# Pull latest code
git pull origin main

# Install new dependencies
npm ci --production

# Build
npm run build

# Restart PM2
pm2 restart plnip-frontend
```

### 10.2 Rollback Strategy

**Persiapan:**
```bash
# Tag current version sebelum deploy
git tag -a v1.0.0 -m "Release v1.0.0"
git push origin v1.0.0
```

**Rollback:**
```bash
# Checkout previous tag
git checkout v0.9.9

# Install dependencies
composer install --no-dev --optimize-autoloader

# Rollback migration
php artisan migrate:rollback --step=1

# Clear cache
php artisan cache:clear
php artisan config:cache
```

### 10.3 Database Migration Best Practice

```bash
# ALWAYS backup before migrate
php artisan db:backup  # Custom command if available

# Or manual backup
exp userid=portal_prod/password@PLNIP_PROD \
    file=/backup/pre_migration_$(date +%Y%m%d).dmp \
    full=y

# Test migration in staging first
php artisan migrate --pretend

# Run migration
php artisan migrate --force

# If error, rollback immediately
php artisan migrate:rollback --step=1
```

## 11. Checklist Deployment

### Pre-Deployment
- [ ] Backup database production
- [ ] Backup file storage (certificates)
- [ ] Test di staging environment
- [ ] Code review approved
- [ ] Tag release version di Git
- [ ] Update documentation
- [ ] Notify users tentang maintenance window

### Deployment
- [ ] Pull latest code
- [ ] Install dependencies (composer/npm)
- [ ] Update .env configuration
- [ ] Run database migrations
- [ ] Clear all cache
- [ ] Build frontend assets
- [ ] Restart services (queue, reverb, pm2)
- [ ] Run smoke tests

### Post-Deployment
- [ ] Verify aplikasi accessible
- [ ] Test login functionality
- [ ] Test API endpoints
- [ ] Check queue processing
- [ ] Check WebSocket connection
- [ ] Monitor error logs
- [ ] Verify certificate downloads
- [ ] Test ERP sync (if enabled)

## 12. Kesimpulan

Dokumentasi ini mencakup semua langkah yang diperlukan untuk setup development environment dan deployment ke production server. Pastikan semua requirement terpenuhi dan ikuti langkah-langkah dengan hati-hati, terutama saat deployment ke production.

Untuk troubleshooting lebih lanjut atau kasus khusus yang tidak tercakup di sini, hubungi tim development PLN IP Learning Hub.
