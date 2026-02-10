# API Reference PLN IP Learning Hub Portal

## Base URL

```
Development: http://localhost:8000/api
Production: https://portal.plnip.co.id/api
```

## Authentication

Semua endpoint yang memerlukan authentication menggunakan **Laravel Sanctum** dengan token-based authentication.

### Headers Required

```http
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

### Token Lifecycle

- Token didapat setelah login sukses
- Token disimpan di httpOnly cookies (otomatis oleh browser)
- Expire setelah 2 jam inactivity
- Refresh otomatis saat ada activity

---

## 1. Authentication

### 1.1 Login

Login untuk mendapatkan authentication token.

**Endpoint:** `POST /login`

**Authentication:** None (public)

**Request Body:**
```json
{
  "email": "user@plnip.co.id",
  "password": "password123"
}
```

**Response Success (200):**
```json
{
  "user": {
    "id": 1,
    "employee_id": "12345678",
    "name": "Budi Santoso",
    "email": "user@plnip.co.id",
    "department": "Engineering",
    "position": "Senior Engineer",
    "role": "user",
    "is_active": true
  },
  "token": "1|abcdefghijklmnopqrstuvwxyz...",
  "message": "Login successful"
}
```

**Response Error (401):**
```json
{
  "message": "Invalid credentials"
}
```

---

### 1.2 Register

Registrasi user baru (self-registration).

**Endpoint:** `POST /register`

**Authentication:** None (public)

**Request Body:**
```json
{
  "employee_id": "12345678",
  "name": "Budi Santoso",
  "email": "budi.santoso@plnip.co.id",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "08123456789",
  "department": "Engineering",
  "position": "Staff"
}
```

**Response Success (201):**
```json
{
  "user": {
    "id": 10,
    "employee_id": "12345678",
    "name": "Budi Santoso",
    "email": "budi.santoso@plnip.co.id",
    "role": "user",
    "source": "manual"
  },
  "token": "2|zyxwvutsrqponmlkjihgfedcba...",
  "message": "Registration successful"
}
```

**Response Error (422):**
```json
{
  "message": "The email has already been taken.",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

---

### 1.3 Logout

Logout dan invalidate token.

**Endpoint:** `POST /logout`

**Authentication:** Required

**Response Success (200):**
```json
{
  "message": "Logged out successfully"
}
```

---

### 1.4 Get Current User

Mendapatkan informasi user yang sedang login.

**Endpoint:** `GET /user`

**Authentication:** Required

**Response Success (200):**
```json
{
  "id": 1,
  "employee_id": "12345678",
  "name": "Budi Santoso",
  "email": "user@plnip.co.id",
  "department": "Engineering",
  "position": "Senior Engineer",
  "phone": "08123456789",
  "role": "user",
  "permissions": ["view courses", "enroll courses", "view certificates"],
  "avatar": "/storage/avatars/user-1.jpg",
  "is_active": true,
  "source": "erp",
  "created_at": "2026-01-15T10:30:00.000000Z"
}
```

---

## 2. Dashboard

### 2.1 Employee Dashboard

Dashboard untuk user/karyawan.

**Endpoint:** `GET /dashboard/employee`

**Authentication:** Required

**Role:** `user`

**Response Success (200):**
```json
{
  "enrolled_courses": 5,
  "completed_courses": 2,
  "certificates": 2,
  "average_progress": 65.5,
  "recent_courses": [
    {
      "id": 10,
      "title": "Oracle Database Administration",
      "progress": 75,
      "last_access": "2026-02-09T14:30:00Z"
    }
  ],
  "recent_announcements": [
    {
      "id": 5,
      "title": "Pengumuman Penting",
      "content": "Lorem ipsum...",
      "created_at": "2026-02-08T09:00:00Z"
    }
  ]
}
```

---

### 2.2 Instructor Dashboard

Dashboard untuk instructor.

**Endpoint:** `GET /dashboard/instructor`

**Authentication:** Required

**Role:** `instructor`

**Response Success (200):**
```json
{
  "teaching_courses": 3,
  "total_students": 45,
  "pending_questions": 8,
  "recent_questions": [
    {
      "id": 20,
      "message": "Bagaimana cara menginstall Oracle?",
      "user": {
        "id": 15,
        "name": "Student A"
      },
      "course": {
        "id": 10,
        "title": "Oracle Database"
      },
      "created_at": "2026-02-10T08:15:00Z"
    }
  ]
}
```

---

### 2.3 Dashboard Stats

Statistik umum (untuk admin/super-admin).

**Endpoint:** `GET /dashboard/stats`

**Authentication:** Required

**Role:** `admin`, `super-admin`

**Response Success (200):**
```json
{
  "total_users": 250,
  "total_courses": 45,
  "total_enrollments": 1250,
  "active_users": 180,
  "new_users_this_month": 15,
  "courses_completed_this_month": 45,
  "certificates_issued_this_month": 42
}
```

---

## 3. User Management

### 3.1 Get All Users (Super Admin)

List semua users dengan pagination dan filter.

**Endpoint:** `GET /superadmin/users`

**Authentication:** Required

**Role:** `super-admin`

**Query Parameters:**
- `page` (integer, optional) - Halaman (default: 1)
- `per_page` (integer, optional) - Items per page (default: 20)
- `search` (string, optional) - Search by name, email, employee_id
- `role` (string, optional) - Filter by role
- `source` (string, optional) - Filter by source (erp/manual)
- `is_active` (boolean, optional) - Filter by active status

**Example Request:**
```
GET /superadmin/users?page=1&per_page=20&search=budi&role=user
```

**Response Success (200):**
```json
{
  "data": [
    {
      "id": 1,
      "employee_id": "12345678",
      "name": "Budi Santoso",
      "email": "budi@plnip.co.id",
      "department": "Engineering",
      "position": "Senior Engineer",
      "role": "user",
      "source": "erp",
      "is_active": true,
      "synced_at": "2026-02-10T02:00:00Z",
      "created_at": "2026-01-15T10:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100
  }
}
```

---

### 3.2 Create User

Buat user baru (manual).

**Endpoint:** `POST /superadmin/users`

**Authentication:** Required

**Role:** `super-admin`

**Request Body:**
```json
{
  "employee_id": "87654321",
  "name": "Andi Wijaya",
  "email": "andi.wijaya@plnip.co.id",
  "password": "password123",
  "phone": "08987654321",
  "department": "IT",
  "position": "Developer",
  "access_group": "USER",
  "is_active": true
}
```

**Response Success (201):**
```json
{
  "message": "User created successfully",
  "user": {
    "id": 101,
    "employee_id": "87654321",
    "name": "Andi Wijaya",
    "email": "andi.wijaya@plnip.co.id",
    "role": "user",
    "source": "manual"
  }
}
```

---

### 3.3 Update User

Update data user.

**Endpoint:** `PUT /superadmin/users/{id}`

**Authentication:** Required

**Role:** `super-admin`

**Request Body:**
```json
{
  "name": "Andi Wijaya Updated",
  "email": "andi.new@plnip.co.id",
  "phone": "08123123123",
  "department": "IT Department",
  "position": "Senior Developer",
  "is_active": true
}
```

**Response Success (200):**
```json
{
  "message": "User updated successfully",
  "user": {
    "id": 101,
    "name": "Andi Wijaya Updated",
    "email": "andi.new@plnip.co.id",
    "updated_at": "2026-02-10T10:15:00Z"
  }
}
```

---

### 3.4 Delete User

Hapus user (soft delete).

**Endpoint:** `DELETE /superadmin/users/{id}`

**Authentication:** Required

**Role:** `super-admin`

**Response Success (200):**
```json
{
  "message": "User deleted successfully"
}
```

---

### 3.5 Override User Role

Override role user (bypass ERP access_group).

**Endpoint:** `POST /superadmin/users/{id}/override-role`

**Authentication:** Required

**Role:** `super-admin`

**Request Body:**
```json
{
  "role": "instructor",
  "reason": "Ditunjuk sebagai instructor untuk kelas Engineering"
}
```

**Response Success (200):**
```json
{
  "message": "Role overridden successfully",
  "user": {
    "id": 10,
    "name": "Budi Santoso",
    "role": "instructor",
    "role_override": true
  }
}
```

---

### 3.6 Get User Audit History

Lihat history perubahan data user.

**Endpoint:** `GET /superadmin/users/{id}/audit-history`

**Authentication:** Required

**Role:** `super-admin`

**Response Success (200):**
```json
{
  "data": [
    {
      "id": 50,
      "action": "update",
      "entity_type": "User",
      "entity_id": 10,
      "user": {
        "id": 1,
        "name": "Super Admin"
      },
      "changes": {
        "role": {
          "old": "user",
          "new": "instructor"
        }
      },
      "reason": "Ditunjuk sebagai instructor",
      "ip_address": "192.168.1.100",
      "created_at": "2026-02-10T10:30:00Z"
    }
  ]
}
```

---

### 3.7 Trigger ERP Sync

Manual trigger untuk sync data dari ERP.

**Endpoint:** `POST /superadmin/sync-erp`

**Authentication:** Required

**Role:** `super-admin`

**Response Success (200):**
```json
{
  "message": "ERP sync completed successfully",
  "stats": {
    "created": 15,
    "updated": 50,
    "skipped": 10,
    "errors": 0
  }
}
```

**Response Error (500):**
```json
{
  "message": "ERP sync failed",
  "error": "Connection timeout to ERP API"
}
```

---

## 4. Course Management

### 4.1 Get All Courses

List semua courses dengan pagination.

**Endpoint:** `GET /courses`

**Authentication:** Required

**Query Parameters:**
- `page` (integer) - Halaman
- `per_page` (integer) - Items per page
- `search` (string) - Search by title, short_name
- `category_id` (integer) - Filter by category
- `is_active` (boolean) - Filter by status

**Response Success (200):**
```json
{
  "data": [
    {
      "id": 10,
      "title": "Oracle Database Administration",
      "short_name": "ORACLEDB101",
      "description": "Learn Oracle Database from scratch",
      "category_id": 5,
      "category_name": "Database",
      "instructor": {
        "id": 5,
        "name": "Instructor A"
      },
      "start_date": "2026-03-01",
      "end_date": "2026-05-31",
      "is_active": true,
      "enrolled_count": 45,
      "moodle_course_id": 123
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 20,
    "total": 45
  }
}
```

---

### 4.2 Get My Courses

Courses yang di-enroll oleh user.

**Endpoint:** `GET /courses/my`

**Authentication:** Required

**Response Success (200):**
```json
{
  "data": [
    {
      "id": 10,
      "title": "Oracle Database Administration",
      "progress": 75.5,
      "status": "active",
      "enrolled_at": "2026-02-01T08:00:00Z",
      "last_access": "2026-02-10T14:30:00Z",
      "certificate_available": false,
      "moodle_login_url": "https://moodle.plnip.co.id/login/token.php?token=..."
    }
  ]
}
```

---

### 4.3 Get Course Detail

Detail course beserta modules dan enrollment info.

**Endpoint:** `GET /courses/{id}`

**Authentication:** Required

**Response Success (200):**
```json
{
  "id": 10,
  "title": "Oracle Database Administration",
  "short_name": "ORACLEDB101",
  "description": "Comprehensive Oracle DB training...",
  "category": {
    "id": 5,
    "name": "Database"
  },
  "instructor": {
    "id": 5,
    "name": "Instructor A",
    "email": "instructor@plnip.co.id"
  },
  "start_date": "2026-03-01",
  "end_date": "2026-05-31",
  "is_active": true,
  "enrolled_count": 45,
  "user_enrolled": true,
  "user_progress": 75.5,
  "user_role": "student",
  "moodle_course_id": 123,
  "moodle_login_url": "https://moodle.plnip.co.id/login/token.php?token=..."
}
```

---

### 4.4 Sync Courses from Moodle

Sinkronisasi courses dari Moodle ke Portal.

**Endpoint:** `POST /courses/sync`

**Authentication:** Required

**Role:** `admin`, `super-admin`

**Response Success (200):**
```json
{
  "message": "Courses synced successfully",
  "stats": {
    "total_moodle": 50,
    "added": 5,
    "updated": 40,
    "errors": 0,
    "duration_seconds": 12.5
  }
}
```

---

### 4.5 Update Course

Update course details.

**Endpoint:** `PUT /courses/{id}`

**Authentication:** Required

**Role:** `admin`, `super-admin`

**Request Body:**
```json
{
  "title": "Oracle Database Administration - Updated",
  "description": "Updated description...",
  "instructor_id": 6,
  "start_date": "2026-03-15",
  "end_date": "2026-06-15",
  "is_active": true
}
```

**Response Success (200):**
```json
{
  "message": "Course updated successfully",
  "course": {
    "id": 10,
    "title": "Oracle Database Administration - Updated",
    "updated_at": "2026-02-10T11:00:00Z"
  }
}
```

---

### 4.6 Enroll User to Course

Enroll user ke course (admin/instructor).

**Endpoint:** `POST /courses/{id}/enroll`

**Authentication:** Required

**Role:** `admin`, `super-admin`, `instructor`

**Request Body:**
```json
{
  "user_id": 15,
  "moodle_role": "student"
}
```

**Moodle Roles:**
- `student` - Peserta
- `editingteacher` - Editing Teacher (full control)
- `teacher` - Non-editing Teacher (can grade)
- `coursecreator` - Course Creator
- `manager` - Manager

**Response Success (200):**
```json
{
  "message": "User enrolled successfully",
  "enrollment": {
    "user_id": 15,
    "course_id": 10,
    "moodle_role": "student",
    "status": "active",
    "enrolled_at": "2026-02-10T11:15:00Z"
  }
}
```

---

### 4.7 Unenroll User from Course

Hapus enrollment user dari course.

**Endpoint:** `DELETE /courses/{id}/enroll/{userId}`

**Authentication:** Required

**Role:** `admin`, `super-admin`, `instructor`

**Response Success (200):**
```json
{
  "message": "User unenrolled successfully"
}
```

---

### 4.8 Update Enrollment Role

Ubah role user di course (misal dari student ke teacher).

**Endpoint:** `PATCH /courses/{id}/enroll/{userId}/role`

**Authentication:** Required

**Role:** `admin`, `super-admin`

**Request Body:**
```json
{
  "moodle_role": "editingteacher"
}
```

**Response Success (200):**
```json
{
  "message": "Enrollment role updated successfully",
  "enrollment": {
    "user_id": 15,
    "course_id": 10,
    "moodle_role": "editingteacher"
  }
}
```

---

### 4.9 Get User Progress in Course

Progress user di course tertentu.

**Endpoint:** `GET /courses/{id}/progress/{userId}`

**Authentication:** Required

**Role:** `admin`, `super-admin`, `instructor`, atau user sendiri

**Response Success (200):**
```json
{
  "user": {
    "id": 15,
    "name": "Student A",
    "email": "student@plnip.co.id"
  },
  "course": {
    "id": 10,
    "title": "Oracle Database Administration"
  },
  "progress": 75.5,
  "completed_activities": 18,
  "total_activities": 24,
  "grade": 85.0,
  "last_access": "2026-02-10T14:30:00Z",
  "enrolled_at": "2026-02-01T08:00:00Z",
  "status": "active"
}
```

---

### 4.10 Get Enrollment Tracking

Tracking semua enrollment untuk monitoring.

**Endpoint:** `GET /courses/enrollments/tracking`

**Authentication:** Required

**Role:** `admin`, `super-admin`

**Query Parameters:**
- `course_id` (integer, optional)
- `user_id` (integer, optional)
- `status` (string, optional) - active, completed, suspended
- `from_date` (date, optional)
- `to_date` (date, optional)

**Response Success (200):**
```json
{
  "data": [
    {
      "id": 100,
      "user": {
        "id": 15,
        "name": "Student A",
        "employee_id": "12345678"
      },
      "course": {
        "id": 10,
        "title": "Oracle Database"
      },
      "progress": 75.5,
      "status": "active",
      "enrolled_at": "2026-02-01T08:00:00Z",
      "last_access": "2026-02-10T14:30:00Z"
    }
  ],
  "meta": {
    "total": 1250,
    "current_page": 1,
    "last_page": 63
  }
}
```

---

## 5. Certificate Management

### 5.1 Get My Certificates

List sertifikat user yang sedang login.

**Endpoint:** `GET /certificates`

**Authentication:** Required

**Response Success (200):**
```json
{
  "data": [
    {
      "id": 50,
      "certificate_number": "CERT-ABC123XY",
      "course": {
        "id": 10,
        "title": "Oracle Database Administration"
      },
      "is_valid": true,
      "created_at": "2026-02-05T10:00:00Z",
      "download_url": "/api/certificates/50/download"
    }
  ]
}
```

---

### 5.2 Download Certificate

Download PDF certificate.

**Endpoint:** `GET /certificates/{id}/download`

**Authentication:** Required

**Response:** PDF file download

---

### 5.3 Upload Certificate (Individual)

Upload sertifikat untuk satu user.

**Endpoint:** `POST /courses/{id}/upload-certificate/{userId}`

**Authentication:** Required

**Role:** `admin`, `super-admin`

**Request:** `multipart/form-data`

```
certificate: [PDF file]
```

**Response Success (200):**
```json
{
  "message": "Sertifikat berhasil diupload",
  "certificate": {
    "id": 51,
    "user_id": 15,
    "course_id": 10,
    "certificate_number": "CERT-XYZ789AB",
    "original_filename": "Certificate_StudentA.pdf",
    "is_valid": true
  }
}
```

---

### 5.4 Upload Certificates (Bulk ZIP)

Upload banyak sertifikat sekaligus via ZIP.

**Endpoint:** `POST /courses/{id}/upload-certificates-zip`

**Authentication:** Required

**Role:** `admin`, `super-admin`

**Request:** `multipart/form-data`

```
zip: [ZIP file containing PDFs]
```

**File Naming Convention:**
- `12345678.pdf` (NIP exact match)
- `Budi Santoso.pdf` (Nama exact match)
- `Budi.pdf` (Nama partial match)

**Response Success (200):**
```json
{
  "message": "Upload ZIP selesai",
  "matched": [
    "12345678.pdf → Budi Santoso",
    "Andi.pdf → Andi Wijaya"
  ],
  "unmatched": [
    "Unknown Person.pdf"
  ],
  "total_matched": 2,
  "total_unmatched": 1
}
```

---

### 5.5 Get All Certificates (Admin)

List semua sertifikat (admin view).

**Endpoint:** `GET /admin/certificates`

**Authentication:** Required

**Role:** `admin`, `super-admin`

**Query Parameters:**
- `course_id` (integer, optional)
- `user_id` (integer, optional)
- `search` (string, optional)
- `per_page` (integer, default: 20)

**Response Success (200):**
```json
{
  "data": [
    {
      "id": 50,
      "certificate_number": "CERT-ABC123XY",
      "user": {
        "id": 15,
        "name": "Student A",
        "email": "student@plnip.co.id",
        "employee_id": "12345678"
      },
      "course": {
        "id": 10,
        "title": "Oracle Database"
      },
      "is_valid": true,
      "created_at": "2026-02-05T10:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "total": 100
  }
}
```

---

### 5.6 Revoke Certificate

Cabut/invalidate sertifikat.

**Endpoint:** `PATCH /admin/certificates/{id}/revoke`

**Authentication:** Required

**Role:** `admin`, `super-admin`

**Request Body:**
```json
{
  "notes": "Nilai tidak memenuhi syarat setelah re-evaluasi"
}
```

**Response Success (200):**
```json
{
  "message": "Sertifikat berhasil dicabut",
  "certificate": {
    "id": 50,
    "is_valid": false,
    "notes": "Nilai tidak memenuhi syarat setelah re-evaluasi"
  }
}
```

---

## 6. Support Ticket

### 6.1 Get My Tickets

List support ticket user.

**Endpoint:** `GET /support/tickets`

**Authentication:** Required

**Query Parameters:**
- `status` (string, optional) - open, in_progress, resolved, closed
- `category` (string, optional)

**Response Success (200):**
```json
{
  "data": [
    {
      "id": 20,
      "subject": "Tidak bisa akses kelas",
      "category": "technical",
      "status": "in_progress",
      "priority": "medium",
      "created_at": "2026-02-09T10:00:00Z",
      "last_reply_at": "2026-02-10T08:30:00Z",
      "replies_count": 2
    }
  ]
}
```

---

### 6.2 Create Ticket

Buat support ticket baru.

**Endpoint:** `POST /support/tickets`

**Authentication:** Required

**Request:** `multipart/form-data`

```json
{
  "category": "technical",
  "subject": "Error saat login",
  "description": "Saya tidak bisa login, muncul error 500",
  "priority": "high",
  "attachments": ["file1.png", "file2.jpg"]
}
```

**Categories:**
- `technical` - Masalah teknis
- `course` - Terkait pembelajaran/kelas
- `certificate` - Masalah sertifikat
- `account` - Masalah akun
- `other` - Lainnya

**Priorities:**
- `low`, `medium`, `high`, `urgent`

**Response Success (201):**
```json
{
  "message": "Ticket created successfully",
  "ticket": {
    "id": 25,
    "ticket_number": "TKT-20260210-0025",
    "subject": "Error saat login",
    "status": "open",
    "created_at": "2026-02-10T12:00:00Z"
  }
}
```

---

### 6.3 Get Ticket Detail

Detail ticket dengan replies.

**Endpoint:** `GET /support/tickets/{id}`

**Authentication:** Required

**Response Success (200):**
```json
{
  "id": 20,
  "ticket_number": "TKT-20260209-0020",
  "user": {
    "id": 15,
    "name": "Student A"
  },
  "subject": "Tidak bisa akses kelas",
  "description": "Saya sudah enroll tapi tidak bisa buka Moodle",
  "category": "technical",
  "status": "in_progress",
  "priority": "medium",
  "attachments": [
    {
      "filename": "screenshot.png",
      "url": "/storage/tickets/screenshot.png"
    }
  ],
  "created_at": "2026-02-09T10:00:00Z",
  "replies": [
    {
      "id": 50,
      "user": {
        "id": 2,
        "name": "Admin A",
        "role": "admin"
      },
      "message": "Kami sedang mengecek masalah ini",
      "created_at": "2026-02-10T08:30:00Z"
    }
  ]
}
```

---

### 6.4 Reply to Ticket

Balas ticket (user atau admin).

**Endpoint:** `POST /support/tickets/{id}/reply`

**Authentication:** Required

**Request Body:**
```json
{
  "message": "Terima kasih atas bantuannya"
}
```

**Response Success (200):**
```json
{
  "message": "Reply added successfully",
  "reply": {
    "id": 51,
    "message": "Terima kasih atas bantuannya",
    "created_at": "2026-02-10T13:00:00Z"
  }
}
```

---

### 6.5 Update Ticket Status

Update status ticket (admin only).

**Endpoint:** `PATCH /support/tickets/{id}/status`

**Authentication:** Required

**Role:** `admin`, `super-admin`

**Request Body:**
```json
{
  "status": "resolved"
}
```

**Status Values:**
- `open`, `in_progress`, `resolved`, `closed`

**Response Success (200):**
```json
{
  "message": "Ticket status updated",
  "ticket": {
    "id": 20,
    "status": "resolved",
    "updated_at": "2026-02-10T13:30:00Z"
  }
}
```

---

### 6.6 Get Ticket Stats

Statistik support ticket.

**Endpoint:** `GET /support/tickets/stats`

**Authentication:** Required

**Role:** `admin`, `super-admin`

**Response Success (200):**
```json
{
  "total": 150,
  "open": 25,
  "in_progress": 30,
  "resolved": 80,
  "closed": 15,
  "by_category": {
    "technical": 60,
    "course": 40,
    "certificate": 25,
    "account": 15,
    "other": 10
  },
  "avg_response_time_hours": 2.5,
  "avg_resolution_time_hours": 24.0
}
```

---

## 7. Escalation Tickets (Admin ↔ Super Admin)

### 7.1 Escalate Ticket to Super Admin

Eskalasi ticket ke super admin.

**Endpoint:** `POST /support/tickets/{id}/escalate`

**Authentication:** Required

**Role:** `admin`

**Request Body:**
```json
{
  "reason": "Memerlukan approval perubahan data di ERP",
  "priority": "high"
}
```

**Response Success (200):**
```json
{
  "message": "Ticket escalated successfully",
  "escalation": {
    "id": 10,
    "support_ticket_id": 20,
    "status": "open",
    "created_at": "2026-02-10T14:00:00Z"
  }
}
```

---

### 7.2 Get Escalation Tickets

List eskalasi (admin melihat yang dibuat, super admin melihat semua).

**Endpoint:** `GET /escalations`

**Authentication:** Required

**Role:** `admin`, `super-admin`

**Response Success (200):**
```json
{
  "data": [
    {
      "id": 10,
      "support_ticket": {
        "id": 20,
        "subject": "Perubahan data user"
      },
      "escalated_by": {
        "id": 2,
        "name": "Admin A"
      },
      "reason": "Memerlukan approval perubahan data di ERP",
      "status": "in_progress",
      "priority": "high",
      "created_at": "2026-02-10T14:00:00Z"
    }
  ]
}
```

---

### 7.3 Reply to Escalation

Balas eskalasi ticket.

**Endpoint:** `POST /escalations/{id}/reply`

**Authentication:** Required

**Role:** `admin`, `super-admin`

**Request Body:**
```json
{
  "message": "Approved, silakan lanjutkan"
}
```

**Response Success (200):**
```json
{
  "message": "Reply added successfully",
  "reply": {
    "id": 15,
    "message": "Approved, silakan lanjutkan",
    "created_at": "2026-02-10T15:00:00Z"
  }
}
```

---

### 7.4 Update Escalation Status

Update status eskalasi.

**Endpoint:** `PATCH /escalations/{id}/status`

**Authentication:** Required

**Role:** `super-admin`

**Request Body:**
```json
{
  "status": "resolved"
}
```

**Response Success (200):**
```json
{
  "message": "Escalation status updated",
  "escalation": {
    "id": 10,
    "status": "resolved"
  }
}
```

---

## 8. Announcements

### 8.1 Get Announcements (User)

List pengumuman untuk user (filtered by scope).

**Endpoint:** `GET /announcements`

**Authentication:** Required

**Query Parameters:**
- `scope` (string, optional) - global, department, class
- `per_page` (integer, default: 20)

**Response Success (200):**
```json
{
  "data": [
    {
      "id": 5,
      "title": "Pengumuman Penting",
      "content": "Lorem ipsum dolor sit amet...",
      "scope": "global",
      "priority": "high",
      "image": "/storage/announcements/image.jpg",
      "author": {
        "id": 1,
        "name": "Super Admin"
      },
      "is_active": true,
      "created_at": "2026-02-08T09:00:00Z"
    }
  ]
}
```

---

### 8.2 Get Latest Announcements

Pengumuman terbaru (untuk dashboard).

**Endpoint:** `GET /announcements/latest`

**Authentication:** Required

**Query Parameters:**
- `limit` (integer, default: 5)

**Response:** Same as Get Announcements, limited to {limit} items

---

### 8.3 Get Announcement Detail

Detail pengumuman.

**Endpoint:** `GET /announcements/{id}`

**Authentication:** Required

**Response Success (200):**
```json
{
  "id": 5,
  "title": "Pengumuman Penting",
  "content": "Lorem ipsum dolor sit amet consectetur adipiscing elit...",
  "scope": "global",
  "priority": "high",
  "image": "/storage/announcements/image.jpg",
  "author": {
    "id": 1,
    "name": "Super Admin",
    "role": "super-admin"
  },
  "is_active": true,
  "created_at": "2026-02-08T09:00:00Z",
  "updated_at": "2026-02-08T09:00:00Z"
}
```

---

### 8.4 Create Announcement (Super Admin)

Buat pengumuman global.

**Endpoint:** `POST /superadmin/announcements`

**Authentication:** Required

**Role:** `super-admin`

**Request:** `multipart/form-data`

```json
{
  "title": "Pengumuman Penting",
  "content": "Lorem ipsum...",
  "scope": "global",
  "priority": "high",
  "image": "[file upload]",
  "is_active": true
}
```

**Scopes:**
- `global` - Seluruh platform
- `department` - Departemen tertentu (+ `target_id` = department_id)
- `class` - Kelas tertentu (+ `target_id` = course_id)

**Priorities:**
- `low`, `normal`, `high`, `urgent`

**Response Success (201):**
```json
{
  "message": "Announcement created successfully",
  "announcement": {
    "id": 10,
    "title": "Pengumuman Penting",
    "scope": "global",
    "created_at": "2026-02-10T16:00:00Z"
  }
}
```

---

### 8.5 Update Announcement

Update pengumuman.

**Endpoint:** `PUT /superadmin/announcements/{id}`

**Authentication:** Required

**Role:** `super-admin`, `admin` (own only), `instructor` (own only)

**Request:** Similar to Create Announcement

**Response Success (200):**
```json
{
  "message": "Announcement updated successfully",
  "announcement": {
    "id": 10,
    "title": "Updated Title",
    "updated_at": "2026-02-10T16:30:00Z"
  }
}
```

---

### 8.6 Delete Announcement

Hapus pengumuman.

**Endpoint:** `DELETE /superadmin/announcements/{id}`

**Authentication:** Required

**Role:** `super-admin`, `admin` (own only), `instructor` (own only)

**Response Success (200):**
```json
{
  "message": "Announcement deleted successfully"
}
```

---

## 9. AI Assistant

### 9.1 Get AI Context

Mendapatkan context untuk AI (features, menu structure).

**Endpoint:** `GET /ai-assistant/context`

**Authentication:** Required

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "user_info": {
      "name": "Budi Santoso",
      "role": "user",
      "employee_id": "12345678"
    },
    "available_features": [
      {
        "name": "Dashboard User",
        "description": "Ringkasan aktivitas belajar",
        "path": "/dashboard",
        "roles": ["employee", "user"]
      }
    ],
    "navigation_menu": {
      "Dashboard": "/dashboard",
      "Kelas Saya": "/dashboard/classes",
      "Sertifikat": "/dashboard/certificates"
    }
  }
}
```

---

### 9.2 Chat with AI

Chat dengan AI Assistant.

**Endpoint:** `POST /ai-assistant/chat`

**Authentication:** Required

**Request Body:**
```json
{
  "message": "Bagaimana cara enroll ke kelas?",
  "conversation_id": "conv-123456789",
  "course_id": 10
}
```

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "response": "Untuk enroll ke kelas, ikuti langkah berikut:\n\n1. Login ke portal\n2. Buka menu 'Kelas Saya'...",
    "conversation_id": "conv-123456789",
    "timestamp": "2026-02-10T17:00:00Z"
  }
}
```

