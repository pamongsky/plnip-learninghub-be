# 💬 Chat Grup Kelas - Konsep & Implementasi

## 📋 Overview Fitur

Chat Grup Kelas adalah fitur **real-time messaging** untuk diskusi antara:

- **User (Peserta)** ↔️ **Instructor (Pengajar)** ↔️ **Peserta Lain**

Dalam satu kelas yang sama, semua member bisa diskusi seperti WhatsApp Group.

---

## 🎯 Konsep Utama

### **1. Dual Message Type (Dua Jenis Pesan)**

#### **A. Discussion (Diskusi Biasa)** 💬

- Chat biasa seperti WhatsApp
- Untuk diskusi umum, berbagi info, tanya jawab ringan
- Bubble warna putih (light mode) / dark (dark mode)
- Tidak perlu dijawab khusus

#### **B. Question (Pertanyaan Penting)** ❓

- **Fitur Khusus untuk Pertanyaan ke Instructor**
- Bubble warna **kuning/amber** (highlight berbeda)
- Badge "PERTANYAAN" di atas pesan
- **Auto-notify instructor** (push notification)
- **Instructor bisa mark as "Terjawab"**
- Tracking: Berapa pertanyaan belum dijawab
- User bisa lihat apakah pertanyaannya sudah ditanggapi

**Kenapa Ada 2 Jenis?**

- Agar pertanyaan penting tidak tenggelam di chat ramai
- Instructor bisa prioritas jawab pertanyaan urgent
- Analytics: berapa pertanyaan terjawab vs belum

---

### **2. Real-Time Broadcasting** ⚡

**Tech Stack:**

- **Laravel Broadcasting** (backend)
- **Laravel Echo + Reverb** (WebSocket server)
- **React Hooks** (frontend subscription)

**Flow:**

```
User A mengirim pesan
    ↓
Backend: ClassChatController::store()
    ↓
Save to database (class_messages table)
    ↓
Broadcast Event: NewClassMessage
    ↓
Reverb Server (WebSocket)
    ↓
Private Channel: "class-chat.{classId}"
    ↓
Frontend: useClassChatChannel() hook
    ↓
User B, C, Instructor → Langsung terima pesan (no reload!)
```

**Channel Naming:**

- `private-class-chat.123` untuk Class ID 123
- Only enrolled students + instructor dapat akses
- Laravel otomatis check authorization via `broadcasting/auth`

---

### **3. Reply to Message (Balas Pesan)** 🔁

**Fitur seperti WhatsApp:**

- Klik "Balas" di message
- Input area shows reference pesan yang dibalas
- User bisa lihat context: "Membalas John: ..."
- Visual: Box kecil di atas message bubble

**Use Case:**

- Diskusi panjang dengan banyak topik
- Jelas siapa balas siapa
- Thread conversation tracking

**Database:**

- Field `reply_to` (nullable) → ID message yang dibalas
- Frontend load relationship untuk show quoted message

---

### **4. Mark as Answered (Instructor Only)** ✅

**Fitur Khusus Instructor:**

Ketika user kirim Question:

1. Message type = "question"
2. `is_answered` = false (default)
3. Instructor lihat badge "PERTANYAAN" (warna kuning)
4. Instructor klik button "Tandai Terjawab"
5. Backend update:
    ```php
    is_answered = true
    answered_by = instructor_id
    answered_at = now()
    ```
6. Visual berubah:
    - Badge hijau "✓ Terjawab"
    - Text "Dijawab oleh Pak Budi"
    - Button "Tandai Terjawab" hilang

**Analytics:**

- Berapa pertanyaan belum dijawab (for instructor dashboard)
- Response time tracking
- Question quality metrics

---

## 🗄️ Database Schema

### **Table: `class_messages`**

```sql
CREATE TABLE class_messages (
    id BIGINT PRIMARY KEY,
    class_id BIGINT NOT NULL,           -- Foreign key ke courses table
    user_id BIGINT NOT NULL,            -- Siapa yang kirim pesan
    message TEXT NOT NULL,              -- Isi pesan (max 2000 chars)
    message_type ENUM('discussion', 'question') DEFAULT 'discussion',
    is_answered BOOLEAN DEFAULT false,  -- Khusus untuk question
    answered_by BIGINT NULLABLE,        -- Instructor yang jawab
    answered_at TIMESTAMP NULLABLE,     -- Kapan dijawab
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    -- Indexes untuk performance
    INDEX idx_class_created (class_id, created_at),
    INDEX idx_class_type (class_id, message_type),
    INDEX idx_questions (class_id, message_type, is_answered)
);
```

