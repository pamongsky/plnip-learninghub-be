<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\Models\AiFaq;
use App\Models\AiFaqAnalytic;
use App\Models\AiFaqSuggestion;

class ChatController extends Controller
{
    /**
     * Handle Chat with Gemini (With Memory/Context)
     */
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'nullable|string', // Make message nullable when attachment exists
            'session_id' => 'nullable|integer',
            'attachment' => 'nullable|file|mimes:jpeg,jpg,png,gif,pdf,doc,docx|max:10240' // Max 10MB
        ]);

        $user = Auth::user();
        $userId = $user ? $user->id : 1;

        $userMessage = $request->input('message', ''); // Default empty string

        // If no message and no attachment, error
        if (empty($userMessage) && !$request->hasFile('attachment')) {
            return response()->json(['message' => 'Pesan atau lampiran harus ada'], 422);
        }

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
            Log::error('GEMINI_API_KEY not configured in .env');
            return response()->json(['message' => 'API Key not configured'], 500);
        }

        Log::info('Chat Request', [
            'user_id' => $userId,
            'session_id' => $sessionId,
            'message' => substr($userMessage, 0, 50) . '...',
            'has_attachment' => $request->hasFile('attachment')
        ]);

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
            $hasImageForGemini = false;

            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                
                // Check storage directory exists
                if (!file_exists(storage_path('app/public/chat_attachments'))) {
                    mkdir(storage_path('app/public/chat_attachments'), 0755, true);
                }
                
                $path = $file->store('chat_attachments', 'public');

                // Save to DB
                $attachmentData = \App\Models\ChatAttachment::create([
                    'chat_message_id' => $userChatMsg->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize()
                ]);

                // Prepare for Gemini (ONLY IMAGES SUPPORTED)
                $mimeType = $file->getMimeType();
                
                // Check if it's an image
                if (in_array($mimeType, ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'])) {
                    // Send image to Gemini via inline_data
                    $base64Data = base64_encode(file_get_contents($file->getRealPath()));
                    
                    $inlineData = [
                        "mime_type" => $mimeType,
                        "data" => $base64Data
                    ];
                    $hasImageForGemini = true;
                } else {
                    // For PDF/docs, just inform the AI (no inline_data)
                    $userMessage .= "\n\n[User melampirkan file: " . $file->getClientOriginalName() . " (" . $mimeType . "). Catatan: Saya tidak bisa membaca konten file ini, tapi file sudah tersimpan.]";
                }
            }

            // 3.5. Check FAQ Cache (ONLY for text-only queries, skip if has image attachment)
            if (!$hasImageForGemini && !empty($userMessage)) {
                $startFaqTime = microtime(true);
                $cacheKey = 'faq_response:' . md5(strtolower(trim($userMessage)));
                
                // Check cache first
                $faqMatch = Cache::remember($cacheKey, 3600, function() use ($userMessage) {
                    return AiFaq::searchByKeyword($userMessage);
                });

                if ($faqMatch) {
                    // FAQ Hit! Return FAQ response
                    $faqResponseTime = round((microtime(true) - $startFaqTime) * 1000, 2);

                    // Log analytics
                    $analytic = AiFaqAnalytic::create([
                        'faq_id' => $faqMatch->id,
                        'user_id' => $userId,
                        'user_query' => $userMessage,
                        'match_score' => $faqMatch->match_score ?? 0.8,
                        'response_source' => 'cache',
                        'response_time_ms' => $faqResponseTime,
                    ]);

                    // Update FAQ usage stats
                    $faqMatch->increment('usage_count');
                    $faqMatch->update(['last_used_at' => now()]);

                    // Create assistant message
                    $assistantChatMsg = ChatMessage::create([
                        'chat_session_id' => $session->id,
                        'role' => 'model',
                        'content' => $faqMatch->answer_short ?: $faqMatch->answer
                    ]);

                    Log::info('FAQ Cache Hit', [
                        'faq_id' => $faqMatch->id,
                        'query' => $userMessage,
                        'response_time_ms' => $faqResponseTime,
                        'analytic_id' => $analytic->id,
                    ]);

                    return response()->json([
                        'message' => [
                            'id' => $assistantChatMsg->id,
                            'chat_session_id' => $session->id,
                            'role' => 'model',
                            'content' => $assistantChatMsg->content,
                            'created_at' => $assistantChatMsg->created_at,
                            'updated_at' => $assistantChatMsg->updated_at,
                        ],
                        'session_id' => $session->id,
                        'source' => 'faq',
                        'faq_id' => $faqMatch->id,
                        'analytic_id' => $analytic->id, // For user feedback
                        'response_time_ms' => $faqResponseTime,
                    ]);
                }
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
            // Using gemini-2.5-flash (Tested & Working)
            $model = "gemini-2.5-flash";
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            // Prepend System Instruction
            array_unshift($contents, [
                "role" => "user",
                "parts" => [["text" => "System Instruction: Kamu adalah asisten AI dari PLN Indonesia Power yang ramah dan membantu. PENTING: Jawab dengan SINGKAT dan JELAS (maksimal 3-4 kalimat). Gunakan bahasa Indonesia yang natural dan mudah dipahami. Jangan gunakan format markdown berlebihan (*, **, ***). Langsung jawab tanpa basa-basi panjang."]]
            ], [
                "role" => "model",
                "parts" => [["text" => "Baik, saya siap membantu dengan jawaban singkat dan jelas!"]]
            ]);

            $payload = [
                "contents" => $contents,
                "generationConfig" => [
                    "temperature" => 0.7,
                    "maxOutputTokens" => 512, // Reduced from 2048 to keep responses shorter
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
                Log::error('Gemini API Final Error', [
                    'attempts' => $attempt,
                    'status' => $response ? $response->status() : 'null',
                    'body' => $response ? $response->body() : 'No response',
                    'model' => $model
                ]);
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

            // Clean up formatting issues
            $aiReply = $this->cleanupMarkdown($aiReply);

            Log::info('Gemini API Success', [
                'session_id' => $session->id,
                'reply_length' => strlen($aiReply),
                'source' => 'gemini_api'
            ]);

            // 6. Save AI Reply
            ChatMessage::create([
                'chat_session_id' => $session->id,
                'role' => 'model',
                'content' => $aiReply
            ]);

            // 6.5. Auto-Learn: Save as FAQ suggestion for future (only for text queries)
            if (!$hasImageForGemini && !empty($userMessage) && strlen($userMessage) > 10) {
                // Check if similar suggestion exists
                $existingSuggestion = AiFaqSuggestion::where('question', 'LIKE', '%' . substr($userMessage, 0, 50) . '%')
                    ->where('status', 'pending')
                    ->first();

                if ($existingSuggestion) {
                    // Increment occurrence count
                    $existingSuggestion->increment('occurrence_count');
                } else {
                    // Create new suggestion
                    AiFaqSuggestion::create([
                        'question' => $userMessage,
                        'answer' => $aiReply,
                        'occurrence_count' => 1,
                        'status' => 'pending',
                    ]);

                    Log::info('FAQ Auto-Learn Suggestion Created', [
                        'question' => substr($userMessage, 0, 100),
                    ]);
                }
            }

            // Update Session Title
            if ($session->messages()->count() <= 2) {
                 $session->update(['title' => substr($userMessage, 0, 50)]);
            }

            return response()->json([
                'reply' => $aiReply,
                'session_id' => $session->id,
                'source' => 'gemini_api',
                'attachment_url' => $attachmentData ? asset('storage/' . $attachmentData->file_path) : null
            ]);

        } catch (\Exception $e) {
            Log::error('Chat Exception: ' . $e->getMessage());
            return response()->json(['message' => 'Terjadi kesalahan sistem.' . $e->getMessage()], 500);
        }
    }

    /**
     * Provide feedback on FAQ response
     */
    public function faqFeedback(Request $request)
    {
        $request->validate([
            'analytic_id' => 'required|integer|exists:ai_faq_analytics,id',
            'was_helpful' => 'required|boolean',
        ]);

        $analytic = AiFaqAnalytic::findOrFail($request->analytic_id);
        $analytic->update(['was_helpful' => $request->was_helpful]);

        // Update FAQ success/failure count
        $faq = $analytic->faq;
        if ($request->was_helpful) {
            $faq->increment('success_count');
        } else {
            $faq->increment('failure_count');
        }

        // If failure rate too high, auto-deactivate
        if ($faq->failure_count > 5 && $faq->success_rate < 30) {
            $faq->update(['is_active' => false]);
            Log::warning('FAQ Auto-Deactivated Due to Low Success Rate', [
                'faq_id' => $faq->id,
                'success_rate' => $faq->success_rate,
            ]);
        }

        return response()->json([
            'message' => 'Terima kasih atas feedback Anda!',
            'faq' => [
                'id' => $faq->id,
                'success_rate' => $faq->success_rate,
                'is_active' => $faq->is_active,
            ]
        ]);
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

    /**
     * Clean up markdown formatting issues from Gemini
     */
    private function cleanupMarkdown($text)
    {
        // Remove excessive asterisks (*** -> bold, ** -> bold)
        $text = preg_replace('/\*\*\*+/', '**', $text); // *** or more -> **

        // Clean up multiple consecutive bold markers
        $text = preg_replace('/\*\*\s*\*\*/', '', $text); // ** ** -> empty

        // Remove standalone asterisks at line start/end
        $text = preg_replace('/^\*+\s*/m', '', $text);
        $text = preg_replace('/\s*\*+$/m', '', $text);

        // Clean up numbered lists with excessive formatting
        $text = preg_replace('/(\d+)\.\s*\*\*/', '$1. ', $text);

        // Remove excessive newlines (more than 2)
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        // Trim whitespace
        $text = trim($text);

        return $text;
    }
}
