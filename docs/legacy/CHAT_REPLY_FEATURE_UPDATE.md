# ✅ WhatsApp-Style Reply Feature - Update

## 🎯 Fitur Baru: Reply Preview yang Lebih Jelas

Sekarang ketika instructor (atau user lain) membalas chat, akan muncul **preview message yang direply** seperti di WhatsApp, sehingga tidak membingungkan.

---

## 📸 UI Components Updated

### 1. **Reply Preview Box (Saat Akan Reply)**

Sebelum mengirim message, user akan melihat preview seperti ini:

```
┌─────────────────────────────────────────┐
│ 🔵 [Avatar] John Doe ❓                 │ ← Avatar + nama user
│    "Pak saya ada kesulitan mengerjakan" │ ← Preview message
│                                      [X] │ ← Tombol cancel
└─────────────────────────────────────────┘
```

**Features:**

- ✅ Avatar user yang direply
- ✅ Nama user dengan warna accent (PLN Primary)
- ✅ Preview message (max 2 lines dengan ellipsis)
- ✅ Icon question jika message type = question
- ✅ Tombol X untuk cancel reply
- ✅ Smooth animation (fade in/out)
- ✅ Border kiri berwarna (seperti WhatsApp)
- ✅ Deteksi gambar: tampilkan "📷 Gambar" jika reply ke image

### 2. **Reply Reference dalam Chat Bubble**

Ketika message sudah terkirim, di dalam bubble akan muncul:

```
┌─────────────────────────────────────────┐
│ ◀ @John Doe                             │ ← Arrow + mention
│ "Pak saya ada kesulitan mengerjakan"    │ ← Original message
│ ─────────────────────────────────────── │
│ Coba kamu baca materi slide 10 dulu     │ ← Reply actual
└─────────────────────────────────────────┘
```

**Features:**

- ✅ Border kiri dengan accent color
- ✅ Arrow icon untuk visual cue
- ✅ @Mention nama user
- ✅ Preview original message (max 2 lines)
- ✅ Berbeda styling untuk own message vs received message
- ✅ Dark mode support
- ✅ Deteksi gambar: tampilkan "📷 Gambar" untuk image messages

---

## 🎨 Styling Details

### Reply Preview Box (Input Area)

```tsx
// Background: Slate 50/800
// Border: Slate 200/700 dengan rounded-xl
// Left accent: PLN Primary (width: 4px)
// Avatar: 32x32px (w-8 h-8)
// Name: PLN Primary color, font-semibold, text-xs
// Message preview: Slate 600/400, text-xs, line-clamp-2
// Cancel button: Hover effect dengan bg-slate-200/700
```

### Reply Reference (Chat Bubble)

```tsx
// Own message (right side):
// - Background: white/20 (transparent white)
// - Border-left: white (4px)
// - Text: white with varying opacity

// Received message (left side):
// - Background: slate-50/800
// - Border-left: PLN Primary/Light (4px)
// - Name: PLN Primary/Light color
// - Message: slate-600/400
```

---

## 🔧 Technical Implementation

### Files Modified:

1. **components/chat/ClassGroupChat.tsx**

### Code Changes:

#### 1. Reply Preview Box (lines ~651-705)

```tsx
{
    replyTo && (
        <motion.div
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -10 }}
            className="mb-3 bg-slate-50 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden"
        >
            <div className="flex items-start gap-3 p-3">
                {/* Left border accent */}
                <div className="w-1 h-full bg-pln-primary rounded-full absolute left-0" />

                {/* Avatar with error handling */}
                <div className="flex-shrink-0 ml-2">
                    {/* Avatar image or initials fallback */}
                </div>

                {/* Reply content */}
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1">
                        <span className="text-xs font-semibold text-pln-primary">
                            {replyTo.user.name}
                        </span>
                        {/* Question icon if applicable */}
                    </div>
                    <p className="text-xs text-slate-600 line-clamp-2">
                        {replyTo.image_path && !replyTo.message.trim()
                            ? "📷 Gambar"
                            : replyTo.message}
                    </p>
                </div>

                {/* Cancel button */}
                <button onClick={cancelReply}>
                    <XMarkIcon className="h-4 w-4" />
                </button>
            </div>
        </motion.div>
    );
}
```

#### 2. Reply Reference in Chat Bubble (lines ~539-558)

```tsx
{
    msg.replyToMessage && (
        <div
            className={`mb-3 p-2.5 rounded-lg border-l-4 ${
                isOwnMessage
                    ? "bg-white/20 border-white"
                    : "bg-slate-50 dark:bg-slate-800 border-pln-primary"
            }`}
        >
            <div className="flex items-center gap-1.5 mb-1">
                <ArrowLeftIcon className="h-3 w-3" />
                <span className="text-xs font-semibold">
                    {msg.mentionedUser
                        ? `@${msg.mentionedUser.name}`
                        : msg.replyToMessage.user?.name}
                </span>
            </div>
            <p className="text-xs line-clamp-2">
                {msg.replyToMessage.image_path &&
                !msg.replyToMessage.message.trim()
                    ? "📷 Gambar"
                    : msg.replyToMessage.message}
            </p>
        </div>
    );
}
```

---

## 🎯 User Experience Flow

### Scenario: Instructor Membalas Pertanyaan Student

1. **Student mengirim pertanyaan:**

    ```
    [Student Avatar] John Doe • 5 menit lalu
    ┌─────────────────────────────────────┐
    │ ❓ PERTANYAAN                       │
    │ Pak saya ada kesulitan mengerjakan  │
    │ tugas halaman 15                    │
    └─────────────────────────────────────┘
    ```