**Why These Indexes?**

- `idx_class_created`: Load messages sorted by time (pagination)
- `idx_class_type`: Filter by discussion/question
- `idx_questions`: Get unanswered questions (instructor dashboard)

**Sample Data:**

```json
{
    "id": 1,
    "class_id": 45,
    "user_id": 123,
    "message": "Pak, deadline tugas bisa diperpanjang?",
    "message_type": "question",
    "is_answered": true,
    "answered_by": 99,
    "answered_at": "2026-02-04 14:30:00",
    "created_at": "2026-02-04 10:15:00"
}
```

---

## 🔧 Backend Implementation

### **1. Model: ClassMessage.php**

```php
class ClassMessage extends Model
{
    // Relationships
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function answeredByUser(): BelongsTo {
        return $this->belongsTo(User::class, 'answered_by');
    }

    // Scopes for filtering
    public function scopeQuestions($query) {
        return $query->where('message_type', 'question');
    }

    public function scopeUnanswered($query) {
        return $query->where('message_type', 'question')
                     ->where('is_answered', false);
    }

    public function scopeForClass($query, $classId) {
        return $query->where('class_id', $classId);
    }

    public function scopeToday($query) {
        return $query->whereDate('created_at', today());
    }
}
```

**Query Examples:**

```php
// Get unanswered questions for class 45
ClassMessage::forClass(45)->unanswered()->get();

// Get today's questions for instructor
ClassMessage::whereIn('class_id', $instructorClassIds)
    ->questions()
    ->today()
    ->count();
```

---

### **2. Controller: ClassChatController.php**

#### **Endpoint 1: Get Messages (Pagination)**

```php
GET /api/classes/{classId}/chat

Response:
{
  "success": true,
  "data": {
    "data": [ /* messages array */ ],
    "current_page": 1,
    "per_page": 50,
    "total": 245
  }
}
```

**Features:**

- Load 50 messages per page (reverse order, newest first)
- Includes user data (`user.name`, `user.avatar`)
- Includes answered_by data for questions

---

#### **Endpoint 2: Send Message**

```php
POST /api/classes/{classId}/chat
Body: {
  "message": "Halo semua!",
  "message_type": "discussion" // or "question"
}

Response:
{
  "success": true,
  "data": {
    "id": 246,
    "message": "Halo semua!",
    "user": { "id": 123, "name": "John" },
    "created_at": "2026-02-04 15:00:00"
  }
}
```

**Backend Flow:**

1. Validate input (max 2000 chars)
2. Create message in database
3. **Broadcast to WebSocket** → `NewClassMessage` event
4. Return message data to sender
5. Other users receive via real-time channel

---

#### **Endpoint 3: Mark as Answered (Instructor)**

```php
PATCH /api/classes/{classId}/chat/{messageId}/answered

Response:
{
  "success": true,
  "data": {
    "id": 246,
    "is_answered": true,
    "answered_by": 99,
    "answered_at": "2026-02-04 15:05:00",
    "answeredByUser": { "id": 99, "name": "Pak Budi" }
  }
}
```

**Validation:**

- Only instructor can mark as answered
- Only message_type = "question" can be marked
- Update timestamp + instructor ID

---

#### **Endpoint 4: Get Questions (Instructor View)**

```php
GET /api/classes/{classId}/chat/questions

Response:
{
  "success": true,
  "data": {
    "data": [
      {
        "id": 240,
        "message": "Pak, deadline bisa diperpanjang?",
        "is_answered": false,
        "user": { "name": "John" },
        "created_at": "2026-02-04 10:00:00"
      },
      {
        "id": 235,
        "message": "Materi minggu depan apa Pak?",
        "is_answered": true,
        "answeredByUser": { "name": "Pak Budi" },
        "created_at": "2026-02-03 14:00:00"
      }
    ]
  }
}
```

**Sorting:**

- Unanswered first (priority)
- Then by created_at DESC

---

#### **Endpoint 5: Question Stats (Instructor Dashboard)**

