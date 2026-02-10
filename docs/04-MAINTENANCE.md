# Maintenance Guide PLN IP Learning Hub Portal

## 1. Monitoring Sistem

### 1.1 Health Checks Rutin

**Backend Health Check:**
```bash
# Check API health
curl https://portal.plnip.co.id/api/health

# Check database connection
php artisan db:show

# Check Moodle connection
php artisan tinker
>>> DB::connection('moodle')->select('SELECT 1 FROM DUAL');
```

**Frontend Health Check:**
```bash
# Check frontend accessibility
curl https://plnip.co.id

# Check Next.js server status (via PM2)
pm2 status plnip-frontend
```

**Services Health Check:**
```bash
# Queue worker status
sudo supervisorctl status plnip-portal-worker:*

# Laravel Reverb WebSocket
sudo systemctl status laravel-reverb

# Nginx status
sudo systemctl status nginx

# PHP-FPM status
sudo systemctl status php8.2-fpm
```

### 1.2 Log Monitoring

**Backend Logs:**
```bash
# Application logs
tail -f /var/www/plnip-portal/storage/logs/laravel.log

# Queue worker logs
tail -f /var/www/plnip-portal/storage/logs/worker.log

# Nginx access logs
tail -f /var/log/nginx/access.log

# Nginx error logs
tail -f /var/log/nginx/error.log

# PHP-FPM logs
tail -f /var/log/php8.2-fpm.log
```

**Frontend Logs:**
```bash
# PM2 logs
pm2 logs plnip-frontend

# PM2 error logs only
pm2 logs plnip-frontend --err
```

**Laravel Reverb Logs:**
```bash
# Systemd logs
sudo journalctl -u laravel-reverb -f

# Last 100 lines
sudo journalctl -u laravel-reverb -n 100
```

### 1.3 Performance Monitoring

**Database Performance:**
```sql
-- Active sessions
SELECT username, status, osuser, machine, program
FROM v$session
WHERE username IS NOT NULL;

-- Long running queries
SELECT sql_text, elapsed_time, executions
FROM v$sql
WHERE elapsed_time > 1000000
ORDER BY elapsed_time DESC;

-- Table sizes
SELECT segment_name, bytes/1024/1024 AS size_mb
FROM user_segments
WHERE segment_type = 'TABLE'
ORDER BY bytes DESC;
```

**Server Resources:**
```bash
# CPU and memory usage
htop

# Disk usage
df -h

# Disk I/O
iotop

# Network usage
iftop

# Process monitoring
ps aux | grep php
ps aux | grep node
```

**Application Performance:**
```bash
# Queue jobs count
php artisan queue:monitor

# Failed jobs
php artisan queue:failed

# Cache hit ratio (if using Redis)
redis-cli info stats
```

### 1.4 Automated Monitoring

**Setup Monitoring Script:**

```bash
# Create monitoring script
sudo nano /usr/local/bin/monitor-plnip.sh
```

```bash
#!/bin/bash
# PLN IP Portal Health Monitoring

TIMESTAMP=$(date +"%Y-%m-%d %H:%M:%S")
LOG_FILE="/var/log/plnip-monitor.log"

echo "[$TIMESTAMP] Starting health check..." >> $LOG_FILE

# Check API
API_STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://portal.plnip.co.id/api/health)
if [ $API_STATUS -ne 200 ]; then
    echo "[$TIMESTAMP] ERROR: API health check failed (HTTP $API_STATUS)" >> $LOG_FILE
    # Send alert (email, Slack, etc)
fi

# Check Frontend
FRONTEND_STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://plnip.co.id)
if [ $FRONTEND_STATUS -ne 200 ]; then
    echo "[$TIMESTAMP] ERROR: Frontend health check failed (HTTP $FRONTEND_STATUS)" >> $LOG_FILE
fi

# Check Queue Worker
WORKER_STATUS=$(sudo supervisorctl status plnip-portal-worker:* | grep -c "RUNNING")
if [ $WORKER_STATUS -lt 2 ]; then
    echo "[$TIMESTAMP] ERROR: Queue workers not running properly" >> $LOG_FILE
    sudo supervisorctl restart plnip-portal-worker:*
fi

# Check Laravel Reverb
REVERB_STATUS=$(sudo systemctl is-active laravel-reverb)
if [ "$REVERB_STATUS" != "active" ]; then
    echo "[$TIMESTAMP] ERROR: Laravel Reverb not running" >> $LOG_FILE
    sudo systemctl restart laravel-reverb
fi

# Check disk space
DISK_USAGE=$(df -h / | awk 'NR==2 {print $5}' | sed 's/%//')
if [ $DISK_USAGE -gt 85 ]; then
    echo "[$TIMESTAMP] WARNING: Disk usage is at ${DISK_USAGE}%" >> $LOG_FILE
fi

echo "[$TIMESTAMP] Health check completed" >> $LOG_FILE
```

