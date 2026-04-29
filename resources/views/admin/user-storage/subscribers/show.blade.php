@extends('layouts.admin')
@section('title', 'สมาชิก — ' . ($user->first_name . ' ' . $user->last_name))

@php
  function fmtBytes3($bytes, $precision = 2) {
      if ($bytes <= 0) return '0 B';
      $units = ['B', 'KB', 'MB', 'GB', 'TB'];
      $pow = min(floor(log($bytes, 1024)), count($units) - 1);
      return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
  }
@endphp

@section('content')
<div class="flex items-center justify-between flex-wrap gap-2 mb-4">
  <div>
    <h4 class="font-bold tracking-tight flex items-center gap-2">
      <i class="bi bi-person-badge text-indigo-500"></i>
      {{ trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: '(ไม่ระบุชื่อ)' }}
    </h4>
    <p class="text-xs text-gray-500 mt-0.5">{{ $user->email }} · ID #{{ $user->id }}</p>
  </div>
  <a href="{{ route('admin.user-storage.subscribers.index') }}" class="px-3 py-1.5 bg-slate-700 hover:bg-slate-800 text-white rounded-lg text-sm">
    <i class="bi bi-arrow-left mr-1"></i> กลับ
  </a>
</div>

@if(session('success'))
  <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm px-4 py-2.5">
    <i class="bi bi-check-circle-fill mr-1"></i>{{ session('success') }}
  </div>
@endif
@if(session('error'))
  <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 text-rose-900 text-sm px-4 py-2.5">
    <i class="bi bi-exclamation-triangle-fill mr-1"></i>{{ session('error') }}
  </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">
  {{-- Plan + usage --}}
  <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
    <div class="p-5 text-white" style="background: linear-gradient(135deg, {{ $plan->color_hex ?? '#6366f1' }}, {{ $plan->color_hex ?? '#6366f1' }}dd);">
      <div class="flex items-start justify-between">
        <div>
          <div class="text-xs opacity-80">แผนปัจจุบัน</div>
          <div class="text-xl font-bold">{{ $plan->name }}</div>
          <div class="text-xs opacity-80 mt-1">โค้ด: {{ $plan->code }}</div>
        </div>
        <div class="text-right">
          @if($plan->isFree())
            <div class="text-lg font-bold">ฟรี</div>
          @else
            <div class="text-lg font-bold">฿{{ number_format((float) $plan->price_thb, 0) }}/เดือน</div>
          @endif
          @if($sub && $sub->current_period_end)
            <div class="text-xs opacity-80 mt-1">
              รอบถัดไป {{ $sub->current_period_end->format('d/m/Y') }}
            </div>
          @endif
        </div>
      </div>
    </div>

    <div class="p-5">
      <div class="flex justify-between text-sm mb-1.5">
        <span>
          ใช้ไป <span class="font-semibold">{{ number_format($summary['storage_used_gb'], 2) }} GB</span>
          / {{ number_format($summary['storage_quota_gb'], 0) }} GB
        </span>
        <span class="font-semibold">{{ $summary['storage_used_pct'] }}%</span>
      </div>
      @php
        $barCls = $summary['storage_critical'] ? 'bg-rose-500'
                : ($summary['storage_warn'] ? 'bg-amber-500' : 'bg-indigo-500');
      @endphp
      <div class="h-2.5 w-full rounded-full bg-gray-100 dark:bg-slate-700 overflow-hidden">
        <div class="h-full {{ $barCls }}" style="width: {{ min(100, $summary['storage_used_pct']) }}%"></div>
      </div>

      <div class="mt-4 grid grid-cols-3 gap-3">
        <div class="rounded-lg border border-gray-100 dark:border-white/5 p-3">
          <div class="text-[10px] text-gray-500 uppercase">ไฟล์ทั้งหมด</div>
          <div class="font-bold text-lg">{{ number_format($fileStats['total_files']) }}</div>
        </div>
        <div class="rounded-lg border border-gray-100 dark:border-white/5 p-3">
          <div class="text-[10px] text-gray-500 uppercase">ถังขยะ</div>
          <div class="font-bold text-lg text-gray-500">{{ number_format($fileStats['trashed_files']) }}</div>
        </div>
        <div class="rounded-lg border border-gray-100 dark:border-white/5 p-3">
          <div class="text-[10px] text-gray-500 uppercase">แชร์ลิงก์</div>
          <div class="font-bold text-lg text-indigo-500">{{ number_format($fileStats['shared_files']) }}</div>
        </div>
      </div>

      {{-- Admin actions --}}
      <div class="mt-4 flex flex-wrap gap-2 pt-4 border-t border-gray-100 dark:border-white/5">
        <form method="POST" action="{{ route('admin.user-storage.subscribers.recalc', $user) }}">
          @csrf
          <button class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100">
            <i class="bi bi-arrow-repeat"></i> คำนวณพื้นที่ใหม่
          </button>
        </form>

        @if($sub && $sub->isUsable())
          @if($sub->isGrace())
            <form method="POST" action="{{ route('admin.user-storage.subscribers.extend-grace', $sub) }}" class="inline-flex items-center gap-1">
              @csrf
              <input type="number" name="days" value="7" min="1" max="60"
                     class="w-16 rounded-lg border-gray-300 text-xs py-1">
              <button class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100">
                <i class="bi bi-hourglass-split"></i> ขยาย Grace
              </button>
            </form>
          @endif

          @if($sub->cancel_at_period_end)
            <form method="POST" action="{{ route('admin.user-storage.subscribers.resume', $sub) }}">
              @csrf
              <button class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100">
                <i class="bi bi-arrow-clockwise"></i> กู้คืนแผน
              </button>
            </form>
          @else
            <form method="POST" action="{{ route('admin.user-storage.subscribers.cancel', $sub) }}"
                  onsubmit="return confirm('ยืนยันยกเลิกแผนของผู้ใช้นี้ (สิ้นสุดเมื่อครบรอบบิล)?')">
              @csrf
              <button class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100">
                <i class="bi bi-x-circle"></i> ยกเลิกแผน
              </button>
            </form>
            <form method="POST" action="{{ route('admin.user-storage.subscribers.cancel', $sub) }}"
                  onsubmit="return confirm('ยืนยันยกเลิกแผนทันที — ผู้ใช้จะเปลี่ยนเป็นแผน Free ทันที?')">
              @csrf
              <input type="hidden" name="immediate" value="1">
              <button class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg bg-rose-600 text-white hover:bg-rose-700">
                <i class="bi bi-fire"></i> ยกเลิกทันที
              </button>
            </form>
          @endif
        @endif
      </div>
    </div>
  </div>

  {{-- Cached usage snapshot --}}
  <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 p-5">
    <h5 class="font-semibold mb-3 text-sm">Usage (cached)</h5>
    <dl class="text-sm space-y-2">
      <div class="flex justify-between">
        <dt class="text-gray-500">Plan code</dt>
        <dd class="font-mono">{{ $user->storage_plan_code }}</dd>
      </div>
      <div class="flex justify-between">
        <dt class="text-gray-500">Plan status</dt>
        <dd class="font-mono">{{ $user->storage_plan_status }}</dd>
      </div>
      <div class="flex justify-between">
        <dt class="text-gray-500">Used (cached)</dt>
        <dd class="font-mono">{{ fmtBytes3($user->storage_used_bytes) }}</dd>
      </div>
      <div class="flex justify-between">
        <dt class="text-gray-500">Quota (cached)</dt>
        <dd class="font-mono">{{ fmtBytes3($user->storage_quota_bytes) }}</dd>
      </div>
      @if($user->storage_renews_at)
        <div class="flex justify-between">
          <dt class="text-gray-500">Renews at</dt>
          <dd class="font-mono text-xs">{{ $user->storage_renews_at->format('d/m/Y H:i') }}</dd>
        </div>
      @endif
    </dl>
  </div>