```php
GET /api/instructor/question-stats

Response:
{
  "success": true,
  "data": {
    "today": 12,           // Questions today
    "unanswered": 5,       // Need attention
    "total": 340           // All-time questions
  }
}
```

**Use Case:**

- Instructor dashboard widget
- "Anda punya 5 pertanyaan belum dijawab" → link ke questions page

---

### **3. Broadcasting Event: NewClassMessage**

```php
class NewClassMessage implements ShouldBroadcast
{
    public ClassMessage $message;

    public function broadcastOn(): array {
        return [
            new PrivateChannel('class-chat.' . $this->message->class_id)
        ];
    }

    public function broadcastAs(): string {
        return 'message.new';
    }

    public function broadcastWith(): array {
        return [
            'id' => $this->message->id,
            'class_id' => $this->message->class_id,
            'user_id' => $this->message->user_id,
            'message' => $this->message->message,
            'message_type' => $this->message->message_type,
            'is_answered' => $this->message->is_answered,
            'created_at' => $this->message->created_at,
            'user' => [
                'id' => $this->message->user->id,
                'name' => $this->message->user->name,
                'avatar' => $this->message->user->avatar,
            ]
        ];
    }
}
```

**Broadcasting Flow:**

1. Event dispatched after message saved
2. Reverb server receives event
3. Broadcast to channel `private-class-chat.{classId}`
4. All connected clients receive via WebSocket
5. Frontend hook updates UI

**Authorization:**

```php
// routes/channels.php
Broadcast::channel('class-chat.{classId}', function ($user, $classId) {
    // Check if user is enrolled in this class
    return CourseEnrollment::where('user_id', $user->id)
        ->where('course_id', $classId)
        ->exists();
});
```

---

## 🎨 Frontend Implementation

### **1. React Component: ClassGroupChat.tsx**

**Props:**

```typescript
interface ClassGroupChatProps {
    classId: number; // ID kelas
    currentUserId: number; // ID user yang login
    isInstructor?: boolean; // Apakah user adalah instructor
    onQuestionCountChange?: (count: number) => void; // Callback untuk update badge
}
```

**State Management:**

```typescript
const [messages, setMessages] = useState<Message[]>([]);
const [newMessage, setNewMessage] = useState("");
const [messageType, setMessageType] = useState<"discussion" | "question">(
    "discussion",
);
const [replyTo, setReplyTo] = useState<Message | null>(null);
const [isSending, setIsSending] = useState(false);
```

---

### **2. Real-Time Hook: useClassChatChannel**

```typescript
// hooks/useRealTimeMessages.ts

export function useClassChatChannel(
    classId: number | null,
    onNewMessage: (data: ClassMessageData) => void,
) {
    useEffect(() => {
        if (!classId) return;

        const echo = getEcho();
        if (!echo) return;

        // Subscribe to private channel
        const channel = echo.private(`class-chat.${classId}`);

        // Listen for new messages
        channel.listen(".message.new", (data: ClassMessageData) => {
            onNewMessage(data);
        });

        // Cleanup
        return () => {
            echo.leave(`class-chat.${classId}`);
        };
    }, [classId]);
}
```

**Usage in Component:**

```typescript
const handleNewClassMessage = useCallback(
    (data: any) => {
        // Avoid duplicates (sender already has message from API response)
        if (data.user_id !== currentUserId) {
            const newMsg: Message = {
                id: data.id,
                message: data.message,
                user: data.user,
                created_at: data.created_at,
                // ...
            };
            setMessages((prev) => [...prev, newMsg]);
        }
    },
    [currentUserId],
);

// Subscribe
useClassChatChannel(classId, handleNewClassMessage);
```

**How It Works:**

1. Component mounts → `useClassChatChannel()` subscribes to channel
2. User B sends message → Backend broadcasts event
3. User A's browser receives via WebSocket
4. Hook calls `onNewMessage(data)`
5. Component adds message to state
6. UI updates instantly (no page reload!)
7. Component unmounts → Auto-unsubscribe

---

### **3. Message UI Design**

#### **Message Bubble Structure:**