---

### 9.3 Get AI Conversation Sessions

List semua conversation sessions user.

**Endpoint:** `GET /ai-assistant/sessions`

**Authentication:** Required

**Response Success (200):**
```json
{
  "success": true,
  "data": [
    {
      "conversation_id": "conv-123456789",
      "title": "Bagaimana cara enroll ke kelas?",
      "message_count": 10,
      "started_at": "2026-02-10T10:00:00Z",
      "last_message_at": "2026-02-10T17:00:00Z"
    }
  ]
}
```

---

### 9.4 Get Conversation History

History chat dari conversation tertentu.

**Endpoint:** `GET /ai-assistant/history?conversation_id=conv-123456789`

**Authentication:** Required

**Response Success (200):**
```json
{
  "success": true,
  "data": [
    {
      "role": "user",
      "content": "Bagaimana cara enroll ke kelas?",
      "timestamp": "2026-02-10T10:00:00Z"
    },
    {
      "role": "assistant",
      "content": "Untuk enroll ke kelas, ikuti langkah berikut...",
      "timestamp": "2026-02-10T10:00:05Z"
    }
  ]
}
```

---

### 9.5 Delete Conversation

Hapus conversation history.

**Endpoint:** `DELETE /ai-assistant/sessions/{conversationId}`

**Authentication:** Required

