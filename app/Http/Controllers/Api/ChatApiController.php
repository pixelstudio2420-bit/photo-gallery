<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\Media\Exceptions\InvalidMediaFileException;
use App\Services\Media\R2MediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ChatApiController extends Controller
{
    private const ALLOWED_IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const ALLOWED_FILE_TYPES  = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'txt'];

    /**
     * Get messages (with optional polling via ?since=timestamp).
     */
    public function messages(Request $request, $conversation)
    {
        $conversation = ChatConversation::findOrFail($conversation);

        if (!$this->canAccess($conversation)) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $role = $this->getRole($conversation);

        $query = $conversation->visibleMessages();

        if ($since = $request->query('since')) {
            try {
                $sinceDate = \Carbon\Carbon::parse($since);
                $query->where('created_at', '>', $sinceDate);
            } catch (\Throwable $e) {}
        }

        $messages = $query->get();

        // Auto-mark messages from other party as read
        $this->markReadByRole($conversation, $role);

        // Get typing indicator for other party
        $otherRole = $role === 'user' ? 'photographer' : 'user';
        $typing = Cache::get("chat:typing:{$conversation->id}:{$otherRole}", false);

        return response()->json([
            'success'     => true,
            'messages'    => $messages,
            'typing'      => (bool) $typing,
            'unread_count' => $role === 'user'
                ? $conversation->fresh()->unread_count_user
                : $conversation->fresh()->unread_count_photographer,
            'timestamp'   => now()->toIso8601String(),
        ]);
    }

    /**
     * Send a text or attachment message.
     */
    public function send(Request $request, $conversation)
    {
        $conversation = ChatConversation::findOrFail($conversation);

        if (!$this->canAccess($conversation)) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'message'    => 'nullable|string|max:5000',
            'attachment' => 'nullable|file|max:10240',
        ]);

        if (empty($request->input('message')) && !$request->hasFile('attachment')) {
            return response()->json(['success' => false, 'error' => 'กรุณาพิมพ์ข้อความหรือแนบไฟล์'], 422);
        }

        $role = $this->getRole($conversation);
        $senderType = $role === 'user' ? 'user' : 'photographer';

        $data = [
            'conversation_id' => $conversation->id,
            'sender_type'     => $senderType,
            'sender_id'       => Auth::id(),
            'message'         => $request->input('message', ''),
            'message_type'    => 'text',
            'is_read'         => false,
            'created_at'      => now(),
        ];

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $ext = strtolower($file->getClientOriginalExtension());
            $isImage = in_array($ext, self::ALLOWED_IMAGE_TYPES);

            // Upload to R2 (private — chat attachments shouldn't be on the
            // public CDN). The category's mime/extension allowlist is the
            // authoritative gate; the legacy local checks above are kept
            // for the early friendly error.
            try {
                $upload = app(R2MediaService::class)
                    ->uploadChatAttachment(
                        (int) Auth::id(),
                        (int) $conversation->id,
                        $file,
                    );
            } catch (InvalidMediaFileException $e) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
            }

            $data['message_type']    = $isImage ? 'image' : 'file';
            $data['attachment_url']  = $upload->key;
            $data['attachment_name'] = $file->getClientOriginalName();
            $data['attachment_size'] = $upload->sizeBytes;
            if (empty($data['message'])) {
                $data['message'] = $isImage ? '📷 รูปภาพ' : "📎 {$file->getClientOriginalName()}";
            }
        }

        $message = ChatMessage::create($data);

        $otherRole = $role === 'user' ? 'photographer' : 'user';
        $conversation->update(['last_message_at' => now(), 'status' => 'active']);
        $conversation->incrementUnread($otherRole);

        // Clear typing indicator (you finished sending)
        Cache::forget("chat:typing:{$conversation->id}:{$role}");

        return response()->json(['success' => true, 'message' => $message]);
    }

    /**
     * Mark all messages from other party as read.
     */
    public function markRead($conversation)
    {
        $conversation = ChatConversation::findOrFail($conversation);

        if (!$this->canAccess($conversation)) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $role = $this->getRole($conversation);
        $this->markReadByRole($conversation, $role);

        return response()->json(['success' => true]);
    }

    /**
     * Typing indicator (5-sec TTL via cache).
     */
    public function typing(Request $request, $conversation)
    {
        $conversation = ChatConversation::findOrFail($conversation);

        if (!$this->canAccess($conversation)) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $role = $this->getRole($conversation);
        $isTyping = (bool) $request->input('typing', true);

        if ($isTyping) {
            Cache::put("chat:typing:{$conversation->id}:{$role}", true, now()->addSeconds(5));
        } else {
            Cache::forget("chat:typing:{$conversation->id}:{$role}");
        }

        return response()->json(['success' => true]);
    }

    /**
     * Search messages across user's conversations.
     */
    public function search(Request $request)
    {
        $request->validate(['q' => 'required|string|min:2|max:100']);

        $userId = Auth::id();
        $photographerProfileId = optional(Auth::user()?->photographerProfile)->id;

        $query = ChatMessage::with('conversation.user', 'conversation.photographer.user')
            ->whereNull('deleted_at')
            ->where('message', 'LIKE', '%' . $request->q . '%')
            ->whereHas('conversation', function ($q) use ($userId, $photographerProfileId) {
                $q->where('user_id', $userId);
                if ($photographerProfileId) {
                    $q->orWhere('photographer_id', $photographerProfileId);
                }
            })
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        return response()->json([
            'success' => true,
            'results' => $query->map(fn($m) => [
                'id'              => $m->id,
                'conversation_id' => $m->conversation_id,
                'message'         => $m->message,
                'sender_type'     => $m->sender_type,
                'created_at'      => $m->created_at,
                'preview'         => \Str::limit($m->message, 100),
                'other_party'     => $m->conversation ? $m->conversation->otherParty($userId) : null,
            ]),
        ]);
    }

    /**
     * Archive or unarchive a conversation.
     */
    public function archive(Request $request, $conversation)
    {
        $conversation = ChatConversation::findOrFail($conversation);

        if (!$this->canAccess($conversation)) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $role = $this->getRole($conversation);
        $action = $request->input('action', 'archive');

        if ($action === 'archive') {
            $conversation->archive($role);
        } else {
            $conversation->unarchive();
        }

        return response()->json(['success' => true, 'archived' => $action === 'archive']);
    }

    /**
     * Delete a single message (sender only).
     */
    public function deleteMessage($conversation, $message)
    {
        $conversation = ChatConversation::findOrFail($conversation);
        $message = ChatMessage::where('conversation_id', $conversation->id)->findOrFail($message);

        if (!$this->canAccess($conversation)) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        if ($message->sender_id !== Auth::id()) {
            return response()->json(['success' => false, 'error' => 'ลบได้เฉพาะข้อความของตัวเอง'], 403);
        }

        $message->softDelete();
        return response()->json(['success' => true]);
    }

    /**
     * List user's conversations (inbox sidebar).
     */
    public function conversations(Request $request)
    {
        $userId = Auth::id();
        $photographerProfileId = optional(Auth::user()?->photographerProfile)->id;

        $query = ChatConversation::with(['user', 'photographer.user', 'latestMessage'])
            ->where(function ($q) use ($userId, $photographerProfileId) {
                $q->where('user_id', $userId);
                if ($photographerProfileId) {
                    $q->orWhere('photographer_id', $photographerProfileId);
                }
            });

        if ($request->boolean('archived')) {
            $query->archived();
        } else {
            $query->active();
        }

        $conversations = $query->orderByDesc('last_message_at')->limit(50)->get();

        $totalUnread = 0;
        $mapped = $conversations->map(function ($c) use ($userId, &$totalUnread) {
            $isUser = $c->user_id === $userId;
            $unread = $isUser ? $c->unread_count_user : $c->unread_count_photographer;
            $totalUnread += $unread;

            return [
                'id'                => $c->id,
                'subject'           => $c->subject,
                'other_party'       => $c->otherParty($userId),
                'latest_message'    => $c->latestMessage?->message,
                'latest_message_at' => $c->last_message_at?->toIso8601String(),
                'unread'            => $unread,
                'archived'          => (bool) $c->archived_at,
            ];
        });

        return response()->json([
            'success'       => true,
            'conversations' => $mapped,
            'total_unread'  => $totalUnread,
        ]);
    }

    /* ────────── Helpers ────────── */

    protected function canAccess(ChatConversation $conversation): bool
    {
        $userId = Auth::id();
        if (!$userId) return false;

        if ($conversation->user_id === $userId) return true;

        $photographerProfileId = optional(Auth::user()?->photographerProfile)->id;
        return $photographerProfileId && $conversation->photographer_id === $photographerProfileId;
    }

    protected function getRole(ChatConversation $conversation): string
    {
        return $conversation->user_id === Auth::id() ? 'user' : 'photographer';
    }

    protected function markReadByRole(ChatConversation $conversation, string $viewerRole): int
    {
        $senderType = $viewerRole === 'user' ? 'photographer' : 'user';

        $count = $conversation->messages()
            ->where('sender_type', $senderType)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        if ($count > 0) {
            $conversation->resetUnread($viewerRole);
        }

        return $count;
    }
}
