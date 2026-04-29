@extends('layouts.admin')

@section('title', $photographer->display_name)

@section('content')
<div class="mb-6">
  <a href="{{ route('admin.photographers.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-indigo-600 transition">
    <i class="bi bi-arrow-left mr-1"></i> กลับไปรายการช่างภาพ
  </a>
</div>

{{-- Flash messages --}}
@if(session('success'))
<div class="mb-4 px-4 py-3 rounded-xl bg-emerald-50 text-emerald-700 text-sm border border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400">
  <i class="bi bi-check-circle mr-1"></i> {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="mb-4 px-4 py-3 rounded-xl bg-red-50 text-red-700 text-sm border border-red-200 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400">
  <i class="bi bi-exclamation-triangle mr-1"></i> {{ session('error') }}
</div>
@endif
@if(session('new_password'))
<div x-data="{ pw: @js(session('new_password')), copied: false, show: true }" x-show="show" x-transition
     class="mb-4 px-4 py-4 rounded-xl bg-amber-50 border border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/30">
  <div class="flex items-start gap-3">
    <i class="bi bi-key-fill text-amber-600 dark:text-amber-400 text-xl mt-0.5"></i>
    <div class="flex-1 min-w-0">
      <div class="text-sm font-semibold text-amber-800 dark:text-amber-300 mb-1">รหัสผ่านใหม่ (แสดงเพียงครั้งเดียว)</div>
      <p class="text-xs text-amber-700/80 dark:text-amber-400/70 mb-2">กรุณาคัดลอกและส่งต่อให้ช่างภาพอย่างปลอดภัย — หลังรีเฟรชหน้านี้จะไม่สามารถดูได้อีก</p>
      <div class="flex items-center gap-2">
        <code class="flex-1 px-3 py-2 rounded-lg bg-white dark:bg-slate-900 border border-amber-200 dark:border-amber-500/30 font-mono text-base select-all" x-text="pw"></code>
        <button type="button" @click="navigator.clipboard.writeText(pw); copied = true; setTimeout(() => copied = false, 2000)"
                class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium bg-amber-500 text-white hover:bg-amber-600 transition">
          <i class="bi" :class="copied ? 'bi-check-lg' : 'bi-clipboard'"></i>
          <span class="ml-1.5" x-text="copied ? 'คัดลอกแล้ว' : 'คัดลอก'"></span>
        </button>
        <button type="button" @click="show = false" class="inline-flex items-center px-2 py-2 rounded-lg text-sm text-amber-600 hover:bg-amber-100 dark:hover:bg-amber-500/20 transition">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    </div>
  </div>
</div>
@endif

@php
  $statusMap = [
    'approved'  => ['bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-600', 'label' => 'อนุมัติแล้ว', 'dot' => 'bg-emerald-500', 'border' => 'border-emerald-200'],
    'pending'   => ['bg' => 'bg-amber-500/10',   'text' => 'text-amber-600',   'label' => 'รอตรวจสอบ', 'dot' => 'bg-amber-500', 'border' => 'border-amber-200'],
    'suspended' => ['bg' => 'bg-red-500/10',     'text' => 'text-red-500',     'label' => 'ระงับ', 'dot' => 'bg-red-500', 'border' => 'border-red-200'],
  ];
  $st = $statusMap[$photographer->status] ?? $statusMap['pending'];
@endphp

{{-- ═══ Hero Card ═══ --}}
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6 dark:bg-slate-800 dark:border-white/[0.06]">
  <div class="h-24 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-400"></div>
  <div class="px-6 pb-6 -mt-10">
    <div class="flex flex-col md:flex-row md:items-end gap-4">
      {{-- Avatar --}}
      <div class="shrink-0">
        <div class="w-20 h-20 rounded-2xl border-4 border-white dark:border-slate-800 overflow-hidden shadow-lg">
          <x-avatar :src="$photographer->avatar"
               :name="$photographer->display_name ?? 'P'"
               :user-id="$photographer->user_id ?? $photographer->id"
               size="xl"
               rounded="rounded" />
        </div>
      </div>
      {{-- Info --}}
      <div class="flex-1 min-w-0 pt-2">
        <div class="flex items-center gap-3 flex-wrap">
          <h3 class="text-xl font-bold tracking-tight">{{ $photographer->display_name }}</h3>
          <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $st['bg'] }} {{ $st['text'] }}">
            <span class="w-1.5 h-1.5 rounded-full {{ $st['dot'] }} mr-1.5"></span>
            {{ $st['label'] }}
          </span>
        </div>
        <div class="flex items-center gap-4 mt-1 text-sm text-gray-500 flex-wrap">
          <span><i class="bi bi-hash mr-0.5"></i>{{ $photographer->photographer_code }}</span>
          <span><i class="bi bi-envelope mr-0.5"></i>{{ $photographer->user->email ?? '-' }}</span>
          <span><i class="bi bi-person mr-0.5"></i>{{ ($photographer->user->first_name ?? '') . ' ' . ($photographer->user->last_name ?? '') }}</span>
          @if($photographer->portfolio_url)
          <a href="{{ $photographer->portfolio_url }}" target="_blank" class="text-indigo-500 hover:text-indigo-700 transition">
            <i class="bi bi-globe mr-0.5"></i>Portfolio
          </a>
          @endif
        </div>
      </div>
      {{-- Action buttons --}}
      <div class="flex items-center gap-2 shrink-0 flex-wrap">
        <a href="{{ route('admin.photographers.edit', $photographer) }}" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-indigo-500/10 text-indigo-600 hover:bg-indigo-500/20 transition">
          <i class="bi bi-pencil mr-1"></i> แก้ไข
        </a>
        @php
          $pgAuthProvider = strtolower((string) ($photographer->user->auth_provider ?? 'local'));
          $pgIsLocal = in_array($pgAuthProvider, ['', 'local', 'email'], true);
        @endphp
        @if($photographer->user && $pgIsLocal)
        <form method="POST" action="{{ route('admin.photographers.reset-password', $photographer) }}" class="inline" onsubmit="return confirm('ยืนยันรีเซ็ตรหัสผ่านของ {{ $photographer->display_name }}?')">
          @csrf
          <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-amber-500/10 text-amber-600 hover:bg-amber-500/20 transition">
            <i class="bi bi-key mr-1"></i> รีเซ็ตรหัส
          </button>
        </form>
        @elseif($photographer->user)
        <span class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-gray-100 text-gray-400 cursor-not-allowed dark:bg-white/[0.04] dark:text-gray-500" title="บัญชีสมัครผ่าน {{ ucfirst($pgAuthProvider) }}">
          <i class="bi bi-key-fill mr-1"></i> รีเซ็ตไม่ได้ ({{ ucfirst($pgAuthProvider) }})
        </span>
        @endif
        @if($photographer->status === 'pending')
        <form method="POST" action="{{ route('admin.photographers.approve', $photographer) }}" class="inline">
          @csrf
          <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-emerald-500 text-white hover:bg-emerald-600 transition">
            <i class="bi bi-check-lg mr-1"></i> อนุมัติ
          </button>
        </form>
        @elseif($photographer->status === 'approved')
        <form method="POST" action="{{ route('admin.photographers.suspend', $photographer) }}" class="inline" onsubmit="return confirm('ยืนยันระงับช่างภาพ {{ $photographer->display_name }}?')">
          @csrf
          <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-red-500/10 text-red-500 hover:bg-red-500/20 transition">
            <i class="bi bi-pause-circle mr-1"></i> ระงับ
          </button>
        </form>
        @elseif($photographer->status === 'suspended')
        <form method="POST" action="{{ route('admin.photographers.reactivate', $photographer) }}" class="inline">
          @csrf
          <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-emerald-500 text-white hover:bg-emerald-600 transition">
            <i class="bi bi-play-circle mr-1"></i> เปิดใช้งาน
          </button>
        </form>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- ═══ Stats Row ═══ --}}