**Response Success (200):**
```json
{
  "success": true,
  "message": "Percakapan dihapus (15 pesan)"
}
```

---

## 10. Moodle Sync (Super Admin & Admin)

### 10.1 Get Sync Status

Status koneksi Moodle.

**Endpoint:** `GET /moodle/sync/status`

**Authentication:** Required

**Role:** `admin`, `super-admin`

**Response Success (200):**
```json
{
  "status": "connected",
  "moodle_version": "4.3.2",
  "total_users": 500,
  "total_courses": 50,
  "database": "MOODLEDB",
  "host": "moodle-db-host"
}
```

---

### 10.2 Get Sync History

History sinkronisasi.

**Endpoint:** `GET /moodle/sync/history`

**Authentication:** Required

**Role:** `admin`, `super-admin`

**Response Success (200):**
```json
{
  "data": [
    {
      "type": "full_sync",
      "started_at": "2026-02-10T02:00:00Z",
      "completed_at": "2026-02-10T02:05:30Z",
      "duration_seconds": 330,
      "stats": {
        "users": { "added": 10, "updated": 50 },
        "courses": { "added": 2, "updated": 45 },
        "enrollments": { "added": 20, "updated": 100 }
      },
      "status": "success"
    }
  ]
}
```

---

### 10.3 Full Sync