```
┌──────────────────────────────────────────────┐
│  👤 Avatar                                    │
│     ┌──────────────────────────────────────┐ │
│     │ John Doe  •  5 menit lalu            │ │
│     ├──────────────────────────────────────┤ │
│     │ [Badge: PERTANYAAN] ✓ Terjawab       │ │ ← Only for questions
│     ├──────────────────────────────────────┤ │
│     │ Pak, deadline tugas bisa              │ │
│     │ diperpanjang? Ada kendala di          │ │
│     │ jaringan kemarin.                     │ │
│     ├──────────────────────────────────────┤ │
│     │ Dijawab oleh Pak Budi                │ │ ← Only if answered
│     └──────────────────────────────────────┘ │
│     [Balas] [Tandai Terjawab]               │ ← Action buttons
└──────────────────────────────────────────────┘
```

**Color Coding:**

- **Own messages** (sent by current user):
    - Right-aligned
    - Blue bubble (`bg-pln-primary`)
    - White text
- **Others' Discussion messages**:
    - Left-aligned
    - White bubble with shadow
    - Dark text

- **Others' Question messages**:
    - Left-aligned
    - **Yellow/amber bubble** (`bg-amber-50`, `border-amber-200`)
    - Amber badge icon
    - Dark text

- **Answered Questions**:
    - Yellow bubble + green checkmark
    - "Dijawab oleh {name}" text

---

### **4. Message Type Selector**

```
┌──────────────────────────────────────┐
│  [💬 Diskusi]  [❓ Pertanyaan]        │ ← Toggle buttons
├──────────────────────────────────────┤
│  [Textarea input]                    │
│                                      │
│  [Send Button →]                     │
└──────────────────────────────────────┘
```

**Logic:**

- Click "Diskusi" → `messageType = "discussion"` (default)
- Click "Pertanyaan" → `messageType = "question"`
- Placeholder text changes:
    - Diskusi: "Tulis pesan diskusi..."
    - Pertanyaan: "Tulis pertanyaan untuk instruktur..."
- Visual indicator: Yellow text when Question mode active

---

### **5. Reply Feature UI**

**When User Clicks "Balas":**

```
┌───────────────────────────────────────┐
│  ↩️ Membalas John Doe  [✕]            │ ← Blue box
│  "Pak, deadline bisa diperpanjang?"   │
├───────────────────────────────────────┤
│  [Textarea: "Saya setuju, saya juga   │
│   ada kendala..."]                    │
│  [Send →]                             │
└───────────────────────────────────────┘
```

**Implementation:**

```typescript
const handleReply = (message: Message) => {
    setReplyTo(message); // Store reference
    // Auto-focus textarea
    document.querySelector("textarea")?.focus();
};

const cancelReply = () => {
    setReplyTo(null);
};
```

**Message Display with Reply:**

```
┌───────────────────────────────────────┐
│ Membalas John Doe                      │ ← Gray box
│ "Pak, deadline bisa diperpanjang?"     │
├───────────────────────────────────────┤
│ Saya setuju, saya juga ada kendala...  │ ← Main message
└───────────────────────────────────────┘
```

---

### **6. Auto-Scroll Behavior**

```typescript
const messagesEndRef = useRef<HTMLDivElement>(null);

// Auto-scroll when new message arrives
useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
}, [messages]);
```

**Invisible div at bottom:**

```tsx
{messages.map(msg => <MessageBubble />)}
<div ref={messagesEndRef} /> {/* Scroll anchor */}
```

---

## 🔐 Authorization & Security

### **1. Channel Authorization**

**routes/channels.php:**

```php
Broadcast::channel('class-chat.{classId}', function ($user, $classId) {
    // Only enrolled students + instructor can join
    $enrollment = CourseEnrollment::where('user_id', $user->id)
        ->where('course_id', $classId)
        ->first();

    $isInstructor = Course::where('id', $classId)
        ->where('instructor_id', $user->id)
        ->exists();

    return $enrollment || $isInstructor;
});
```

**Why Private Channel?**

- **Public channel**: Anyone can listen (insecure!)
- **Private channel**: Laravel checks authorization
- Prevents unauthorized users reading class messages

---

### **2. API Middleware**

```php
// routes/api.php
Route::prefix('classes/{classId}/chat')->group(function () {
    Route::get('/', [ClassChatController::class, 'index']);
    Route::post('/', [ClassChatController::class, 'store']);
    // ...
})->middleware(['auth:sanctum', 'enrolled.in.class']);
```

**Custom Middleware: `enrolled.in.class`**

