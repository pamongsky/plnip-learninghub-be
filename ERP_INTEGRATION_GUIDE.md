# ERP Integration Documentation

## Overview

PLN IP Learning Hub now supports automated user synchronization from the Enterprise Resource Planning (ERP) system. This integration enables:

- **Automatic user creation** from ERP database
- **Role mapping** from ERP access groups to portal roles
- **User data updates** (email, department, position, etc.)
- **Scheduled synchronization** via console command
- **Manual sync triggering** via API endpoint
- **Audit logging** of all sync operations
- **JIT validation** of user status at login time (optional)

## Architecture

### Sync Strategies

#### 1. Scheduled Sync (PRIMARY)
- Configured time daily (default: 2:00 AM)
- Automated via Laravel scheduler
- Configurable in `ERP_SYNC_SCHEDULE`
- **Best for**: Predictable user data updates

#### 2. Just-In-Time (JIT) Validation
- Real-time status check at login
- Validates user is still active in ERP
- Requires `ERP_JIT_VALIDATION=true`
- **Best for**: Security, deactivating users immediately

#### 3. Webhook (Future)
- ERP pushes updates to portal
- Real-time sync on user changes
- Requires `ERP_WEBHOOK_ENABLED=true` and webhook token
- **Best for**: Mission-critical user management

### User Identification

**Employee ID** is the primary key for all ERP sync operations:
- Permanent and never changes
- Unique across organization
- Cannot be duplicated
- More reliable than email or name

### Data Flow

```
ERP Database
     ↓
ERP API (/api/employees)
     ↓
ERPSyncService (fetch & process)
     ↓
User Model (create/update)
     ↓
Role Assignment (via access_group mapping)
     ↓
AuditLog (track all changes)
```

### Role Mapping

Access groups in ERP map to portal roles:

| ERP access_group | Portal Role | Permission Level |
|---|---|---|
| `SUPERADMIN` | `super-admin` | Full system access |
| `ADMIN_UNIT` | `admin` | Department admin |
| `INSTRUCTOR` | `instructor` | Class management |
| `USER` | `user` | Learning access only |

## Configuration

### Environment Variables

Add to `.env` file:

```bash
# Enable ERP integration
ERP_ENABLED=false  # Set to true when ready

# ERP API Configuration
ERP_API_URL=https://erp.plnip.co.id/api/employees
ERP_API_KEY=your_erp_api_key_here

# Sync Configuration
ERP_SYNC_TIMEOUT=30              # API timeout in seconds
ERP_SYNC_SCHEDULE=02:00          # Daily sync time (HH:MM)
ERP_MAX_RETRIES=3                # Retry attempts on failure
ERP_RETRY_DELAY=60               # Delay between retries (seconds)

# Security
ERP_VERIFY_SSL=true              # Validate SSL certificates
ERP_JIT_VALIDATION=false         # Check status at login

# Webhook (future use)
ERP_WEBHOOK_ENABLED=false
ERP_WEBHOOK_TOKEN=your_token
```

### File: `config/erp.php`

Central configuration file for all ERP settings:

```php
config('erp.enabled')        // ERP integration active?
config('erp.api_url')        // ERP API endpoint
config('erp.api_key')        // API authentication
config('erp.schedule')       // Scheduled sync time
config('erp.jit_validation') // JIT validation enabled?
```

## ERP API Format

### Expected Employee Data Structure

Your ERP API should return employees in this format:

```json
{
  "employees": [
    {
      "employee_id": "EMP001234",
      "email": "john.doe@plnip.co.id",
      "name": "John Doe",
      "phone": "082112345678",
      "department": "Transmisi",
      "position": "Senior Engineer",
      "access_group": "ADMIN_UNIT",
      "is_active": true
    }
  ]
}
```

### Required Fields
- `employee_id` (string) - Unique identifier
- `email` (string) - User email address
- `name` (string) - Full name

### Optional Fields
- `phone` (string) - Phone number
- `department` (string) - Department name
- `position` (string) - Job position
- `access_group` (string) - Maps to portal role
- `is_active` (boolean) - Active status

### API Requirements
- **Method**: GET
- **Authentication**: Bearer token in `Authorization` header
- **Response**: JSON with `employees` array
- **Status codes**: 200 (success), 4xx/5xx (error)

## Usage

### 1. Scheduled Sync (Automatic)

Once `ERP_ENABLED=true`, the sync runs automatically daily at configured time.

**Monitor logs:**
```bash
# View audit logs
tail -f storage/logs/audit.log

# View security logs
tail -f storage/logs/security.log
```

### 2. Manual Sync via API

**Endpoint:** `POST /api/superadmin/sync-erp`
**Authentication:** Super-admin role required
**Headers:** `Authorization: Bearer {sanctum_token}`

**Response:**
```json
{
  "message": "ERP sync completed successfully",
  "stats": {
    "created": 15,
    "updated": 8,
    "skipped": 2,
    "errors": 0
  },
  "timestamp": "2024-01-15T10:30:45Z"
}
```

### 3. Manual Sync via Command

Run from server command line:

```bash
# Standard sync
php artisan erp:sync

# Force sync even if disabled
php artisan erp:sync --force

# With output
php artisan erp:sync -v
```

**Output:**
```
🔄 Starting ERP user sync...

✅ ERP Sync Completed
┌─────────────────┬───────┐
│ Status          │ Count │
├─────────────────┼───────┤
│ ✨ Created      │    15 │
│ ♻️  Updated     │     8 │
│ ⏭️  Skipped     │     2 │
│ ⚠️  Errors      │     0 │
└─────────────────┴───────┘
```