Sinkronisasi penuh (users, courses, enrollments).

**Endpoint:** `POST /moodle/sync/full`

**Authentication:** Required

**Role:** `super-admin`

**Response Success (200):**
```json
{
  "message": "Full sync completed successfully",
  "started_at": "2026-02-10T18:00:00Z",
  "completed_at": "2026-02-10T18:05:00Z",
  "duration": 300,
  "users": { "total_moodle": 500, "added": 5, "updated": 50 },
  "courses": { "total_moodle": 50, "added": 2, "updated": 45 },
  "enrollments": { "total_moodle": 2000, "added": 50, "updated": 100 }
}
```

---

### 10.4 Sync Users Only

Sinkronisasi users saja.

**Endpoint:** `POST /moodle/sync/users`

**Authentication:** Required

**Role:** `admin`, `super-admin`

**Response Success (200):**
```json
{
  "message": "Users synced successfully",
  "stats": {
    "total_moodle": 500,
    "added": 5,
    "updated": 50,
    "errors": 0,
    "duration_seconds": 15.5
  }
}
```

---

### 10.5 Sync Courses Only

Sinkronisasi courses saja.

**Endpoint:** `POST /moodle/sync/courses`

**Authentication:** Required

**Role:** `admin`, `super-admin`

**Response:** Similar to Sync Users