```bash
# Make executable
sudo chmod +x /usr/local/bin/monitor-plnip.sh

# Add to crontab (every 5 minutes)
sudo crontab -e
*/5 * * * * /usr/local/bin/monitor-plnip.sh
```

## 2. Database Backup dan Restore

### 2.1 Manual Backup

**Full Database Export:**
```bash
# Export Oracle database
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
ORACLE_HOME=/usr/lib/oracle/21/client64
export ORACLE_HOME
export PATH=$ORACLE_HOME/bin:$PATH

exp userid=portal_prod/password@PLNIP_PROD \
    file=/backup/plnip/db_full_$TIMESTAMP.dmp \
    log=/backup/plnip/db_full_$TIMESTAMP.log \
    full=y \
    compress=y
```

**Schema Only Export:**
```bash
exp userid=portal_prod/password@PLNIP_PROD \
    file=/backup/plnip/db_schema_$TIMESTAMP.dmp \
    log=/backup/plnip/db_schema_$TIMESTAMP.log \
    owner=portal_prod \
    rows=n
```

**Specific Tables Export:**
```bash
exp userid=portal_prod/password@PLNIP_PROD \
    file=/backup/plnip/db_tables_$TIMESTAMP.dmp \
    tables=(users,courses,certificates) \
    compress=y
```

### 2.2 Automated Backup Script

```bash
# Create backup script
sudo nano /usr/local/bin/backup-plnip-db.sh
```

```bash
#!/bin/bash
# PLN IP Portal Database Backup

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backup/plnip"
RETENTION_DAYS=30

# Oracle environment
ORACLE_HOME=/usr/lib/oracle/21/client64
export ORACLE_HOME
export PATH=$ORACLE_HOME/bin:$PATH

# Database credentials (use .env or secure vault)
DB_USER="portal_prod"
DB_PASS="password"
DB_HOST="PLNIP_PROD"

echo "Starting backup at $(date)"

# Full export
exp userid=$DB_USER/$DB_PASS@$DB_HOST \
    file=$BACKUP_DIR/db_full_$TIMESTAMP.dmp \
    log=$BACKUP_DIR/db_full_$TIMESTAMP.log \
    full=y \
    compress=y

if [ $? -eq 0 ]; then
    echo "Backup completed successfully: db_full_$TIMESTAMP.dmp"

    # Compress log file
    gzip $BACKUP_DIR/db_full_$TIMESTAMP.log

    # Delete old backups (older than retention period)
    find $BACKUP_DIR -name "db_full_*.dmp" -mtime +$RETENTION_DAYS -delete
    find $BACKUP_DIR -name "db_full_*.log.gz" -mtime +$RETENTION_DAYS -delete

    echo "Old backups cleaned up (retention: $RETENTION_DAYS days)"
else
    echo "Backup failed! Check log: $BACKUP_DIR/db_full_$TIMESTAMP.log"
    # Send alert
    exit 1
fi
```

```bash
# Make executable
sudo chmod +x /usr/local/bin/backup-plnip-db.sh

# Schedule daily at 03:00 AM
sudo crontab -e
0 3 * * * /usr/local/bin/backup-plnip-db.sh >> /var/log/plnip-backup.log 2>&1
```

### 2.3 Database Restore

**Full Restore:**
```bash
# IMPORTANT: Backup current database first!
exp userid=portal_prod/password@PLNIP_PROD \
    file=/backup/pre_restore_$(date +%Y%m%d_%H%M%S).dmp \
    full=y

# Import from backup
imp userid=portal_prod/password@PLNIP_PROD \
    file=/backup/plnip/db_full_20260210_030000.dmp \
    log=/backup/plnip/restore_$(date +%Y%m%d_%H%M%S).log \
    full=y \
    ignore=y
```