## User Source Field

All users have a `source` field to track their origin:

| Source | Description | Editable | Sync |
|--------|-------------|----------|------|
| `manual` | Created in dev phase | ✅ Yes | ❌ Never |
| `erp` | Created from ERP sync | ❌ No | ✅ Auto-sync |

**Dev to Production Transition:**
- Dev phase: Create users manually (`source=manual`)
- Production: Enable ERP sync (`ERP_ENABLED=true`)
- Manual users persist, not overwritten by ERP sync
- Super admin can manually delete manual users if needed

## Role Management

### Automatic Role Assignment

When user syncs from ERP:
1. Read `access_group` from ERP data
2. Map to portal role (see Role Mapping table)
3. Assign role automatically

### Manual Role Override

Super-admin can override any user's role:

**Via API:**
```
POST /api/superadmin/users/{user}/override-role
{
  "role": "super-admin",
  "reason": "Promoted to administrator due to..."
}
```

**Effect:**
- User's original role is stored in `role_override` field
- Role override persists even if ERP data changes
- Change is logged in audit trail with reason
- Super-admin can revert anytime

## Audit Logging

Every ERP sync operation is logged:

```php
AuditLog::create([
    'user_id' => null,  // System sync
    'action' => 'create|update|erp_sync_manual',
    'entity_type' => 'User',
    'entity_id' => $user->id,
    'changes' => [...],
    'reason' => 'User created/updated from ERP sync',
    'ip_address' => $request->ip(),
]);
```

**View audit logs:**
```bash
# Recent sync operations
SELECT * FROM audit_logs 
WHERE action = 'erp_sync_manual' 
ORDER BY created_at DESC LIMIT 20;

# User creation history
SELECT * FROM audit_logs 
WHERE entity_type = 'User' AND entity_id = {user_id}
ORDER BY created_at DESC;
```

## Error Handling

### Common Errors

| Error | Cause | Solution |
|-------|-------|----------|
| `ERP API error: 401` | Invalid API key | Check `ERP_API_KEY` in .env |
| `ERP API error: 404` | Wrong endpoint | Verify `ERP_API_URL` |
| `Connection timeout` | ERP server down | Check ERP server status |
| `Invalid employee data` | Missing required fields | Update ERP data format |

### Logging

All errors are logged to appropriate channels:

```bash
# Security issues (auth, validation)
storage/logs/security.log

# Audit trail (sync operations)
storage/logs/audit.log

# General errors
storage/logs/laravel.log
```

## Security Considerations

### API Security
- ✅ Bearer token authentication
- ✅ Super-admin role required
- ✅ IP logging on all operations
- ✅ Audit trail of all changes
- ✅ SSL certificate verification (default)

### Rate Limiting
- No rate limit on scheduled sync
- Manual sync limited by Laravel rate limiting
- Retry logic with exponential backoff (future)

### Data Protection
- Passwords generated randomly (32 chars)
- Sensitive data logged securely
- Access logs maintained in audit trail
- Old audit records can be archived

## JIT Validation (Optional)

Just-In-Time validation checks ERP status at login:

```php
// In AuthController login method
if (config('erp.jit_validation')) {
    $syncService = new ERPSyncService();
    $isValid = $syncService->validateUserStatus($user);
    if (!$isValid) {
        // Block login - user inactive in ERP
    }
}
```

**Enable:**
```bash
ERP_JIT_VALIDATION=true
```

**Effect:**
- Deactivates users immediately when removed from ERP
- Prevents access by former employees
- Minor performance impact (one extra API call per login)

## Troubleshooting

### Sync not running?

1. Check if enabled:
   ```bash
   php artisan tinker
   > config('erp.enabled')
   ```

2. Check Laravel scheduler:
   ```bash
   # Add to crontab if missing
   * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
   ```

3. Run manually to debug:
   ```bash
   php artisan erp:sync -v
   ```

### Users not created?

1. Check API response format:
   ```bash
   curl -H "Authorization: Bearer YOUR_KEY" https://erp.plnip.co.id/api/employees
   ```

2. Check logs:
   ```bash
   tail -f storage/logs/security.log
   ```

3. Verify employee_id uniqueness in database

### Role not assigned?

1. Check access_group mapping:
   ```php
   UserService::mapAccessGroupToRole('YOUR_GROUP_NAME')
   ```

2. Check if role override is active
3. Run sync again after role assignment fixes

## Performance

### Sync Duration
- Small org (< 100 users): ~5-10 seconds
- Medium org (100-500 users): ~30-60 seconds
- Large org (500+ users): 1-3 minutes

### Optimization
- Sync during off-peak hours (default 2:00 AM)
- Disable JIT validation if not needed
- Archive old audit logs periodically
- Use database indexes on employee_id, email

## Future Enhancements

- [ ] Webhook receiver for ERP push updates
- [ ] Batch import from CSV/Excel
- [ ] Automatic deactivation of removed employees
- [ ] Department-based access groups
- [ ] Custom field mapping
- [ ] Sync conflict resolution UI
- [ ] Performance monitoring dashboard

## Support

For issues or questions:
1. Check logs in `storage/logs/`
2. Review audit trail in database
3. Run `php artisan erp:sync -v` for detailed output
4. Contact PLN IP DevOps team

---

**Last Updated:** January 2024
**Version:** 1.0
