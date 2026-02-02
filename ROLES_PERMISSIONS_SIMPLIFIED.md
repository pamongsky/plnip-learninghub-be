# ✅ Roles & Permissions - Simplified Version

## 🎯 Apa Yang Berhasil Disetup

### Frontend (Next.js)

**File:** `app/superadmin/roles/page.tsx`

Sekarang page ini punya 2 fitur:

#### 1. **View All Roles & Permissions** (Read-Only)

- ✅ List semua roles (super-admin, admin, instructor, employee, user)
- ✅ Klik role untuk lihat permissions detail
- ✅ Permissions grouped by category (Users, Announcements, etc)
- ✅ Auto-refresh every 5 seconds (real-time)

#### 2. **Create Custom Role** (New!)

- ✅ Button "Create Custom Role"
- ✅ Form untuk:
    - Role name (lowercase, otomatis sanitize)
    - Display name
    - Select default permissions (multi-checkbox)
- ✅ Submit → POST `/superadmin/roles` dengan selected permissions

### Backend (Laravel)

**Routes:** `routes/api.php` lines 61-66

```php
Route::prefix('superadmin/roles')->middleware('role:super-admin')->group(function () {
    Route::get('/', [\App\Http\Controllers\API\RoleController::class, 'getAllRoles']);
    Route::get('/permissions/all', [\App\Http\Controllers\API\RoleController::class, 'getAllPermissions']);
    Route::post('/', [\App\Http\Controllers\API\RoleController::class, 'createRole']);
    Route::get('/{role}', [\App\Http\Controllers\API\RoleController::class, 'showRole']);

    // ❌ Removed: PUT (update permissions) - nanti di admin section
    // ❌ Removed: DELETE (delete role) - tidak perlu untuk now
});
```

**Controller:** `app/Http/Controllers/API/RoleController.php`

Methods yang aktif:

- ✅ `getAllRoles()` - Fetch semua roles with permissions
- ✅ `getAllPermissions()` - Fetch all permissions
- ✅ `createRole()` - Create custom role dengan selected permissions
- ✅ `showRole()` - Get detail single role

---

## 🚀 Cara Pake

### 1. **View Roles & Permissions**

```
1. Go to: http://localhost:3000/superadmin/roles
2. See all 5 built-in roles
3. Click role card → see permissions detail
4. Auto-refresh every 5 seconds
```

### 2. **Create Custom Role**

```
1. Click "Create Custom Role" button
2. Fill form:
   - Role Name: "moderator" (auto-converted to lowercase)
   - Display Name: "Moderator"
   - Select Permissions: Check boxes sesuai kebutuhan
3. Click "Create Role"
4. ✅ Success! Role akan muncul di list dan bisa assigned ke users
```

### 3. **Assign Role to Users**

```
Nanti di User Management section:
- Admin bisa assign role ke user
- Termasuk custom role yang baru dibuat
```

---

## 📊 Current Endpoints

| Method | Endpoint                            | Permission  | Purpose               |
| ------ | ----------------------------------- | ----------- | --------------------- |
| GET    | `/superadmin/roles`                 | super-admin | List semua roles      |
| GET    | `/superadmin/roles/permissions/all` | super-admin | List all permissions  |
| POST   | `/superadmin/roles`                 | super-admin | Create custom role ✨ |
| GET    | `/superadmin/roles/{role}`          | super-admin | Get role detail       |

---

## 🎯 Next Phase (For Admin Section Later)

Nanti bisa ditambah di admin panel:

- ✅ Edit permissions untuk role (PUT endpoint - buat nanti)
- ✅ Delete custom role (DELETE endpoint - buat nanti)
- ✅ Permission management UI di admin dashboard

---

## ✨ Features Summary

| Feature               | Status      | Notes                    |
| --------------------- | ----------- | ------------------------ |
| View Roles            | ✅ Done     | Real-time, 5 sec refresh |
| View Permissions      | ✅ Done     | Grouped by category      |
| Create Custom Role    | ✅ Done     | With default permissions |
| Edit Role Permissions | ❌ Later    | Untuk admin section      |
| Delete Role           | ❌ Later    | Untuk admin section      |
| Assign Role to User   | ✅ Existing | Di User Management       |

---

## 💡 Quick Example: Create "Content Manager" Role

```
Frontend Form:
  Role Name: "content-manager"
  Display Name: "Content Manager"
  Permissions: ☑ announcements.view
               ☑ announcements.create
               ☑ announcements.edit
               ☑ courses.view
               ☑ courses.create

POST /superadmin/roles
{
  "name": "content-manager",
  "display_name": "Content Manager",
  "permissions": [
    "announcements.view",
    "announcements.create",
    "announcements.edit",
    "courses.view",
    "courses.create"
  ]
}

Response:
{
  "message": "Role berhasil dibuat",
  "role": {
    "id": 6,
    "name": "content-manager",
    "permissions": [...]
  }
}
```

---

## 🔒 Security

- ✅ Only super-admin can create roles
- ✅ Built-in roles protected (cannot delete)
- ✅ Permission validation server-side
- ✅ Database transaction untuk create role

---

## 📝 Testing

### Test 1: Create role as super-admin

```bash
curl -X POST http://localhost:8000/api/superadmin/roles \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "test-role",
    "display_name": "Test Role",
    "permissions": ["announcements.view"]
  }'
```

### Test 2: View roles

```bash
curl http://localhost:8000/api/superadmin/roles \
  -H "Authorization: Bearer {token}"
```

### Test 3: Try create as non-super-admin

```
Should return 403 Forbidden
```

---

## 🎉 Selesai!

Roles & Permissions page sekarang:

- ✅ Simple dan clean
- ✅ View-only untuk existing roles
- ✅ Bisa create custom role
- ✅ Real-time updates
- ✅ Ready untuk expand nanti

Nanti untuk manage permissions lebih detail, bisa dibuat di admin section yang dedicated!