**Table-specific Restore:**
```bash
# Restore specific tables
imp userid=portal_prod/password@PLNIP_PROD \
    file=/backup/plnip/db_full_20260210_030000.dmp \
    tables=(users,courses) \
    ignore=y \
    commit=y
```

**Restore from Different Schema:**
```bash
# Import from different schema to current
imp userid=portal_prod/password@PLNIP_PROD \
    file=/backup/plnip/db_full_20260210_030000.dmp \
    fromuser=portal_old \
    touser=portal_prod \
    ignore=y
```

### 2.4 File Storage Backup

**Certificate Files Backup:**
```bash
# Create backup script for files
sudo nano /usr/local/bin/backup-plnip-files.sh
```

```bash
#!/bin/bash
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backup/plnip"
SOURCE_DIR="/var/www/plnip-portal/storage/app/public"

echo "Starting file backup at $(date)"

# Backup certificates and uploads
tar -czf $BACKUP_DIR/files_$TIMESTAMP.tar.gz \
    $SOURCE_DIR/certificates \
    $SOURCE_DIR/avatars \
    $SOURCE_DIR/cms \
    $SOURCE_DIR/tickets

if [ $? -eq 0 ]; then
    echo "File backup completed: files_$TIMESTAMP.tar.gz"

    # Delete old file backups (older than 30 days)
    find $BACKUP_DIR -name "files_*.tar.gz" -mtime +30 -delete
else
    echo "File backup failed!"
    exit 1
fi
```

```bash
# Make executable
sudo chmod +x /usr/local/bin/backup-plnip-files.sh

# Schedule weekly on Sunday at 04:00 AM
sudo crontab -e
0 4 * * 0 /usr/local/bin/backup-plnip-files.sh >> /var/log/plnip-backup.log 2>&1
```

**Restore Files:**
```bash
# Extract to temporary location first
mkdir -p /tmp/restore
tar -xzf /backup/plnip/files_20260210_040000.tar.gz -C /tmp/restore

# Verify contents
ls -la /tmp/restore

# Copy to production (CAREFUL!)
rsync -av /tmp/restore/var/www/plnip-portal/storage/app/public/ \
    /var/www/plnip-portal/storage/app/public/

# Fix permissions
sudo chown -R www-data:www-data /var/www/plnip-portal/storage
```

## 3. Common Issues dan Solutions

### 3.1 Database Connection Issues

**Symptom:** `ORA-12154: TNS:could not resolve the connect identifier`

**Diagnosis:**
```bash
# Check TNS configuration
cat $ORACLE_HOME/network/admin/tnsnames.ora

# Test connection manually
sqlplus username/password@service_name

# Check listener status
lsnrctl status
```

**Solution:**
```bash
# Update tnsnames.ora
sudo nano $ORACLE_HOME/network/admin/tnsnames.ora

# Add/update entry:
PLNIP_PROD =
  (DESCRIPTION =
    (ADDRESS = (PROTOCOL = TCP)(HOST = db-host)(PORT = 1521))
    (CONNECT_DATA =
      (SERVICE_NAME = PLNIPPROD)
    )
  )

# Restart application
sudo systemctl restart php8.2-fpm
```

---

### 3.2 Queue Worker Stopped

**Symptom:** Jobs tidak diproses, notifikasi tidak terkirim

**Diagnosis:**
```bash
# Check worker status
sudo supervisorctl status plnip-portal-worker:*

# Check logs
tail -f /var/www/plnip-portal/storage/logs/worker.log

# Check failed jobs
php artisan queue:failed
```

**Solution:**
```bash
# Restart workers
sudo supervisorctl restart plnip-portal-worker:*

# If stuck, force stop and start
sudo supervisorctl stop plnip-portal-worker:*
sleep 5
sudo supervisorctl start plnip-portal-worker:*

# Retry failed jobs
php artisan queue:retry all

# If all else fails, flush failed jobs and restart
php artisan queue:flush
sudo supervisorctl restart plnip-portal-worker:*
```

---

### 3.3 Laravel Reverb Not Working

**Symptom:** WebSocket connection failed, real-time features tidak berjalan