```php
public function handle(Request $request, Closure $next) {
    $classId = $request->route('classId');
    $user = $request->user();

    $enrolled = CourseEnrollment::where('user_id', $user->id)
        ->where('course_id', $classId)
        ->exists();

    if (!$enrolled && !$user->hasRole('instructor')) {
        return response()->json(['error' => 'Not enrolled'], 403);
    }

    return $next($request);
}
```

---

### **3. Input Validation**

**ClassChatController:**

```php
$validated = $request->validate([
    'message' => 'required|string|max:2000',  // Prevent spam
    'message_type' => 'in:discussion,question',
]);
```

**XSS Prevention:**

```php
// Frontend: Display message as plain text, not HTML
<p className="whitespace-pre-wrap">{msg.message}</p>

// Backend: Laravel auto-escapes, but you can also:
$message = strip_tags($request->message);
```

---

## 🎯 User Flow Examples

### **Flow 1: User Mengirim Diskusi Biasa**

1. User buka halaman `/dashboard/classes/45/chat`
2. Pilih "💬 Diskusi" (default)
3. Ketik: "Halo teman-teman, ada yang bisa bantu?"
4. Klik Send (atau Enter)
5. **Frontend:**
    - API call POST `/api/classes/45/chat`
    - Message muncul di bubble (right-aligned, blue)
6. **Backend:**
    - Save ke database
    - Broadcast `NewClassMessage` event
7. **Other Users:**
    - WebSocket receives event
    - Message muncul real-time (left-aligned, white bubble)
    - No page reload!

---

### **Flow 2: User Mengirim Pertanyaan ke Instructor**

1. User pilih "❓ Pertanyaan"
2. Ketik: "Pak, deadline tugas bisa diperpanjang?"
3. Klik Send
4. **Frontend:**
    - Message muncul dengan **yellow bubble** + badge "PERTANYAAN"
5. **Backend:**
    - `message_type = "question"`
    - Save dengan `is_answered = false`
    - Broadcast event
    - **(Future)** Send push notification ke instructor
6. **Instructor:**
    - Lihat message dengan highlight kuning
    - Dashboard shows: "5 pertanyaan belum dijawab"
    - Bisa klik "Tandai Terjawab" setelah respond

---

### **Flow 3: Instructor Menjawab Pertanyaan**

1. Instructor lihat question (yellow bubble)
2. Ketik reply (discussion message)
3. Klik "Tandai Terjawab" button
4. **Frontend:**
    - API call PATCH `/api/classes/45/chat/240/answered`
    - Badge berubah jadi "✓ Terjawab" (green)
    - Text "Dijawab oleh Pak Budi" muncul
    - Button "Tandai Terjawab" hilang
5. **Backend:**
    - Update `is_answered = true`
    - `answered_by = instructor_id`
    - `answered_at = now()`
6. **User (Penanya):**
    - Lihat status update real-time
    - Tahu pertanyaan sudah ditanggapi

---

### **Flow 4: Reply to Message**

1. User A kirim: "Ada yang punya catatan materi minggu lalu?"
2. User B klik "Balas" di message User A
3. Blue box muncul: "Membalas User A: Ada yang punya catatan..."
4. User B ketik: "Saya punya, nanti saya share"
5. Send → Message dengan reply reference
6. Visual: Gray box quote + main message di bawah
7. Thread conversation clear!

---

## 📊 Performance Considerations

### **1. Pagination Strategy**

**Why Pagination?**

- Class dengan 1000+ messages → Load semua = slow!
- Solution: Load 50 latest, load more on scroll

**Implementation:**

```typescript
const [messages, setMessages] = useState<Message[]>([]);
const [currentPage, setCurrentPage] = useState(1);
const [hasMore, setHasMore] = useState(true);

const loadMore = async () => {
    const response = await api.get(
        `/classes/${classId}/chat?page=${currentPage + 1}`,
    );
    setMessages((prev) => [...response.data.data, ...prev]); // Prepend older messages
    setCurrentPage((prev) => prev + 1);
    setHasMore(response.data.current_page < response.data.last_page);
};
```

**Infinite Scroll:**

```typescript
const handleScroll = (e) => {
    if (e.target.scrollTop === 0 && hasMore && !isLoading) {
        loadMore();
    }
};
```

---

### **2. Database Indexing**

**Critical Indexes:**