<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3 mb-6">
  @php
    $statCards = [
      ['icon' => 'bi-calendar-event', 'color' => 'indigo', 'value' => $stats['events_count'], 'label' => 'อีเวนต์'],
      ['icon' => 'bi-image', 'color' => 'purple', 'value' => number_format($stats['photos_count']), 'label' => 'รูปภาพ'],
      ['icon' => 'bi-cart-check', 'color' => 'blue', 'value' => number_format($stats['total_orders']), 'label' => 'คำสั่งซื้อ'],
      ['icon' => 'bi-cash-stack', 'color' => 'emerald', 'value' => '฿'.number_format($stats['total_revenue'],0), 'label' => 'รายได้รวม'],
      ['icon' => 'bi-wallet2', 'color' => 'teal', 'value' => '฿'.number_format($stats['total_earnings'],0), 'label' => 'จ่ายแล้ว'],
      ['icon' => 'bi-clock-history', 'color' => 'amber', 'value' => '฿'.number_format($stats['pending_payout'],0), 'label' => 'รอจ่าย'],
      ['icon' => 'bi-star-fill', 'color' => 'yellow', 'value' => $stats['avg_rating'] > 0 ? number_format($stats['avg_rating'],1) : '-', 'label' => 'คะแนนเฉลี่ย'],
      ['icon' => 'bi-chat-dots', 'color' => 'pink', 'value' => $stats['reviews_count'], 'label' => 'รีวิว'],
    ];
  @endphp
  @foreach($statCards as $sc)
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="py-3 px-3 text-center">
      <i class="bi {{ $sc['icon'] }} text-{{ $sc['color'] }}-500 text-lg mb-1"></i>
      <div class="font-bold text-lg leading-tight">{{ $sc['value'] }}</div>
      <small class="text-gray-400 text-xs">{{ $sc['label'] }}</small>
    </div>
  </div>
  @endforeach
