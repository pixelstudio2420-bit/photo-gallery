<?php
namespace App\Http\Controllers\Public;
use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\PhotographerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function index()
    {
        $conversations = ChatConversation::where('user_id', Auth::id())
            ->with(['photographer', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->get();
        return view('public.chat.index', compact('conversations'));
    }

    public function show($conversation)
    {
        $conversation = ChatConversation::with('messages')
            ->where('user_id', Auth::id())
            ->findOrFail($conversation);

        // Mark photographer messages as read
        $conversation->messages()
            ->where('sender_type', 'photographer')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = $conversation->messages()->orderBy('created_at', 'asc')->get();

        return view('public.chat.show', compact('conversation', 'messages'));
    }

    public function send(Request $request, $conversation)
    {
        $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $conversation = ChatConversation::where('user_id', Auth::id())
            ->findOrFail($conversation);

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type'     => 'user',
            'sender_id'       => Auth::id(),
            'message'         => $request->input('message'),
            'is_read'         => false,
            'created_at'      => now(),
        ]);

        $conversation->update(['last_message_at' => now()]);

        // Notify the photographer in their bell. Idempotency on
        // (user_id, type, ref_id=conversation_id) prevents sequential
        // messages within the same conversation from spamming the bell —
        // a single "you have a new message" pointing to the chat is
        // enough; counts surface in the chat page itself. Throttled to
        // one notification per conversation per 5 minutes (refresh
        // ref_id with a coarse time bucket).
        try {
            $profile = PhotographerProfile::find($conversation->photographer_id);
            if ($profile && $profile->user_id) {
                $bucket = floor(now()->timestamp / 300); // 5-min bucket
                \App\Models\UserNotification::notifyOnce(
                    $profile->user_id,
                    'chat_message',
                    '💬 ข้อความใหม่จากลูกค้า',
                    mb_substr($message->message, 0, 80),
                    'photographer/chat/' . $conversation->id,
                    'chat:' . $conversation->id . ':' . $bucket
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('chat.send.notify_failed: ' . $e->getMessage());
        }

        return back();
    }

    public function start($photographer)
    {
        // $photographer parameter is the photographer profile ID, with
        // a fallback to user_id (so links from "Chat with photographer"
        // buttons that pass user_id still resolve cleanly).
        $photographerProfile = PhotographerProfile::find($photographer)
            ?? PhotographerProfile::where('user_id', $photographer)->first();
        if (!$photographerProfile) {
            abort(404, 'ไม่พบช่างภาพ');
        }

        // Find existing conversation or create new one
        $conversation = ChatConversation::firstOrCreate(
            [
                'user_id'         => Auth::id(),
                'photographer_id' => $photographerProfile->id,
            ],
            [
                'last_message_at' => now(),
                'created_at'      => now(),
            ]
        );

        return redirect()->route('chat.show', $conversation->id);
    }
}
