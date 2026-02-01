# ERP API Response Format Specification

## Required Endpoint Format

### Endpoint Details
- **Method:** `GET`
- **URL:** Configured in `ERP_API_URL` environment variable
- **Default:** `https://erp.plnip.co.id/api/employees`
- **Authentication:** Bearer token in Authorization header

### Request Example
```bash
curl -X GET "https://erp.plnip.co.id/api/employees" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Accept: application/json"
```

### Response Format (Required)

```json
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
    },
    {
      "employee_id": "EMP001235",
      "name": "Jane Smith",
      "email": "jane.smith@plnip.co.id",
      "phone": "082112345679",
      "department": "Distribusi",
      "position": "Analyst",
      "access_group": "INSTRUCTOR",
      "is_active": true
    },
    {
      "employee_id": "EMP001236",
      "name": "Bob Johnson",
      "email": "bob.johnson@plnip.co.id",
      "phone": null,
      "department": "IT",
      "position": "System Administrator",
      "access_group": "SUPERADMIN",
      "is_active": false
    }
  ]
}
```

## Field Specifications

### REQUIRED Fields
All three of these fields MUST be present for each employee:

#### `employee_id` (string)
- **Type:** String
- **Unique:** Yes, must be unique per organization
- **Mutable:** No, never changes
- **Max Length:** 255 characters
- **Format:** Alphanumeric (numbers, letters, hyphens allowed)
- **Usage:** Primary key for all lookups and updates
- **Example:** `"EMP001234"`, `"E-2024-001234"`, `"ID-PL-001234"`

**✅ Valid Examples:**
```
"EMP001234"
"E001234"
"PLNIP-001234"
"2024001234"
```

#### `name` (string)
- **Type:** String
- **Unique:** No
- **Mutable:** Yes (will be updated on sync)
- **Max Length:** 255 characters
- **Format:** Any characters
- **Usage:** Display name in portal
- **Example:** `"John Doe"`, `"Dr. Jane Smith, S.E., M.Eng."`

**✅ Valid Examples:**
```
"John Doe"
"Dr. Jane Smith"
"Muhammad Ali Bahar"
"李明"
```

#### `email` (string)
- **Type:** String
- **Unique:** Should be unique (per email standards)
- **Mutable:** Yes (will be updated on sync)
- **Format:** Valid email address
- **Max Length:** 255 characters
- **Usage:** Portal login, communication
- **Example:** `"john.doe@plnip.co.id"`

**✅ Valid Examples:**
```
"john.doe@plnip.co.id"
"jane.smith@training.plnip.co.id"
"bob.johnson@plnip.go.id"
```

**❌ Invalid Examples (will be rejected):**
```
"john.doe"                     # No domain
"@plnip.co.id"                 # No local part
"john doe@plnip.co.id"         # Space not allowed
""                             # Empty
```

### OPTIONAL Fields
These fields can be omitted or null:

#### `phone` (string | null)
- **Type:** String or null
- **Unique:** No
- **Max Length:** 20 characters
- **Format:** Phone number (any format)
- **Example:** `"082112345678"`, `"+62-821-1234-5678"`, `"(021) 123-4567"`

**Valid Examples:**
```json
"phone": "082112345678"
"phone": "+62-821-1234-5678"
"phone": "(021) 123-4567"
"phone": null
```

#### `department` (string | null)
- **Type:** String or null
- **Unique:** No
- **Max Length:** 255 characters
- **Format:** Department name
- **Example:** `"Transmisi"`, `"Pusat"`, `"Corporate"`

**Valid Examples:**
```json
"department": "Transmisi"
"department": "Pembangkitan"
"department": "Distribusi"
"department": "Pusat"
"department": "Corporate"
"department": "IT"
"department": null
```

#### `position` (string | null)
- **Type:** String or null
- **Unique:** No
- **Max Length:** 255 characters
- **Format:** Job position/title
- **Example:** `"Senior Engineer"`, `"Manager"`, `"Analyst"`

**Valid Examples:**
```json
"position": "Senior Engineer"
"position": "Project Manager"
"position": "Business Analyst"
"position": "Support Staff"
"position": null
```

