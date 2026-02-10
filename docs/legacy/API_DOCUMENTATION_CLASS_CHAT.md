# Class Chat API Documentation

## Overview

The Class Chat API provides real-time messaging functionality for PLNIP Portal, supporting text messages, image uploads, threaded replies with mentions, and instructor question management.

**Base URL**: `https://api.portal.plnip.ac.id/api`

**Authentication**: Laravel Sanctum (Session-based)

**Broadcasting**: Pusher WebSocket (Laravel Echo)

---

## Endpoints

### 1. Get Chat Messages

Retrieve paginated chat messages for a specific class.

**Endpoint**: `GET /classes/{classId}/chat`

**Authentication**: Required (Student, Instructor, or Admin enrolled in class)

**Parameters**:

- `classId` (path, required): The ID of the class
- `page` (query, optional): Page number for pagination (default: 1)
- `per_page` (query, optional): Items per page (default: 50, max: 100)

**Response**: `200 OK`

```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 123,
                "class_id": 1,
                "user_id": 42,
                "message": "Hello everyone!",
                "message_type": "discussion",
                "is_answered": false,
                "reply_to": null,
                "mentioned_user_id": null,
                "image_path": null,
                "created_at": "2026-02-05T10:30:00.000000Z",
                "updated_at": "2026-02-05T10:30:00.000000Z",
                "user": {
                    "id": 42,
                    "name": "John Doe",
                    "avatar": "avatars/john.jpg"
                },
                "replyToMessage": null,
                "mentionedUser": null
            },
            {
                "id": 124,
                "class_id": 1,
                "user_id": 43,
                "message": "Great question!",
                "message_type": "discussion",
                "is_answered": false,
                "reply_to": 123,
                "mentioned_user_id": 42,
                "image_path": null,
                "created_at": "2026-02-05T10:31:00.000000Z",
                "updated_at": "2026-02-05T10:31:00.000000Z",
                "user": {
                    "id": 43,
                    "name": "Jane Smith",
                    "avatar": "avatars/jane.jpg"
                },
                "replyToMessage": {
                    "id": 123,
                    "message": "Hello everyone!",
                    "user": {
                        "id": 42,
                        "name": "John Doe"
                    }
                },
                "mentionedUser": {
                    "id": 42,
                    "name": "John Doe"
                }
            }
        ],
        "first_page_url": "https://api.portal.plnip.ac.id/api/classes/1/chat?page=1",
        "from": 1,
        "last_page": 5,
        "last_page_url": "https://api.portal.plnip.ac.id/api/classes/1/chat?page=5",
        "next_page_url": "https://api.portal.plnip.ac.id/api/classes/1/chat?page=2",
        "path": "https://api.portal.plnip.ac.id/api/classes/1/chat",
        "per_page": 50,
        "prev_page_url": null,
        "to": 50,
        "total": 234
    }
}
```

**Error Responses**:

- `401 Unauthorized`: User not authenticated
- `403 Forbidden`: User not enrolled in class
- `404 Not Found`: Class does not exist

---

### 2. Send Message

Send a new message (text, image, or both) to a class chat.

**Endpoint**: `POST /classes/{classId}/chat`

**Authentication**: Required (Student, Instructor, or Admin enrolled in class)

**Content-Type**: `multipart/form-data`

**Parameters**:

- `classId` (path, required): The ID of the class
- `message` (body, optional): Text message (max 2000 characters)
- `message_type` (body, optional): Either "discussion" or "question" (default: "discussion")
- `reply_to` (body, optional): ID of message being replied to
- `mentioned_user_id` (body, optional): ID of user being mentioned
- `image` (body, optional): Image file (JPEG, PNG, JPG, GIF, max 5MB)

**Validation**:

- At least one of `message` or `image` must be provided
- If `reply_to` is provided, the referenced message must exist and belong to the same class
- `image` must be a valid image file format
- Maximum image size: 5MB

**Request Example**:

```javascript
const formData = new FormData();
formData.append("message", "This is my question");
formData.append("message_type", "question");
formData.append("image", imageFile);

fetch("https://api.portal.plnip.ac.id/api/classes/1/chat", {
    method: "POST",
    credentials: "include",
    body: formData,
});
```

**Response**: `201 Created`

```json
{
    "success": true,
    "data": {
        "id": 125,
        "class_id": 1,
        "user_id": 42,
        "message": "This is my question",
        "message_type": "question",
        "is_answered": false,
        "reply_to": null,
        "mentioned_user_id": null,
        "image_path": "class-chat-images/xyz123.jpg",
        "created_at": "2026-02-05T10:35:00.000000Z",
        "updated_at": "2026-02-05T10:35:00.000000Z",
        "user": {
            "id": 42,
            "name": "John Doe",
            "avatar": "avatars/john.jpg"
        },
        "replyToMessage": null,
        "mentionedUser": null
    },
    "message": "Pesan berhasil dikirim"
}
```

**Error Responses**:

- `401 Unauthorized`: User not authenticated
- `403 Forbidden`: User not enrolled in class
- `422 Unprocessable Entity`: Validation failed
    ```json
    {
        "success": false,
        "message": "Validasi gagal",
        "errors": {
            "message": ["Pesan atau gambar harus diisi"],
            "image": ["File harus berupa gambar", "Ukuran file maksimal 5MB"]
        }
    }
    ```
- `500 Internal Server Error`: Server error occurred

**WebSocket Broadcast**:
After successful creation, a `message.new` event is broadcast to all connected users on channel `class-chat.{classId}` with the message data.

---

### 3. Mark Question as Answered

Mark a question message as answered (Instructor only).

**Endpoint**: `POST /classes/{classId}/chat/{messageId}/mark-answered`

**Authentication**: Required (Instructor of the class)

**Parameters**:

- `classId` (path, required): The ID of the class
- `messageId` (path, required): The ID of the question message

**Response**: `200 OK`

```json
{
    "success": true,
    "data": {
        "id": 125,
        "class_id": 1,
        "user_id": 42,
        "message": "This is my question",
        "message_type": "question",
        "is_answered": true,
        "answered_by": 50,
        "answered_at": "2026-02-05T11:00:00.000000Z",
        "reply_to": null,
        "mentioned_user_id": null,
        "image_path": null,
        "created_at": "2026-02-05T10:35:00.000000Z",
        "updated_at": "2026-02-05T11:00:00.000000Z",
        "user": {
            "id": 42,
            "name": "John Doe",
            "avatar": "avatars/john.jpg"
        },
        "answeredByUser": {
            "id": 50,
            "name": "Prof. Smith"
        }
    },
    "message": "Pertanyaan ditandai sudah dijawab"
}
```

**Error Responses**:

- `401 Unauthorized`: User not authenticated
- `403 Forbidden`: User is not the instructor of this class
- `404 Not Found`: Message not found or is not a question

**WebSocket Broadcast**:
After marking as answered, a `question.answered` event is broadcast to:

1. Channel `instructor-dashboard` (for real-time stats update)
2. Channel `class.{classId}` (for students to see updated status)

---

### 4. Get Questions for Class

Retrieve all questions for a class with filtering options (Instructor only).

**Endpoint**: `GET /classes/{classId}/questions`

**Authentication**: Required (Instructor of the class)

**Parameters**:

- `classId` (path, required): The ID of the class
- `page` (query, optional): Page number for pagination (default: 1)
- `status` (query, optional): Filter by status ("answered", "unanswered", "all") (default: "all")

**Response**: `200 OK`

```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 125,
                "class_id": 1,
                "user_id": 42,
                "message": "How do I solve this problem?",
                "message_type": "question",
                "is_answered": false,
                "answered_by": null,
                "answered_at": null,
                "created_at": "2026-02-05T10:35:00.000000Z",
                "user": {
                    "id": 42,
                    "name": "John Doe",
                    "avatar": "avatars/john.jpg"
                },
                "answeredByUser": null
            }
        ],
        "per_page": 20,
        "total": 15
    }
}
```

