<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\ContactReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactController extends Controller
{
    public function index()
    {
        $seo = app(\App\Services\SeoService::class);
        $seo->title('ติดต่อเรา')
            ->description('ติดต่อทีมงาน Photo Gallery — ส่งคำถามหรือปัญหา เราจะตอบกลับภายใน 24 ชั่วโมง')
            ->setBreadcrumbs([
                ['name' => 'หน้าแรก', 'url' => url('/')],
                ['name' => 'ติดต่อเรา'],
            ]);

        return view('public.contact');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|max:200',
            'email'    => 'required|email',
            'subject'  => 'required|max:300',
            'category' => 'nullable|in:general,billing,technical,account,refund,photographer,other',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'message'  => 'required|string|max:5000',
        ]);

        // Non-logged-in users can't set priority > normal
        $priority = $validated['priority'] ?? 'normal';
        if (!auth()->check() && in_array($priority, ['high', 'urgent'])) {
            $priority = 'normal';
        }

        $category = $validated['category'] ?? 'general';

        // Create ticket
        $ticketNumber = ContactMessage::generateTicketNumber();

        $contact = ContactMessage::create([
            'ticket_number'    => $ticketNumber,
            'user_id'          => auth()->id(),
            'name'             => $validated['name'],
            'email'            => $validated['email'],
            'subject'          => $validated['subject'],
            'category'         => $category,
            'priority'         => $priority,
            'message'          => $validated['message'],
            'status'           => 'new',
            'last_activity_at' => now(),
        ]);

        // Calculate SLA deadline
        $contact->update(['sla_deadline' => $contact->calculateSlaDeadline($priority)]);

        // Log activity
        $contact->logActivity('created', null, [], 'สร้าง ticket ใหม่');

        // Line notification
        try {
            $line = app(\App\Services\LineNotifyService::class);
            $line->notifyNewContact([
                'name'    => $validated['name'],
                'email'   => $validated['email'],
                'subject' => "[{$ticketNumber}] " . $validated['subject'],
            ]);
        } catch (\Throwable $e) {
            \Log::error('Line notification error: ' . $e->getMessage());
        }

        // Admin in-app notification — handled by AdminNotificationObserver
        // on ContactMessage::created. Removed direct call here to prevent
        // duplicate bell-icon entries.

        // Admin alert email
        try {
            $adminEmail = \App\Models\AppSetting::get('admin_notification_email', \App\Models\AppSetting::get('mail_from_email'));
            if ($adminEmail) {
                app(\App\Services\MailService::class)->adminNewContactAlert($adminEmail, [
                    'id'            => $contact->id,
                    'sender_name'   => $validated['name'],
                    'sender_email'  => $validated['email'],
                    'subject'       => $validated['subject'],
                    'message'       => $validated['message'],
                    'category'      => ContactMessage::CATEGORIES[$category] ?? $category,
                    'created_at'    => $contact->created_at?->format('d/m/Y H:i'),
                    'ip_address'    => $request->ip(),
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning('Contact admin email failed: ' . $e->getMessage());
        }

        return redirect()
            ->route(auth()->check() ? 'support.show' : 'contact', auth()->check() ? ['ticket' => $contact->ticket_number] : [])
            ->with('success', "ส่งข้อความสำเร็จ! หมายเลข ticket ของคุณคือ <strong>{$ticketNumber}</strong> เราจะตอบกลับภายใน " . (\App\Models\ContactMessage::PRIORITIES[$priority]['sla_hours'] ?? 24) . " ชั่วโมง");
    }

    /* ═════════════════════════════════════════════════════════════════
     *  USER SUPPORT PORTAL (logged-in users)
     * ═════════════════════════════════════════════════════════════════ */

    /**
     * List user's tickets.
     */
    public function mytickets(Request $request)
    {
        $query = ContactMessage::where(function ($q) {
            $q->where('user_id', Auth::id())
              ->orWhere('email', Auth::user()?->email);
        })->orderByDesc('last_activity_at');

        if ($request->filled('status')) {
            if ($request->status === 'open') {
                $query->open();
            } elseif ($request->status === 'resolved') {
                $query->resolved();
            }
        }

        $tickets = $query->paginate(10)->withQueryString();

        $stats = [
            'total'    => ContactMessage::where('user_id', Auth::id())->count(),
            'open'     => ContactMessage::where('user_id', Auth::id())->open()->count(),
            'resolved' => ContactMessage::where('user_id', Auth::id())->resolved()->count(),
        ];

        return view('public.support.index', compact('tickets', 'stats'));
    }

    /**
     * Show single ticket (threaded view).
     */
    public function showTicket(string $ticket)
    {
        $ticket = ContactMessage::where('ticket_number', $ticket)
            ->where(function ($q) {
                $q->where('user_id', Auth::id())
                  ->orWhere('email', Auth::user()?->email);
            })
            ->firstOrFail();

        // Mark admin replies as read
        $ticket->replies()->where('sender_type', 'admin')->whereNull('read_at')
            ->where('is_internal_note', false)
            ->update(['read_at' => now()]);

        $ticket->load(['publicReplies', 'assignedAdmin']);

        return view('public.support.show', compact('ticket'));
    }

    /**
     * User replies to their own ticket.
     */
    public function replyTicket(Request $request, string $ticket)
    {
        $request->validate(['message' => 'required|string|max:5000']);

        $ticket = ContactMessage::where('ticket_number', $ticket)
            ->where(function ($q) {
                $q->where('user_id', Auth::id())
                  ->orWhere('email', Auth::user()?->email);
            })
            ->firstOrFail();

        if (in_array($ticket->status, ['closed'])) {
            return back()->with('error', 'Ticket นี้ถูกปิดแล้ว ไม่สามารถตอบกลับได้');
        }

        ContactReply::create([
            'ticket_id'    => $ticket->id,
            'sender_type'  => 'user',
            'sender_id'    => Auth::id(),
            'sender_name'  => Auth::user()?->first_name ?? $ticket->name,
            'sender_email' => Auth::user()?->email ?? $ticket->email,
            'message'      => $request->message,
        ]);

        $ticket->update([
            'status'           => $ticket->status === 'waiting' ? 'open' : $ticket->status,
            'last_activity_at' => now(),
            'reply_count'      => $ticket->reply_count + 1,
        ]);

        $ticket->logActivity('replied', null, [], 'ลูกค้าตอบกลับ');

        // Notify assigned admin
        if ($ticket->assigned_to_admin_id) {
            try {
                \App\Models\AdminNotification::notify(
                    'contact',
                    "ลูกค้าตอบกลับ {$ticket->ticket_number}",
                    mb_substr($request->message, 0, 100),
                    "admin/messages/{$ticket->id}",
                    (string) $ticket->id
                );
            } catch (\Throwable $e) {}
        }

        return back()->with('success', 'ส่งข้อความเรียบร้อย');
    }

    /**
     * User rates a resolved ticket.
     */
    public function rateTicket(Request $request, string $ticket)
    {
        $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $ticket = ContactMessage::where('ticket_number', $ticket)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $ticket->update([
            'satisfaction_rating'  => $request->rating,
            'satisfaction_comment' => $request->comment,
        ]);

        return back()->with('success', 'ขอบคุณสำหรับการประเมิน!');
    }
}