#### `access_group` (string | null)
- **Type:** String or null
- **Unique:** No
- **Allowed Values:** `SUPERADMIN`, `ADMIN_UNIT`, `INSTRUCTOR`, `USER`
- **Default (if null):** `USER`
- **Usage:** Maps to portal role
- **Mutable:** Yes (will be updated on sync)

**Valid Examples:**
```json
"access_group": "SUPERADMIN"      # → super-admin role
"access_group": "ADMIN_UNIT"      # → admin role
"access_group": "INSTRUCTOR"      # → instructor role
"access_group": "USER"            # → user role
"access_group": null              # → defaults to user role
```

**❌ Invalid Examples (will cause errors):**
```json
"access_group": "ADMIN"           # Wrong value
"access_group": "Manager"         # Not in mapping
"access_group": "superadmin"      # Wrong case
```

#### `is_active` (boolean | null)
- **Type:** Boolean or null
- **Unique:** No
- **Default (if null):** `true`
- **Mutable:** Yes (will be updated on sync)
- **Usage:** Determines if user can login
- **Example:** `true`, `false`

**Valid Examples:**
```json
"is_active": true
"is_active": false
"is_active": null              # → treated as true
```

## Complete Examples

### Minimal Response (Only Required Fields)
```json
{
  "employees": [
    {
      "employee_id": "EMP001234",
      "name": "John Doe",
      "email": "john.doe@plnip.co.id"
    }
  ]
}
```

**Result in Portal:**
- Name: John Doe
- Email: john.doe@plnip.co.id
- Employee ID: EMP001234
- Phone: null
- Department: null
- Position: null
- Access Group: USER (default)
- Active: true (default)
- Role: user (from default access_group)

### Complete Response (All Fields)
```json
{
  "employees": [
    {
      "employee_id": "EMP001234",
      "name": "Dr. John Doe",
      "email": "john.doe@plnip.co.id",
      "phone": "082112345678",
      "department": "Transmisi",
      "position": "Senior Engineer",
      "access_group": "ADMIN_UNIT",
      "is_active": true
    },
    {
      "employee_id": "EMP001235",
      "name": "Jane Smith",
      "email": "jane.smith@plnip.co.id",
      "phone": "+62-821-9876543",
      "department": "Distribusi",
      "position": "Manager",
      "access_group": "INSTRUCTOR",
      "is_active": true
    },
    {
      "employee_id": "EMP001236",
      "name": "Bob Johnson",
      "email": "bob.johnson@plnip.co.id",
      "phone": null,
      "department": "IT",
      "position": "Analyst",
      "access_group": "USER",
      "is_active": false
    }
  ]
}
```

### Mixed Optional Fields
```json
{
  "employees": [
    {
      "employee_id": "EMP001234",
      "name": "John Doe",
      "email": "john.doe@plnip.co.id",
      "phone": "082112345678",
      "department": null,
      "position": "Engineer",
      "access_group": "INSTRUCTOR",
      "is_active": true
    },
    {
      "employee_id": "EMP001235",
      "name": "Jane Smith",
      "email": "jane.smith@plnip.co.id",
      "phone": null,
      "department": "Transmisi",
      "position": null,
      "access_group": null,
      "is_active": true
    }
  ]
}
```

## HTTP Status Codes

### Success Responses

#### 200 OK (Successful)
- All employees returned successfully
- Response contains valid JSON structure
- Expected response time: < 30 seconds

```
HTTP/1.1 200 OK
Content-Type: application/json
{
  "employees": [...]
}
```

### Error Responses

#### 401 Unauthorized
- API key missing or invalid
- Token expired
- Wrong credentials

```
HTTP/1.1 401 Unauthorized
Content-Type: application/json
{
  "error": "Invalid API key",
  "message": "Authentication failed"
}
```

**Solution:** Update `ERP_API_KEY` in .env

#### 404 Not Found
- Endpoint doesn't exist
- Wrong URL path

```
HTTP/1.1 404 Not Found
Content-Type: application/json
{
  "error": "Endpoint not found",
  "path": "/api/employees"
}
```

**Solution:** Verify `ERP_API_URL` in .env

#### 500 Internal Server Error
- Server error on ERP side
- Database issue

