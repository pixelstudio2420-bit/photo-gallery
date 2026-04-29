@extends('layouts.admin')

@section('title', 'Support Tickets')

@section('content')
<div class="space-y-5">

  {{-- Header --}}
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-slate-800 dark:text-gray-100">
        <i class="bi bi-ticket-detailed text-indigo-500 mr-2"></i>Support Tickets
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">จัดการคำถามและปัญหาจากลูกค้า</p>
    </div>
    @if($stats['overdue'] > 0)
    <a href="?overdue=1" class="px-4 py-2 bg-red-500 text-white rounded-xl text-sm font-medium hover:bg-red-600 animate-pulse">
      <i class="bi bi-clock-history"></i> เกินกำหนด {{ $stats['overdue'] }} รายการ
    </a>
    @endif
  </div>

  {{-- Stats Cards --}}
  <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">
    @php
      $statCards = [
        ['label' => 'ทั้งหมด',    'value' => $stats['total'],      'color' => 'slate',   'icon' => 'inbox'],
        ['label' => 'ใหม่',       'value' => $stats['new'],        'color' => 'blue',    'icon' => 'envelope'],
        ['label' => 'เปิดอยู่',    'value' => $stats['open'],       'color' => 'amber',   'icon' => 'envelope-open'],
        ['label' => 'แก้ไขแล้ว',   'value' => $stats['resolved'],   'color' => 'emerald', 'icon' => 'check-circle'],
        ['label' => 'ยังไม่มอบหมาย','value' => $stats['unassigned'], 'color' => 'gray',    'icon' => 'person-dash'],
        ['label' => 'ของฉัน',     'value' => $stats['mine'],       'color' => 'indigo',  'icon' => 'person-check'],
        ['label' => 'เกินกำหนด',   'value' => $stats['overdue'],    'color' => 'red',     'icon' => 'clock-history'],
        ['label' => 'เร่งด่วน',    'value' => $stats['urgent'],     'color' => 'red',     'icon' => 'exclamation-triangle'],
      ];
    @endphp
    @foreach($statCards as $s)
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-3">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-{{ $s['color'] }}-100 text-{{ $s['color'] }}-600 flex items-center justify-center shrink-0">
          <i class="bi bi-{{ $s['icon'] }} text-sm"></i>
        </div>
        <div class="min-w-0">
          <div class="text-[10px] text-gray-500 truncate">{{ $s['label'] }}</div>
          <div class="text-lg font-bold text-{{ $s['color'] }}-600">{{ number_format($s['value']) }}</div>
        </div>
      </div>
    </div>
    @endforeach
  </div>

  {{-- Filter Bar --}}
  <form method="GET" class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4 grid grid-cols-1 md:grid-cols-7 gap-3">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Ticket #, หัวข้อ, ชื่อ..."
           class="col-span-2 px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
    <select name="status" class="px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
      <option value="">ทุกสถานะ</option>
      <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>เปิดอยู่ทั้งหมด</option>
      <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>แก้ไขแล้ว</option>
      @foreach(\App\Models\ContactMessage::STATUSES as $k => $label)
      <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $label }}</option>
      @endforeach
    </select>
    <select name="priority" class="px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
      <option value="">ทุกความสำคัญ</option>
      @foreach(\App\Models\ContactMessage::PRIORITIES as $k => $p)
      <option value="{{ $k }}" {{ request('priority') === $k ? 'selected' : '' }}>{{ $p['label'] }}</option>
      @endforeach
    </select>
    <select name="category" class="px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
      <option value="">ทุกหมวด</option>
      @foreach(\App\Models\ContactMessage::CATEGORIES as $k => $label)
      <option value="{{ $k }}" {{ request('category') === $k ? 'selected' : '' }}>{{ $label }}</option>
      @endforeach
    </select>
    <select name="assigned" class="px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
      <option value="">ทุกการมอบหมาย</option>
      <option value="me" {{ request('assigned') === 'me' ? 'selected' : '' }}>ของฉัน</option>
      <option value="unassigned" {{ request('assigned') === 'unassigned' ? 'selected' : '' }}>ยังไม่มอบหมาย</option>
      @foreach($admins as $a)
      <option value="{{ $a->id }}" {{ (string)request('assigned') === (string)$a->id ? 'selected' : '' }}>{{ $a->first_name }} {{ $a->last_name }}</option>
      @endforeach
    </select>
    <button type="submit" class="px-4 py-2 bg-indigo-500 text-white rounded-lg text-sm font-medium hover:bg-indigo-600">ค้นหา</button>
  </form>

  {{-- Ticket List --}}
  <form method="POST" action="{{ route('admin.messages.bulk-action') }}" id="bulkForm">
    @csrf
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl overflow-hidden">

      {{-- Bulk Actions --}}
      <div class="p-3 border-b border-gray-100 dark:border-white/5 flex flex-wrap items-center gap-2 text-sm">
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" id="selectAll" class="rounded border-gray-300">
          <span class="text-gray-600">เลือกทั้งหมด</span>
        </label>
        <div class="flex gap-2 flex-wrap">
          <button type="button" onclick="bulkAction('assign_me')" class="px-3 py-1.5 bg-indigo-50 text-indigo-600 rounded-lg text-xs font-medium hover:bg-indigo-100">
            <i class="bi bi-person-check"></i> มอบให้ฉัน
          </button>
          <button type="button" onclick="bulkAction('resolve')" class="px-3 py-1.5 bg-emerald-50 text-emerald-600 rounded-lg text-xs font-medium hover:bg-emerald-100">
            <i class="bi bi-check2"></i> แก้ไขแล้ว
          </button>
          <button type="button" onclick="bulkAction('close')" class="px-3 py-1.5 bg-gray-100 text-gray-600 rounded-lg text-xs font-medium hover:bg-gray-200">
            <i class="bi bi-lock"></i> ปิด
          </button>
          <button type="button" onclick="bulkAction('delete')" class="px-3 py-1.5 bg-red-50 text-red-600 rounded-lg text-xs font-medium hover:bg-red-100">
            <i class="bi bi-trash"></i> ลบ
          </button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-white/[0.02]">
            <tr>
              <th class="w-8 p-3"></th>
              <th class="text-left p-3 font-semibold text-xs text-gray-600 uppercase">Ticket / Subject</th>
              <th class="text-left p-3 font-semibold text-xs text-gray-600 uppercase">From</th>
              <th class="text-left p-3 font-semibold text-xs text-gray-600 uppercase">Category</th>
              <th class="text-left p-3 font-semibold text-xs text-gray-600 uppercase">Priority</th>
              <th class="text-left p-3 font-semibold text-xs text-gray-600 uppercase">Status</th>
              <th class="text-left p-3 font-semibold text-xs text-gray-600 uppercase">Assigned</th>
              <th class="text-left p-3 font-semibold text-xs text-gray-600 uppercase">SLA</th>
              <th class="text-left p-3 font-semibold text-xs text-gray-600 uppercase">Activity</th>
            </tr>
          </thead>
          <tbody>
            @forelse($tickets as $t)
            <tr class="border-t border-gray-50 dark:border-white/5 hover:bg-gray-50 dark:hover:bg-white/[0.02] {{ $t->isOverdue() ? 'bg-red-50/50 dark:bg-red-500/[0.05]' : '' }}">
              <td class="p-3">
                <input type="checkbox" name="ids[]" value="{{ $t->id }}" class="ticket-checkbox rounded border-gray-300">
              </td>
              <td class="p-3">
                <a href="{{ route('admin.messages.show', $t) }}" class="block">
                  <div class="font-mono text-xs text-indigo-600 mb-0.5">{{ $t->ticket_number }}</div>
                  <div class="font-semibold text-slate-800 dark:text-gray-100 truncate max-w-xs hover:text-indigo-600">{{ $t->subject }}</div>
                  @if($t->reply_count > 0)
                  <div class="text-xs text-gray-500 mt-0.5"><i class="bi bi-chat-left-dots"></i> {{ $t->reply_count }} replies</div>
                  @endif
                </a>
              </td>
              <td class="p-3">
                <div class="font-medium text-sm">{{ $t->name }}</div>
                <div class="text-xs text-gray-500 truncate max-w-[150px]">{{ $t->email }}</div>
              </td>
              <td class="p-3">
                <span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-700 rounded">{{ $t->category_label }}</span>
              </td>
              <td class="p-3">
                <span class="text-xs px-2 py-0.5 bg-{{ $t->priority_color }}-100 text-{{ $t->priority_color }}-700 rounded font-semibold">{{ $t->priority_label }}</span>
              </td>
              <td class="p-3">
                @switch($t->status)
                  @case('new')<span class="text-xs px-2 py-0.5 bg-blue-100 text-blue-700 rounded font-medium">ใหม่</span>@break
                  @case('open')<span class="text-xs px-2 py-0.5 bg-amber-100 text-amber-700 rounded font-medium">เปิดอยู่</span>@break
                  @case('in_progress')<span class="text-xs px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded font-medium">กำลังทำ</span>@break
                  @case('waiting')<span class="text-xs px-2 py-0.5 bg-purple-100 text-purple-700 rounded font-medium">รอลูกค้า</span>@break
                  @case('resolved')<span class="text-xs px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded font-medium">แก้ไขแล้ว</span>@break
                  @case('closed')<span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-700 rounded font-medium">ปิด</span>@break
                @endswitch
              </td>
              <td class="p-3 text-xs">
                @if($t->assignedAdmin)
                  <div class="flex items-center gap-1.5">
                    <div class="w-6 h-6 rounded-full bg-indigo-500 text-white flex items-center justify-center text-[10px] font-bold">
                      {{ mb_strtoupper(mb_substr($t->assignedAdmin->first_name ?? 'A', 0, 1, 'UTF-8'), 'UTF-8') }}
                    </div>
                    <span>{{ $t->assignedAdmin->first_name }}</span>
                  </div>
                @else
                  <span class="text-gray-400">-</span>
                @endif
              </td>
              <td class="p-3 text-xs">
                @if($t->sla_deadline)
                  <span class="{{ $t->isOverdue() ? 'text-red-600 font-semibold' : 'text-gray-600' }}">{{ $t->slaTimeRemaining() }}</span>
                @else
                  <span class="text-gray-400">-</span>
                @endif
              </td>
              <td class="p-3 text-xs text-gray-500">{{ ($t->last_activity_at ?? $t->created_at)->diffForHumans() }}</td>
            </tr>
            @empty
            <tr>
              <td colspan="9" class="p-12 text-center text-gray-500">
                <i class="bi bi-inbox text-3xl text-gray-300"></i>
                <p class="mt-2">ไม่พบ tickets</p>
              </td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </form>

  @if($tickets->hasPages())
  <div class="flex justify-center">{{ $tickets->links() }}</div>
  @endif
</div>

@endsection

@push('scripts')
<script>
document.getElementById('selectAll').addEventListener('change', function() {
  document.querySelectorAll('.ticket-checkbox').forEach(cb => cb.checked = this.checked);
});
function bulkAction(action) {
  const checked = document.querySelectorAll('.ticket-checkbox:checked');
  if (checked.length === 0) return alert('กรุณาเลือกรายการ');
  const texts = { assign_me: 'มอบให้ฉัน', resolve: 'แก้ไขแล้ว', close: 'ปิด', delete: 'ลบ' };
  if (!confirm(`${texts[action]} ${checked.length} รายการ?`)) return;
  const form = document.getElementById('bulkForm');
  let input = form.querySelector('input[name="action"]');
  if (!input) { input = document.createElement('input'); input.type = 'hidden'; input.name = 'action'; form.appendChild(input); }
  input.value = action;
  form.submit();
}
</script>
@endpush
