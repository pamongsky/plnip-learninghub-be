# 📊 Roles & Permissions - Analisis & Konsep Penambahan Role

## ✅ Status Current - Real-Time?

**GOOD NEWS:** System SUDAH real-time! ✨

### How It Works:

1. **Frontend** → Auto-refresh setiap 5 detik (`fetchData()` di interval)
2. **Backend** → Data di-fetch dari database real-time via API
3. **Immediate Update** → Setelah save, langsung fetch data baru

```typescript
// app/superadmin/roles/page.tsx line 66
useEffect(() => {
    fetchData();
    const interval = setInterval(fetchData, 5000); // ✅ Real-time every 5 seconds
    return () => clearInterval(interval);
}, []);
```

---

## ⚠️ Issue: Admin vs Instructor Permissions Tidak Match

### Current Assignment:

**Admin (15 permissions):**

- users: view, create, edit, audit
- announcements: view, create, edit, delete
- reports: view, export
- messages: view, send
- tickets: view, create, resolve

**Instructor (9 permissions):**

- announcements: view
- courses: view, create, edit, enroll
- messages: view, send
- tickets: view, create

### Problems/Gaps:

❌ **Admin tidak bisa delete announcements** → tapi bisa create?
❌ **Instructor tidak bisa delete courses** → tapi bisa create/edit?
❌ **Instructor tidak punya permissions untuk:** announcements.create, announcements.edit
❌ **Admin tidak punya courses permissions** → padahal butuh manage course content?

### Recommendation untuk Fix:

**Option 1: Hierarchical Permissions** (Admin > Instructor)

```
Admin = Instructor permissions + User Management + Reports
```

**Option 2: Role-Specific Permissions** (Current - tapi perlu clearer logic)

```
Admin = User + Announcement Management (tidak punya courses)
Instructor = Courses + Limited announcements (announcement.view only)
```

---

## 🎯 Konsep Menambah Role Baru

### Step-by-Step Process:

#### **1. Backend - Create Permission (if needed)**

File: `database/seeders/RolePermissionSeeder.php`

```php
// Add new permissions in the loop at line 20-50
Permission::firstOrCreate([
    'name' => 'audit.view',
    'display_name' => 'Lihat Audit Log',
    'description' => 'View system audit logs'
]);
```

#### **2. Backend - Create Role in Migration**

File: `database/migrations/[timestamp]_create_roles_table.php`

Already exists! Just need to seed:

```php
// In database/seeders/RolePermissionSeeder.php line ~70

$newRole = Role::firstOrCreate([
    'name' => 'manager',  // New role
    'guard_name' => 'web',
    'display_name' => 'Manager',
    'description' => 'Can manage departments'
]);

// Assign permissions to new role
if ($newRole) {
    $newRole->syncPermissions([
        'users.view',
        'announcements.view',
        'courses.view',
        'reports.view',
        // ... selected permissions
    ]);
}
```

#### **3. Backend - Update RoleController**

File: `app/Http/Controllers/API/RoleController.php`

Update method `getDisplayName()` dan `getDescription()`:

```php
private function getDisplayName($roleName): string
{
    return match($roleName) {
        'super-admin' => 'Super Admin',
        'admin' => 'Admin',
        'instructor' => 'Instructor',
        'manager' => 'Manager',        // ✅ Add new role
        'user' => 'User',
        'employee' => 'Employee',
        default => ucwords(str_replace('-', ' ', $roleName)),
    };
}
```

#### **4. Frontend - Already Supports Dynamic Roles!**

`app/superadmin/roles/page.tsx` fetches from API, so:

- ✅ New role akan otomatis muncul setelah refresh
- ✅ Tidak perlu update frontend code
- ✅ Permissions akan dinamis di-load

---

## 🚀 Complete Workflow untuk Menambah Role "Manager"

### Step 1: Buat seeder baru

```bash
php artisan make:seeder AddManagerRoleSeeder
```

### Step 2: Isi seeder

```php
<?php
// database/seeders/AddManagerRoleSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AddManagerRoleSeeder extends Seeder
{
    public function run(): void
    {
        $managerRole = Role::firstOrCreate([
            'name' => 'manager',
            'guard_name' => 'web',
        ]);

        // Assign specific permissions
        $managerRole->syncPermissions([
            'users.view',
            'users.audit',
            'announcements.view',
            'announcements.create',
            'announcements.edit',
            'courses.view',
            'courses.edit',
            'reports.view',
            'reports.export',
            'messages.view',
            'messages.send',
            'tickets.view',
            'tickets.resolve',
        ]);
    }
}
```

### Step 3: Register in DatabaseSeeder

```php
// database/seeders/DatabaseSeeder.php
public function run(): void
{
    $this->call([
        RolePermissionSeeder::class,
        AddManagerRoleSeeder::class,  // ✅ Add this
    ]);
}
```

### Step 4: Run seeder

```bash
php artisan db:seed --class=AddManagerRoleSeeder
```

### Step 5: Update RoleController

```php
private function getDisplayName($roleName): string
{
    return match($roleName) {
        'super-admin' => 'Super Admin',
        'admin' => 'Admin',
        'instructor' => 'Instructor',
        'manager' => 'Manager',        // ✅ Add this
        'user' => 'User',
        'employee' => 'Employee',
        default => ucwords(str_replace('-', ' ', $roleName)),
    };
}
```

### Step 6: Done! ✨

- Buka browser → http://localhost:3000/superadmin/roles
- Role "Manager" akan muncul otomatis
- Bisa edit permissions dari UI

---

## 📋 Architecture Summary

```
Database Layer:
  roles (id, name, guard_name)
  permissions (id, name)
  role_has_permissions (role_id, permission_id)
        ↓
Backend API Layer (RoleController):
  GET /superadmin/roles → All roles with permissions
  GET /superadmin/roles/permissions/all → All available permissions
  POST /superadmin/roles → Create new role
  PUT /superadmin/roles/{id}/permissions → Update permissions
  DELETE /superadmin/roles/{id} → Delete role
        ↓
Frontend UI Layer:
  Real-time refresh every 5 seconds
  Display roles & permissions
  Edit permissions with checkboxes
  Create/Delete roles (super-admin only)
```

---

## ✅ Quick Checklist untuk Tambah Role Baru

- [ ] Create seeder atau update `RolePermissionSeeder.php`
- [ ] Create Role dengan `Role::firstOrCreate(['name' => 'xxx'])`
- [ ] Assign permissions dengan `$role->syncPermissions([...])`
- [ ] Update `getDisplayName()` di RoleController
- [ ] Run `php artisan db:seed`
- [ ] Buka superadmin/roles di frontend → lihat role baru
- [ ] Test edit permissions → save → auto-refresh
- [ ] Assign role ke user via user management

---

## 🔐 Protection Rules (Built-in)

❌ **Cannot delete built-in roles:**

- super-admin, admin, instructor, employee, user

✅ **Can delete custom roles** (if created after seeding)

❌ **Only super-admin can manage roles**

✅ **Permissions based on role assignment**

---

## 🎨 UI Flow (Frontend)

```
Superadmin Visits: /superadmin/roles
      ↓
1. Fetch all roles + permissions (real-time)
2. Display 5 role cards
3. Click role → show permissions checkboxes
4. Toggle permissions → "Save Changes"
5. Auto-refresh every 5 seconds
6. New roles appear automatically
```
