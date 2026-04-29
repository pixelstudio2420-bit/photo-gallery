@extends('layouts.admin')

@section('title', 'Ticket ' . $message->ticket_number)

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-5">

  {{-- Left: Thread --}}
  <div class="space-y-4">
    <div>
      <a href="{{ route('admin.messages.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
        <i class="bi bi-chevron-left"></i> กลับไปรายการ
      </a>
      <div class="flex flex-wrap items-center gap-2 mt-2">
        <span class="font-mono text-sm text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">{{ $message->ticket_number }}</span>
        <span class="text-xs px-2 py-0.5 bg-{{ $message->priority_color }}-100 text-{{ $message->priority_color }}-700 rounded font-semibold">{{ $message->priority_label }}</span>
        <span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-700 rounded">{{ $message->category_label }}</span>
        @if($message->isOverdue())
          <span class="text-xs px-2 py-0.5 bg-red-500 text-white rounded font-semibold animate-pulse">
            <i class="bi bi-clock-history"></i> เกินกำหนด
          </span>
        @endif
      </div>
      <h1 class="text-2xl font-bold text-slate-800 dark:text-gray-100 mt-2">{{ $message->subject }}</h1>
    </div>

    {{-- Original Message --}}
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-5">
      <div class="flex items-start gap-3 mb-3">
        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-slate-400 to-gray-500 text-white flex items-center justify-center font-semibold">
          {{ mb_strtoupper(mb_substr($message->name, 0, 1, 'UTF-8'), 'UTF-8') }}
        </div>
        <div class="flex-1">
          <div class="font-semibold text-slate-800 dark:text-gray-100">{{ $message->name }}</div>
          <div class="text-xs text-gray-500">{{ $message->email }} · {{ $message->created_at->format('d/m/Y H:i') }}</div>
        </div>
      </div>
      <div class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $message->message }}</div>
    </div>

    {{-- Thread --}}
    @foreach($message->replies as $reply)
      @if($reply->is_internal_note)
        <div class="bg-amber-50 dark:bg-amber-500/10 border-l-4 border-amber-400 rounded-r-2xl p-4 ml-6">
          <div class="flex items-center gap-2 mb-1">
            <i class="bi bi-sticky-fill text-amber-600"></i>
            <span class="font-semibold text-amber-800 text-sm">โน้ตภายใน (ลูกค้าไม่เห็น)</span>
            <span class="text-xs text-gray-500">{{ $reply->sender_name }} · {{ $reply->created_at->diffForHumans() }}</span>
          </div>
          <div class="text-sm text-gray-800 whitespace-pre-wrap">{{ $reply->message }}</div>
        </div>
      @elseif($reply->sender_type === 'admin')
        <div class="bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-100 dark:border-indigo-500/30 rounded-2xl p-5 ml-6">
          <div class="flex items-start gap-3 mb-2">
            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500 to-violet-500 text-white flex items-center justify-center font-semibold text-sm">
              {{ mb_strtoupper(mb_substr($reply->sender_name, 0, 1, 'UTF-8'), 'UTF-8') }}
            </div>
            <div class="flex-1">
              <div class="flex items-center gap-2">
                <span class="font-semibold text-slate-800 dark:text-gray-100">{{ $reply->sender_name }}</span>
                <span class="text-xs px-2 py-0.5 bg-indigo-500 text-white rounded">Admin</span>
              </div>
              <div class="text-xs text-gray-500">{{ $reply->created_at->format('d/m/Y H:i') }} · {{ $reply->created_at->diffForHumans() }}</div>
            </div>
            @if($reply->read_at)
            <span class="text-xs text-gray-400" title="อ่านแล้ว">
              <i class="bi bi-check2-all text-blue-500"></i>
            </span>
            @endif
          </div>
          <div class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $reply->message }}</div>
        </div>
      @else
        <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-5">
          <div class="flex items-start gap-3 mb-2">
            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-slate-400 to-gray-500 text-white flex items-center justify-center font-semibold text-sm">
              {{ mb_strtoupper(mb_substr($reply->sender_name, 0, 1, 'UTF-8'), 'UTF-8') }}
            </div>
            <div class="flex-1">
              <div class="font-semibold text-slate-800 dark:text-gray-100">{{ $reply->sender_name }}</div>
              <div class="text-xs text-gray-500">{{ $reply->created_at->format('d/m/Y H:i') }} · {{ $reply->created_at->diffForHumans() }}</div>
            </div>
          </div>
          <div class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $reply->message }}</div>
        </div>
      @endif
    @endforeach

    {{-- Reply Form --}}
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-5" x-data="{ isNote: false }">
      <form method="POST" action="{{ route('admin.messages.reply', $message) }}">
        @csrf
        <div class="flex items-center gap-3 mb-3">
          <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" name="is_internal_note" value="1" x-model="isNote" class="rounded border-gray-300">
            <span class="font-medium" :class="isNote ? 'text-amber-600' : 'text-gray-700'">
              <i class="bi" :class="isNote ? 'bi-sticky-fill' : 'bi-reply'"></i>
              <span x-text="isNote ? 'โน้ตภายใน (ลูกค้าไม่เห็น)' : 'ตอบลูกค้า'"></span>
            </span>
          </label>
        </div>
        <textarea name="message" rows="5" required maxlength="10000" placeholder="พิมพ์ข้อความ..."
                  class="w-full px-4 py-3 border border-gray-200 dark:border-white/10 rounded-xl bg-white dark:bg-slate-900 dark:text-gray-200"
                  :class="isNote ? 'bg-amber-50 border-amber-200' : ''"></textarea>
        <div class="flex items-center justify-between gap-3 mt-3 flex-wrap">
          <select name="new_status" class="px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
            <option value="">ไม่เปลี่ยนสถานะ</option>
            @foreach(\App\Models\ContactMessage::STATUSES as $k => $label)
              <option value="{{ $k }}" {{ $k === 'in_progress' ? 'selected' : '' }}>→ {{ $label }}</option>
            @endforeach
          </select>
          <button type="submit"
                  :class="isNote ? 'bg-amber-500 hover:bg-amber-600' : 'bg-indigo-500 hover:bg-indigo-600'"
                  class="px-6 py-2.5 text-white rounded-xl font-medium transition">
            <i class="bi" :class="isNote ? 'bi-sticky' : 'bi-send'"></i>
            <span x-text="isNote ? 'เพิ่มโน้ต' : 'ส่งคำตอบ'"></span>
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- Right: Sidebar --}}
  <div class="space-y-4">
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4">
      <h3 class="text-xs font-semibold text-gray-500 uppercase mb-2">สถานะ</h3>
      <form method="POST" action="{{ route('admin.messages.update-status', $message) }}">
        @csrf
        <select name="status" onchange="this.form.submit()" class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
          @foreach(\App\Models\ContactMessage::STATUSES as $k => $label)
          <option value="{{ $k }}" {{ $message->status === $k ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </form>
    </div>

    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4">
      <h3 class="text-xs font-semibold text-gray-500 uppercase mb-2">ความสำคัญ</h3>
      <form method="POST" action="{{ route('admin.messages.update-priority', $message) }}">
        @csrf
        <select name="priority" onchange="this.form.submit()" class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
          @foreach(\App\Models\ContactMessage::PRIORITIES as $k => $p)
          <option value="{{ $k }}" {{ $message->priority === $k ? 'selected' : '' }}>{{ $p['label'] }} ({{ $p['sla_hours'] }}h SLA)</option>
          @endforeach
        </select>
      </form>
    </div>

    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4">
      <h3 class="text-xs font-semibold text-gray-500 uppercase mb-2">หมวดหมู่</h3>
      <form method="POST" action="{{ route('admin.messages.update-category', $message) }}">
        @csrf
        <select name="category" onchange="this.form.submit()" class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
          @foreach(\App\Models\ContactMessage::CATEGORIES as $k => $label)
          <option value="{{ $k }}" {{ $message->category === $k ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </form>
    </div>

    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4">
      <h3 class="text-xs font-semibold text-gray-500 uppercase mb-2">มอบหมายให้</h3>
      <form method="POST" action="{{ route('admin.messages.assign', $message) }}">
        @csrf
        <select name="admin_id" onchange="this.form.submit()" class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
          <option value="">— ยังไม่มอบหมาย —</option>
          @foreach($admins as $a)
          <option value="{{ $a->id }}" {{ $message->assigned_to_admin_id == $a->id ? 'selected' : '' }}>
            {{ $a->first_name }} {{ $a->last_name }}
          </option>
          @endforeach
        </select>
      </form>
    </div>

    @if($message->sla_deadline)
    <div class="bg-white dark:bg-slate-800 border {{ $message->isOverdue() ? 'border-red-300 bg-red-50' : 'border-gray-100 dark:border-white/5' }} rounded-2xl p-4">
      <h3 class="text-xs font-semibold text-gray-500 uppercase mb-2">SLA Deadline</h3>
      <div class="text-lg font-bold {{ $message->isOverdue() ? 'text-red-600' : 'text-slate-800 dark:text-gray-100' }}">
        {{ $message->sla_deadline->format('d/m/Y H:i') }}
      </div>
      <div class="text-xs {{ $message->isOverdue() ? 'text-red-600' : 'text-gray-500' }}">
        {{ $message->slaTimeRemaining() }}
      </div>
    </div>
    @endif

    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4 text-xs space-y-2">
      <div class="flex justify-between"><span class="text-gray-500">สร้าง:</span><span>{{ $message->created_at->format('d/m/Y H:i') }}</span></div>
      @if($message->first_response_at)
      <div class="flex justify-between"><span class="text-gray-500">ตอบครั้งแรก:</span><span>{{ $message->first_response_at->format('d/m/Y H:i') }}</span></div>
      @endif
      @if($message->resolved_at)
      <div class="flex justify-between"><span class="text-gray-500">แก้ไขเมื่อ:</span><span>{{ $message->resolved_at->format('d/m/Y H:i') }}</span></div>
      @endif
      <div class="flex justify-between"><span class="text-gray-500">ตอบกลับ:</span><span>{{ $message->reply_count }} ครั้ง</span></div>
      @if($message->user_id)
      <div class="flex justify-between"><span class="text-gray-500">User ID:</span><span>#{{ $message->user_id }}</span></div>
      @endif
    </div>

    @if($message->activities->count())
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4">
      <h3 class="text-xs font-semibold text-gray-500 uppercase mb-3">กิจกรรม</h3>
      <div class="space-y-2 max-h-64 overflow-y-auto">
        @foreach($message->activities as $act)
        <div class="flex items-start gap-2 text-xs">
          <i class="bi {{ $act->icon }} text-{{ $act->color }}-500 mt-0.5"></i>
          <div class="flex-1">
            <div class="text-gray-700 dark:text-gray-300">{{ $act->description ?: $act->type }}</div>
            <div class="text-gray-400">{{ $act->actor_name }} · {{ $act->created_at->diffForHumans() }}</div>
          </div>
        </div>
        @endforeach
      </div>
    </div>
    @endif

    <form method="POST" action="{{ route('admin.messages.destroy', $message) }}" onsubmit="return confirm('ลบ ticket นี้ถาวร?')">
      @csrf
      @method('DELETE')
      <button type="submit" class="w-full px-4 py-2 border border-red-200 text-red-600 rounded-xl text-sm font-medium hover:bg-red-50 transition">
        <i class="bi bi-trash"></i> ลบ Ticket
      </button>
    </form>
  </div>
</div>
@endsection
