@extends('layouts.admin')

@section('title', 'ข้อมูลผู้ใช้')

@section('content')
{{-- ═══ Header ═══ --}}
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
  <div class="flex items-center gap-3">
    <a href="{{ route('admin.users.index') }}" class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-gray-100 text-gray-500 hover:bg-gray-200 transition dark:bg-white/[0.06] dark:text-gray-400 dark:hover:bg-white/10">
      <i class="bi bi-arrow-left"></i>
    </a>
    <h4 class="font-bold mb-0 tracking-tight">
      <i class="bi bi-person mr-2 text-indigo-500"></i>{{ $user->first_name }} {{ $user->last_name }}
    </h4>
  </div>
  <div class="flex items-center gap-2 flex-wrap">
    <a href="{{ route('admin.users.edit', $user) }}" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-indigo-500/10 text-indigo-600 hover:bg-indigo-500/20 transition dark:text-indigo-400">
      <i class="bi bi-pencil mr-1.5"></i> แก้ไข
    </a>
    @php
      $authProvider = strtolower((string) ($user->auth_provider ?? 'local'));
      $isLocalAccount = in_array($authProvider, ['', 'local', 'email'], true);
    @endphp
    @if($isLocalAccount)
    <form method="POST" action="{{ route('admin.users.reset-password', $user) }}" class="inline" onsubmit="return confirm('ยืนยันรีเซ็ตรหัสผ่านของ {{ $user->first_name }}?')">
      @csrf
      <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-amber-500/10 text-amber-600 hover:bg-amber-500/20 transition">
        <i class="bi bi-key mr-1.5"></i> รีเซ็ตรหัสผ่าน
      </button>
    </form>
    @else
    <span class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-gray-100 text-gray-400 cursor-not-allowed dark:bg-white/[0.04] dark:text-gray-500" title="บัญชีสมัครผ่าน {{ ucfirst($authProvider) }} — ไม่สามารถรีเซ็ตรหัสผ่านได้">
      <i class="bi bi-key-fill mr-1.5"></i> รีเซ็ตรหัสไม่ได้ ({{ ucfirst($authProvider) }})
    </span>
    @endif
    @if(($user->status ?? 'active') === 'active' && (auth('admin')->user()?->hasPermission('users') ?? false))
    <form method="POST" action="{{ route('admin.users.impersonate', $user) }}" class="inline" onsubmit="return confirm('เข้าสู่ระบบในฐานะ {{ $user->first_name }} {{ $user->last_name }}? การกระทำนี้จะถูกบันทึกไว้ในบันทึกกิจกรรม')">
      @csrf
      <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-purple-500/10 text-purple-600 hover:bg-purple-500/20 transition dark:text-purple-400">
        <i class="bi bi-incognito mr-1.5"></i> Impersonate
      </button>
    </form>
    @endif
    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="inline" onsubmit="return confirm('{{ ($user->status ?? 'active') === 'active' ? 'ยืนยันบล็อคผู้ใช้?' : 'ยืนยันปลดบล็อคผู้ใช้?' }}')">
      @csrf
      @method('PUT')
      <input type="hidden" name="toggle_block" value="1">
      @php $isActive = ($user->status ?? 'active') === 'active'; @endphp
      <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium transition {{ $isActive ? 'bg-red-500/10 text-red-500 hover:bg-red-500/20' : 'bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20' }}">
        <i class="bi bi-{{ $isActive ? 'lock' : 'unlock' }} mr-1.5"></i> {{ $isActive ? 'บล็อค' : 'ปลดบล็อค' }}
      </button>
    </form>
  </div>
</div>

{{-- ═══ Flash Messages ═══ --}}
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
@if(session('warning'))
<div class="mb-4 px-4 py-3 rounded-xl bg-amber-50 text-amber-700 text-sm border border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-400">
  <i class="bi bi-exclamation-circle mr-1"></i> {{ session('warning') }}