2. **Instructor klik tombol "Balas":**

    ```
    Reply preview muncul di atas input box:
    ┌─────────────────────────────────────┐
    │ 🔵 John Doe ❓                      │
    │    Pak saya ada kesulitan...     [X]│
    └─────────────────────────────────────┘
    [Input box untuk menulis reply]
    ```

3. **Instructor menulis dan kirim reply:**

    ```
    [Instructor Avatar] Pak Faqih • Baru saja
    ┌─────────────────────────────────────┐
    │ ◀ @John Doe                         │
    │ Pak saya ada kesulitan mengerjakan  │
    │ ───────────────────────────────────│
    │ Coba kamu baca materi slide 10      │
    │ dulu, ada contoh yang mirip         │
    └─────────────────────────────────────┘
    ```

4. **Student melihat reply:**
    ```
    Student melihat message dengan context jelas:
    - Tahu message mana yang direply
    - Tahu siapa yang di-mention (@John Doe)
    - Bisa langsung scroll ke original message
    ```

---

## ✨ Key Improvements

### Before ❌

```
Instructor reply tanpa context:
┌─────────────────────────────────────┐
│ Coba kamu baca materi slide 10 dulu │
└─────────────────────────────────────┘
```

**Problem:** Student bingung, "Reply ke pertanyaan yang mana?"

### After ✅

```
┌─────────────────────────────────────┐
│ ◀ @John Doe                         │
│ Pak saya ada kesulitan mengerjakan  │
│ ───────────────────────────────────│
│ Coba kamu baca materi slide 10 dulu │
└─────────────────────────────────────┘
```

**Solution:** Context jelas, tidak bikin bingung!

---

## 🔍 Edge Cases Handled

### 1. **Reply to Image-only Message**

```tsx
{
    msg.replyToMessage.image_path && !msg.replyToMessage.message.trim()
        ? "📷 Gambar"
        : msg.replyToMessage.message;
}
```

Preview shows "📷 Gambar" instead of empty text.

### 2. **Long Message Preview**

```tsx
className = "text-xs line-clamp-2";
```

Message preview limited to 2 lines with ellipsis.

### 3. **Avatar Error Fallback**

```tsx
onError={(e) => {
  // Show initials instead
  parent.innerHTML = `<div class="...">${getInitials(name)}</div>`;
}}
```

If avatar image fails to load, show initials.

### 4. **Dark Mode Support**

```tsx
className = "dark:bg-slate-800 dark:text-slate-400";
```

All components have dark mode variants.

### 5. **Cancel Reply Anytime**

```tsx
<button onClick={cancelReply}>
    <XMarkIcon />
</button>
```

User can cancel reply before sending.

---

## 📊 Component Hierarchy

```
ClassGroupChat
│
├── Chat Container
│   └── Messages List
│       └── Message Item
│           ├── Avatar
│           ├── Message Bubble
│           │   ├── Question Badge (if question)
│           │   ├── Reply Reference ⭐ NEW IMPROVED
│           │   ├── Image Attachment
│           │   ├── Message Text
│           │   └── Answered Info
│           └── Action Buttons
│               └── Reply Button
│
└── Input Area
    ├── Reply Preview Box ⭐ NEW IMPROVED
    ├── Image Preview
    ├── Image Upload Button
    ├── Text Input
    └── Send Button
```

---

## 🎨 Color Scheme

### Light Mode

- **Reply box background**: `bg-slate-50` (#F8FAFC)
- **Border**: `border-slate-200` (#E2E8F0)
- **Accent border**: `border-pln-primary` (PLN Blue)
- **Name text**: `text-pln-primary`
- **Message text**: `text-slate-600` (#475569)

### Dark Mode

- **Reply box background**: `dark:bg-slate-800` (#1E293B)
- **Border**: `dark:border-slate-700` (#334155)
- **Accent border**: `dark:border-pln-light` (PLN Light Blue)
- **Name text**: `dark:text-pln-light`
- **Message text**: `dark:text-slate-400` (#94A3B8)

---

## ✅ Testing Checklist

- [x] Reply preview muncul saat klik "Balas"
- [x] Avatar user yang direply tampil dengan benar
- [x] Nama user tampil dengan benar
- [x] Preview message tampil (max 2 lines)
- [x] Cancel button berfungsi
- [x] Reply reference tampil di chat bubble
- [x] @Mention tampil dengan benar
- [x] Border kiri tampil dengan warna accent
- [x] Dark mode berfungsi dengan baik
- [x] Image-only messages show "📷 Gambar"
- [x] Long messages truncated dengan ellipsis
- [x] Avatar error fallback ke initials
- [x] Smooth animation saat show/hide
- [x] Responsive di mobile devices
- [x] No TypeScript errors
- [x] No console errors

---

## 🚀 Ready for Production

Status: **✅ PRODUCTION READY**

Fitur reply sudah lengkap dan mirip WhatsApp:

- ✅ Visual yang jelas dan tidak membingungkan
- ✅ Context preservation (tahu reply ke message mana)
- ✅ Smooth UX dengan animation
- ✅ Dark mode support
- ✅ Error handling lengkap
- ✅ Responsive design
- ✅ Accessibility friendly

---

**Last Updated**: February 5, 2026
**Version**: 1.1.0
**Feature**: WhatsApp-Style Reply Enhancement ✨