</div>

{{-- Subscription history --}}
<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden mb-5">
  <div class="px-4 py-3 border-b border-gray-100 dark:border-white/5">
    <h5 class="font-semibold">ประวัติการสมัครแผน</h5>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 dark:bg-slate-700/50 text-xs text-gray-500">
        <tr>
          <th class="px-3 py-2 text-left">#</th>
          <th class="px-3 py-2 text-left">แผน</th>
          <th class="px-3 py-2 text-left">สถานะ</th>
          <th class="px-3 py-2 text-left">เริ่ม</th>
          <th class="px-3 py-2 text-left">สิ้นสุด</th>
          <th class="px-3 py-2 text-left">Grace</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-white/5">
        @forelse($subscriptions as $s)
          <tr>
            <td class="px-3 py-2 font-mono text-xs">#{{ $s->id }}</td>
            <td class="px-3 py-2">{{ $s->plan->name ?? '-' }}</td>
            <td class="px-3 py-2 text-xs">{{ strtoupper($s->status) }}</td>
            <td class="px-3 py-2 text-xs">{{ optional($s->current_period_start)->format('d/m/Y') }}</td>
            <td class="px-3 py-2 text-xs">{{ optional($s->current_period_end)->format('d/m/Y') }}</td>
            <td class="px-3 py-2 text-xs">{{ optional($s->grace_ends_at)->format('d/m/Y') }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="px-3 py-6 text-center text-xs text-gray-400">ยังไม่มีประวัติการสมัคร</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- Invoices --}}
<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
  <div class="px-4 py-3 border-b border-gray-100 dark:border-white/5">
    <h5 class="font-semibold">ใบเสร็จ (50 รายการล่าสุด)</h5>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 dark:bg-slate-700/50 text-xs text-gray-500">
        <tr>
          <th class="px-3 py-2 text-left">เลขใบเสร็จ</th>
          <th class="px-3 py-2 text-left">แผน</th>
          <th class="px-3 py-2 text-right">ยอด</th>
          <th class="px-3 py-2 text-left">สถานะ</th>
          <th class="px-3 py-2 text-left">วันที่</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-white/5">
        @forelse($invoices as $inv)
          @php
            $b = match($inv->status) {
              'paid'     => 'bg-emerald-100 text-emerald-700',
              'pending'  => 'bg-amber-100 text-amber-700',
              'failed'   => 'bg-rose-100 text-rose-700',
              default    => 'bg-gray-100 text-gray-700',
            };
          @endphp
          <tr>
            <td class="px-3 py-2 font-mono text-xs">{{ $inv->invoice_number }}</td>
            <td class="px-3 py-2 text-xs">{{ $inv->subscription->plan->name ?? '-' }}</td>
            <td class="px-3 py-2 text-right">฿{{ number_format((float) $inv->amount_thb, 2) }}</td>
            <td class="px-3 py-2">
              <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold {{ $b }}">{{ strtoupper($inv->status) }}</span>
            </td>
            <td class="px-3 py-2 text-xs">{{ $inv->created_at?->format('d M Y H:i') }}</td>
          </tr>
        @empty
          <tr><td colspan="5" class="px-3 py-6 text-center text-xs text-gray-400">ยังไม่มีใบเสร็จ</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
