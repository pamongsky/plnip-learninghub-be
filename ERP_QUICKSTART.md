# ERP Integration - Quick Start Guide

## 🚀 Getting Started in 5 Minutes

### Step 1: Configure Environment (1 min)
Edit your `.env` file:

```bash
# Start with ERP disabled (safe for development)
ERP_ENABLED=false

# Point to ERP API (get from PLN IT)
ERP_API_URL=https://erp.plnip.co.id/api/employees
ERP_API_KEY=your_api_key_here

# Sync at 2:00 AM daily
ERP_SYNC_SCHEDULE=02:00
```

### Step 2: Create Users Manually (Dev Phase)
In super admin panel:
1. Go to "Kelola Semua User"
2. Click "Tambah User"
3. Fill form: name, email, employee_id, role, etc.
4. User created with `source=manual`
5. Manual users are **never** overwritten by ERP

### Step 3: Enable ERP When Ready (Production)
```bash
ERP_ENABLED=true
```

Now:
- Daily sync runs automatically at 2:00 AM
- ERP users are created/updated
- Manual users from dev phase stay intact
- All changes logged in audit trail

### Step 4: Trigger Manual Sync Anytime
In super admin panel → "Sync ERP" button

Or via API:
```bash
curl -X POST http://localhost:8000/api/superadmin/sync-erp \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Step 5: Monitor Results
Check logs:
```bash
# Recent sync operations
tail -f storage/logs/audit.log | grep "ERP sync"

# Errors and warnings
tail -f storage/logs/security.log | grep "ERP"
```

---

## 📋 Pre-Deployment Checklist

- [ ] ERP API credentials obtained from PLN IT
- [ ] ERP API endpoint verified (HTTP 200 response)
- [ ] `.env` configured with credentials
- [ ] Manual test: `php artisan erp:sync -v`
- [ ] Manual test: Click "Sync ERP" in UI
- [ ] Users created/updated correctly
- [ ] Audit logs show sync operations
- [ ] No errors in `security.log`

---

## 🔍 Testing the Integration

### Quick Test via Command Line
```bash
# Test if everything is configured
php artisan tinker
> config('erp.enabled')    # Should be true
> config('erp.api_url')    # Should be your ERP URL
> config('erp.api_key')    # Should not be empty

# Exit tinker
exit
```

### Run Sync with Verbose Output
```bash
php artisan erp:sync -v

# Output should show:
# 🔄 Starting ERP user sync...
# ✅ ERP Sync Completed
# ✨ Created: 15
# ♻️  Updated: 8
# ⏭️  Skipped: 0
# ⚠️  Errors: 0
```

### Check Database
```bash
php artisan tinker

# Count ERP vs manual users
> App\Models\User::where('source', 'erp')->count()    # Should > 0
> App\Models\User::where('source', 'manual')->count()  # Dev users

# View recent sync logs
> App\Models\AuditLog::where('action', 'create')
    ->where('reason', 'like', '%ERP%')
    ->orderBy('created_at', 'desc')
    ->take(10)
    ->get()

exit
```

---

## ⚠️ Common Issues & Fixes

### "ERP API error: 401"
```bash
# Wrong or expired API key
# Solution: Get new key from PLN IT and update ERP_API_KEY in .env
```

### "Connection timeout"
```bash
# ERP server is down or unreachable
# Solution: Check VPN connection, firewall rules
# Increase timeout: ERP_SYNC_TIMEOUT=60
```

### "No employees data from ERP"
```bash
# ERP API returned empty response
# Check: curl -H "Authorization: Bearer KEY" https://your-erp-url
# Solution: Verify API endpoint and auth
```

### Users not created after sync
```bash
# Check if ERP is enabled
echo $ERP_ENABLED  # Should output: true

# Check logs for errors
tail -f storage/logs/security.log

# Verify ERP API response format
php artisan erp:sync -v  # Shows detail
```

### Scheduled sync not running
```bash
# Check if scheduler is active
ps aux | grep schedule:run

# If not running, add to crontab:
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1

# Or run manually:
php artisan schedule:run
```

---

## 📊 ERP API Format Required

Your ERP API must return data like this:

```bash
GET https://your-erp-url/api/employees
Authorization: Bearer YOUR_API_KEY

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

**Required fields:**
- `employee_id` (string, unique)
- `name` (string)
- `email` (string)

**Optional fields:**
- `phone`, `department`, `position`
- `access_group` (maps to role)
- `is_active` (boolean)

---

## 🔐 Role Mapping

Your `access_group` field must be one of:

| ERP Value | Portal Role | Permissions |
|---|---|---|
| `SUPERADMIN` | super-admin | Full access |
| `ADMIN_UNIT` | admin | Department management |
| `INSTRUCTOR` | instructor | Class management |
| `USER` | user | Learning only |

**Example:** If ERP sends `access_group: "INSTRUCTOR"`, user gets `instructor` role automatically.

---

## 🛡️ Security Notes

- ✅ Manual users are **never** overwritten
- ✅ All changes are logged in audit trail
- ✅ Only super-admin can trigger manual sync
- ✅ Super-admin can override any role (logged with reason)
- ✅ Employee_id is permanent key (never changes)

---

## 📞 Getting Help

1. **Check logs first:**
   ```bash
   tail -f storage/logs/security.log
   tail -f storage/logs/audit.log
   ```

2. **Read full guide:**
   - See `ERP_INTEGRATION_GUIDE.md` in project root

3. **Implementation details:**
   - See `ERP_SYNC_IMPLEMENTATION.md` in project root

4. **Ask PLN IT team:**
   - ERP API credentials
   - ERP API endpoint
   - Required access_group values
   - Employee data structure

---

## 📚 Command Reference

```bash
# Manual sync (immediate execution)
php artisan erp:sync

# Verbose output (see all details)
php artisan erp:sync -v

# Force sync even if disabled
php artisan erp:sync --force

# Run scheduler once
php artisan schedule:run

# Test configuration
php artisan tinker
> config('erp')
```

---

## ✅ You're Ready!

If you can:
1. ✅ See ERP users in database
2. ✅ See "Sync ERP" button in UI
3. ✅ Check audit logs for operations
4. ✅ Manual users still exist from dev phase

**→ Integration is working! 🎉**

Next: Configure JIT validation, webhook, or other features from `ERP_INTEGRATION_GUIDE.md`.

---

**Last Updated:** January 2024
**Quick Start Version:** 1.0