**Diagnosis:**
```bash
# Check service status
sudo systemctl status laravel-reverb

# Check logs
sudo journalctl -u laravel-reverb -n 50

# Check port availability
sudo netstat -tuln | grep 8080

# Test WebSocket connection
curl -i -N -H "Connection: Upgrade" \
     -H "Upgrade: websocket" \
     -H "Sec-WebSocket-Version: 13" \
     -H "Sec-WebSocket-Key: SGVsbG8sIHdvcmxkIQ==" \
     http://localhost:8080
```

**Solution:**
```bash
# Restart service
sudo systemctl restart laravel-reverb

# If port conflict, check what's using port 8080
sudo lsof -i :8080

# Kill conflicting process if needed
sudo kill -9 <PID>

# Start Reverb again
sudo systemctl start laravel-reverb

# Check if running
sudo systemctl status laravel-reverb
```

---

### 3.4 Certificate Upload Fails

**Symptom:** Error saat upload PDF certificate

**Diagnosis:**
```bash
# Check storage permissions
ls -la /var/www/plnip-portal/storage/app/public/certificates

# Check disk space
df -h /var/www/plnip-portal/storage

# Check PHP upload limits
php -i | grep -E 'upload_max_filesize|post_max_size|max_execution_time'

# Check Nginx limits
grep client_max_body_size /etc/nginx/sites-available/plnip-portal
```

**Solution:**
```bash
# Fix permissions
sudo chown -R www-data:www-data /var/www/plnip-portal/storage
sudo chmod -R 775 /var/www/plnip-portal/storage

# Increase PHP limits
sudo nano /etc/php/8.2/fpm/php.ini
# Update:
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300

# Increase Nginx limits
sudo nano /etc/nginx/sites-available/plnip-portal
# Add inside server block:
client_max_body_size 100M;

# Restart services
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx
```

---

### 3.5 Moodle Sync Error

**Symptom:** Error saat sync dari Moodle

**Diagnosis:**
```bash
# Test Moodle database connection
php artisan tinker
>>> DB::connection('moodle')->select('SELECT 1 FROM DUAL');

# Test Moodle web service
curl "https://moodle.plnip.co.id/webservice/rest/server.php?wstoken=YOUR_TOKEN&wsfunction=core_webservice_get_site_info&moodlewsrestformat=json"

# Check sync logs
tail -f /var/www/plnip-portal/storage/logs/laravel.log | grep MoodleSync
```

**Solution:**
```bash
# Verify Moodle credentials in .env
nano /var/www/plnip-portal/.env

# Check:
MOODLE_DB_HOST=...
MOODLE_DB_USERNAME=...
MOODLE_DB_PASSWORD=...
MOODLE_URL=...
MOODLE_WS_TOKEN=...

# Clear config cache
php artisan config:clear
php artisan cache:clear

# Test sync manually
php artisan tinker
>>> app(\App\Services\MoodleSyncService::class)->getConnectionStatus();
```

---

### 3.6 ERP Sync Failed

**Symptom:** Data user tidak sync dari ERP

**Diagnosis:**
```bash
# Check ERP config
grep ERP_ /var/www/plnip-portal/.env

# Test ERP API manually
curl -H "Authorization: Bearer YOUR_ERP_API_KEY" \
     https://erp.plnip.co.id/api/employees

# Check sync logs
tail -f /var/www/plnip-portal/storage/logs/laravel.log | grep ERPSync
```

**Solution:**
```bash
# Verify ERP settings
nano /var/www/plnip-portal/.env

# Check:
ERP_ENABLED=true
ERP_API_URL=https://erp.plnip.co.id/api/employees
ERP_API_KEY=your_valid_key

# Clear cache
php artisan config:clear

# Manual trigger sync to test
php artisan tinker
>>> app(\App\Services\ERPSyncService::class)->syncUsers();
```

---

### 3.7 High Memory Usage

**Symptom:** Server memory usage tinggi, aplikasi lambat

**Diagnosis:**
```bash
# Check memory usage
free -h
htop

# Check PHP processes
ps aux | grep php | awk '{print $2, $4, $11}' | sort -k2 -rn | head -10

# Check for memory leaks
php artisan tinker
>>> memory_get_usage(true) / 1024 / 1024; // MB
```

**Solution:**
```bash
# Increase PHP memory limit
sudo nano /etc/php/8.2/fpm/php.ini
# Update:
memory_limit = 512M

# Optimize PHP-FPM pool
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
# Adjust:
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm

# Clear application cache
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan config:clear

# Restart queue workers
sudo supervisorctl restart plnip-portal-worker:*
```