---

### 10.6 Sync Enrollments Only

Sinkronisasi enrollments saja.

**Endpoint:** `POST /moodle/sync/enrollments`

**Authentication:** Required

**Role:** `admin`, `super-admin`

**Response:** Similar to Sync Users

---

## 11. Profile Management

### 11.1 Get Profile

Profile user yang sedang login.

**Endpoint:** `GET /profile`

**Authentication:** Required

**Response Success (200):**
```json
{
  "id": 15,
  "employee_id": "12345678",
  "name": "Budi Santoso",
  "email": "budi@plnip.co.id",
  "phone": "08123456789",
  "department": "Engineering",
  "position": "Senior Engineer",
  "avatar": "/storage/avatars/user-15.jpg",
  "role": "user",
  "source": "erp",
  "is_active": true,
  "created_at": "2026-01-15T10:00:00Z"
}
```

---

### 11.2 Update Profile

Update profile user.

**Endpoint:** `PUT /profile`

**Authentication:** Required

**Request Body:**
```json
{
  "name": "Budi Santoso Updated",
  "phone": "08111222333",
  "department": "IT Engineering",
  "position": "Lead Engineer"
}
```

**Note:** Email dan employee_id tidak bisa diubah sendiri (hanya via super admin).

**Response Success (200):**
```json
{
  "message": "Profile updated successfully",
  "user": {
    "id": 15,
    "name": "Budi Santoso Updated",
    "phone": "08111222333",
    "updated_at": "2026-02-10T18:30:00Z"
  }
}
```

