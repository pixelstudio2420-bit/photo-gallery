@extends('layouts.admin')
@section('title', 'Cloud Storage — ภาพรวม')

@php
  function fmtBytes($bytes, $precision = 2) {
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
      <i class="bi bi-cloud-fill text-indigo-500"></i> Cloud Storage
      <span class="text-xs font-normal text-gray-400 ml-2">/ ระบบพื้นที่เก็บไฟล์ผู้ใช้ทั่วไป</span>
    </h4>
    <p class="text-xs text-gray-500 mt-0.5">ภาพรวมรายได้ สมาชิก และการใช้พื้นที่ทั้งหมด</p>
  </div>
  <div class="flex gap-2">
    <a href="{{ route('admin.user-storage.plans.index') }}" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm">
      <i class="bi bi-layers mr-1"></i> จัดการแผน
    </a>
    <a href="{{ route('admin.user-storage.subscribers.index') }}" class="px-3 py-1.5 bg-slate-700 hover:bg-slate-800 text-white rounded-lg text-sm">
      <i class="bi bi-people mr-1"></i> สมาชิก
    </a>
    <a href="{{ route('admin.user-storage.files.index') }}" class="px-3 py-1.5 bg-slate-700 hover:bg-slate-800 text-white rounded-lg text-sm">
      <i class="bi bi-folder2-open mr-1"></i> ไฟล์ทั้งหมด
    </a>
  </div>
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

{{-- System toggles --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
  <div class="bg-white dark:bg-slate-800 rounded-xl p-5 border {{ $settings['user_storage_enabled'] ? 'border-emerald-200 dark:border-emerald-500/30' : 'border-gray-200 dark:border-white/5' }}">
    <div class="flex items-start justify-between">
      <div>
        <div class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-1">Functional Toggle</div>
        <div class="font-bold text-lg">ระบบ Cloud Storage</div>
        <p class="text-sm text-gray-500 mt-1">เปิด/ปิดฟีเจอร์ทั้งหมดรวมถึงการอัปโหลดและดาวน์โหลด</p>
      </div>
      <form method="POST" action="{{ route('admin.user-storage.toggle') }}">
        @csrf
        <input type="hidden" name="key" value="user_storage_enabled">
        <button class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-semibold {{ $settings['user_storage_enabled'] ? 'bg-emerald-500 text-white' : 'bg-gray-200 text-gray-600' }}">
          <i class="bi {{ $settings['user_storage_enabled'] ? 'bi-toggle-on' : 'bi-toggle-off' }} text-lg"></i>
          {{ $settings['user_storage_enabled'] ? 'เปิดอยู่' : 'ปิดอยู่' }}
        </button>
      </form>
    </div>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-xl p-5 border {{ $settings['sales_mode_storage_enabled'] ? 'border-indigo-200 dark:border-indigo-500/30' : 'border-gray-200 dark:border-white/5' }}">
    <div class="flex items-start justify-between">
      <div>
        <div class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-1">Sales Mode</div>
        <div class="font-bold text-lg">โหมดขายพื้นที่เก็บไฟล์</div>
        <p class="text-sm text-gray-500 mt-1">เปิดให้ผู้ใช้สมัครแผนแบบเสียเงินได้ (หน้า pricing)</p>
      </div>
      <form method="POST" action="{{ route('admin.user-storage.toggle') }}">
        @csrf
        <input type="hidden" name="key" value="sales_mode_storage_enabled">
        <button class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-semibold {{ $settings['sales_mode_storage_enabled'] ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-600' }}">
          <i class="bi {{ $settings['sales_mode_storage_enabled'] ? 'bi-toggle-on' : 'bi-toggle-off' }} text-lg"></i>
          {{ $settings['sales_mode_storage_enabled'] ? 'เปิดขาย' : 'ปิดขาย' }}
        </button>
      </form>
    </div>
  </div>
</div>

{{-- KPI cards --}}
<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
  <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
    <div class="text-xs text-gray-500">สมาชิกใช้งานอยู่</div>
    <div class="text-2xl font-bold text-indigo-500">{{ number_format($kpis['active_subscribers']) }}</div>
    <div class="text-[11px] text-gray-400 mt-1">Active + Grace</div>
  </div>
  <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
    <div class="text-xs text-gray-500">MRR (รายเดือน)</div>
    <div class="text-2xl font-bold text-emerald-500">฿{{ number_format($kpis['mrr'], 0) }}</div>
    <div class="text-[11px] text-gray-400 mt-1">Monthly Recurring</div>
  </div>
  <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
    <div class="text-xs text-gray-500">รายได้ 30 วันล่าสุด</div>
    <div class="text-2xl font-bold">฿{{ number_format($kpis['last30_paid'], 0) }}</div>
    <div class="text-[11px] text-gray-400 mt-1">จ่ายสำเร็จ</div>
  </div>
  <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
    <div class="text-xs text-gray-500">อยู่ระหว่าง Grace</div>
    <div class="text-2xl font-bold text-amber-500">{{ number_format($kpis['in_grace']) }}</div>
    <div class="text-[11px] text-gray-400 mt-1">จ่ายล้มเหลว รอชำระ</div>
  </div>
  <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-white/5">
    <div class="text-xs text-gray-500">พื้นที่ใช้ไป</div>
    <div class="text-2xl font-bold text-violet-500">{{ number_format($kpis['total_used_gb'], 2) }} GB</div>
    <div class="text-[11px] text-gray-400 mt-1">{{ number_format($kpis['total_files']) }} ไฟล์</div>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">
  {{-- Plan distribution --}}
  <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 lg:col-span-1">
    <div class="px-4 py-3 border-b border-gray-100 dark:border-white/5">
      <h5 class="font-semibold">การกระจายตามแผน</h5>
    </div>
    <div class="p-4 space-y-3">
      @php $totalSubs = max(1, collect($plansKpi)->sum('count')); @endphp
      @foreach($plansKpi as $row)
        @php
          $plan = $row['plan'];
          $pct = round(($row['count'] / $totalSubs) * 100, 1);
          $accent = $plan->color_hex ?: '#6366f1';
        @endphp
        <div>
          <div class="flex justify-between text-sm mb-1">
            <span class="font-medium">
              <span class="inline-block w-2.5 h-2.5 rounded-sm mr-1.5" style="background:{{ $accent }};"></span>
              {{ $plan->name }}
            </span>
            <span class="text-gray-500">{{ number_format($row['count']) }} ({{ $pct }}%)</span>
          </div>
          <div class="h-1.5 bg-gray-100 dark:bg-slate-700 rounded-full overflow-hidden">
            <div class="h-full rounded-full" style="width:{{ $pct }}%;background:{{ $accent }};"></div>
          </div>
        </div>
      @endforeach
    </div>
  </div>

  {{-- Grace-expiring soon --}}
  <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 lg:col-span-2">
    <div class="px-4 py-3 border-b border-gray-100 dark:border-white/5 flex items-center justify-between">
      <h5 class="font-semibold">
        <i class="bi bi-hourglass-split text-amber-500 mr-1"></i>ใกล้หมด Grace Period
      </h5>
      <span class="text-xs text-gray-400">Top 10 เรียงตามวันหมด</span>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-slate-700/50 text-xs text-gray-500">
          <tr>
            <th class="px-3 py-2 text-left">ผู้ใช้</th>
            <th class="px-3 py-2 text-left">แผน</th>
            <th class="px-3 py-2 text-left">Grace หมด</th>
            <th class="px-3 py-2 text-right">-</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
          @forelse($graceSoon as $g)
            <tr>
              <td class="px-3 py-2">
                <div class="font-medium text-gray-800 dark:text-white/90">
                  {{ trim(($g->user->first_name ?? '') . ' ' . ($g->user->last_name ?? '')) ?: $g->user->email }}
                </div>
                <div class="text-[10px] text-gray-400">{{ $g->user->email ?? '-' }}</div>
              </td>
              <td class="px-3 py-2 text-xs">{{ $g->plan->name ?? '-' }}</td>
              <td class="px-3 py-2 text-xs text-amber-600 font-semibold">
                {{ optional($g->grace_ends_at)->format('d/m/Y H:i') }}
              </td>
              <td class="px-3 py-2 text-right">
                <a href="{{ route('admin.user-storage.subscribers.show', $g->user_id) }}" class="text-xs text-indigo-500 hover:underline">
                  ดูรายละเอียด
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="px-3 py-6 text-center text-xs text-gray-400">ไม่มีสมาชิกที่อยู่ใน Grace Period</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- Recent invoices --}}
<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden mb-5">
  <div class="px-4 py-3 border-b border-gray-100 dark:border-white/5 flex items-center justify-between">
    <h5 class="font-semibold">ใบเสร็จล่าสุด</h5>
    <span class="text-xs text-gray-400">20 รายการ</span>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 dark:bg-slate-700/50 text-xs text-gray-500">
        <tr>
          <th class="px-3 py-2 text-left">เลขใบเสร็จ</th>
          <th class="px-3 py-2 text-left">ผู้ใช้</th>
          <th class="px-3 py-2 text-left">แผน</th>
          <th class="px-3 py-2 text-right">ยอดเงิน</th>
          <th class="px-3 py-2 text-left">สถานะ</th>
          <th class="px-3 py-2 text-left">วันที่</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-white/5">
        @forelse($recentInvoices as $inv)
          @php
            $badge = match($inv->status) {
              'paid'     => 'bg-emerald-100 text-emerald-700',
              'pending'  => 'bg-amber-100 text-amber-700',
              'failed'   => 'bg-rose-100 text-rose-700',
              'refunded' => 'bg-gray-100 text-gray-700',
              default    => 'bg-gray-100 text-gray-700',
            };
          @endphp
          <tr>
            <td class="px-3 py-2 font-mono text-xs">{{ $inv->invoice_number }}</td>
            <td class="px-3 py-2">
              @if($inv->user)
                <a href="{{ route('admin.user-storage.subscribers.show', $inv->user_id) }}" class="text-indigo-500 hover:underline">
                  {{ trim(($inv->user->first_name ?? '') . ' ' . ($inv->user->last_name ?? '')) ?: $inv->user->email }}
                </a>
              @else
                <span class="text-gray-400">#{{ $inv->user_id }}</span>
              @endif
            </td>
            <td class="px-3 py-2 text-xs">{{ $inv->subscription->plan->name ?? '-' }}</td>
            <td class="px-3 py-2 text-right font-semibold">฿{{ number_format((float) $inv->amount_thb, 2) }}</td>
            <td class="px-3 py-2">
              <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold {{ $badge }}">{{ strtoupper($inv->status) }}</span>
            </td>
            <td class="px-3 py-2 text-xs text-gray-500">{{ $inv->created_at?->format('d M H:i') }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="px-3 py-6 text-center text-xs text-gray-400">ยังไม่มีใบเสร็จในระบบ</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- Default settings --}}
<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 p-5">
  <h5 class="font-semibold mb-4">
    <i class="bi bi-sliders mr-1 text-indigo-500"></i>การตั้งค่าเริ่มต้น
  </h5>
  <form method="POST" action="{{ route('admin.user-storage.settings') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
    @csrf
    <div>
      <label class="text-xs text-gray-500 mb-1 block">แผนเริ่มต้นสำหรับผู้ใช้ใหม่</label>
      <select name="default_user_storage_plan" class="w-full rounded-lg border-gray-300 dark:bg-slate-700 dark:border-white/10 text-sm">
        @foreach($plans as $p)
          <option value="{{ $p->code }}" {{ $settings['default_user_storage_plan'] === $p->code ? 'selected' : '' }}>
            {{ $p->name }} ({{ number_format($p->storage_gb, 0) }} GB)
          </option>
        @endforeach
      </select>
    </div>
    <div>
      <label class="text-xs text-gray-500 mb-1 block">ระยะเวลา Grace Period (วัน)</label>
      <input type="number" min="0" max="60" name="user_storage_grace_period_days"
             value="{{ $settings['grace_period_days'] }}"
             class="w-full rounded-lg border-gray-300 dark:bg-slate-700 dark:border-white/10 text-sm">
    </div>
    <div class="flex items-end">
      <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm">
        <i class="bi bi-save mr-1"></i>บันทึก
      </button>
    </div>
  </form>
</div>
@endsection