---

### 3.8 Frontend Build Failed

**Symptom:** `npm run build` error di frontend

**Diagnosis:**
```bash
cd /var/www/plnip-portal-frontend

# Check Node.js version
node -v

# Check npm version
npm -v

# Check for syntax errors
npm run lint

# Check build output
npm run build 2>&1 | tee build.log
```

**Solution:**
```bash
# Clear cache
rm -rf .next
rm -rf node_modules
rm package-lock.json

# Reinstall dependencies
npm install

# If still fails, check Node.js version
# PLN IP Portal requires Node.js 18.x or higher
nvm install 18
nvm use 18

# Build again
npm run build

# If successful, restart PM2
pm2 restart plnip-frontend
```

---

### 3.9 SSL Certificate Expired

**Symptom:** Browser warning "Your connection is not private"

**Diagnosis:**
```bash
# Check certificate expiry
openssl x509 -in /etc/ssl/certs/plnip.co.id.crt -noout -dates

# Check Let's Encrypt certificate
sudo certbot certificates
```

**Solution:**
```bash
# Manual renewal (Let's Encrypt)
sudo certbot renew

# Force renewal even if not expired
sudo certbot renew --force-renewal

# Restart Nginx
sudo systemctl restart nginx

# Test renewal
sudo certbot renew --dry-run

# Ensure auto-renewal cron is set
sudo crontab -l | grep certbot
# Should see:
# 0 0 1 * * /usr/bin/certbot renew --quiet
```

---

### 3.10 Slow Database Queries

**Symptom:** Aplikasi lambat, response time tinggi

**Diagnosis:**
```sql
-- Check long running queries
SELECT sql_text, elapsed_time/1000000 AS seconds, executions
FROM v$sql
WHERE elapsed_time > 5000000
ORDER BY elapsed_time DESC
FETCH FIRST 10 ROWS ONLY;

-- Check table scans
SELECT name, value
FROM v$sysstat
WHERE name = 'table scans (long tables)';

-- Check missing indexes
SELECT * FROM user_tables WHERE table_name NOT IN (
  SELECT table_name FROM user_indexes
);
```

**Solution:**
```sql
-- Add indexes pada frequently queried columns
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_employee_id ON users(employee_id);
CREATE INDEX idx_courses_moodle_id ON courses(moodle_course_id);
CREATE INDEX idx_enrollments_user_course ON course_enrollments(user_id, course_id);
CREATE INDEX idx_certificates_user_id ON certificates(user_id);

-- Update statistics
EXEC DBMS_STATS.GATHER_SCHEMA_STATS('PORTAL_PROD');

-- Clear query cache di Laravel
php artisan cache:clear
```

---

## 4. Performance Optimization

### 4.1 Application Cache

**Optimize Laravel Cache:**
```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Clear all cache when needed
php artisan optimize:clear
```

**Setup Redis for Caching (Recommended):**
```bash
# Install Redis
sudo apt install redis-server

# Configure Laravel to use Redis
# Edit .env:
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Restart services
sudo systemctl restart redis-server
sudo systemctl restart php8.2-fpm
```

### 4.2 Database Optimization

**Optimize Tables:**
```sql
-- Analyze tables
EXEC DBMS_STATS.GATHER_TABLE_STATS('PORTAL_PROD', 'USERS');
EXEC DBMS_STATS.GATHER_TABLE_STATS('PORTAL_PROD', 'COURSES');
EXEC DBMS_STATS.GATHER_TABLE_STATS('PORTAL_PROD', 'CERTIFICATES');

-- Rebuild indexes
ALTER INDEX idx_users_email REBUILD;
ALTER INDEX idx_courses_moodle_id REBUILD;
```

**Regular Maintenance:**
```bash
# Create weekly optimization script
sudo nano /usr/local/bin/optimize-plnip-db.sh
```