```sql
-- Load messages for class (sorted by time)
INDEX idx_class_created (class_id, created_at);

-- Filter questions vs discussions
INDEX idx_class_type (class_id, message_type);

-- Find unanswered questions (instructor dashboard)
INDEX idx_questions (class_id, message_type, is_answered);
```

**Query Performance:**

```sql
-- Without index: Full table scan (slow!)
-- With index: Index scan (fast!)

EXPLAIN SELECT * FROM class_messages
WHERE class_id = 45
  AND message_type = 'question'
  AND is_answered = false
ORDER BY created_at DESC;

-- Result: Uses idx_questions (Good!)
```

---

### **3. Real-Time Connection Management**

**Challenge:**

- 100 users in class → 100 WebSocket connections
- Server resource intensive!

**Solutions:**

**A. Connection Pooling (Reverb built-in)**

- Reverb handles multiple connections efficiently
- Horizontal scaling ready

**B. Fallback to Polling (if WebSocket fails)**

```typescript
const [isConnected, setIsConnected] = useState(false);

useEffect(() => {
    const echo = getEcho();
    if (!echo) {
        // Fallback: Poll every 5 seconds
        const interval = setInterval(() => {
            loadNewMessages();
        }, 5000);
        return () => clearInterval(interval);
    }
}, []);
```

**C. Auto-Reconnect on Disconnect**

- Laravel Echo handles reconnection automatically
- If network drops, Echo tries to reconnect
- Messages queued during disconnect

---

### **4. Message Caching**

**Frontend Cache:**

```typescript
// Cache messages in memory
const messageCache = useRef<Map<number, Message>>(new Map());

const addMessage = (msg: Message) => {
    if (!messageCache.current.has(msg.id)) {
        messageCache.current.set(msg.id, msg);
        setMessages((prev) => [...prev, msg]);
    }
};
```

**Backend Cache (Redis):**

```php
// Cache recent messages for 5 minutes
$messages = Cache::remember("class_{$classId}_messages", 300, function () use ($classId) {
    return ClassMessage::forClass($classId)
        ->with('user')
        ->orderBy('created_at', 'desc')
        ->limit(50)
        ->get();
});
```

---

## 🚀 Advanced Features (Future Enhancement)

### **1. File Attachments** 📎

```sql
ALTER TABLE class_messages ADD COLUMN attachment_path VARCHAR(255) NULLABLE;
ALTER TABLE class_messages ADD COLUMN attachment_type VARCHAR(50) NULLABLE;
```

**UI:**

- Paperclip icon next to Send button
- Upload image/PDF/docs
- Preview in chat bubble

---

### **2. Typing Indicators** ⌨️

```typescript
// Broadcast when user is typing
echo.private(`class-chat.${classId}`).whisper("typing", { name: user.name });

// Listen for typing events
channel.listenForWhisper("typing", (e) => {
    setTypingUsers((prev) => [...prev, e.name]);
    // Show: "John is typing..."
});
```

---

### **3. Read Receipts** ✓✓

```sql
CREATE TABLE message_reads (
    id BIGINT PRIMARY KEY,
    message_id BIGINT,
    user_id BIGINT,
    read_at TIMESTAMP,
    INDEX (message_id, user_id)
);
```

**UI:**

- Blue checkmark ✓ (sent)
- Double blue checkmark ✓✓ (read)

---

### **4. Emoji Reactions** 😀👍❤️

```sql
CREATE TABLE message_reactions (
    id BIGINT PRIMARY KEY,
    message_id BIGINT,
    user_id BIGINT,
    emoji VARCHAR(10),
    INDEX (message_id)
);
```

**UI:**

- Emoji picker below message
- Count reactions: "👍 5 ❤️ 3"

---

### **5. Search Messages** 🔍

```typescript
const searchMessages = async (query: string) => {
    const response = await api.get(`/classes/${classId}/chat/search`, {
        params: { q: query },
    });
    setSearchResults(response.data);
};
```

**Backend:**

```php
ClassMessage::forClass($classId)
    ->where('message', 'LIKE', "%{$query}%")
    ->get();
```

---

### **6. Pin Important Messages** 📌

```sql
ALTER TABLE class_messages ADD COLUMN is_pinned BOOLEAN DEFAULT false;
```

**UI:**

- Pin icon button
- Show pinned messages at top
- Max 3 pinned messages

---

## 📱 Mobile Responsiveness

**Current Implementation:**