---

### 11.3 Upload Avatar

Upload foto profil.

**Endpoint:** `POST /profile/avatar`

**Authentication:** Required

**Request:** `multipart/form-data`

```
avatar: [image file (jpg, png, max 2MB)]
```

**Response Success (200):**
```json
{
  "message": "Avatar uploaded successfully",
  "avatar_url": "/storage/avatars/user-15.jpg"
}
```

---

### 11.4 Delete Avatar

Hapus foto profil.

**Endpoint:** `DELETE /profile/avatar`

**Authentication:** Required

**Response Success (200):**
```json
{
  "message": "Avatar deleted successfully"
}
```

---

### 11.5 Change Password

Ganti password.

**Endpoint:** `PUT /profile/password`

**Authentication:** Required

**Request Body:**
```json
{
  "current_password": "oldpassword123",
  "password": "newpassword456",
  "password_confirmation": "newpassword456"
}
```

**Response Success (200):**
```json
{
  "message": "Password changed successfully"
}
```

**Response Error (422):**
```json
{
  "message": "The current password is incorrect.",
  "errors": {
    "current_password": ["The current password is incorrect."]
  }
}
```

---

## 12. Direct Messages

### 12.1 Get Conversations

List conversations user.

**Endpoint:** `GET /messages/conversations`

**Authentication:** Required

**Response Success (200):**
```json
{
  "data": [
    {
      "id": 10,
      "participant": {
        "id": 5,
        "name": "Instructor A",
        "avatar": "/storage/avatars/user-5.jpg",
        "role": "instructor"
      },
      "last_message": {
        "message": "Terima kasih atas pertanyaannya",
        "sent_at": "2026-02-10T17:30:00Z"
      },
      "unread_count": 2,
      "updated_at": "2026-02-10T17:30:00Z"
    }
  ]
}
```