```bash
#!/bin/bash
ORACLE_HOME=/usr/lib/oracle/21/client64
export ORACLE_HOME
export PATH=$ORACLE_HOME/bin:$PATH

sqlplus portal_prod/password@PLNIP_PROD <<EOF
-- Gather schema statistics
EXEC DBMS_STATS.GATHER_SCHEMA_STATS('PORTAL_PROD');

-- Analyze tables
EXEC DBMS_STATS.GATHER_TABLE_STATS('PORTAL_PROD', 'USERS');
EXEC DBMS_STATS.GATHER_TABLE_STATS('PORTAL_PROD', 'COURSES');
EXEC DBMS_STATS.GATHER_TABLE_STATS('PORTAL_PROD', 'COURSE_ENROLLMENTS');
EXEC DBMS_STATS.GATHER_TABLE_STATS('PORTAL_PROD', 'CERTIFICATES');

EXIT;
EOF

echo "Database optimization completed at $(date)"
```

```bash
# Schedule weekly
sudo crontab -e
0 2 * * 0 /usr/local/bin/optimize-plnip-db.sh >> /var/log/plnip-optimize.log 2>&1
```

### 4.3 Frontend Optimization

**Next.js Build Optimization:**
```javascript
// next.config.js
module.exports = {
  compress: true,
  swcMinify: true,
  images: {
    domains: ['portal.plnip.co.id'],
    formats: ['image/avif', 'image/webp'],
  },
  // Enable experimental features
  experimental: {
    optimizeCss: true,
  },
}
```

**PM2 Cluster Mode:**
```bash
# Stop current instance
pm2 stop plnip-frontend

# Start in cluster mode
pm2 start npm --name "plnip-frontend" -i max -- start

# Save configuration
pm2 save
```

### 4.4 Web Server Optimization

**Nginx Optimization:**
```nginx
# /etc/nginx/nginx.conf
http {
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript
               application/json application/javascript application/xml+rss;

    # Buffer sizes
    client_body_buffer_size 128k;
    client_max_body_size 100m;
    client_header_buffer_size 1k;
    large_client_header_buffers 4 16k;

    # Timeouts
    client_body_timeout 30;
    client_header_timeout 30;
    keepalive_timeout 65;
    send_timeout 30;

    # Enable sendfile
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;

    # Connection optimization
    keepalive_requests 100;
}
```

**Apply changes:**
```bash
sudo nginx -t
sudo systemctl reload nginx
```

## 5. Security Maintenance

### 5.1 Security Audit

**Check for Vulnerabilities:**
```bash
# Backend (Composer)
cd /var/www/plnip-portal
composer audit

# Frontend (npm)
cd /var/www/plnip-portal-frontend
npm audit

# Fix vulnerabilities
composer update --with-dependencies
npm audit fix
```

### 5.2 Update Packages

**Backend Updates:**
```bash
cd /var/www/plnip-portal

# Check outdated packages
composer outdated

# Update packages (test in staging first!)
composer update

# Run tests
php artisan test

# Clear cache
php artisan optimize:clear
```

**Frontend Updates:**
```bash
cd /var/www/plnip-portal-frontend

# Check outdated packages
npm outdated

# Update packages
npm update

# Build and test
npm run build
npm start
```

### 5.3 Security Headers

**Add Security Headers to Nginx:**
```nginx
# /etc/nginx/sites-available/plnip-portal
server {
    # ... existing config ...

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' https:; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';" always;

    # ... rest of config ...
}
```

```bash
sudo nginx -t
sudo systemctl reload nginx
```

### 5.4 Password Policy

**Enforce Strong Passwords:**

Edit `app/Http/Requests/Auth/RegisterRequest.php`:
```php
'password' => [
    'required',
    'string',
    'min:12',
    'confirmed',
    'regex:/[a-z]/',      // lowercase
    'regex:/[A-Z]/',      // uppercase
    'regex:/[0-9]/',      // number
    'regex:/[@$!%*#?&]/', // special char
],
```

### 5.5 Failed Login Monitoring

**Monitor Failed Logins:**
```bash
# Check failed login attempts
tail -f /var/www/plnip-portal/storage/logs/laravel.log | grep "Failed login"

# Count failed attempts per IP
grep "Failed login" /var/www/plnip-portal/storage/logs/laravel.log | \
  awk '{print $10}' | sort | uniq -c | sort -rn | head -10
```

**Block Suspicious IPs:**
```bash
# Block IP via iptables
sudo iptables -A INPUT -s 192.168.1.100 -j DROP

# Or via Nginx
sudo nano /etc/nginx/conf.d/blacklist.conf
# Add:
deny 192.168.1.100;

sudo systemctl reload nginx
```

## 6. Disaster Recovery Plan

