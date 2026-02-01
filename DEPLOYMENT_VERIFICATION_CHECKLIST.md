# 🚀 ERP Integration - Deployment Verification Checklist

## Pre-Deployment Phase

### Code Review

- [ ] All files created successfully
- [ ] No syntax errors in PHP files
- [ ] No TypeScript errors in frontend
- [ ] All imports are correct
- [ ] No hardcoded credentials

### Configuration Review

- [ ] `.env.example` updated with all ERP variables
- [ ] `config/erp.php` is syntactically correct
- [ ] Service class can be instantiated
- [ ] Routes are properly configured
- [ ] Controller methods exist

### Testing (Local Development)

#### Database & Migrations

- [ ] Run migrations: `php artisan migrate`
- [ ] Verify tables created:
    ```bash
    php artisan tinker
    > Schema::hasTable('users')  # Should be true
    > Schema::hasColumn('users', 'source')  # Should be true
    ```

#### Service Class Testing

```bash
php artisan tinker

# Test service instantiation
> $service = new App\Services\ERPSyncService()
> $service  # Should show instance

# Test configuration reading
> config('erp.enabled')  # Should be false
> config('erp.api_url')  # Should show URL
```

#### Console Command Testing

```bash
# Test command exists
php artisan list | grep erp:sync

# Test help output
php artisan erp:sync --help

# Test dry run (won't connect to ERP)
php artisan erp:sync --force  # With ERP_ENABLED=false, should show warning
```

#### Frontend Component Testing

- [ ] Open `/superadmin/users` in browser
- [ ] "Sync ERP" button visible
- [ ] Button has loading state
- [ ] No console errors

### Documentation Verification

- [ ] All 6 markdown files exist
- [ ] Links in index are correct
- [ ] Code examples are accurate
- [ ] Troubleshooting section is complete
- [ ] API specification is clear

---

## Staging Deployment

### Pre-Deployment

- [ ] Get ERP API details from ERP team
- [ ] Test ERP API endpoint with curl
- [ ] Verify API response format matches spec
- [ ] Get API authentication key

### Deployment Steps

#### 1. Code Deployment

```bash
# Pull latest code
git pull origin main

# Install dependencies (if any new packages)
composer install

# Run migrations
php artisan migrate
```

#### 2. Environment Configuration

```bash
# Update .env
ERP_ENABLED=false          # Start disabled
ERP_API_URL=https://...    # From ERP team
ERP_API_KEY=staging_key    # From ERP team
ERP_SYNC_SCHEDULE=02:00
ERP_JIT_VALIDATION=false

# Clear config cache
php artisan config:clear
php artisan cache:clear
```

#### 3. Manual Testing

```bash
# Test with ERP disabled (safe)
php artisan erp:sync -v
# Output: Should show warning about ERP disabled

# Test configuration
php artisan tinker
> config('erp.enabled')    # false
> config('erp.api_url')    # Your URL
```

#### 4. Enable ERP Sync

```bash
# Update .env
ERP_ENABLED=true

# Clear config
php artisan config:clear

# Test sync
php artisan erp:sync -v
# Output: Should show sync results
```

#### 5. Verify in Database

```bash
php artisan tinker

# Check if users created
> App\Models\User::where('source', 'erp')->count()

# Check sync logs
> App\Models\AuditLog::where('action', 'create')
    ->where('reason', 'like', '%ERP%')
    ->count()

# Check for errors
> App\Models\AuditLog::latest()->take(10)->get()
```

#### 6. UI Testing

- [ ] Open `/superadmin/users`
- [ ] Click "Sync ERP" button
- [ ] Wait for completion
- [ ] Check success message
- [ ] User list refreshes
- [ ] Check logs in `storage/logs/audit.log`

### Post-Deployment Staging

- [ ] Monitor logs for 1 hour
- [ ] Check for any errors
- [ ] Verify users are being synced
- [ ] Test role assignments
- [ ] Test manual user preservation
- [ ] Test JIT validation (if enabled)
- [ ] Load test with multiple sync triggers

---

## Production Deployment

### Pre-Production Checklist

- [ ] Staging testing passed
- [ ] All bugs fixed
- [ ] Performance tested
- [ ] Security review completed
- [ ] Backup plan in place
- [ ] Rollback procedure documented
- [ ] Team trained on new system
- [ ] On-call support arranged

### Deployment Window

- [ ] Schedule during maintenance window
- [ ] Notify stakeholders
- [ ] Have team on standby
- [ ] Test communication channels