---

### 12.2 Start Conversation

Mulai conversation baru dengan user.

**Endpoint:** `POST /messages/conversations`

**Authentication:** Required

**Request Body:**
```json
{
  "recipient_id": 5,
  "message": "Halo, saya ingin bertanya tentang kelas Oracle"
}
```

**Response Success (201):**
```json
{
  "message": "Conversation started",
  "conversation": {
    "id": 15,
    "participant": {
      "id": 5,
      "name": "Instructor A"
    },
    "created_at": "2026-02-10T18:00:00Z"
  }
}
```

---

### 12.3 Get Messages

Ambil messages dari conversation.

**Endpoint:** `GET /messages/conversations/{id}`

**Authentication:** Required

**Query Parameters:**
- `page` (integer, default: 1)
- `per_page` (integer, default: 50)

**Response Success (200):**
```json
{
  "data": [
    {
      "id": 100,
      "sender": {
        "id": 15,
        "name": "Budi Santoso"
      },
      "message": "Halo, saya ingin bertanya",
      "is_read": true,
      "sent_at": "2026-02-10T18:00:00Z"
    },
    {
      "id": 101,
      "sender": {
        "id": 5,
        "name": "Instructor A"
      },
      "message": "Silakan, ada yang bisa saya bantu?",
      "is_read": true,
      "sent_at": "2026-02-10T18:01:00Z"
    }
  ]
}
```

---

### 12.4 Send Message

Kirim message ke conversation.

**Endpoint:** `POST /messages/conversations/{id}`

**Authentication:** Required

**Request Body:**
```json
{
  "message": "Terima kasih atas penjelasannya"
}
```

**Response Success (201):**
```json
{
  "message": "Message sent",
  "data": {
    "id": 102,
    "message": "Terima kasih atas penjelasannya",
    "sent_at": "2026-02-10T18:05:00Z"
  }
}
```

---

### 12.5 Mark as Read

Tandai messages sebagai sudah dibaca.

**Endpoint:** `PATCH /messages/conversations/{id}/read`

**Authentication:** Required

**Response Success (200):**
```json
{
  "message": "Messages marked as read"
}
```

---

### 12.6 Get Available Users

List user yang bisa di-message.

**Endpoint:** `GET /messages/users`

**Authentication:** Required

**Query Parameters:**
- `search` (string, optional)
- `role` (string, optional)

**Response Success (200):**
```json
{
  "data": [
    {
      "id": 5,
      "name": "Instructor A",
      "email": "instructor@plnip.co.id",
      "role": "instructor",
      "avatar": "/storage/avatars/user-5.jpg"
    }
  ]
}
```

---

### 12.7 Get Unread Count

Total unread messages.

**Endpoint:** `GET /messages/unread`

**Authentication:** Required

**Response Success (200):**
```json
{
  "unread_count": 5
}
```

---

## 13. Class Chat

### 13.1 Get Class Chat Messages

Messages dari class group chat.

**Endpoint:** `GET /classes/{classId}/chat`

**Authentication:** Required

**Query Parameters:**
- `page` (integer)
- `per_page` (integer, default: 50)

**Response Success (200):**
```json
{
  "data": [
    {
      "id": 200,
      "user": {
        "id": 15,
        "name": "Budi Santoso",
        "role": "student"
      },
      "message": "Kapan deadline tugas chapter 3?",
      "is_question": true,
      "is_answered": false,
      "created_at": "2026-02-10T14:00:00Z"
    }
  ]
}
```

---

### 13.2 Send Class Chat Message

Kirim message ke class chat.

**Endpoint:** `POST /classes/{classId}/chat`

**Authentication:** Required

**Request Body:**
```json
{
  "message": "Kapan deadline tugas chapter 3?",
  "is_question": true
}
```

**Response Success (201):**
```json
{
  "message": "Message sent",
  "data": {
    "id": 201,
    "message": "Kapan deadline tugas chapter 3?",
    "is_question": true,
    "created_at": "2026-02-10T14:00:00Z"
  }
}
```

---

### 13.3 Mark Question as Answered

Tandai pertanyaan sudah dijawab (instructor only).

**Endpoint:** `PATCH /classes/{classId}/chat/{messageId}/answered`

**Authentication:** Required

**Role:** `instructor`, `admin`, `super-admin`

**Response Success (200):**
```json
{
  "message": "Question marked as answered"
}
```

---

### 13.4 Get Unanswered Questions

Pertanyaan belum dijawab (instructor view).

**Endpoint:** `GET /classes/{classId}/chat/questions`

**Authentication:** Required

**Role:** `instructor`

**Response Success (200):**
```json
{
  "data": [
    {
      "id": 200,
      "user": {
        "id": 15,
        "name": "Budi Santoso"
      },
      "message": "Kapan deadline tugas chapter 3?",
      "course": {
        "id": 10,
        "title": "Oracle Database"
      },
      "created_at": "2026-02-10T14:00:00Z"
    }
  ]
}
```

---

## 14. Landing Page CMS (Super Admin)

### 14.1 Get Landing Page Data

Data public landing page.

**Endpoint:** `GET /landing-page`

**Authentication:** None (public)

**Response Success (200):**
```json
{
  "settings": {
    "hero_title": "PLN IP Learning Hub",
    "hero_subtitle": "Platform pembelajaran digital PLN Indonesia Power",
    "logo": "/storage/cms/logo.png"
  },
  "hero_images": [
    { "id": 1, "image": "/storage/cms/hero1.jpg" }
  ],
  "leaders": [
    {
      "id": 1,
      "name": "Direktur Utama",
      "position": "CEO",
      "photo": "/storage/cms/leader1.jpg"
    }
  ],
  "partners": [
    { "id": 1, "name": "Partner A", "logo": "/storage/cms/partner1.png" }
  ],
  "stats": {
    "total_courses": 50,
    "total_students": 500,
    "total_certificates": 200
  }
}
```

---

### 14.2 Update Landing Page Settings

Update settings (title, subtitle, logo).

**Endpoint:** `POST /cms/settings`

**Authentication:** Required

**Role:** `super-admin`

**Request:** `multipart/form-data`

```json
{
  "hero_title": "New Title",
  "hero_subtitle": "New Subtitle",
  "logo": "[file upload]"
}
```