</div>
@endif
@if(session('new_password'))
<div x-data="{ pw: @js(session('new_password')), copied: false, show: true }" x-show="show" x-transition
     class="mb-4 px-4 py-4 rounded-xl bg-amber-50 border border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/30">
  <div class="flex items-start gap-3">
    <i class="bi bi-key-fill text-amber-600 dark:text-amber-400 text-xl mt-0.5"></i>
    <div class="flex-1 min-w-0">
      <div class="text-sm font-semibold text-amber-800 dark:text-amber-300 mb-1">รหัสผ่านใหม่ (แสดงเพียงครั้งเดียว)</div>
      <p class="text-xs text-amber-700/80 dark:text-amber-400/70 mb-2">กรุณาคัดลอกและส่งต่อให้ผู้ใช้อย่างปลอดภัย — หลังรีเฟรชหน้านี้จะไม่สามารถดูได้อีก</p>
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

{{-- ═══ Hero Card ═══ --}}
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6 dark:bg-slate-800 dark:border-white/[0.06]">
  <div class="bg-gradient-to-r from-indigo-500 to-violet-600 px-6 py-8 sm:py-10">
    <div class="flex flex-col sm:flex-row sm:items-center gap-5">
      {{-- Avatar --}}
      <div class="shrink-0">
        <div class="w-20 h-20 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-white text-3xl font-bold shadow-lg border-2 border-white/30">
          {{ strtoupper(mb_substr($user->first_name ?? 'U', 0, 1)) }}
        </div>
      </div>

      {{-- User Info --}}
      <div class="flex-1 min-w-0">
        <h3 class="text-xl sm:text-2xl font-bold text-white tracking-tight">{{ $user->first_name }} {{ $user->last_name }}</h3>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1.5 text-sm text-indigo-100">
          <span><i class="bi bi-envelope mr-1"></i>{{ $user->email }}</span>
          @if($user->phone)
          <span><i class="bi bi-telephone mr-1"></i>{{ $user->phone }}</span>
          @endif
        </div>

        {{-- Badges --}}
        <div class="flex flex-wrap items-center gap-2 mt-3">
          {{-- Status Badge --}}
          @if(($user->status ?? 'active') === 'active')
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-400/20 text-emerald-100 backdrop-blur-sm">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-300 mr-1.5"></span> Active
            </span>
          @else
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-400/20 text-red-100 backdrop-blur-sm">
              <span class="w-1.5 h-1.5 rounded-full bg-red-300 mr-1.5"></span> Suspended
            </span>
          @endif

          {{-- Email Verified --}}
          @if($user->email_verified_at)
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-white/15 text-white backdrop-blur-sm">
              <i class="bi bi-patch-check-fill mr-1"></i> ยืนยันอีเมลแล้ว
            </span>
          @else
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-white/10 text-indigo-200 backdrop-blur-sm">
              <i class="bi bi-patch-exclamation mr-1"></i> ยังไม่ยืนยันอีเมล
            </span>
          @endif

          {{-- Auth Provider --}}
          @php
            $provider = $user->auth_provider ?? 'email';
            $providerMap = [
              'google'   => ['icon' => 'bi-google',   'label' => 'Google',   'bg' => 'bg-white/15'],
              'facebook' => ['icon' => 'bi-facebook',  'label' => 'Facebook', 'bg' => 'bg-white/15'],
              'line'     => ['icon' => 'bi-chat-fill', 'label' => 'LINE',     'bg' => 'bg-white/15'],
              'email'    => ['icon' => 'bi-envelope',  'label' => 'Email',    'bg' => 'bg-white/10'],
            ];
            $prov = $providerMap[$provider] ?? $providerMap['email'];
          @endphp
          <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $prov['bg'] }} text-white backdrop-blur-sm">
            <i class="bi {{ $prov['icon'] }} mr-1"></i> {{ $prov['label'] }}
          </span>
        </div>
      </div>

      {{-- Meta Info --}}
      <div class="shrink-0 text-sm text-indigo-100 space-y-1 sm:text-right">
        <div><i class="bi bi-calendar-plus mr-1"></i> สมาชิกตั้งแต่ {{ $user->created_at?->format('d/m/Y') }}</div>
        @if($user->last_login_at)
        <div><i class="bi bi-clock-history mr-1"></i> เข้าใช้ล่าสุด {{ \Carbon\Carbon::parse($user->last_login_at)->format('d/m/Y H:i') }}</div>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- ═══ Stats Cards ═══ --}}
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
  @php
    $statCards = [
      ['icon' => 'bi-cart-check',    'color' => 'indigo',  'value' => $stats['orders_count'] ?? 0,                         'label' => 'คำสั่งซื้อ'],
      ['icon' => 'bi-cash-stack',    'color' => 'emerald', 'value' => '฿' . number_format($stats['total_spent'] ?? 0, 0),  'label' => 'ยอดใช้จ่ายรวม'],
      ['icon' => 'bi-chat-dots',     'color' => 'purple',  'value' => $stats['reviews_count'] ?? 0,                        'label' => 'รีวิว'],
      ['icon' => 'bi-star-fill',     'color' => 'amber',   'value' => ($stats['avg_rating'] ?? 0) > 0 ? number_format($stats['avg_rating'], 1) : '-', 'label' => 'คะแนนเฉลี่ย', 'star' => true],
      ['icon' => 'bi-box-arrow-in-right', 'color' => 'blue', 'value' => $stats['login_count'] ?? 0,                        'label' => 'เข้าสู่ระบบ'],
      ['icon' => 'bi-calendar-range','color' => 'pink',    'value' => ($stats['days_since_signup'] ?? 0) . ' วัน',          'label' => 'อายุสมาชิก'],
    ];
  @endphp
  @foreach($statCards as $sc)
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="py-4 px-3 text-center">
      <i class="bi {{ $sc['icon'] }} text-{{ $sc['color'] }}-500 text-lg mb-1"></i>
      <div class="font-bold text-lg leading-tight flex items-center justify-center gap-1">
        {{ $sc['value'] }}
        @if(!empty($sc['star']) && ($stats['avg_rating'] ?? 0) > 0)
          <i class="bi bi-star-fill text-amber-400 text-sm"></i>
        @endif
      </div>
      <small class="text-gray-400 text-xs">{{ $sc['label'] }}</small>
    </div>
  </div>
  @endforeach