### Deployment Steps (Same as Staging)

1. Code deployment
2. Environment configuration
3. Manual testing
4. Enable ERP sync
5. Database verification
6. UI testing

### Production Monitoring (First 24 Hours)

#### Logs

```bash
# Watch audit log
tail -f storage/logs/audit.log

# Watch security log
tail -f storage/logs/security.log

# Watch general log
tail -f storage/logs/laravel.log
```

#### Metrics

- [ ] Sync completes daily without errors
- [ ] Users created/updated correctly
- [ ] No performance degradation
- [ ] Audit logs are comprehensive
- [ ] No security alerts

#### Database Checks

```bash
# Check recent syncs
SELECT COUNT(*) as total_erp_users
FROM users
WHERE source = 'erp' AND synced_at > NOW() - INTERVAL 24 HOUR;

# Check for errors
SELECT COUNT(*) as sync_errors
FROM audit_logs
WHERE created_at > NOW() - INTERVAL 24 HOUR
AND (status_code >= 400 OR error_message IS NOT NULL);
```

---

## Rollback Procedure

### If Something Goes Wrong

#### Step 1: Disable ERP Sync

```bash
# Edit .env
ERP_ENABLED=false

# Clear cache
php artisan config:clear

# Verify
php artisan tinker
> config('erp.enabled')  # Should be false
```

#### Step 2: Stop Scheduled Sync

```bash
# Restart scheduler (it will respect ERP_ENABLED=false)
php artisan schedule:run
```

#### Step 3: Investigate Issue

```bash
# Check recent logs
tail -f storage/logs/security.log
tail -f storage/logs/audit.log

# Run sync with verbose output
php artisan erp:sync -v

# Check database integrity
php artisan tinker
> App\Models\User::where('source', 'erp')->count()
> App\Models\AuditLog::latest()->take(5)->get()
```

#### Step 4: Fix and Re-enable

```bash
# Once issue is resolved
ERP_ENABLED=true
php artisan config:clear

# Test again
php artisan erp:sync -v
```

### Full Rollback (Code Level)

```bash
# Revert to previous version
git revert <commit_hash>

# Or if using branches
git checkout previous-stable-branch

# Reinstall
composer install

# Clear cache
php artisan config:clear
php artisan cache:clear

# Database is safe (backward compatible)
```

---

## Post-Deployment Verification

### Day 1

- [ ] Check logs every 2 hours
- [ ] Verify daily sync ran
- [ ] No errors in security log
- [ ] Users synced correctly
- [ ] Performance acceptable

### Week 1

- [ ] Monitor daily sync operations
- [ ] Check audit logs weekly
- [ ] Verify role assignments
- [ ] Test manual user updates
- [ ] Performance metrics normal

### Month 1

- [ ] All scheduled syncs successful
- [ ] No unplanned rollbacks
- [ ] Users report no issues
- [ ] Audit trail comprehensive
- [ ] Ready for full feature set (JIT, webhooks, etc.)

---

## Performance Verification

### Sync Time Metrics

```bash
# Check in audit log
grep "ERP sync completed" storage/logs/audit.log

# Expected times:
# < 100 users: 5-10 seconds
# 100-500 users: 30-60 seconds
# 500+ users: 1-3 minutes
```

### Database Performance

```bash
# Check query times
SHOW QUERIES DURING LAST SYNC;

# Should be < 100ms per user
```

### API Response Time

```bash
# Test manual sync endpoint
time curl -X POST http://localhost:8000/api/superadmin/sync-erp \
  -H "Authorization: Bearer TOKEN"

# Should complete in < 30 seconds
```

---

## Security Verification

### Authentication & Authorization

- [ ] Only super-admin can trigger sync
- [ ] API requires valid token
- [ ] Token validation works
- [ ] IP logging works

### Data Protection

- [ ] No credentials in logs
- [ ] No passwords exposed
- [ ] SSL certificates valid
- [ ] HTTPS only

### Audit Trail

- [ ] All operations logged
- [ ] Timestamps correct
- [ ] User info recorded
- [ ] Change history preserved

### Error Handling

- [ ] Errors logged securely
- [ ] No sensitive data in errors
- [ ] User gets friendly message
- [ ] Admin gets details in logs

---

## Feature Verification

### Core Features

- [ ] Scheduled sync works
- [ ] Manual sync works
- [ ] User creation works
- [ ] User updates work
- [ ] Role assignment works
- [ ] Manual users preserved
- [ ] Audit logging works

