@extends('layouts.app')

@section('title', 'การแจ้งเตือน')

@section('content')
<div class="max-w-4xl mx-auto px-4 md:px-6 py-6">

  {{-- Header --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h1 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-md">
          <i class="bi bi-bell-fill"></i>
        </span>
        การแจ้งเตือน
      </h1>
      @if($unreadCount > 0)
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          ยังไม่ได้อ่าน <span class="font-semibold text-indigo-600 dark:text-indigo-400">{{ $unreadCount }}</span> รายการ
        </p>
      @endif
    </div>
    @if($unreadCount > 0)
      <button id="markAllReadBtn"
              class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-500/20 text-sm font-medium transition">
        <i class="bi bi-check2-all"></i> อ่านทั้งหมด
      </button>
    @endif
  </div>

  {{-- Filter Tabs --}}
  <div class="mb-5 flex items-center gap-2 border-b border-slate-200 dark:border-white/10">
    <a href="{{ route('notifications.index') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 transition
          {{ $filter === 'all' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300' }}">
      <i class="bi bi-bell"></i> ทั้งหมด
    </a>
    <a href="{{ route('notifications.index', ['filter' => 'unread']) }}"
       class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 transition
          {{ $filter === 'unread' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300' }}">
      <span class="relative flex h-2 w-2">
        @if($unreadCount > 0)
          <span class="absolute inline-flex h-full w-full rounded-full bg-indigo-500 opacity-75 animate-ping"></span>
        @endif
        <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
      </span>
      ยังไม่ได้อ่าน
      @if($unreadCount > 0)
        <span class="inline-flex items-center justify-center min-w-[20px] px-1.5 py-0.5 rounded-full bg-indigo-500 text-white text-[10px] font-bold">{{ $unreadCount }}</span>
      @endif
    </a>
  </div>

  {{-- Notifications List --}}
  @if($notifications->isEmpty())
    <div class="text-center py-20 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-500/20 dark:to-purple-500/20 text-indigo-500 dark:text-indigo-400 mb-4">
        <i class="bi bi-bell-slash text-3xl"></i>
      </div>
      <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-1">
        {{ $filter === 'unread' ? 'ไม่มีการแจ้งเตือนที่ยังไม่ได้อ่าน' : 'ยังไม่มีการแจ้งเตือน' }}
      </h3>
      <p class="text-sm text-slate-500 dark:text-slate-400">เราจะแจ้งเตือนเมื่อมีกิจกรรมใหม่</p>
    </div>
  @else
    <div class="space-y-2" id="notificationList">
      @foreach($notifications as $notification)
      @php
        $typeConfig = [
          'order'         => ['icon' => 'bi-bag-check-fill',    'color' => 'indigo',   'gradient' => 'from-indigo-500 to-purple-600'],
          'payment'       => ['icon' => 'bi-check-circle-fill', 'color' => 'emerald',  'gradient' => 'from-emerald-500 to-teal-500'],
          'slip'          => ['icon' => 'bi-receipt',           'color' => 'amber',    'gradient' => 'from-amber-500 to-orange-500'],
          'download'      => ['icon' => 'bi-download',          'color' => 'blue',     'gradient' => 'from-blue-500 to-cyan-500'],
          'system'        => ['icon' => 'bi-info-circle-fill',  'color' => 'slate',    'gradient' => 'from-slate-500 to-slate-700'],
          'order_status'  => ['icon' => 'bi-bag-check-fill',    'color' => 'indigo',   'gradient' => 'from-indigo-500 to-purple-600'],
          'slip_approved' => ['icon' => 'bi-check-circle-fill', 'color' => 'emerald',  'gradient' => 'from-emerald-500 to-teal-500'],
          'slip_rejected' => ['icon' => 'bi-x-circle-fill',     'color' => 'rose',     'gradient' => 'from-rose-500 to-red-500'],
        ];
        $tc = $typeConfig[$notification->type] ?? $typeConfig['system'];
        $isRead = (bool)$notification->is_read;
      @endphp
      <div class="notification-item relative rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm hover:shadow-md transition-all duration-200
              {{ !$isRead ? 'ring-1 ring-indigo-200 dark:ring-indigo-500/20' : '' }}"
           data-id="{{ $notification->id }}">
        {{-- Unread accent --}}
        @if(!$isRead)
          <div class="absolute left-0 top-4 bottom-4 w-1 rounded-r-full bg-gradient-to-b {{ $tc['gradient'] }}"></div>
        @endif

        <div class="p-4 pl-5">
          <div class="flex items-start gap-3">
            {{-- Icon --}}
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br {{ $tc['gradient'] }} text-white flex items-center justify-center shadow-md flex-shrink-0">
              <i class="bi {{ $tc['icon'] }} text-lg"></i>
            </div>

            {{-- Content --}}
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-2 flex-wrap mb-1">
                <h3 class="font-semibold text-sm {{ $isRead ? 'text-slate-600 dark:text-slate-400' : 'text-slate-900 dark:text-white' }}">
                  {{ $notification->title }}
                </h3>
                <div class="flex items-center gap-2 flex-shrink-0">
                  <span class="text-xs text-slate-500 dark:text-slate-400">{{ $notification->created_at ? $notification->created_at->diffForHumans() : '' }}</span>
                  @if(!$isRead)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-gradient-to-r {{ $tc['gradient'] }} text-white text-[10px] font-bold">ใหม่</span>
                  @endif
                </div>
              </div>
              <p class="text-sm {{ $isRead ? 'text-slate-500 dark:text-slate-400' : 'text-slate-700 dark:text-slate-300' }} leading-relaxed">
                {{ $notification->message }}
              </p>
              <div class="flex items-center gap-3 mt-2">
                <span class="text-xs text-slate-400 dark:text-slate-500">{{ $notification->created_at ? $notification->created_at->format('d/m/Y H:i') : '' }}</span>
                @if(!$isRead)
                  <button class="mark-read-btn text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-medium" data-id="{{ $notification->id }}">
                    <i class="bi bi-check2 mr-0.5"></i>ทำเครื่องหมายอ่านแล้ว
                  </button>
                @endif
                @if($notification->action_url)
                  {{-- Uses the action_href accessor so both relative paths
                       ("orders/5") and legacy absolute URLs render correctly. --}}
                  <a href="{{ $notification->action_href }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                    <i class="bi bi-arrow-right mr-0.5"></i>ดูรายละเอียด
                  </a>
                @endif
              </div>
            </div>
          </div>
        </div>
      </div>
      @endforeach
    </div>

    @if($notifications->hasPages())
      <div class="mt-6 flex justify-center">
        {{ $notifications->withQueryString()->links() }}
      </div>
    @endif
  @endif
</div>

@push('scripts')
<script>
const csrf = document.querySelector('meta[name="csrf-token"]')?.content || window.__csrf || '';

document.querySelectorAll('.mark-read-btn').forEach(btn => {
  btn.addEventListener('click', async function() {
    const id = this.dataset.id;
    const card = this.closest('.notification-item');
    try {
      await fetch(`/api/notifications/${id}/read`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
      });
      card.classList.remove('ring-1', 'ring-indigo-200', 'dark:ring-indigo-500/20');
      card.querySelector('.absolute.left-0')?.remove();
      this.remove();
    } catch(e) {}
  });
});

const markAllBtn = document.getElementById('markAllReadBtn');
if (markAllBtn) {
  markAllBtn.addEventListener('click', async function() {
    try {
      await fetch('/api/notifications/read-all', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
      });
      window.location.reload();
    } catch(e) {}
  });
}
</script>
@endpush
@endsection
