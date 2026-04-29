@extends('layouts.admin')

@section('title', 'จัดการการแจ้งเตือน')

@section('content')
<div class="space-y-5">

  {{-- Header --}}
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-800 dark:text-gray-100">
        <i class="bi bi-bell-fill text-indigo-500 mr-2"></i>การแจ้งเตือน
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">จัดการการแจ้งเตือนทั้งหมดในระบบ</p>
    </div>
    <div class="flex gap-2">
      <button onclick="document.getElementById('broadcastModal').classList.remove('hidden')"
              class="px-4 py-2 bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-xl text-sm font-medium hover:shadow-lg transition flex items-center gap-2">
        <i class="bi bi-megaphone"></i> ส่งแจ้งเตือน
      </button>
      @if($stats['unread'] > 0)
      <form method="POST" action="{{ route('admin.notifications.read-all') }}">
        @csrf
        <button type="submit" class="px-4 py-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-200 rounded-xl text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/5 transition flex items-center gap-2">
          <i class="bi bi-check2-all"></i> อ่านทั้งหมด
        </button>
      </form>
      @endif
    </div>
  </div>

  {{-- Stats Cards --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-500/10 text-blue-600 flex items-center justify-center">
          <i class="bi bi-bell"></i>
        </div>
        <div>
          <div class="text-xs text-gray-500 dark:text-gray-400">ทั้งหมด</div>
          <div class="text-lg font-bold text-slate-800 dark:text-gray-100">{{ number_format($stats['total']) }}</div>
        </div>
      </div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-red-50 dark:bg-red-500/10 text-red-600 flex items-center justify-center">
          <i class="bi bi-envelope-exclamation"></i>
        </div>
        <div>
          <div class="text-xs text-gray-500 dark:text-gray-400">ยังไม่อ่าน</div>
          <div class="text-lg font-bold text-red-600">{{ number_format($stats['unread']) }}</div>
        </div>
      </div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 flex items-center justify-center">
          <i class="bi bi-calendar-day"></i>
        </div>
        <div>
          <div class="text-xs text-gray-500 dark:text-gray-400">วันนี้</div>
          <div class="text-lg font-bold text-slate-800 dark:text-gray-100">{{ number_format($stats['today']) }}</div>
        </div>
      </div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-600 flex items-center justify-center">
          <i class="bi bi-calendar-week"></i>
        </div>
        <div>
          <div class="text-xs text-gray-500 dark:text-gray-400">สัปดาห์นี้</div>
          <div class="text-lg font-bold text-slate-800 dark:text-gray-100">{{ number_format($stats['this_week']) }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Filter Bar --}}
  <form class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4 grid grid-cols-1 md:grid-cols-5 gap-3" method="GET">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="ค้นหา..."
           class="col-span-2 px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
    <select name="type" class="px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
      <option value="">ทุกประเภท</option>
      @foreach($typeBreakdown as $type => $count)
        <option value="{{ $type }}" {{ request('type') === $type ? 'selected' : '' }}>{{ $type }} ({{ $count }})</option>
      @endforeach
    </select>
    <select name="status" class="px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
      <option value="">ทุกสถานะ</option>
      <option value="unread" {{ request('status') === 'unread' ? 'selected' : '' }}>ยังไม่อ่าน</option>
      <option value="read" {{ request('status') === 'read' ? 'selected' : '' }}>อ่านแล้ว</option>
    </select>
    <button type="submit" class="px-4 py-2 bg-indigo-500 text-white rounded-lg text-sm font-medium hover:bg-indigo-600 transition">
      <i class="bi bi-search mr-1"></i>ค้นหา
    </button>
  </form>

  {{-- Notifications Table --}}
  <form method="POST" action="{{ route('admin.notifications.bulk-action') }}" id="bulkForm">
    @csrf
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl overflow-hidden">

      {{-- Bulk actions bar --}}
      <div class="p-3 border-b border-gray-100 dark:border-white/5 flex flex-wrap items-center gap-3 text-sm">
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" id="selectAll" class="rounded border-gray-300">
          <span class="text-gray-600 dark:text-gray-400">เลือกทั้งหมด</span>
        </label>
        <div class="flex gap-2">
          <button type="button" onclick="bulkAction('mark_read')" class="px-3 py-1.5 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 rounded-lg text-xs font-medium hover:bg-emerald-100 transition">
            <i class="bi bi-check2"></i> อ่านแล้ว
          </button>
          <button type="button" onclick="bulkAction('mark_unread')" class="px-3 py-1.5 bg-amber-50 dark:bg-amber-500/10 text-amber-600 rounded-lg text-xs font-medium hover:bg-amber-100 transition">
            <i class="bi bi-envelope"></i> ยังไม่อ่าน
          </button>
          <button type="button" onclick="bulkAction('delete')" class="px-3 py-1.5 bg-red-50 dark:bg-red-500/10 text-red-600 rounded-lg text-xs font-medium hover:bg-red-100 transition">
            <i class="bi bi-trash"></i> ลบ
          </button>
        </div>
      </div>

      {{-- List --}}
      @forelse($notifications as $n)
      <div class="flex items-start gap-3 p-4 border-b border-gray-50 dark:border-white/5 hover:bg-gray-50 dark:hover:bg-white/[0.02] transition {{ !$n->is_read ? 'bg-indigo-50/30 dark:bg-indigo-500/[0.05]' : '' }}">
        <input type="checkbox" name="ids[]" value="{{ $n->id }}" class="mt-1.5 rounded border-gray-300 notif-checkbox">

        {{-- Icon --}}
        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0
                    {{ match($n->type) {
                      'order' => 'bg-blue-100 text-blue-600',
                      'payment' => 'bg-emerald-100 text-emerald-600',
                      'slip' => 'bg-amber-100 text-amber-600',
                      'user' => 'bg-purple-100 text-purple-600',
                      'photographer' => 'bg-indigo-100 text-indigo-600',
                      'contact' => 'bg-pink-100 text-pink-600',
                      'review' => 'bg-yellow-100 text-yellow-600',
                      'refund' => 'bg-red-100 text-red-600',
                      'security' => 'bg-red-100 text-red-600',
                      'system' => 'bg-slate-100 text-slate-600',
                      default => 'bg-gray-100 text-gray-600',
                    } }}">
          <i class="bi bi-{{ match($n->type) {
            'order' => 'cart-check',
            'payment' => 'cash-coin',
            'slip' => 'receipt',
            'user' => 'person-plus',
            'photographer' => 'camera',
            'contact' => 'envelope',
            'review' => 'star-fill',
            'refund' => 'arrow-counterclockwise',
            'security' => 'shield-exclamation',
            'system' => 'gear',
            default => 'bell',
          } }}"></i>
        </div>

        {{-- Content --}}
        <div class="flex-1 min-w-0">
          <div class="flex items-start justify-between gap-2">
            <div class="flex-1">
              <div class="flex items-center gap-2">
                <h4 class="font-semibold text-sm text-slate-800 dark:text-gray-100 truncate">{{ $n->title }}</h4>
                @if(!$n->is_read)
                  <span class="inline-flex items-center w-2 h-2 rounded-full bg-indigo-500"></span>
                @endif
                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-white/5 text-gray-600 dark:text-gray-400">{{ $n->type }}</span>
              </div>
              <p class="text-sm text-gray-600 dark:text-gray-400 mt-0.5 line-clamp-2">{{ $n->message }}</p>
              <div class="flex items-center gap-3 text-xs text-gray-400 dark:text-gray-500 mt-2">
                <span><i class="bi bi-clock mr-1"></i>{{ $n->created_at?->diffForHumans() }}</span>
                <span class="hidden sm:inline">{{ $n->created_at?->format('d/m/Y H:i') }}</span>
                @if($n->ref_id)
                  <span>Ref: #{{ $n->ref_id }}</span>
                @endif
              </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-1 shrink-0">
              @if($n->link)
                <a href="{{ url($n->link) }}"
                   class="w-8 h-8 rounded-lg text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 flex items-center justify-center transition" title="เปิดลิงก์">
                  <i class="bi bi-box-arrow-up-right text-sm"></i>
                </a>
              @endif
              @if(!$n->is_read)
                <form method="POST" action="{{ route('admin.notifications.read', $n->id) }}" class="inline">
                  @csrf
                  <button type="submit" class="w-8 h-8 rounded-lg text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 flex items-center justify-center transition" title="ทำเครื่องหมายอ่านแล้ว">
                    <i class="bi bi-check2 text-sm"></i>
                  </button>
                </form>
              @endif
              <form method="POST" action="{{ route('admin.notifications.destroy', $n->id) }}" class="inline" onsubmit="return confirm('ลบการแจ้งเตือนนี้?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="w-8 h-8 rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10 flex items-center justify-center transition" title="ลบ">
                  <i class="bi bi-trash text-sm"></i>
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
      @empty
      <div class="p-12 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-white/5 text-gray-400 mb-3">
          <i class="bi bi-bell-slash text-2xl"></i>
        </div>
        <p class="text-gray-500 dark:text-gray-400 font-medium">ไม่พบการแจ้งเตือน</p>
      </div>
      @endforelse

    </div>
  </form>

  {{-- Pagination --}}
  @if($notifications->hasPages())
  <div class="flex justify-center">
    {{ $notifications->links() }}
  </div>
  @endif

