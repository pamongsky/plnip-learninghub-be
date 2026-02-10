# PERMISSION MANAGEMENT API - Real-time CRUD

Base URL: `http://localhost:3000/api/superadmin/permissions`
Auth: Bearer Token (Super Admin only)

## 📋 **ENDPOINTS**

### 1. **GET /** - List All Permissions

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:3000/api/superadmin/permissions
```

Response:

```json
{
  "success": true,
  "data": {
    "permissions": [...],
    "grouped": {
      "users": [...],
      "announcements": [...],
      "courses": [...]
    },
    "total": 33
  }
}
```

---

### 2. **POST /** - Create Single Permission

```bash
curl -X POST \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "reports.delete"}' \
  http://localhost:3000/api/superadmin/permissions
```

Response:

```json
{
    "success": true,
    "message": "Permission created successfully",
    "data": {
        "id": 34,
        "name": "reports.delete",
        "guard_name": "web"
    }
}
```

**Validation:**

- Format harus: `category.action` (e.g. users.create)
- Lowercase only
- Unique (tidak boleh duplikat)

---

### 3. **POST /bulk** - Create Multiple Permissions

```bash
curl -X POST \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "permissions": [
      {"name": "faqs.view"},
      {"name": "faqs.create"},
      {"name": "faqs.edit"},
      {"name": "faqs.delete"}
    ]
  }' \
  http://localhost:3000/api/superadmin/permissions/bulk
```

Response:

```json
{
  "success": true,
  "message": "4 permission(s) created successfully",
  "data": {
    "created": [...],
    "errors": []
  }
}
```

---

### 4. **POST /sync-standard** - Sync Standard Permissions

```bash
curl -X POST \
  -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:3000/api/superadmin/permissions/sync-standard
```

Auto-create semua standard permissions yang belum ada:

- users._, announcements._, courses._, reports._
- messages._, tickets._, escalations._, settings._

Response:

```json
{
  "success": true,
  "message": "5 permission(s) added",
  "data": {
    "created": ["reports.delete", "faqs.view"],
    "skipped": ["users.view", "users.create", ...],
    "total_standard": 33
  }
}
```

---

### 5. **GET /{id}** - View Single Permission

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:3000/api/superadmin/permissions/1
```

Response:

```json
{
    "success": true,
    "data": {
        "permission": {
            "id": 1,
            "name": "users.view"
        },
        "roles": [
            { "id": 1, "name": "super-admin" },
            { "id": 2, "name": "admin" }
        ],
        "roles_count": 2
    }
}
```

---

### 6. **PUT /{id}** - Update Permission

```bash
curl -X PUT \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "users.view-all"}' \
  http://localhost:3000/api/superadmin/permissions/1
```

Response:

```json
{
    "success": true,
    "message": "Permission updated successfully",
    "data": {
        "id": 1,
        "name": "users.view-all"
    }
}
```

---

### 7. **DELETE /{id}** - Delete Permission

```bash
curl -X DELETE \
  -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:3000/api/superadmin/permissions/34
```

Response (Success):

```json
{
    "success": true,
    "message": "Permission deleted successfully"
}
```

Response (Error - masih dipakai):

```json
{
    "success": false,
    "message": "Cannot delete permission. It is assigned to 3 role(s). Remove from roles first."
}
```

---

### 8. **GET /stats** - Permission Statistics

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:3000/api/superadmin/permissions/stats
```

Response:

```json
{
    "success": true,
    "data": {
        "total_permissions": 33,
        "total_roles": 4,
        "unassigned_permissions": 5,
        "most_used_permissions": [
            { "name": "users.view", "roles_count": 3 },
            { "name": "announcements.view", "roles_count": 4 }
        ]
    }
}
```

---

## 🚀 **USAGE EXAMPLES**

### Create New FAQ Permissions

```bash
# Bulk create untuk fitur FAQ baru
curl -X POST \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "permissions": [
      {"name": "faqs.view"},
      {"name": "faqs.create"},
      {"name": "faqs.edit"},
      {"name": "faqs.delete"},
      {"name": "faqs.publish"}
    ]
  }' \
  http://localhost:3000/api/superadmin/permissions/bulk
```

### Sync Missing Permissions

```bash
# Auto-add semua standard permissions yang hilang
curl -X POST \
  -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:3000/api/superadmin/permissions/sync-standard
```

### Check Permission Usage

```bash
# Sebelum hapus, cek dulu dipakai berapa role
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:3000/api/superadmin/permissions/15
```

---

## ✅ **BENEFITS**

1. **Real-time**: Tidak perlu run seeder, langsung via API
2. **Granular**: Bisa create permission spesifik kapan aja
3. **Safe**: Auto-check sebelum delete (ga bisa delete kalau masih dipakai)
4. **Auto-cache clear**: Cache otomatis di-clear setelah perubahan
5. **Bulk operation**: Bisa create banyak sekaligus
6. **Sync helper**: Bisa sync standard permissions otomatis

---

## 🎯 **FRONTEND INTEGRATION**

Contoh panggil dari Next.js:

```typescript
// Create permission
const createPermission = async (name: string) => {
    const res = await fetch("/api/superadmin/permissions", {
        method: "POST",
        headers: {
            Authorization: `Bearer ${token}`,
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ name }),
    });
    return res.json();
};

// Sync standard permissions
const syncPermissions = async () => {
    const res = await fetch("/api/superadmin/permissions/sync-standard", {
        method: "POST",
        headers: { Authorization: `Bearer ${token}` },
    });
    return res.json();
};

// Delete permission
const deletePermission = async (id: number) => {
    const res = await fetch(`/api/superadmin/permissions/${id}`, {
        method: "DELETE",
        headers: { Authorization: `Bearer ${token}` },
    });
    return res.json();
};
```

---

**Sekarang tidak butuh seeder lagi! Semua bisa dikelola real-time via API.** 🎉
