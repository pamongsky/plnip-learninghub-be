# Production Deployment Checklist - Class Chat Feature

## ✅ Pre-Deployment Verification

### Backend (Laravel)

#### 1. Database Migrations

- [x] Run migrations: `php artisan migrate`
- [x] Verify `class_messages` table has columns: `reply_to`, `mentioned_user_id`, `image_path`
- [x] Check foreign key constraints are active

#### 2. Code Quality

- [x] All syntax errors fixed (NewClassMessage.php closing brace)
- [x] Proper error handling in ClassChatController
- [x] Relationship loading before broadcasting
- [x] Validation for reply_to and mentioned_user_id
- [x] File upload validation (max 5MB, image types only)

#### 3. Queue Configuration

- [x] Queue worker running: `php artisan queue:work --tries=3 --timeout=90`
- [x] Failed jobs cleared: `php artisan queue:flush`
- [x] Queue connection configured (redis/database/sync)
- [ ] **PRODUCTION**: Use supervisor/systemd for queue worker persistence
- [ ] **PRODUCTION**: Configure queue monitoring (Laravel Horizon recommended)

#### 4. Broadcasting Setup

- [x] Pusher credentials configured in `.env`
- [x] Broadcasting driver set to `pusher`
- [x] WebSocket channels tested:
    - `class-chat.{classId}` - for chat messages
    - `instructor-dashboard` - for question stats

#### 5. Cache & Optimization

- [x] Config cached: `php artisan config:cache`
- [x] Routes cached: `php artisan route:cache`
- [ ] **PRODUCTION**: Run `php artisan optimize`
- [ ] **PRODUCTION**: Enable OPcache in php.ini

#### 6. Storage & Permissions

- [x] Storage link created: `php artisan storage:link`
- [ ] **PRODUCTION**: Set proper permissions:
    ```bash
    chmod -R 775 storage bootstrap/cache
    chown -R www-data:www-data storage bootstrap/cache
    ```

#### 7. Security

- [ ] **PRODUCTION**: Update `.env` with production values
- [ ] **PRODUCTION**: APP_DEBUG=false
- [ ] **PRODUCTION**: APP_ENV=production
- [ ] **PRODUCTION**: Regenerate APP_KEY if needed
- [ ] **PRODUCTION**: HTTPS enabled
- [ ] **PRODUCTION**: CSRF protection active

### Frontend (Next.js)

#### 1. TypeScript Compilation

- [x] No TypeScript errors
- [x] Strict mode compliance
- [x] All interfaces updated (ClassMessage with image_path)

#### 2. Error Handling

- [x] Try-catch blocks around API calls
- [x] Optimistic updates with rollback on error
- [x] User-friendly error messages
- [x] Network error handling

#### 3. Build & Optimization

- [ ] **PRODUCTION**: Run `npm run build`
- [ ] **PRODUCTION**: Test production build locally: `npm run start`
- [ ] **PRODUCTION**: Optimize images
- [ ] **PRODUCTION**: Remove console.log statements

#### 4. Environment Variables

- [ ] **PRODUCTION**: Update `.env.production`
- [ ] **PRODUCTION**: Verify API endpoints point to production backend
- [ ] **PRODUCTION**: Pusher credentials match backend

---

## 🚀 Production Deployment Steps

### 1. Backend Deployment

```bash
# Clone/pull latest code
git pull origin main

# Install dependencies
composer install --optimize-autoloader --no-dev

# Run migrations
php artisan migrate --force

# Clear and rebuild cache
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Cache for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Ensure storage link
php artisan storage:link

# Restart queue workers
php artisan queue:restart

# Set permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 2. Frontend Deployment

```bash
# Pull latest code
git pull origin main

# Install dependencies
npm ci

# Build for production
npm run build

# Start production server (or use PM2)
npm run start

# OR with PM2
pm2 start npm --name "plnip-frontend" -- start
pm2 save
```

### 3. Queue Worker Supervisor Configuration

Create `/etc/supervisor/conf.d/laravel-worker.conf`:

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/plnip-portal/artisan queue:work --sleep=3 --tries=3 --timeout=90
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/plnip-portal/storage/logs/worker.log
stopwaitsecs=3600
```

Then:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

---

## 🧪 Production Testing Checklist

### Critical Path Testing

1. **Send Text Message**
    - [ ] Message appears immediately (optimistic update)
    - [ ] Message broadcasts to other users
    - [ ] Real-time update without page refresh
    - [ ] Sender name shows "Anda" for sender
    - [ ] Sender name shows actual name for receivers

2. **Send Image Message**
    - [ ] Image preview shows before sending
    - [ ] Image uploads successfully
    - [ ] Image displays for all users
    - [ ] Image stored in `storage/app/public/class-chat-images/`
    - [ ] Image accessible via `/storage/` URL

3. **Reply with Mention**
    - [ ] Click reply button on message
    - [ ] Reply reference shows with @mention
    - [ ] Reply sends with correct `reply_to` and `mentioned_user_id`
    - [ ] Reply displays correctly in chat
    - [ ] Mention notification received (if implemented)