</div>

{{-- Broadcast Modal --}}
<div id="broadcastModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-slate-800 rounded-2xl max-w-lg w-full p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-bold text-slate-800 dark:text-gray-100">
        <i class="bi bi-megaphone text-indigo-500 mr-2"></i>ส่งแจ้งเตือนถึงผู้ใช้
      </h3>
      <button onclick="document.getElementById('broadcastModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <form method="POST" action="{{ route('admin.notifications.broadcast') }}" class="space-y-3">
      @csrf
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ส่งไปยัง</label>
        <select name="target" class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg bg-white dark:bg-slate-900 dark:text-gray-200" required>
          <option value="all">ทุกคน (All Users)</option>
          <option value="customers">เฉพาะลูกค้า (Customers)</option>
          <option value="photographers">เฉพาะช่างภาพ (Photographers)</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">หัวข้อ</label>
        <input type="text" name="title" maxlength="255" required
               class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg bg-white dark:bg-slate-900 dark:text-gray-200">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ข้อความ</label>
        <textarea name="message" rows="4" maxlength="1000" required
                  class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg bg-white dark:bg-slate-900 dark:text-gray-200"></textarea>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ลิงก์ (ถ้ามี)</label>
        <input type="text" name="action_url" placeholder="events, orders/123, ..."
               class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg bg-white dark:bg-slate-900 dark:text-gray-200">
      </div>
      <div class="flex gap-2 pt-3">
        <button type="button" onclick="document.getElementById('broadcastModal').classList.add('hidden')"
                class="flex-1 px-4 py-2 border border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-300 rounded-lg font-medium hover:bg-gray-50 dark:hover:bg-white/5 transition">
          ยกเลิก
        </button>
        <button type="submit"
                class="flex-1 px-4 py-2 bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-lg font-medium hover:shadow-lg transition">
          <i class="bi bi-send mr-1"></i>ส่ง
        </button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
// Select all toggle
document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.notif-checkbox').forEach(cb => cb.checked = this.checked);
});

// Bulk action submit
function bulkAction(action) {
    const checked = document.querySelectorAll('.notif-checkbox:checked');
    if (checked.length === 0) {
        alert('กรุณาเลือกรายการอย่างน้อย 1 รายการ');
        return;
    }

    const actionText = { delete: 'ลบ', mark_read: 'ทำเครื่องหมายอ่านแล้ว', mark_unread: 'ทำเครื่องหมายยังไม่อ่าน' };
    if (!confirm(`ต้องการ${actionText[action]} ${checked.length} รายการ?`)) return;

    const form = document.getElementById('bulkForm');
    let input = form.querySelector('input[name="action"]');
    if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'action';
        form.appendChild(input);
    }
    input.value = action;
    form.submit();
}
</script>
@endpush