**Error Responses**:

- `401 Unauthorized`: User not authenticated
- `403 Forbidden`: User is not the instructor of this class

---

### 5. Get Question Statistics

Get statistics about unanswered questions for instructor's classes.

**Endpoint**: `GET /instructor/question-stats`

**Authentication**: Required (Instructor role)

**Response**: `200 OK`

```json
{
    "success": true,
    "data": {
        "today_questions": 12,
        "unanswered_questions": 5
    }
}
```

**Error Responses**:

- `401 Unauthorized`: User not authenticated
- `403 Forbidden`: User is not an instructor

---

## WebSocket Events

### Channel: `class-chat.{classId}`

**Event**: `message.new`

Broadcast when a new message is sent to the class chat.

**Payload**:

```json
{
    "id": 125,
    "class_id": 1,
    "user_id": 42,
    "message": "Hello!",
    "message_type": "discussion",
    "is_answered": false,
    "reply_to": null,
    "mentioned_user_id": null,
    "image_path": null,
    "created_at": "2026-02-05T10:35:00.000000Z",
    "user": {
        "id": 42,
        "name": "John Doe",
        "avatar": "avatars/john.jpg"
    },
    "replyToMessage": null,
    "mentionedUser": null
}
```

**Client Example** (Laravel Echo):

```javascript
Echo.private(`class-chat.${classId}`).listen(".message.new", (message) => {
    console.log("New message:", message);
    // Add message to chat UI
});
```

---

### Channel: `instructor-dashboard`

**Event**: `question.answered`

Broadcast when an instructor marks a question as answered.

**Payload**:

```json
{
    "message_id": 125,
    "class_id": 1,
    "answered_by": 50,
    "answered_at": "2026-02-05T11:00:00.000000Z"
}
```

**Client Example**:

```javascript
Echo.channel("instructor-dashboard").listen(".question.answered", (data) => {
    console.log("Question answered:", data);
    // Decrement unanswered question count
});
```

---

## Data Models

### ClassMessage

```typescript
interface ClassMessage {
    id: number;
    class_id: number;
    user_id: number;
    message: string;
    message_type: "discussion" | "question";
    is_answered: boolean;
    answered_by: number | null;
    answered_at: string | null;
    reply_to: number | null;
    mentioned_user_id: number | null;
    image_path: string | null;
    created_at: string;
    updated_at: string;
    user?: User;
    answeredByUser?: User;
    replyToMessage?: ClassMessage;
    mentionedUser?: User;
}
```

### User (simplified)

```typescript
interface User {
    id: number;
    name: string;
    avatar: string | null;
}
```

---

## Error Handling

All API endpoints follow a consistent error response format:

```json
{
    "success": false,
    "message": "Error description in Indonesian",
    "errors": {
        "field_name": ["Validation error message"]
    }
}
```

**Common HTTP Status Codes**:

- `200 OK`: Request successful
- `201 Created`: Resource created successfully
- `401 Unauthorized`: Authentication required or failed
- `403 Forbidden`: User doesn't have permission
- `404 Not Found`: Resource not found
- `422 Unprocessable Entity`: Validation failed
- `500 Internal Server Error`: Server error

---

## Rate Limiting

**Default Rate Limit**: 60 requests per minute per user

**Headers**:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1644067200
```

**Rate Limit Exceeded Response**: `429 Too Many Requests`

```json
{
    "success": false,
    "message": "Too many requests. Please try again later."
}
```

---

## Authentication

The API uses Laravel Sanctum with session-based authentication.

**Login Flow**:

1. Get CSRF cookie: `GET /sanctum/csrf-cookie`
2. Login: `POST /login` with credentials
3. Include credentials with all subsequent requests

**Request Headers**:

```
Accept: application/json
Content-Type: application/json (or multipart/form-data for file uploads)
X-CSRF-TOKEN: <token from cookie>
```

**JavaScript Example**:

```javascript
import axios from "axios";