- Full responsive design
- Mobile: Stack vertically, full width bubbles
- Tablet: Side-by-side layout possible
- Desktop: Max width 75% per bubble

**Optimization for Mobile:**

```css
/* Mobile */
@media (max-width: 768px) {
    .message-bubble {
        max-width: 90%; /* Wider on mobile */
        font-size: 14px;
    }

    .avatar {
        width: 32px; /* Smaller avatar */
        height: 32px;
    }
}
```

---

## 🧪 Testing Scenarios

### **Unit Tests**

**Backend:**

```php
public function test_user_can_send_discussion_message() {
    $user = User::factory()->create();
    $class = Course::factory()->create();
    CourseEnrollment::create(['user_id' => $user->id, 'course_id' => $class->id]);

    $response = $this->actingAs($user)
        ->postJson("/api/classes/{$class->id}/chat", [
            'message' => 'Hello class!',
            'message_type' => 'discussion'
        ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('class_messages', [
        'class_id' => $class->id,
        'user_id' => $user->id,
        'message' => 'Hello class!',
    ]);
}

public function test_instructor_can_mark_question_as_answered() {
    $instructor = User::factory()->create();
    $message = ClassMessage::factory()->create(['message_type' => 'question']);

    $response = $this->actingAs($instructor)
        ->patchJson("/api/classes/{$message->class_id}/chat/{$message->id}/answered");

    $response->assertStatus(200);
    $this->assertTrue($message->fresh()->is_answered);
}
```

---

### **Integration Tests**

**Real-Time Broadcasting:**

```php
Event::fake();

$this->actingAs($user)
    ->postJson("/api/classes/45/chat", ['message' => 'Test']);

Event::assertDispatched(NewClassMessage::class);
```

---

### **Frontend Tests (Jest + React Testing Library)**

```typescript
test('renders message with question badge', () => {
  const message = {
    id: 1,
    message: 'Test question?',
    message_type: 'question',
    is_answered: false,
    user: { name: 'John' }
  };

  render(<MessageBubble message={message} />);

  expect(screen.getByText('PERTANYAAN')).toBeInTheDocument();
  expect(screen.getByText('Test question?')).toBeInTheDocument();
});
```

---

## 📈 Analytics & Metrics

**Instructor Dashboard Metrics:**

```php
// Average response time
$avgResponseTime = ClassMessage::forClass($classId)
    ->questions()
    ->whereNotNull('answered_at')
    ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, answered_at)) as avg_mins')
    ->value('avg_mins');

// Question answer rate
$totalQuestions = ClassMessage::forClass($classId)->questions()->count();
$answeredQuestions = ClassMessage::forClass($classId)->questions()->where('is_answered', true)->count();
$answerRate = ($answeredQuestions / $totalQuestions) * 100;
```

**Engagement Metrics:**

```php
// Most active students (by message count)
$topParticipants = ClassMessage::forClass($classId)
    ->select('user_id', DB::raw('COUNT(*) as message_count'))
    ->groupBy('user_id')
    ->orderByDesc('message_count')
    ->limit(10)
    ->with('user')
    ->get();
```

---

## 🎓 Summary Konsep

### **Key Points:**

1. **Dual Message Type**
    - Discussion (💬) = Chat biasa
    - Question (❓) = Highlight, tracking, notify instructor

2. **Real-Time Tech**
    - Laravel Broadcasting + Reverb
    - WebSocket for instant updates
    - No page reload needed

3. **Reply Feature**
    - Thread conversation
    - Context clear
    - Like WhatsApp

4. **Instructor Tools**
    - Mark as Answered
    - Question stats
    - Response tracking

5. **Security**
    - Private channels (authorization required)
    - Middleware checks enrollment
    - Input validation

6. **Performance**
    - Pagination (50 messages/page)
    - Database indexing
    - Caching strategy

7. **UX Design**
    - Color-coded bubbles
    - Auto-scroll
    - Loading states
    - Mobile responsive

---

**Status:** ✅ **PRODUCTION READY**  
**Routes:** `/dashboard/classes/{id}/chat` (User), `/instructor/classes/{id}` (Instructor has same component)  
**Backend:** 7 API endpoints, Real-time broadcasting  
**Frontend:** React component with WebSocket integration

Konsep ini gabungan **WhatsApp Group Chat** + **Classroom Q&A System** dengan real-time capabilities! 🚀