4. **Instructor: Filter Unanswered Questions**
    - [ ] Filter button shows for instructors only
    - [ ] Clicking filter shows only unanswered questions
    - [ ] Question count in dashboard is accurate
    - [ ] Marking question as answered updates filter

5. **Real-time Stats Dashboard**
    - [ ] Dashboard loads current question count
    - [ ] Count decrements when question is answered
    - [ ] No page refresh needed
    - [ ] WebSocket connection stable

6. **Error Scenarios**
    - [ ] Network timeout shows error message
    - [ ] Invalid file type rejected with error
    - [ ] File too large (>5MB) rejected
    - [ ] Offline mode shows appropriate error
    - [ ] Failed messages removed from chat (not stuck)

### Performance Testing

- [ ] Chat loads with 100+ messages in <2s
- [ ] Message send latency <500ms
- [ ] Broadcast latency <1s
- [ ] Image upload <3s for 2MB file
- [ ] Queue worker processes jobs without lag
- [ ] No memory leaks in long-running queue workers
- [ ] Database queries optimized (N+1 prevention)

### Security Testing

- [ ] CSRF protection active on all endpoints
- [ ] Authentication required for all chat operations
- [ ] Users can only send to classes they're enrolled in
- [ ] File upload validates file types properly
- [ ] XSS protection on message display
- [ ] SQL injection protection verified
- [ ] Rate limiting on message endpoints (if configured)

---

## 🔥 Rollback Plan

If critical issues occur in production:

### Backend Rollback

```bash
# Rollback database migrations
php artisan migrate:rollback --step=1

# Revert code
git revert HEAD
git push origin main

# Restart services
php artisan queue:restart
sudo supervisorctl restart laravel-worker:*
```

### Frontend Rollback

```bash
# Revert code
git revert HEAD
git push origin main

# Rebuild and restart
npm run build
pm2 restart plnip-frontend
```

---

## 📊 Monitoring & Alerts

### Key Metrics to Monitor

1. **Queue Health**
    - Failed jobs count: `php artisan queue:failed`
    - Queue length: Monitor redis/database queue size
    - Processing time: Average job execution time

2. **Broadcasting**
    - Pusher connection status
    - Message delivery rate
    - WebSocket connection failures

3. **Performance**
    - Response time for `/classes/{id}/chat` endpoint
    - Database query time
    - Storage disk usage

4. **Errors**
    - Laravel error log: `storage/logs/laravel.log`
    - Queue worker errors
    - 500 errors count
    - Failed message sends

### Recommended Tools

- **Laravel Telescope**: Development debugging
- **Laravel Horizon**: Queue monitoring (if using Redis)
- **Sentry**: Error tracking
- **New Relic/DataDog**: APM
- **Uptime Robot**: Uptime monitoring

---

## 📝 Post-Deployment Verification

After deployment, verify:

1. [ ] Queue worker is running: `sudo supervisorctl status laravel-worker:*`
2. [ ] No errors in logs: `tail -f storage/logs/laravel.log`
3. [ ] WebSocket connection working: Check browser console
4. [ ] Send test message in production
5. [ ] Verify real-time broadcast works
6. [ ] Check image upload works
7. [ ] Verify instructor dashboard stats update
8. [ ] Test with multiple concurrent users

---

## 🆘 Emergency Contacts & Documentation

- **Laravel Logs**: `/path/to/plnip-portal/storage/logs/laravel.log`
- **Queue Logs**: `/path/to/plnip-portal/storage/logs/worker.log`
- **Nginx/Apache Logs**: `/var/log/nginx/error.log`
- **Supervisor Logs**: `/var/log/supervisor/supervisord.log`

### Common Issues & Solutions

**Problem**: Queue jobs not processing
**Solution**:

```bash
sudo supervisorctl restart laravel-worker:*
php artisan queue:restart
```

**Problem**: WebSocket not connecting
**Solution**:

- Check Pusher credentials in `.env`
- Verify CORS settings
- Check firewall rules for WebSocket ports

**Problem**: Images not displaying
**Solution**:

```bash
php artisan storage:link
chmod -R 775 storage/app/public
```

**Problem**: 500 errors on message send
**Solution**:

- Check `storage/logs/laravel.log` for details
- Verify database connections
- Check queue worker is running
- Verify relationships are loaded before broadcasting

---

## ✅ Sign-off

- [ ] **Backend Developer**: Code reviewed and tested
- [ ] **Frontend Developer**: UI/UX tested across browsers
- [ ] **QA Team**: All test cases passed
- [ ] **DevOps**: Infrastructure ready, monitoring configured
- [ ] **Product Owner**: Features meet requirements

**Deployment Date**: ********\_********

**Deployed By**: ********\_********

**Production URL**: ********\_********

---

## 🔄 Version History

| Version | Date       | Changes                                                                           | Author |
| ------- | ---------- | --------------------------------------------------------------------------------- | ------ |
| 1.0.0   | 2026-02-05 | Initial release: Chat with avatars, mentions, replies, filtering, real-time stats | -      |