### Optional Features

- [ ] JIT validation (if enabled)
- [ ] Webhook receiver (if enabled)
- [ ] Custom fields (if configured)

### UI Features

- [ ] Sync button visible
- [ ] Loading state works
- [ ] Success messages display
- [ ] Error messages helpful
- [ ] Auto-refresh works
- [ ] User badges correct

---

## Monitoring Setup

### Log Rotation

```bash
# Verify logs rotate properly
ls -la storage/logs/

# Should have:
# - audit.log
# - security.log
# - laravel.log (rotated daily)
```

### Alert Configuration

- [ ] Setup alerts for errors
- [ ] Setup alerts for failures
- [ ] Setup alerts for timeouts
- [ ] Test alert delivery

### Dashboard (Optional)

- [ ] Create monitoring dashboard
- [ ] Track sync success rate
- [ ] Track user creation rate
- [ ] Track error rate

---

## Documentation Verification

### User Guides

- [ ] Quick start is clear
- [ ] Configuration instructions work
- [ ] Troubleshooting helps
- [ ] Examples are accurate

### Team Training

- [ ] Team reviewed docs
- [ ] Team understands features
- [ ] Team can troubleshoot
- [ ] Team knows escalation path

### Runbook

- [ ] Emergency procedures documented
- [ ] Rollback steps clear
- [ ] Support contacts listed
- [ ] Posted in accessible location

---

## Sign-Off

### Development Team

- [ ] Code reviewed ✓
- [ ] Tests passed ✓
- [ ] Documentation complete ✓
- [ ] Ready for staging ✓

**Signed by:** ******\_\_\_****** **Date:** ******\_\_\_******

### QA Team

- [ ] Testing completed ✓
- [ ] No critical bugs ✓
- [ ] Performance acceptable ✓
- [ ] Ready for production ✓

**Signed by:** ******\_\_\_****** **Date:** ******\_\_\_******

### Operations Team

- [ ] Infrastructure ready ✓
- [ ] Monitoring configured ✓
- [ ] Rollback plan tested ✓
- [ ] Ready for deployment ✓

**Signed by:** ******\_\_\_****** **Date:** ******\_\_\_******

### Business Owner

- [ ] Requirements met ✓
- [ ] Risks acceptable ✓
- [ ] Deployment authorized ✓

**Signed by:** ******\_\_\_****** **Date:** ******\_\_\_******

---

## Post-Deployment Handoff

### Documentation Handed To

- [ ] Operations Team
- [ ] Support Team
- [ ] System Administrators
- [ ] Development Team (for future maintenance)

### Training Completed For

- [ ] Operations Team
- [ ] Support Team
- [ ] System Administrators
- [ ] Development Team

### Support Contacts

| Role             | Name | Contact |
| ---------------- | ---- | ------- |
| On-Call DevOps   |      |         |
| Platform Lead    |      |         |
| Support Manager  |      |         |
| ERP Team Contact |      |         |

### Escalation Path

1. Contact on-call DevOps
2. Escalate to Platform Lead if needed
3. Contact ERP Team for ERP-related issues
4. Escalate to Support Manager if needed

---

## Success Criteria

### Deployment is Successful If:

- ✅ All tests pass
- ✅ No critical errors in logs
- ✅ Users synced within 1 hour
- ✅ Audit logs complete
- ✅ UI responds normally
- ✅ No performance degradation
- ✅ Team trained and confident
- ✅ Documentation available
- ✅ Monitoring active

### Deployment is Failed If:

- ❌ Any critical errors
- ❌ Sync doesn't complete
- ❌ Data loss or corruption
- ❌ Security breach
- ❌ Performance issues
- ❌ UI broken
- ❌ Team unable to operate

---

## Next Steps After Successful Deployment

1. **Monitor** - Watch logs for 1 week
2. **Gather Feedback** - From users and operations
3. **Plan Next Phase**:
    - Enable JIT validation
    - Setup webhook (if ERP supports)
    - Add more features
    - Performance optimization

4. **Documentation**:
    - Update any runbooks
    - Archive deployment logs
    - Update architecture diagrams

5. **Team**:
    - Conduct retrospective
    - Document lessons learned
    - Plan training for next phase

---

**Checklist Version:** 1.0
**Last Updated:** January 2024
**Status:** Ready for Use

Print this checklist and check off items as you deploy. Keep it with deployment records for audit trail.