</div>

{{-- ═══ Tabs Section ═══ --}}
<div x-data="{ tab: 'info' }" class="mb-6">
  {{-- Tab Navigation --}}
  <div class="bg-white rounded-t-xl border border-b-0 border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="flex gap-1 border-b border-gray-200 dark:border-white/[0.06] px-4 overflow-x-auto">
      @php
        $tabs = [
          'info'     => ['icon' => 'bi-person-badge',   'label' => 'ข้อมูลทั่วไป'],
          'orders'   => ['icon' => 'bi-cart-check',     'label' => 'คำสั่งซื้อ'],
          'reviews'  => ['icon' => 'bi-star',           'label' => 'รีวิว'],
          'sessions' => ['icon' => 'bi-display',        'label' => 'เซสชัน'],
        ];
      @endphp
      @foreach($tabs as $key => $t)
      <button @click="tab = '{{ $key }}'"
              :class="tab === '{{ $key }}' ? 'border-indigo-500 text-indigo-600 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
              class="flex items-center gap-1.5 px-4 py-2.5 text-sm border-b-2 transition whitespace-nowrap">
        <i class="bi {{ $t['icon'] }}"></i> {{ $t['label'] }}
      </button>
      @endforeach
    </div>
  </div>

  {{-- Tab Content --}}
  <div class="bg-white rounded-b-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">

    {{-- ── ข้อมูลทั่วไป Tab ── --}}
    <div x-show="tab === 'info'" x-transition class="p-6">
      <h6 class="font-semibold text-sm text-gray-500 uppercase tracking-wider mb-4">
        <i class="bi bi-person-badge mr-1 text-indigo-500"></i>ข้อมูลผู้ใช้
      </h6>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
        <div>
          <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">ชื่อ</label>
          <p class="mt-0.5 font-medium">{{ $user->first_name }}</p>
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">นามสกุล</label>
          <p class="mt-0.5 font-medium">{{ $user->last_name }}</p>
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">อีเมล</label>
          <p class="mt-0.5 font-medium">{{ $user->email }}</p>
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">โทรศัพท์</label>
          <p class="mt-0.5 font-medium">{{ $user->phone ?? '-' }}</p>
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">ชื่อผู้ใช้</label>
          <p class="mt-0.5 font-medium">{{ $user->username ?? '-' }}</p>
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">ผู้ให้บริการยืนยันตัวตน</label>
          <p class="mt-0.5">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
              <i class="bi {{ $prov['icon'] }} mr-1"></i> {{ ucfirst($user->auth_provider ?? 'email') }}
            </span>
          </p>
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">สถานะ</label>
          <p class="mt-0.5">
            @if(($user->status ?? 'active') === 'active')
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span> Active
              </span>
            @else
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400">
                <span class="w-1.5 h-1.5 rounded-full bg-red-500 mr-1.5"></span> Suspended
              </span>
            @endif
          </p>
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">ยืนยันอีเมล</label>
          <p class="mt-0.5">
            @if($user->email_verified_at)
              <span class="inline-flex items-center text-sm text-emerald-600 dark:text-emerald-400">
                <i class="bi bi-patch-check-fill mr-1"></i> {{ \Carbon\Carbon::parse($user->email_verified_at)->format('d/m/Y H:i') }}
              </span>
            @else
              <span class="text-sm text-gray-400">ยังไม่ยืนยัน</span>
            @endif
          </p>
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">จำนวนเข้าสู่ระบบ</label>
          <p class="mt-0.5 font-medium">{{ $user->login_count ?? 0 }} ครั้ง</p>
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">เข้าสู่ระบบล่าสุด</label>
          <p class="mt-0.5 font-medium">{{ $user->last_login_at ? \Carbon\Carbon::parse($user->last_login_at)->format('d/m/Y H:i') : '-' }}</p>
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">วันที่สมัคร</label>
          <p class="mt-0.5 font-medium">{{ $user->created_at?->format('d/m/Y H:i') }}</p>
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">อัปเดตล่าสุด</label>
          <p class="mt-0.5 font-medium">{{ $user->updated_at?->format('d/m/Y H:i') }}</p>
        </div>
      </div>

      {{-- Social Logins --}}
      @if($user->socialLogins && $user->socialLogins->count())
      <div class="mt-8 pt-6 border-t border-gray-100 dark:border-white/[0.06]">
        <h6 class="font-semibold text-sm text-gray-500 uppercase tracking-wider mb-4">
          <i class="bi bi-share mr-1 text-indigo-500"></i>บัญชีที่เชื่อมต่อ
        </h6>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          @foreach($user->socialLogins as $social)
          @php
            $socialMap = [
              'google'   => ['icon' => 'bi-google',     'color' => 'text-red-500',   'bg' => 'bg-red-50 dark:bg-red-500/10',     'border' => 'border-red-100 dark:border-red-500/20'],
              'facebook' => ['icon' => 'bi-facebook',    'color' => 'text-blue-600',  'bg' => 'bg-blue-50 dark:bg-blue-500/10',   'border' => 'border-blue-100 dark:border-blue-500/20'],
              'line'     => ['icon' => 'bi-chat-fill',   'color' => 'text-green-500', 'bg' => 'bg-green-50 dark:bg-green-500/10', 'border' => 'border-green-100 dark:border-green-500/20'],
            ];
            $s = $socialMap[$social->provider ?? ''] ?? ['icon' => 'bi-link-45deg', 'color' => 'text-gray-500', 'bg' => 'bg-gray-50 dark:bg-white/[0.03]', 'border' => 'border-gray-100 dark:border-white/[0.06]'];
          @endphp
          <div class="flex items-center gap-3 p-3 rounded-xl border {{ $s['border'] }} {{ $s['bg'] }}">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center {{ $s['bg'] }}">
              <i class="bi {{ $s['icon'] }} {{ $s['color'] }} text-lg"></i>
            </div>
            <div class="min-w-0">
              <div class="font-medium text-sm">{{ ucfirst($social->provider ?? 'Unknown') }}</div>
              <div class="text-xs text-gray-400 truncate">{{ $social->provider_id ?? '-' }}</div>
              @if($social->created_at)
              <div class="text-xs text-gray-400">เชื่อมต่อ {{ $social->created_at->format('d/m/Y') }}</div>
              @endif
            </div>
          </div>
          @endforeach
        </div>
      </div>
      @endif

      {{-- Photographer Profile --}}
      @if($user->photographerProfile)
      <div class="mt-8 pt-6 border-t border-gray-100 dark:border-white/[0.06]">
        <h6 class="font-semibold text-sm text-gray-500 uppercase tracking-wider mb-4">
          <i class="bi bi-camera mr-1 text-purple-500"></i>โปรไฟล์ช่างภาพ
        </h6>
        <div class="p-4 rounded-xl border border-purple-100 bg-purple-50/50 dark:bg-purple-500/5 dark:border-purple-500/20">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
            <div>
              <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">ชื่อที่แสดง</label>
              <p class="mt-0.5 font-medium">{{ $user->photographerProfile->display_name ?? '-' }}</p>
            </div>
            <div>
              <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">รหัสช่างภาพ</label>
              <p class="mt-0.5 font-mono font-medium text-indigo-600 dark:text-indigo-400">{{ $user->photographerProfile->photographer_code ?? '-' }}</p>
            </div>
            <div>
              <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">สถานะช่างภาพ</label>
              <p class="mt-0.5">
                @php
                  $pgStatus = $user->photographerProfile->status ?? 'pending';
                  $pgStatusMap = [
                    'approved'  => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
                    'pending'   => 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
                    'suspended' => 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400',
                  ];
                  $pgStatusLabel = ['approved' => 'อนุมัติแล้ว', 'pending' => 'รอตรวจสอบ', 'suspended' => 'ระงับ'];
                @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $pgStatusMap[$pgStatus] ?? $pgStatusMap['pending'] }}">
                  {{ $pgStatusLabel[$pgStatus] ?? ucfirst($pgStatus) }}
                </span>
              </p>
            </div>
            <div>
              <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">ค่าคอมมิชชั่น</label>
              <p class="mt-0.5 font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($user->photographerProfile->commission_rate ?? 0, 0) }}%</p>
            </div>
            @if($user->photographerProfile->bio)
            <div class="md:col-span-2">
              <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Bio</label>
              <p class="mt-0.5 text-gray-600 dark:text-gray-300">{{ $user->photographerProfile->bio }}</p>
            </div>
            @endif
          </div>
          <div class="mt-3 pt-3 border-t border-purple-100 dark:border-purple-500/20">
            <a href="{{ route('admin.photographers.show', $user->photographerProfile) }}" class="inline-flex items-center text-sm font-medium text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-300 transition">
              <i class="bi bi-arrow-right-circle mr-1"></i> ดูโปรไฟล์ช่างภาพเต็ม
            </a>
          </div>
        </div>
      </div>
      @endif
    </div>

    {{-- ── คำสั่งซื้อ Tab ── --}}
    <div x-show="tab === 'orders'" x-transition x-cloak class="p-6">
      <h6 class="font-semibold mb-4"><i class="bi bi-cart-check mr-1 text-indigo-500"></i>คำสั่งซื้อล่าสุด</h6>
      @if($recentOrders->count())
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-white/[0.02]">
            <tr>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ID</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">อีเวนต์</th>
              <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">ยอดรวม</th>
              <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">สถานะ</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">วันที่</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
            @foreach($recentOrders as $order)
            <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02]">
              <td class="px-4 py-3">
                <a href="{{ route('admin.orders.show', $order) }}" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                  #{{ $order->id }}
                </a>
              </td>
              <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $order->event->name ?? '-' }}</td>
              <td class="px-4 py-3 text-right font-semibold text-gray-800 dark:text-gray-100">฿{{ number_format($order->total ?? 0, 2) }}</td>
              <td class="px-4 py-3 text-center">
                @php
                  $orderStatusClass = match($order->status) {
                    'paid', 'completed' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400',
                    'pending'           => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
                    'cancelled', 'failed' => 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400',
                    default             => 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400',
                  };
                @endphp
                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $orderStatusClass }}">
                  {{ ucfirst($order->status) }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-500 text-sm">{{ $order->created_at?->format('d/m/Y H:i') }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @else
      <div class="py-12 text-center">
        <i class="bi bi-cart text-4xl text-gray-300 dark:text-gray-600"></i>
        <p class="text-gray-400 dark:text-gray-500 mt-2">ยังไม่มีคำสั่งซื้อ</p>
      </div>
      @endif
    </div>

    {{-- ── รีวิว Tab ── --}}
    <div x-show="tab === 'reviews'" x-transition x-cloak class="p-6">
      <h6 class="font-semibold mb-4"><i class="bi bi-star mr-1 text-amber-500"></i>รีวิวล่าสุด</h6>
      @if($recentReviews->count())
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-white/[0.02]">
            <tr>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">อีเวนต์</th>
              <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">คะแนน</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ความคิดเห็น</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">วันที่</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
            @foreach($recentReviews as $review)
            <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02]">
              <td class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">{{ $review->event->name ?? '-' }}</td>
              <td class="px-4 py-3 text-center">
                <div class="inline-flex items-center gap-0.5">
                  @for($i = 1; $i <= 5; $i++)
                    <i class="bi bi-star-fill text-xs {{ $i <= ($review->rating ?? 0) ? 'text-amber-400' : 'text-gray-200 dark:text-gray-600' }}"></i>
                  @endfor
                </div>
              </td>
              <td class="px-4 py-3 text-gray-600 dark:text-gray-400 max-w-xs truncate">{{ $review->comment ?? '-' }}</td>
              <td class="px-4 py-3 text-gray-500 text-sm">{{ $review->created_at?->format('d/m/Y H:i') }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @else
      <div class="py-12 text-center">
        <i class="bi bi-star text-4xl text-gray-300 dark:text-gray-600"></i>
        <p class="text-gray-400 dark:text-gray-500 mt-2">ยังไม่มีรีวิว</p>
      </div>
      @endif
    </div>

    {{-- ── เซสชัน Tab ── --}}
    <div x-show="tab === 'sessions'" x-transition x-cloak class="p-6">
      <h6 class="font-semibold mb-4"><i class="bi bi-display mr-1 text-blue-500"></i>เซสชันที่ใช้งาน</h6>
      @if($sessions->count())
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-white/[0.02]">
            <tr>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">IP Address</th>
              <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">อุปกรณ์</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">เบราว์เซอร์ / OS</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ใช้งานล่าสุด</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
            @foreach($sessions as $session)
            <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02]">
              <td class="px-4 py-3">
                <code class="text-xs bg-gray-100 dark:bg-white/[0.06] px-2 py-0.5 rounded">{{ $session->ip_address ?? '-' }}</code>
              </td>
              <td class="px-4 py-3 text-center">
                @php
                  $deviceIcon = match($session->device_type ?? 'unknown') {
                    'desktop'  => 'bi-pc-display',
                    'mobile'   => 'bi-phone',
                    'tablet'   => 'bi-tablet',
                    default    => 'bi-question-circle',
                  };
                @endphp
                <i class="bi {{ $deviceIcon }} text-gray-500 dark:text-gray-400" title="{{ ucfirst($session->device_type ?? 'Unknown') }}"></i>
              </td>
              <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                <span>{{ $session->browser ?? '-' }}</span>
                @if($session->os)
                  <span class="text-gray-400 mx-1">/</span>
                  <span class="text-gray-500 dark:text-gray-400">{{ $session->os }}</span>
                @endif
              </td>
              <td class="px-4 py-3 text-gray-500 text-sm">
                @if($session->last_activity)
                  {{ \Carbon\Carbon::parse($session->last_activity)->format('d/m/Y H:i') }}
                  <span class="text-xs text-gray-400">({{ \Carbon\Carbon::parse($session->last_activity)->diffForHumans() }})</span>
                @else
                  -
                @endif
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @else
      <div class="py-12 text-center">
        <i class="bi bi-display text-4xl text-gray-300 dark:text-gray-600"></i>
        <p class="text-gray-400 dark:text-gray-500 mt-2">ไม่พบข้อมูลเซสชัน</p>
      </div>
      @endif
    </div>

  </div>
</div>

{{-- ═══ Danger Zone ═══ --}}
@if(($stats['orders_count'] ?? 0) === 0)
<div class="rounded-xl border-2 border-red-200 dark:border-red-500/30 bg-red-50/50 dark:bg-red-500/5 p-5 mb-6">
  <h6 class="font-semibold text-red-600 dark:text-red-400 mb-1">
    <i class="bi bi-exclamation-triangle mr-1"></i> Danger Zone
  </h6>
  <p class="text-sm text-red-500/80 dark:text-red-400/70 mb-4">การลบผู้ใช้เป็นการดำเนินการถาวร ไม่สามารถย้อนกลับได้</p>
  <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบผู้ใช้ {{ $user->first_name }} {{ $user->last_name }}? การดำเนินการนี้ไม่สามารถย้อนกลับได้')">
    @csrf
    @method('DELETE')
    <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-red-500 text-white hover:bg-red-600 transition shadow-sm">
      <i class="bi bi-trash3 mr-1.5"></i> ลบผู้ใช้
    </button>
  </form>
</div>
@endif
@endsection
