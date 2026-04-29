<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\ContactMessage;
use App\Models\ContactReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    /**
     * Ticket list with filters + stats.
     */
    public function index(Request $request)
    {
        $query = ContactMessage::with('assignedAdmin');

        if ($request->filled('q')) {
            $s = $request->q;
            $query->where(function ($q) use ($s) {
                $q->where('ticket_number', 'ilike', "%{$s}%")
                  ->orWhere('subject', 'ilike', "%{$s}%")
                  ->orWhere('name', 'ilike', "%{$s}%")
                  ->orWhere('email', 'ilike', "%{$s}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'open') {
                $query->open();
            } elseif ($request->status === 'resolved') {
                $query->resolved();
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('priority'))  $query->byPriority($request->priority);
        if ($request->filled('category'))  $query->byCategory($request->category);

        if ($request->filled('assigned')) {
            if ($request->assigned === 'me') {
                $query->assignedTo(Auth::guard('admin')->id());
            } elseif ($request->assigned === 'unassigned') {
                $query->unassigned();
            } elseif (is_numeric($request->assigned)) {
                $query->assignedTo((int) $request->assigned);
            }
        }

        if ($request->boolean('overdue')) {
            $query->overdue();
        }

        $tickets = $query->orderByRaw("
            CASE priority
                WHEN 'urgent' THEN 1
                WHEN 'high' THEN 2
                WHEN 'normal' THEN 3
                WHEN 'low' THEN 4
                ELSE 5
            END
        ")->orderByDesc('created_at')->paginate(20)->withQueryString();

        $stats = [
            'total'       => ContactMessage::count(),
            'new'         => ContactMessage::where('status', 'new')->count(),
            'open'        => ContactMessage::open()->count(),
            'resolved'    => ContactMessage::resolved()->count(),
            'unassigned'  => ContactMessage::unassigned()->open()->count(),
            'mine'        => ContactMessage::assignedTo(Auth::guard('admin')->id())->open()->count(),
            'overdue'     => ContactMessage::overdue()->count(),
            'urgent'      => ContactMessage::open()->byPriority('urgent')->count(),
        ];

        $admins = Admin::orderBy('first_name')->get(['id', 'first_name', 'last_name']);

        return view('admin.messages.index', compact('tickets', 'stats', 'admins'));
    }

    /**
     * Show ticket with threaded replies.
     */
    public function show(ContactMessage $message)
    {
        // Mark status as "read" → "open" if still new
        if ($message->status === 'new') {
            $message->changeStatus('open', Auth::guard('admin')->id());
        }

        // Mark user replies as read
        $message->replies()->where('sender_type', 'user')->whereNull('read_at')
            ->update(['read_at' => now()]);

        $message->load(['replies', 'activities', 'assignedAdmin', 'resolvedByAdmin', 'user']);
        $admins = Admin::orderBy('first_name')->get(['id', 'first_name', 'last_name']);

        return view('admin.messages.show', compact('message', 'admins'));
    }

    /**
     * Admin replies to a ticket.
     */
    public function reply(Request $request, ContactMessage $message)
    {
        $request->validate([
            'message'          => 'required|string|max:10000',
            'is_internal_note' => 'nullable|boolean',
            'new_status'       => 'nullable|in:new,open,in_progress,waiting,resolved,closed',
        ]);

        $isNote = (bool) $request->input('is_internal_note');
        $adminId = Auth::guard('admin')->id();
        $admin = Auth::guard('admin')->user();

        ContactReply::create([
            'ticket_id'        => $message->id,
            'sender_type'      => 'admin',
            'sender_id'        => $adminId,
            'sender_name'      => $admin->full_name ?? 'Admin',
            'sender_email'     => $admin->email,
            'message'          => $request->message,
            'is_internal_note' => $isNote,
        ]);

        // Log activity
        $message->logActivity(
            $isNote ? 'note_added' : 'replied',
            $adminId,
            [],
            $isNote ? 'เพิ่มโน้ตภายใน' : 'ตอบกลับลูกค้า'
        );

        // Update ticket metadata
        $updates = [
            'last_activity_at' => now(),
            'reply_count'      => $message->reply_count + 1,
        ];

        if (!$isNote) {
            if (!$message->first_response_at) {
                $updates['first_response_at'] = now();
            }

            // Send email to customer
            try {
                app(\App\Services\MailService::class)->contactReply(
                    $message->email,
                    $message->name,
                    $message->subject,
                    $request->message,
                    [
                        'ticket_id'  => $message->ticket_number,
                        'replied_by' => $admin->full_name ?? 'Admin',
                        'replied_at' => now()->format('d/m/Y H:i'),
                    ]
                );
            } catch (\Throwable $e) {
                \Log::warning('Reply email failed: ' . $e->getMessage());
            }

            // In-app notification
            if ($message->user_id) {
                try {
                    \App\Models\UserNotification::contactReply($message->user_id, $message->subject);
                } catch (\Throwable $e) {
                    \Log::warning('Reply notification failed: ' . $e->getMessage());
                }
            }
        }

        $message->update($updates);

        // Optional status change
        if ($request->filled('new_status') && $request->new_status !== $message->status) {
            $message->changeStatus($request->new_status, $adminId);
        }

        return back()->with('success', $isNote ? 'เพิ่มโน้ตเรียบร้อย' : 'ส่งคำตอบเรียบร้อย');
    }

    /**
     * Assign ticket to an admin.
     */
    public function assign(Request $request, ContactMessage $message)
    {
        $request->validate(['admin_id' => 'nullable|exists:auth_admins,id']);

        $adminId = Auth::guard('admin')->id();
        $oldAssigneeId = $message->assigned_to_admin_id;
        $newAssigneeId = $request->admin_id ? (int) $request->admin_id : null;

        $message->update([
            'assigned_to_admin_id' => $newAssigneeId,
            'last_activity_at'     => now(),
        ]);

        if ($newAssigneeId) {
            $admin = Admin::find($newAssigneeId);
            $message->logActivity('assigned', $adminId, [
                'old_id' => $oldAssigneeId,
                'new_id' => $newAssigneeId,
                'new_name' => $admin?->full_name,
            ], "มอบหมายให้ " . ($admin?->full_name ?? 'Admin'));
        } else {
            $message->logActivity('unassigned', $adminId, ['old_id' => $oldAssigneeId], 'ยกเลิกการมอบหมาย');
        }

        return back()->with('success', $newAssigneeId ? 'มอบหมายเรียบร้อย' : 'ยกเลิกการมอบหมายเรียบร้อย');
    }

    public function updatePriority(Request $request, ContactMessage $message)
    {
        $request->validate(['priority' => 'required|in:low,normal,high,urgent']);

        $old = $message->priority;
        if ($old === $request->priority) return back();

        $message->update([
            'priority'         => $request->priority,
            'sla_deadline'     => $message->calculateSlaDeadline($request->priority),
            'last_activity_at' => now(),
        ]);

        $adminId = Auth::guard('admin')->id();
        $message->logActivity('priority_changed', $adminId, [
            'old' => $old, 'new' => $request->priority,
        ], "เปลี่ยนความสำคัญ");

        return back()->with('success', 'อัปเดตความสำคัญเรียบร้อย');
    }

    public function updateCategory(Request $request, ContactMessage $message)
    {
        $request->validate(['category' => 'required|in:general,billing,technical,account,refund,photographer,other']);

        $old = $message->category;
        if ($old === $request->category) return back();

        $message->update(['category' => $request->category, 'last_activity_at' => now()]);

        $adminId = Auth::guard('admin')->id();
        $message->logActivity('category_changed', $adminId, [
            'old' => $old, 'new' => $request->category,
        ], "เปลี่ยนหมวดหมู่");

        return back()->with('success', 'อัปเดตหมวดหมู่เรียบร้อย');
    }

    public function updateStatus(Request $request, ContactMessage $message)
    {
        $request->validate(['status' => 'required|in:new,open,in_progress,waiting,resolved,closed']);
        $adminId = Auth::guard('admin')->id();
        $message->changeStatus($request->status, $adminId);
        return back()->with('success', 'อัปเดตสถานะเรียบร้อย');
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:resolve,close,delete,assign_me',
            'ids'    => 'required|array',
            'ids.*'  => 'integer',
        ]);

        $adminId = Auth::guard('admin')->id();
        $tickets = ContactMessage::whereIn('id', $request->ids)->get();
        $count = $tickets->count();

        switch ($request->action) {
            case 'resolve':
                foreach ($tickets as $t) $t->changeStatus('resolved', $adminId);
                $msg = "แก้ไขแล้ว {$count} รายการ";
                break;
            case 'close':
                foreach ($tickets as $t) $t->changeStatus('closed', $adminId);
                $msg = "ปิด {$count} รายการ";
                break;
            case 'delete':
                ContactMessage::whereIn('id', $request->ids)->delete();
                $msg = "ลบ {$count} รายการ";
                break;
            case 'assign_me':
                ContactMessage::whereIn('id', $request->ids)->update(['assigned_to_admin_id' => $adminId]);
                foreach ($tickets as $t) {
                    $t->logActivity('assigned', $adminId, [], 'มอบหมายให้ตัวเอง (bulk)');
                }
                $msg = "มอบหมายให้ตัวเอง {$count} รายการ";
                break;
            default:
                $msg = '';
        }

        return back()->with('success', $msg);
    }

    public function destroy(ContactMessage $message)
    {
        $num = $message->ticket_number;
        $message->delete();
        return redirect()->route('admin.messages.index')->with('success', "ลบ {$num} เรียบร้อย");
    }
}
