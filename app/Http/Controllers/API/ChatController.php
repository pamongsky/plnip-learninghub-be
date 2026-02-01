<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\ChatSession;
use App\Models\ChatMessage;

class ChatController extends Controller
{
    /**
     * Handle Chat with Gemini (With Memory/Context)
     */
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'session_id' => 'nullable|integer',
            'attachment' => 'nullable|file|max:5120' // Max 5MB, optional
        ]);

        $user = Auth::user();
        $userId = $user ? $user->id : 1; 

        $userMessage = $request->input('message');
        $sessionId = $request->input('session_id');

        // 1. Manage Session
        if ($sessionId) {
            $session = ChatSession::where('id', $sessionId)->where('user_id', $userId)->first();
        } 
        
        if (!isset($session)) {
            $session = ChatSession::firstOrCreate(
                ['user_id' => $userId, 'title' => 'New Chat'],
                ['title' => 'New Chat']
            );
        }

        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            return response()->json(['message' => 'API Key not configured'], 500);
        }

        try {
            // 2. Save User Message FIRST (so we have ID for attachments)
            $userChatMsg = ChatMessage::create([
                'chat_session_id' => $session->id,
                'role' => 'user',
                'content' => $userMessage
            ]);

            // 3. Handle Attachment
            $inlineData = null;
            $attachmentData = null;

            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $path = $file->store('chat_attachments', 'public');
                
                // Save to DB
                $attachmentData = \App\Models\ChatAttachment::create([
                    'chat_message_id' => $userChatMsg->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize()
                ]);

                // Prepare for Gemini
                // Note: simplified for images. For docs, more complex logic needed (PDF text extraction etc)
                // But Gemini 2.5 Flash supports image inputs via inline_data.
                // For PDF, we can try inline_data application/pdf if supported, or just text.
                
                $mimeType = $file->getMimeType();
                $base64Data = base64_encode(file_get_contents($file->getRealPath()));
                
                $inlineData = [
                    "mime_type" => $mimeType,
                    "data" => $base64Data
                ];
            }

            // 4. Build Context (History)
            // Retrieve messages with attachments (if any)
            $history = $session->messages()
                ->with('attachments')
                ->where('id', '<', $userChatMsg->id)
                ->orderBy('created_at', 'desc') // Get latest messages
                ->take(10)
                ->get()
                ->sortBy('created_at'); // Sort chronological for AI context

            $contents = [];
            
            // Add history
            foreach ($history as $msg) {
                // If msg has attachment, we might need to include it in history or just skip it to save tokens/cost
                // Ideally we include it. But sending back full images in history is costly.
                // For now, let's keep text only in history context to be safe and fast.
                $contents[] = [
                    "role" => $msg->role,
                    "parts" => [["text" => $msg->content]]
                ];
            }

            // Add Current Message (Text + Inline Data)
            $currentParts = [
                ["text" => $userMessage]
            ];
            if ($inlineData) {
                $currentParts[] = ["inline_data" => $inlineData];
            }

            $contents[] = [
                "role" => "user",
                "parts" => $currentParts
            ];

            // 5. Call Gemini API
            // 5. Call Gemini API
            // Using gemini-2.0-flash-lite (Lightweight & Fast)
            $model = "gemini-2.0-flash-lite";
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            
            // Prepend System Instruction
            array_unshift($contents, [
                "role" => "user",
                "parts" => [["text" => "System Instruction: Kamu adalah asisten AI dari PLN Indonesia Power yang ramah, cerdas, dan membantu."]]
            ], [
                "role" => "model",
                "parts" => [["text" => "Baik, saya mengerti. Saya siap membantu."]]
            ]);

            $payload = [
                "contents" => $contents,
                "generationConfig" => [
                    "temperature" => 0.7,
                    "maxOutputTokens" => 2048,
                ]
            ];

            // Retry Logic for Rate Limits (429)
            $maxRetries = 3;
            $attempt = 0;
            $response = null;
            $success = false;

            while ($attempt < $maxRetries && !$success) {
                $attempt++;
                $response = Http::withoutVerifying()->withHeaders([
                    'Content-Type' => 'application/json'
                ])->post($url, $payload);

                if ($response->successful()) {
                    $success = true;
                } elseif ($response->status() == 429) {
                    // Rate Limit hit: Wait and retry (Aggressive Backoff for 15s+ requirements)
                    $wait = 5 * $attempt; // 5s, 10s, 15s
                    Log::warning("Gemini 429 Rate Limit Hit. Attempt $attempt/$maxRetries. Waiting {$wait}s...");
                    sleep($wait); 
                } else {
                    // Other errors (404, 500, etc) - stop retrying
                    break;
                }
            }
            
            if (!$success || !$response) {
                Log::error('Gemini API Final Error: ' . ($response ? $response->body() : 'No response'));
                $status = $response ? $response->status() : 500;
                $msg = 'Maaf, AI sedang sibuk (Gangguan Koneksi).';
                
                if ($status == 429) {
                    $msg = 'Antrian penuh (Limit). Mohon coba lagi dalam beberapa detik.';
                } elseif ($status == 404) {
                     $msg = 'Model AI tidak ditemukan. Cek Key.';
                }
                
                return response()->json(['message' => $msg], 502);
            }

            $data = $response->json();
            $aiReply = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Maaf, saya tidak mengerti.';

            // 6. Save AI Reply
            ChatMessage::create([
                'chat_session_id' => $session->id,
                'role' => 'model',
                'content' => $aiReply
            ]);
            
            // Update Session Title
            if ($session->messages()->count() <= 2) {
                 $session->update(['title' => substr($userMessage, 0, 50)]);
            }

            return response()->json([
                'reply' => $aiReply,
                'session_id' => $session->id,
                'attachment_url' => $attachmentData ? asset('storage/' . $attachmentData->file_path) : null
            ]);

        } catch (\Exception $e) {
            Log::error('Chat Exception: ' . $e->getMessage());
            return response()->json(['message' => 'Terjadi kesalahan sistem.' . $e->getMessage()], 500);
        }
    }
    /**
     * Get Chat History
     */
    /**
     * Get Chat History (Specific Session or Latest)
     */
    public function history(Request $request)
    {
        $user = Auth::user();
        $userId = $user ? $user->id : 1; // Fallback
        
        $sessionId = $request->query('session_id');

        if ($sessionId) {
            $session = ChatSession::where('id', $sessionId)->where('user_id', $userId)->first();
        } else {
            // Get latest session
            $session = ChatSession::where('user_id', $userId)
                ->latest()
                ->first();
        }

        if (!$session) {
            return response()->json([
                'messages' => [],
                'session_id' => null
            ]);
        }

        $messages = $session->messages()
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'text' => $msg->content,
                    'isUser' => $msg->role === 'user',
                    'timestamp' => $msg->created_at->format('H:i')
                ];
            });

        return response()->json([
            'messages' => $messages,
            'session_id' => $session->id,
            'session_title' => $session->title
        ]);
    }

    /**
     * Get All Chat Sessions (For Sidebar)
     */
    public function getSessions(Request $request)
    {
        $user = Auth::user();
        $userId = $user ? $user->id : 1; // Fallback

        $sessions = ChatSession::where('user_id', $userId)
            ->orderBy('updated_at', 'desc')
            ->select('id', 'title', 'created_at', 'updated_at')
            ->get();

        return response()->json($sessions);
    }

    /**
     * Rename Session
     */
    public function renameSession(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255'
        ]);

        $user = Auth::user();
        $userId = $user ? $user->id : 1;

        $session = ChatSession::where('id', $id)->where('user_id', $userId)->first();

        if (!$session) {
            return response()->json(['message' => 'Session not found'], 404);
        }

        $session->update(['title' => $request->title]);

        return response()->json(['message' => 'Session renamed', 'session' => $session]);
    }

    /**
     * Delete Session
     */
    public function deleteSession($id)
    {
        $user = Auth::user();
        $userId = $user ? $user->id : 1;

        $session = ChatSession::where('id', $id)->where('user_id', $userId)->first();

        if (!$session) {
            return response()->json(['message' => 'Session not found'], 404);
        }

        $session->delete();

        return response()->json(['message' => 'Session deleted']);
    }
}
