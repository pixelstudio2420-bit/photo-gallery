<?php
namespace App\Http\Controllers\Photographer;
use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function index()
    {
        $conversations = ChatConversation::where('photographer_id', Auth::user()->photographerProfile->id)
            ->with(['user', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->get();
        return view('photographer.chat.index', compact('conversations'));
    }

    public function show($conversation)
    {
        $conversation = ChatConversation::with('messages')
            ->where('photographer_id', Auth::user()->photographerProfile->id)
            ->findOrFail($conversation);

        // Mark unread messages from user as read
        $conversation->messages()
            ->where('sender_type', 'user')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = $conversation->messages()->orderBy('created_at', 'asc')->get();

        return view('photographer.chat.show', compact('conversation', 'messages'));
    }

    public function send(Request $request, $conversation)
    {
        $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $conversation = ChatConversation::where('photographer_id', Auth::user()->photographerProfile->id)
            ->findOrFail($conversation);

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type'     => 'photographer',
            'sender_id'       => Auth::id(),
            'message'         => $request->input('message'),
            'is_read'         => false,
            'created_at'      => now(),
        ]);

        $conversation->update(['last_message_at' => now()]);

        // Notify the customer in their bell. Same throttling logic as
        // the public ChatController — coarse 5-minute bucket on ref_id
        // prevents bell-spam when the photographer types several
        // messages in a row.
        try {
            if ($conversation->user_id) {
                $bucket = floor(now()->timestamp / 300);
                \App\Models\UserNotification::notifyOnce(
                    $conversation->user_id,
                    'chat_message',
                    '💬 ข้อความใหม่จากช่างภาพ',
                    mb_substr($message->message, 0, 80),
                    'chat/' . $conversation->id,
                    'chat:' . $conversation->id . ':' . $bucket
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('photographer.chat.send.notify_failed: ' . $e->getMessage());
        }

        return back();
    }
}