```
HTTP/1.1 500 Internal Server Error
Content-Type: application/json
{
  "error": "Internal server error",
  "message": "Database connection failed"
}
```

**Solution:** Contact ERP team to check server status

#### 503 Service Unavailable
- ERP server is down/maintenance
- Too many requests

```
HTTP/1.1 503 Service Unavailable
Content-Type: application/json
{
  "error": "Service temporarily unavailable",
  "retry_after": 3600
}
```

**Solution:** Wait and retry later

## Data Validation Rules

### Employee ID Validation
```
✅ MUST be present
✅ MUST be unique per organization
✅ MUST not be null or empty
✅ MUST be string type
❌ Cannot contain special characters (except -, _)
❌ Cannot be changed/updated
```

### Email Validation
```
✅ MUST be valid email format
✅ MUST contain @ symbol
✅ MUST contain domain
✅ SHOULD be company domain
❌ Cannot be null or empty
❌ Cannot have spaces
```

### Name Validation
```
✅ MUST be present
✅ MUST be non-empty string
✅ CAN contain any UTF-8 characters
✅ CAN be up to 255 characters
❌ Cannot be null
```

### Access Group Validation
```
✅ MUST be one of: SUPERADMIN, ADMIN_UNIT, INSTRUCTOR, USER
✅ CAN be null (defaults to USER)
✅ Case-sensitive (SUPERADMIN not superadmin)
✅ MUST match exactly
❌ Custom values not supported
```

## Sync Behavior Based on Data

### New Employee (not in database)
```
Input: employee_id="EMP001234" (doesn't exist)
Action: Create new user
Fields: All provided fields used
Role: Assigned from access_group
Status: source=erp, synced_at=now()
Result: User created with all data
```

### Existing ERP Employee (in database with source=erp)
```
Input: employee_id="EMP001234" (exists with source=erp)
Action: Update user data
Fields: name, email, phone, department, position, access_group updated
Role: Updated if no override exists
Status: synced_at=now()
Result: User updated, changes logged in audit
```

### Existing Manual User (in database with source=manual)
```
Input: employee_id="EMP001234" (exists with source=manual)
Action: Skip this user
Reason: Manual users are preserved
Result: No changes, counted as "skipped"
```

### Inactive Employee (is_active=false)
```
Input: is_active=false
Action: User created/updated as inactive
Effect: User cannot login
JIT Check: Will be rejected at login if enabled
Result: User exists but is_active=false
```

## Testing Checklist for ERP Team

- [ ] API endpoint is accessible
- [ ] API requires Bearer token authentication
- [ ] Response contains "employees" array
- [ ] Each employee has employee_id, name, email
- [ ] employee_id values are unique
- [ ] Email addresses are valid format
- [ ] access_group values match allowed list
- [ ] Response time is < 30 seconds
- [ ] API handles 401 with proper error message
- [ ] API handles errors with proper status codes
- [ ] Optional fields can be null
- [ ] Employee data is current (real-time or hourly sync)

## Integration Checklist

- [ ] Get API endpoint URL from ERP team
- [ ] Get API authentication key/token
- [ ] Test endpoint manually with curl
- [ ] Verify response format matches spec
- [ ] Configure `ERP_API_URL` in .env
- [ ] Configure `ERP_API_KEY` in .env
- [ ] Set `ERP_ENABLED=true` in .env
- [ ] Run `php artisan erp:sync -v`
- [ ] Verify users created in database
- [ ] Check audit logs for success
- [ ] Test scheduled sync (wait for time or run manually)

## Support & Troubleshooting

**If API returns error:**
1. Check API endpoint URL
2. Verify API key/token
3. Check VPN/network connectivity
4. Verify response format
5. Contact ERP team

**If sync fails:**
1. Check `storage/logs/security.log`
2. Check `storage/logs/audit.log`
3. Run `php artisan erp:sync -v` for details
4. Verify ERP API is responding
5. Check user/role configuration

**For detailed help:**
- See `ERP_INTEGRATION_GUIDE.md` (Troubleshooting section)
- See `ERP_QUICKSTART.md` (Common Issues section)
- Review logs at `storage/logs/`

---

**Document Version:** 1.0
**Last Updated:** January 2024
**Status:** ✅ Ready for ERP API Implementation