const api = axios.create({
    baseURL: "https://api.portal.plnip.ac.id/api",
    withCredentials: true,
    headers: {
        Accept: "application/json",
    },
});

// Get CSRF cookie first
await axios.get("https://api.portal.plnip.ac.id/sanctum/csrf-cookie", {
    withCredentials: true,
});

// Then make API calls
const response = await api.get("/classes/1/chat");
```

---

## Image Storage

**Storage Path**: `storage/app/public/class-chat-images/`

**Public URL**: `https://api.portal.plnip.ac.id/storage/class-chat-images/{filename}`

**Allowed Formats**: JPEG, PNG, JPG, GIF

**Maximum Size**: 5MB (5120 KB)

**Image Optimization**: Images are stored as-is. Consider implementing client-side compression or server-side optimization for production.

---

## Testing

### cURL Examples

**Get Messages**:

```bash
curl -X GET 'https://api.portal.plnip.ac.id/api/classes/1/chat' \
  -H 'Accept: application/json' \
  -H 'Cookie: laravel_session=...'
```

**Send Text Message**:

```bash
curl -X POST 'https://api.portal.plnip.ac.id/api/classes/1/chat' \
  -H 'Accept: application/json' \
  -H 'X-CSRF-TOKEN: ...' \
  -H 'Cookie: laravel_session=...' \
  -F 'message=Hello world' \
  -F 'message_type=discussion'
```

**Send Image**:

```bash
curl -X POST 'https://api.portal.plnip.ac.id/api/classes/1/chat' \
  -H 'Accept: application/json' \
  -H 'X-CSRF-TOKEN: ...' \
  -H 'Cookie: laravel_session=...' \
  -F 'message=Check this image' \
  -F 'image=@/path/to/image.jpg'
```

**Mark Question as Answered**:

```bash
curl -X POST 'https://api.portal.plnip.ac.id/api/classes/1/chat/125/mark-answered' \
  -H 'Accept: application/json' \
  -H 'X-CSRF-TOKEN: ...' \
  -H 'Cookie: laravel_session=...'
```

---

## Best Practices

### Client-Side Implementation

1. **Optimistic Updates**: Add messages to UI immediately, update with server response
2. **Error Handling**: Always handle network errors and show user-friendly messages
3. **File Validation**: Validate file size and type before upload
4. **WebSocket Reconnection**: Implement auto-reconnect logic for WebSocket connections
5. **Debouncing**: Debounce typing indicators to reduce server load
6. **Pagination**: Implement infinite scroll for better UX with large chat histories
7. **Image Compression**: Compress images client-side before upload
8. **Offline Support**: Queue messages when offline, send when online

### Server-Side Performance

1. **Queue Workers**: Ensure queue workers are running for broadcasts
2. **Database Indexes**: Index `class_id`, `user_id`, `created_at` on `class_messages` table
3. **Eager Loading**: Always eager load relationships to prevent N+1 queries
4. **Caching**: Cache frequently accessed data (user profiles, class info)
5. **Rate Limiting**: Implement proper rate limiting to prevent abuse
6. **Log Monitoring**: Monitor Laravel logs for errors
7. **WebSocket Scaling**: Use Redis for WebSocket broadcasting in production

---

## Support & Contact

**Documentation**: https://docs.portal.plnip.ac.id

**API Status**: https://status.portal.plnip.ac.id

**Support Email**: support@plnip.ac.id

**Developer Portal**: https://developers.portal.plnip.ac.id

---

## Changelog

### Version 1.0.0 (2026-02-05)

- Initial release
- Text messaging
- Image upload
- Threaded replies with mentions
- Question filtering for instructors
- Real-time statistics dashboard
- WebSocket broadcasting