</div>

{{-- ═══ Tabs Section ═══ --}}
<div x-data="{ tab: 'overview' }" class="mb-6">
  {{-- Tab Navigation --}}
  <div class="bg-white rounded-t-xl border border-b-0 border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <nav class="flex gap-0 px-4 overflow-x-auto">
      @php
        $tabs = [
          'overview' => ['icon' => 'bi-person-badge', 'label' => 'ภาพรวม'],
          'events'   => ['icon' => 'bi-calendar-event', 'label' => 'อีเวนต์'],
          'earnings' => ['icon' => 'bi-wallet2', 'label' => 'รายได้'],
          'reviews'  => ['icon' => 'bi-star', 'label' => 'รีวิว'],
        ];
      @endphp
      @foreach($tabs as $key => $t)
      <button @click="tab = '{{ $key }}'"
              :class="tab === '{{ $key }}' ? 'border-indigo-500 text-indigo-600 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700'"
              class="flex items-center gap-1.5 px-4 py-3 text-sm border-b-2 transition whitespace-nowrap">
        <i class="bi {{ $t['icon'] }}"></i> {{ $t['label'] }}
      </button>
      @endforeach
    </nav>
  </div>

  {{-- Tab Content --}}
  <div class="bg-white rounded-b-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">

    {{-- ── Overview Tab ── --}}
    <div x-show="tab === 'overview'" x-transition class="p-6">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Profile Info --}}
        <div>
          <h6 class="font-semibold text-sm text-gray-500 uppercase tracking-wider mb-4">
            <i class="bi bi-person-badge mr-1 text-indigo-500"></i>ข้อมูลโปรไฟล์
          </h6>
          <div class="space-y-4">
            <div>
              <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">ชื่อที่แสดง</label>
              <p class="mt-0.5 font-medium">{{ $photographer->display_name }}</p>
            </div>
            <div>
              <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Bio</label>
              <p class="mt-0.5 text-gray-600 dark:text-gray-300">{{ $photographer->bio ?: '-' }}</p>
            </div>
            <div>
              <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Portfolio URL</label>
              <p class="mt-0.5">
                @if($photographer->portfolio_url)
                  <a href="{{ $photographer->portfolio_url }}" target="_blank" class="text-indigo-500 hover:underline">{{ $photographer->portfolio_url }}</a>
                @else
                  <span class="text-gray-400">-</span>
                @endif
              </p>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">ค่าคอมมิชชั่น</label>
                <div class="mt-0.5 flex items-center gap-2">
                  <span class="text-xl font-bold text-indigo-600">{{ number_format($photographer->commission_rate, 0) }}%</span>
                  <span class="text-xs text-gray-400">(ช่างภาพได้รับ)</span>
                </div>
              </div>
              <div>
                <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">ค่าแพลตฟอร์ม</label>
                <p class="mt-0.5 text-xl font-bold text-gray-400">{{ number_format(100 - $photographer->commission_rate, 0) }}%</p>
              </div>
            </div>
          </div>

          {{-- Quick Commission Adjust --}}
          @php
            $cMinShow = (float) \App\Models\AppSetting::get('min_commission_rate', 0);
            $cMaxShow = (float) \App\Models\AppSetting::get('max_commission_rate', 100);
          @endphp
          <div class="mt-6 p-4 rounded-xl bg-gray-50 dark:bg-white/[0.03]" x-data="{ editing: false }">
            <div class="flex items-center justify-between">
              <span class="text-sm font-semibold text-gray-600 dark:text-gray-300"><i class="bi bi-sliders mr-1"></i>ปรับค่าคอมมิชชั่น</span>
              <button @click="editing = !editing" class="text-xs text-indigo-500 hover:text-indigo-700" x-text="editing ? 'ยกเลิก' : 'แก้ไข'"></button>
            </div>
            <form method="POST" action="{{ route('admin.photographers.adjust-commission', $photographer) }}" x-show="editing" x-transition class="mt-3 flex items-center gap-2">
              @csrf
              <input type="number" name="commission_rate" value="{{ $photographer->commission_rate }}" min="{{ $cMinShow }}" max="{{ $cMaxShow }}" step="1"
                     class="w-24 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">
              <span class="text-gray-500 text-sm">% <small class="text-gray-400">({{ $cMinShow }}–{{ $cMaxShow }})</small></span>
              <button type="submit" class="px-4 py-2 bg-indigo-500 text-white text-sm rounded-lg hover:bg-indigo-600 transition">บันทึก</button>
            </form>
          </div>
        </div>

        {{-- Bank Info + Account Info --}}
        <div>
          <h6 class="font-semibold text-sm text-gray-500 uppercase tracking-wider mb-4">
            <i class="bi bi-bank mr-1 text-emerald-500"></i>ข้อมูลบัญชีธนาคาร
          </h6>
          <div class="space-y-4">
            @if($photographer->bank_name || $photographer->promptpay_number)
            <div class="p-4 rounded-xl border border-gray-100 dark:border-white/[0.06]">
              @if($photographer->bank_name)
              <div class="mb-3">
                <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">ธนาคาร</label>
                <p class="mt-0.5 font-medium">{{ $photographer->bank_name }}</p>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">เลขบัญชี</label>
                  <p class="mt-0.5 font-mono">{{ $photographer->bank_account_number ?: '-' }}</p>
                </div>
                <div>
                  <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">ชื่อบัญชี</label>
                  <p class="mt-0.5">{{ $photographer->bank_account_name ?: '-' }}</p>
                </div>
              </div>
              @endif
              @if($photographer->promptpay_number)
              <div class="mt-3 pt-3 border-t border-gray-100 dark:border-white/[0.06]">
                <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">PromptPay</label>
                <p class="mt-0.5 font-mono font-medium">{{ $photographer->promptpay_number }}</p>
              </div>
              @endif
            </div>
            @else
            <div class="p-6 text-center rounded-xl border border-dashed border-gray-200 dark:border-white/10">
              <i class="bi bi-bank text-2xl text-gray-300"></i>
              <p class="text-gray-400 text-sm mt-1">ยังไม่ได้ตั้งค่าบัญชีธนาคาร</p>
            </div>
            @endif
          </div>

          <h6 class="font-semibold text-sm text-gray-500 uppercase tracking-wider mt-6 mb-4">
            <i class="bi bi-info-circle mr-1 text-blue-500"></i>ข้อมูลระบบ
          </h6>
          <div class="space-y-3 text-sm">
            <div class="flex justify-between py-2 border-b border-gray-100 dark:border-white/[0.06]">
              <span class="text-gray-500">User ID</span>
              <span class="font-medium">{{ $photographer->user_id }}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-gray-100 dark:border-white/[0.06]">
              <span class="text-gray-500">รหัสช่างภาพ</span>
              <span class="font-mono font-medium text-indigo-600">{{ $photographer->photographer_code }}</span>
            </div>
            <div class="flex justify-between py-2 border-b border-gray-100 dark:border-white/[0.06]">
              <span class="text-gray-500">วันที่สมัคร</span>
              <span>{{ $photographer->created_at?->format('d/m/Y H:i') }}</span>
            </div>
            @if($photographer->approved_at)
            <div class="flex justify-between py-2 border-b border-gray-100 dark:border-white/[0.06]">
              <span class="text-gray-500">อนุมัติเมื่อ</span>
              <span>{{ $photographer->approved_at->format('d/m/Y H:i') }}</span>
            </div>
            @endif
            <div class="flex justify-between py-2">
              <span class="text-gray-500">อัพเดทล่าสุด</span>
              <span>{{ $photographer->updated_at?->format('d/m/Y H:i') }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ── Events Tab ── --}}
    <div x-show="tab === 'events'" x-transition x-cloak class="p-6">
      <h6 class="font-semibold mb-4"><i class="bi bi-calendar-event mr-1 text-indigo-500"></i>อีเวนต์ทั้งหมด ({{ $stats['events_count'] }})</h6>
      @if($recentEvents->count())
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-white/[0.02]">
            <tr>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">ชื่ออีเวนต์</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">หมวดหมู่</th>
              <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase">รูปภาพ</th>
              <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase">สถานะ</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">วันถ่าย</th>
              <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase">ราคา/รูป</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
            @foreach($recentEvents as $event)
            <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02]">
              <td class="px-4 py-3">
                <a href="{{ route('admin.events.show', $event) }}" class="font-medium text-gray-800 dark:text-gray-100 hover:text-indigo-600">{{ $event->name }}</a>
              </td>
              <td class="px-4 py-3 text-gray-500">{{ $event->category->name ?? '-' }}</td>
              <td class="px-4 py-3 text-center"><span class="font-semibold">{{ $event->photos_count }}</span></td>
              <td class="px-4 py-3 text-center">
                @php
                  $evSt = match($event->status) {
                    'active', 'published' => 'bg-emerald-500/10 text-emerald-600',
                    'draft' => 'bg-gray-200/60 text-gray-500',
                    default => 'bg-amber-500/10 text-amber-600',
                  };
                @endphp
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $evSt }}">{{ ucfirst($event->status) }}</span>
              </td>
              <td class="px-4 py-3 text-gray-500">{{ $event->shoot_date?->format('d/m/Y') ?? '-' }}</td>
              <td class="px-4 py-3 text-right">
                @if($event->is_free)
                  <span class="text-emerald-500 font-medium">ฟรี</span>
                @else
                  <span class="font-medium">฿{{ number_format($event->price_per_photo, 0) }}</span>
                @endif
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @else
      <div class="text-center py-10">
        <i class="bi bi-calendar-x text-3xl text-gray-300 dark:text-gray-600"></i>
        <p class="text-gray-400 mt-2">ยังไม่มีอีเวนต์</p>
      </div>
      @endif
    </div>

    {{-- ── Earnings Tab ── --}}
    <div x-show="tab === 'earnings'" x-transition x-cloak class="p-6">
      <div class="flex items-center justify-between mb-4">
        <h6 class="font-semibold"><i class="bi bi-wallet2 mr-1 text-emerald-500"></i>ประวัติรายได้</h6>
        <div class="flex items-center gap-4 text-sm">
          <span class="text-gray-500">จ่ายแล้ว: <strong class="text-emerald-600">฿{{ number_format($stats['total_earnings'], 2) }}</strong></span>
          <span class="text-gray-500">รอจ่าย: <strong class="text-amber-600">฿{{ number_format($stats['pending_payout'], 2) }}</strong></span>
        </div>
      </div>
      @if($recentPayouts->count())
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-white/[0.02]">
            <tr>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">ID</th>
              <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase">ยอดขาย</th>
              <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase">คอมมิชชั่น</th>
              <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase">ค่าแพลตฟอร์ม</th>
              <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase">ได้รับ</th>
              <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase">สถานะ</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">วันที่</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
            @foreach($recentPayouts as $payout)
            <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02]">
              <td class="px-4 py-3 font-mono text-gray-500">#{{ $payout->id }}</td>
              <td class="px-4 py-3 text-right">฿{{ number_format($payout->gross_amount, 2) }}</td>
              <td class="px-4 py-3 text-center text-indigo-600 font-medium">{{ number_format($payout->commission_rate, 0) }}%</td>
              <td class="px-4 py-3 text-right text-gray-400">฿{{ number_format($payout->platform_fee, 2) }}</td>
              <td class="px-4 py-3 text-right font-semibold text-emerald-600">฿{{ number_format($payout->payout_amount, 2) }}</td>
              <td class="px-4 py-3 text-center">
                @php
                  $pSt = match($payout->status) {
                    'completed' => 'bg-emerald-500/10 text-emerald-600',
                    'pending' => 'bg-amber-500/10 text-amber-600',
                    'failed' => 'bg-red-500/10 text-red-500',
                    default => 'bg-gray-200/60 text-gray-500',
                  };
                @endphp
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $pSt }}">{{ ucfirst($payout->status) }}</span>
              </td>
              <td class="px-4 py-3 text-gray-500">{{ $payout->created_at?->format('d/m/Y') }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @else
      <div class="text-center py-10">
        <i class="bi bi-wallet text-3xl text-gray-300 dark:text-gray-600"></i>
        <p class="text-gray-400 mt-2">ยังไม่มีข้อมูลรายได้</p>
      </div>
      @endif
    </div>

    {{-- ── Reviews Tab ── --}}
    <div x-show="tab === 'reviews'" x-transition x-cloak class="p-6">
      <div class="flex items-center justify-between mb-4">
        <h6 class="font-semibold"><i class="bi bi-star mr-1 text-amber-500"></i>รีวิวทั้งหมด ({{ $stats['reviews_count'] }})</h6>
        @if($stats['avg_rating'] > 0)
        <div class="flex items-center gap-1.5">
          @for($i = 1; $i <= 5; $i++)
            <i class="bi bi-star{{ $i <= round($stats['avg_rating']) ? '-fill' : '' }} text-amber-400"></i>
          @endfor
          <span class="font-semibold ml-1">{{ number_format($stats['avg_rating'], 1) }}</span>
        </div>
        @endif
      </div>
      @if($recentReviews->count())
      <div class="space-y-4">
        @foreach($recentReviews as $review)
        <div class="p-4 rounded-xl border border-gray-100 dark:border-white/[0.06] hover:shadow-sm transition">
          <div class="flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1">
                <span class="font-medium">{{ $review->user->first_name ?? 'ผู้ใช้' }}</span>
                <div class="flex gap-0.5">
                  @for($i = 1; $i <= 5; $i++)
                    <i class="bi bi-star{{ $i <= $review->rating ? '-fill' : '' }} text-amber-400 text-xs"></i>
                  @endfor
                </div>
              </div>
              <p class="text-gray-600 dark:text-gray-300 text-sm">{{ $review->comment ?: '-' }}</p>
              @if($review->event)
              <span class="text-xs text-gray-400 mt-1 inline-block">
                <i class="bi bi-calendar-event mr-0.5"></i>{{ $review->event->name }}
              </span>
              @endif
            </div>
            <span class="text-xs text-gray-400 whitespace-nowrap">{{ $review->created_at?->format('d/m/Y') }}</span>
          </div>
          @if($review->admin_reply)
          <div class="mt-3 ml-4 p-3 rounded-lg bg-indigo-50/50 dark:bg-indigo-500/10 text-sm">
            <span class="text-xs font-semibold text-indigo-600"><i class="bi bi-reply mr-0.5"></i>ตอบกลับ:</span>
            <p class="text-gray-600 dark:text-gray-300 mt-0.5 mb-0">{{ $review->admin_reply }}</p>
          </div>
          @endif
        </div>
        @endforeach
      </div>
      @else
      <div class="text-center py-10">
        <i class="bi bi-star text-3xl text-gray-300 dark:text-gray-600"></i>
        <p class="text-gray-400 mt-2">ยังไม่มีรีวิว</p>
      </div>
      @endif
    </div>

  </div>
</div>
@endsection