### 6.1 Recovery Time Objective (RTO)

Target waktu untuk recovery: **4 jam**

### 6.2 Recovery Point Objective (RPO)

Target data loss maximum: **24 jam** (daily backup)

### 6.3 Disaster Scenarios

**Scenario 1: Complete Server Failure**

1. Provision new server
2. Install dependencies (PHP, Oracle Client, Node.js)
3. Restore database dari backup terakhir
4. Deploy aplikasi dari Git repository
5. Restore file storage dari backup
6. Update DNS jika IP berubah
7. Test semua functionality
8. Switch traffic ke new server

**Scenario 2: Database Corruption**

1. Stop aplikasi untuk prevent further damage
2. Export current state (meskipun corrupt)
3. Restore database dari backup terakhir yang valid
4. Apply transaction logs jika tersedia
5. Validate data integrity
6. Restart aplikasi
7. Monitor untuk issues

**Scenario 3: Data Breach**

1. Isolate affected systems
2. Change all passwords dan credentials
3. Audit access logs
4. Identify scope of breach
5. Restore data dari clean backup
6. Apply security patches
7. Notify affected users
8. Document incident

### 6.4 Recovery Checklist

**Pre-Recovery:**
- [ ] Assess damage extent
- [ ] Identify backup to restore
- [ ] Notify stakeholders
- [ ] Prepare recovery environment

**During Recovery:**
- [ ] Restore database
- [ ] Restore file storage
- [ ] Deploy application
- [ ] Restore configuration
- [ ] Test core functionality
- [ ] Verify data integrity

**Post-Recovery:**
- [ ] Monitor system stability
- [ ] Document incident
- [ ] Update recovery procedures
- [ ] Conduct post-mortem
- [ ] Implement preventive measures

## 7. Maintenance Schedule

### 7.1 Daily Tasks

- Monitor logs untuk errors
- Check queue worker status
- Verify backup completion
- Review performance metrics

### 7.2 Weekly Tasks

- Update security patches (if available)
- Review audit logs
- Check disk space usage
- Optimize database statistics
- Review failed jobs

### 7.3 Monthly Tasks

- Update dependencies (test in staging first)
- Review access logs
- Analyze performance trends
- Test backup restoration
- Security audit
- Review and clean old logs

### 7.4 Quarterly Tasks

- Full security assessment
- Disaster recovery drill
- Performance benchmark
- Review and update documentation
- Capacity planning review

## 8. Contact dan Escalation

### 8.1 Support Tiers

**Tier 1: Application Support**
- Handle user questions
- Basic troubleshooting
- Ticket management

**Tier 2: Technical Support**
- Application errors
- Performance issues
- Integration problems

**Tier 3: Infrastructure Support**
- Server issues
- Database problems
- Network connectivity

**Tier 4: Development Team**
- Bug fixes
- Feature enhancements
- Code-level issues

### 8.2 Escalation Matrix

| Severity | Response Time | Resolution Time | Escalation |
|----------|---------------|-----------------|------------|
| P1 (Critical) | 15 minutes | 4 hours | Immediate to Dev Team |
| P2 (High) | 1 hour | 8 hours | After 2 hours to Dev Team |
| P3 (Medium) | 4 hours | 2 days | After 1 day to Dev Team |
| P4 (Low) | 1 day | 1 week | After 3 days to Dev Team |

### 8.3 Emergency Contacts

```
Development Team Lead: [Name] - [Phone] - [Email]
System Administrator: [Name] - [Phone] - [Email]
Database Administrator: [Name] - [Phone] - [Email]
Network Administrator: [Name] - [Phone] - [Email]
Security Officer: [Name] - [Phone] - [Email]
```

## 9. Kesimpulan

Maintenance guide ini mencakup aspek-aspek penting dalam mengelola PLN IP Learning Hub Portal. Pastikan semua tim terkait memahami prosedur yang ada dan melakukan maintenance secara rutin sesuai schedule yang ditentukan.

Untuk pertanyaan atau bantuan lebih lanjut, hubungi tim development atau system administrator yang bertanggung jawab.

**Remember:**
- Prevention is better than cure
- Monitor proactively, not reactively
- Document everything
- Test backups regularly
- Keep systems updated

---

**Document Version:** 1.0
**Last Updated:** February 2026
**Next Review:** May 2026