**Response Success (200):**
```json
{
  "message": "Settings updated successfully"
}
```

---

### 14.3 Add Hero Image

Tambah hero image untuk carousel.

**Endpoint:** `POST /cms/hero-images`

**Authentication:** Required

**Role:** `super-admin`

**Request:** `multipart/form-data`

```
image: [file upload]
```

**Response Success (201):**
```json
{
  "message": "Hero image added",
  "hero_image": {
    "id": 5,
    "image": "/storage/cms/hero5.jpg"
  }
}
```

---

### 14.4 Delete Hero Image

Hapus hero image.

**Endpoint:** `DELETE /cms/hero-images/{id}`

**Authentication:** Required

**Role:** `super-admin`

**Response Success (200):**
```json
{
  "message": "Hero image deleted"
}
```

---

### 14.5 Add Leader

Tambah data pimpinan.

**Endpoint:** `POST /cms/leaders`

**Authentication:** Required

**Role:** `super-admin`

**Request:** `multipart/form-data`

```json
{
  "name": "John Doe",
  "position": "Direktur Operasional",
  "photo": "[file upload]"
}
```

**Response Success (201):**
```json
{
  "message": "Leader added",
  "leader": {
    "id": 10,
    "name": "John Doe",
    "position": "Direktur Operasional",
    "photo": "/storage/cms/leader10.jpg"
  }
}
```

---

### 14.6 Update Leader

Update data pimpinan.

**Endpoint:** `POST /cms/leaders/{id}`

**Authentication:** Required

**Role:** `super-admin`

**Request:** `multipart/form-data` (same as Add Leader)

**Response Success (200):**
```json
{
  "message": "Leader updated"
}
```

---

### 14.7 Delete Leader

Hapus data pimpinan.

**Endpoint:** `DELETE /cms/leaders/{id}`

**Authentication:** Required

**Role:** `super-admin`

**Response Success (200):**
```json
{
  "message": "Leader deleted"
}
```

---

### 14.8 Add Partner

Tambah partner/mitra.

**Endpoint:** `POST /cms/partners`

**Authentication:** Required

**Role:** `super-admin`

**Request:** `multipart/form-data`

```json
{
  "name": "Partner Company",
  "logo": "[file upload]"
}
```

**Response Success (201):**
```json
{
  "message": "Partner added",
  "partner": {
    "id": 5,
    "name": "Partner Company",
    "logo": "/storage/cms/partner5.png"
  }
}
```

---

### 14.9 Delete Partner

Hapus partner.

**Endpoint:** `DELETE /cms/partners/{id}`

**Authentication:** Required

**Role:** `super-admin`

**Response Success (200):**
```json
{
  "message": "Partner deleted"
}
```

---

## 15. Activity Log (Super Admin)

### 15.1 Get Activity Logs

List semua activity logs.

**Endpoint:** `GET /activity-log`

**Authentication:** Required

**Role:** `super-admin`

**Query Parameters:**
- `page` (integer)
- `per_page` (integer, default: 50)
- `user_id` (integer, optional)
- `log_name` (string, optional)
- `from_date` (date, optional)
- `to_date` (date, optional)

**Response Success (200):**
```json
{
  "data": [
    {
      "id": 1000,
      "log_name": "user",
      "description": "User logged in",
      "subject_type": "User",
      "subject_id": 15,
      "causer": {
        "id": 15,
        "name": "Budi Santoso"
      },
      "properties": {
        "ip": "192.168.1.100",
        "user_agent": "Mozilla/5.0..."
      },
      "created_at": "2026-02-10T10:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 20,
    "total": 1000
  }
}
```

---

### 15.2 Get User Activity Logs

Activity logs untuk user tertentu.

**Endpoint:** `GET /activity-log/users/{userId}`

**Authentication:** Required

**Role:** `super-admin`

**Response:** Similar to Get Activity Logs, filtered by user

---

## 16. Moodle SSO

### 16.1 Get Moodle Login URL

Mendapatkan autologin URL untuk SSO ke Moodle.

**Endpoint:** `POST /moodle/login-url`

**Authentication:** Required

**Request Body:**
```json
{
  "course_id": 10
}
```

**Response Success (200):**
```json
{
  "login_url": "https://moodle.plnip.co.id/login/token.php?token=abcdef123456&service=moodle_mobile_app",
  "redirect_to": "https://moodle.plnip.co.id/course/view.php?id=123"
}
```

---

## Error Responses

### Standard Error Format

Semua error response menggunakan format standar:

```json
{
  "message": "Error message here",
  "errors": {
    "field_name": ["Error detail 1", "Error detail 2"]
  }
}
```

### HTTP Status Codes

| Code | Meaning              | Deskripsi                              |
|------|----------------------|----------------------------------------|
| 200  | OK                   | Request berhasil                       |
| 201  | Created              | Resource berhasil dibuat               |
| 400  | Bad Request          | Request tidak valid                    |
| 401  | Unauthorized         | Token tidak valid atau expired         |
| 403  | Forbidden            | User tidak punya permission            |
| 404  | Not Found            | Resource tidak ditemukan               |
| 422  | Unprocessable Entity | Validation error                       |
| 500  | Internal Server Error| Server error                           |
| 503  | Service Unavailable  | Service sedang maintenance             |

---

## Rate Limiting

API menggunakan rate limiting untuk prevent abuse:

- **Anonymous requests**: 60 requests per minute
- **Authenticated requests**: 120 requests per minute

**Headers di response:**
```
X-RateLimit-Limit: 120
X-RateLimit-Remaining: 119
X-RateLimit-Reset: 1707562800
```

Jika exceed limit:
```json
{
  "message": "Too many requests. Please try again in 60 seconds."
}
```

---

## Pagination

List endpoints menggunakan pagination. Response format:

```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 20,
    "total": 200,
    "from": 1,
    "to": 20
  },
  "links": {
    "first": "http://api.example.com/endpoint?page=1",
    "last": "http://api.example.com/endpoint?page=10",
    "prev": null,
    "next": "http://api.example.com/endpoint?page=2"
  }
}
```

---

## Kesimpulan

API Reference ini mencakup semua endpoint yang tersedia di PLN IP Learning Hub Portal. Untuk testing API, gunakan tools seperti Postman atau Insomnia dengan mengimport collection yang tersedia di repository.

Untuk pertanyaan lebih lanjut atau bantuan integrasi, hubungi tim development PLN IP Learning Hub.
