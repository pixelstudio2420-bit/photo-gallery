@extends('layouts.photographer')

@section('title', 'โปรไฟล์ช่างภาพ')

@php
  use App\Models\PhotographerProfile;
  $tier              = $photographer->tier ?? PhotographerProfile::TIER_CREATOR;
  $hasPromptPay      = !empty($photographer->promptpay_number);
  $promptPayVerified = $photographer->isPromptPayVerified();
  $hasBank           = !empty($photographer->bank_account_number) && !empty($photographer->bank_account_name);
  $hasPortfolio      = !empty($photographer->portfolio_url) || count((array) $photographer->portfolio_samples) > 0;
  $hasBio            = !empty($photographer->bio);
  $emailVerified     = !empty(Auth::user()?->email_verified_at) || (Auth::user()?->email_verified ?? false);
  $completeness      = $photographer->completenessPercent();

  // Live data from subscription system (the new source of truth)
  $planSummary  = app(\App\Services\SubscriptionService::class)->dashboardSummary($photographer);
  $planObj      = $planSummary['plan'] ?? null;
  $usedGb       = (float) ($planSummary['storage_used_gb'] ?? 0);
  $quotaGb      = (float) ($planSummary['storage_quota_gb'] ?? 0);
  $storagePct   = (float) ($planSummary['storage_used_pct'] ?? 0);

  // Member since
  $memberSince = $photographer->created_at ?? null;

  // Stats counts (best-effort)
  $eventsCount = \App\Models\Event::where('photographer_id', $photographer->user_id)->count();
  $photosCount = \Illuminate\Support\Facades\DB::table('event_photos')
      ->join('event_events', 'event_events.id', '=', 'event_photos.event_id')
      ->where('event_events.photographer_id', $photographer->user_id)
      ->where('event_photos.status', 'active')
      ->count();
@endphp

@section('content')
@include('photographer.partials.page-hero', [
  'icon'     => 'bi-person-circle',
  'eyebrow'  => 'บัญชี',
  'title'    => 'โปรไฟล์ช่างภาพ',
  'subtitle' => 'จัดการข้อมูลส่วนตัว · PromptPay · บัญชีธนาคาร · พอร์ตโฟลิโอ',
  'actions'  => '<a href="'.route('photographer.setup-bank').'" class="pg-btn-ghost"><i class="bi bi-bank"></i> ตั้งค่าบัญชีธนาคาร</a>',
])

{{-- ═══ Account Status Banner ═══ --}}
{{-- Replaces the legacy tier banner with practical info: subscription
     plan, storage usage, verification badges, member-since, and key
     activity stats. The tier name (Creator/Seller/Pro) doesn't mean
     much to users — what they care about is "is my account ready to
     start selling?" which the verification chips answer directly. --}}
<div class="rounded-2xl shadow-sm mb-6 overflow-hidden border border-white/[0.06]"
     style="background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 50%,#ec4899 100%);">
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-0 text-white">

    {{-- LEFT: Identity + member since + completeness ring --}}
    <div class="lg:col-span-5 p-5 lg:p-6 relative overflow-hidden">
      <div class="absolute -right-12 -top-12 w-48 h-48 rounded-full" style="background:radial-gradient(circle,rgba(255,255,255,.18),transparent 70%);"></div>

      <div class="relative flex items-start gap-4">
        {{-- Avatar circle + plan badge overlay
             The little circular badge in the bottom-right corner echoes
             the photographer's CURRENT subscription plan icon (camera /
             rocket / stars / buildings / gem) so the tier is recognisable
             at a glance, even before scrolling to the plan section. --}}
        <div class="relative w-16 h-16 shrink-0">
          <div class="w-16 h-16 rounded-2xl bg-white/20 backdrop-blur-sm border border-white/30 flex items-center justify-center text-white overflow-hidden">
            @if($photographer->avatar)
              <img src="{{ app(\App\Services\StorageManager::class)->url($photographer->avatar) }}" class="w-full h-full rounded-2xl object-cover" alt="">
            @else
              <i class="bi bi-camera-fill text-2xl"></i>
            @endif
          </div>
          @if($planObj)
            <span class="absolute -bottom-1 -right-1 w-7 h-7 rounded-full ring-2 ring-white/95 flex items-center justify-center text-white text-[12px] shadow-md"
                  style="background:linear-gradient(135deg, {{ $planObj->color_hex ?: '#7c3aed' }}, #4f46e5);"
                  title="แผน {{ $planObj->name }}">
              <i class="bi {{ $planObj->iconClass() }}"></i>
            </span>
          @endif
        </div>

        <div class="min-w-0 flex-1">
          <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-white/70 m-0">
            <i class="bi bi-person-badge mr-0.5"></i> ช่างภาพ
          </p>
          <h2 class="font-bold text-2xl tracking-tight mt-0.5 mb-1 truncate">
            {{ $photographer->display_name ?? 'ช่างภาพ' }}
          </h2>
          <p class="text-xs text-white/80 m-0 inline-flex items-center gap-3 flex-wrap">
            <span><i class="bi bi-fingerprint mr-1"></i>{{ $photographer->photographer_code ?? '—' }}</span>
            @if($memberSince)
              <span><i class="bi bi-calendar3 mr-1"></i>เริ่มเมื่อ {{ \Carbon\Carbon::parse($memberSince)->translatedFormat('M Y') }}</span>
            @endif
          </p>

          {{-- Profile completeness — slim bar --}}
          <div class="mt-3">
            <div class="flex items-center justify-between text-[11px] mb-1">
              <span class="text-white/70 font-medium uppercase tracking-wider">ความสมบูรณ์</span>
              <span class="font-bold">{{ $completeness }}%</span>
            </div>
            <div class="h-1.5 rounded-full bg-white/20 overflow-hidden">
              <div class="h-full bg-white rounded-full transition-all" style="width: {{ $completeness }}%"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- MIDDLE: Quick stats --}}
    <div class="lg:col-span-4 px-5 lg:px-6 lg:border-l border-white/[0.12] pb-5 lg:py-6 grid grid-cols-3 gap-3">
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-white/65 m-0 mb-1">
          <i class="bi bi-calendar-event mr-0.5"></i> อีเวนต์
        </p>
        <p class="text-2xl font-bold m-0" style="font-variant-numeric:tabular-nums;">{{ number_format($eventsCount) }}</p>
      </div>
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-white/65 m-0 mb-1">
          <i class="bi bi-images mr-0.5"></i> รูปภาพ
        </p>
        <p class="text-2xl font-bold m-0" style="font-variant-numeric:tabular-nums;">{{ number_format($photosCount) }}</p>
      </div>
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-white/65 m-0 mb-1">
          <i class="bi bi-cash-coin mr-0.5"></i> รับ
        </p>
        <p class="text-2xl font-bold m-0" style="font-variant-numeric:tabular-nums;">
          {{ rtrim(rtrim(number_format(100 - (float) ($planObj?->commission_pct ?? 0), 1), '0'), '.') }}<span class="text-sm font-medium text-white/65">%</span>
        </p>
      </div>
    </div>

    {{-- RIGHT: Plan + storage --}}
    <div class="lg:col-span-3 p-5 lg:p-6 lg:border-l border-white/[0.12] bg-white/[0.06] backdrop-blur-sm">
      <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-white/70 m-0">
        <i class="bi {{ $planObj?->iconClass() ?? 'bi-camera' }} mr-0.5"></i> แผน
      </p>
      <div class="flex items-center gap-2 mt-1 mb-2">
        {{-- Plan icon chip — uses the plan's accent colour as a subtle
             tint so each tier reads differently at a glance --}}
        @if($planObj)
          <span class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 text-white text-sm shadow-sm"
                style="background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.25);">
            <i class="bi {{ $planObj->iconClass() }}"></i>
          </span>
        @endif
        <h4 class="font-bold text-lg m-0">{{ $planObj?->name ?? 'Free' }}</h4>
        @if($planObj && !($planSummary['is_free'] ?? true))
          <span class="text-xs text-white/75 ml-auto">฿{{ number_format((float) $planObj->price_thb, 0) }}/เดือน</span>
        @endif
      </div>

      @if($quotaGb > 0)
        <div class="flex items-baseline justify-between text-[11px] mb-1">
          <span class="text-white/70">พื้นที่</span>
          <span class="font-bold" style="font-variant-numeric:tabular-nums;">{{ number_format($usedGb, 2) }} / {{ number_format($quotaGb, 0) }} GB</span>
        </div>
        <div class="h-1.5 rounded-full bg-white/15 overflow-hidden mb-3">
          <div class="h-full bg-white rounded-full transition-all" style="width: {{ min(100, $storagePct) }}%"></div>
        </div>
      @endif

      <a href="{{ route('photographer.subscription.plans') }}"
         class="inline-flex items-center gap-1.5 text-[11px] font-bold text-white bg-white/15 hover:bg-white/25 backdrop-blur-sm px-3 py-1.5 rounded-lg transition border border-white/15 no-underline">
        <i class="bi bi-arrow-up-circle"></i> {{ ($planSummary['is_free'] ?? true) ? 'อัปเกรดแผน' : 'เปลี่ยนแผน' }}
      </a>
    </div>
  </div>

  {{-- Verification badges row --}}
  <div class="px-5 lg:px-6 py-3 bg-black/15 backdrop-blur-sm border-t border-white/[0.08] flex items-center gap-2 flex-wrap text-[11px]">
    <span class="text-white/60 font-bold uppercase tracking-[0.14em] mr-1">การยืนยัน:</span>

    @php
      $checks = [
        ['key' => 'email',     'label' => 'Email',       'icon' => 'bi-envelope-check', 'ok' => $emailVerified],
        ['key' => 'promptpay', 'label' => 'PromptPay',   'icon' => 'bi-qr-code',         'ok' => $promptPayVerified || $hasPromptPay],
        ['key' => 'bank',      'label' => 'บัญชีธนาคาร',  'icon' => 'bi-bank',           'ok' => $hasBank],
        ['key' => 'bio',       'label' => 'Bio',         'icon' => 'bi-card-text',       'ok' => $hasBio],
        ['key' => 'portfolio', 'label' => 'Portfolio',   'icon' => 'bi-images',          'ok' => $hasPortfolio],
      ];
    @endphp

    @foreach($checks as $c)
      <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full {{ $c['ok'] ? 'bg-emerald-400/25 text-emerald-100 border border-emerald-300/30' : 'bg-white/[0.08] text-white/55 border border-white/[0.08]' }}">
        <i class="bi {{ $c['ok'] ? 'bi-check-circle-fill' : $c['icon'] }}"></i>
        <span class="font-medium">{{ $c['label'] }}</span>
      </span>
    @endforeach
  </div>
</div>

{{-- Profile Info Card --}}
<div class="pg-card mb-6">
  <div class="p-5">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <label class="text-gray-500 text-xs uppercase tracking-wider font-medium">ชื่อที่แสดง</label>
        <p class="font-semibold mt-1 mb-3">{{ $photographer->display_name ?? '-' }}</p>
      </div>
      <div>
        <label class="text-gray-500 text-xs uppercase tracking-wider font-medium">รหัสช่างภาพ</label>
        <p class="font-semibold mt-1 mb-3 font-mono">{{ $photographer->photographer_code ?? '-' }}</p>
      </div>
      <div class="md:col-span-2">
        <label class="text-gray-500 text-xs uppercase tracking-wider font-medium">ประวัติย่อ</label>
        <p class="mt-1 mb-3">{{ $photographer->bio ?? '-' }}</p>
      </div>
      <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-6">
        <div>
          <label class="text-gray-500 text-xs uppercase tracking-wider font-medium">Portfolio URL</label>
          <p class="mt-1 mb-3">
            @if($photographer->portfolio_url)
              <a href="{{ $photographer->portfolio_url }}" target="_blank" class="text-indigo-600 hover:underline">{{ $photographer->portfolio_url }}</a>
            @else
              -
            @endif
          </p>
        </div>
        <div>
          <label class="text-gray-500 text-xs uppercase tracking-wider font-medium">ค่าคอมมิชชัน</label>
          <p class="font-semibold mt-1 mb-3">{{ $photographer->commission_rate ?? 0 }}%</p>
        </div>
        <div>
          <label class="text-gray-500 text-xs uppercase tracking-wider font-medium">สถานะ</label>
          <p class="mt-1 mb-3">
            @if($photographer->status === 'active' || $photographer->status === 'approved')
              <span class="inline-block text-xs font-medium px-3 py-1 rounded-full" style="background:rgba(16,185,129,0.1);color:#10b981;">Active</span>
            @else
              <span class="inline-block text-xs font-medium px-3 py-1 rounded-full" style="background:rgba(245,158,11,0.1);color:#f59e0b;">{{ ucfirst($photographer->status ?? 'pending') }}</span>
            @endif
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Edit Form + Pro Upgrade --}}
<div class="pg-card">
  <div class="px-5 pt-5">
    <h6 class="font-semibold"><i class="bi bi-pencil mr-1 text-indigo-600"></i> แก้ไขโปรไฟล์</h6>
  </div>
  <div class="p-5 pt-3">
    {{-- Flash / validation messages — without this block, silent failures (e.g. a
         checkbox rule that rejects missing boxes) look like nothing happened
         when the user hits "Save". We learned that the hard way chasing a
         ghost bug where avatar uploads appeared no-op because an unrelated
         `accepted` rule was failing behind the scenes. --}}
    @if(session('success'))
      <div class="mb-4 p-3 rounded-lg flex items-start gap-2"
           style="background:rgba(16,185,129,0.1);color:#059669;border:1px solid rgba(16,185,129,0.25);">
        <i class="bi bi-check-circle mt-0.5"></i>
        <div class="text-sm">{{ session('success') }}</div>
      </div>
    @endif
    @if(session('error'))
      <div class="mb-4 p-3 rounded-lg flex items-start gap-2"
           style="background:rgba(239,68,68,0.1);color:#dc2626;border:1px solid rgba(239,68,68,0.25);">
        <i class="bi bi-exclamation-triangle mt-0.5"></i>
        <div class="text-sm">{{ session('error') }}</div>
      </div>
    @endif
    @if ($errors->any())
      <div class="mb-4 p-3 rounded-lg"
           style="background:rgba(239,68,68,0.08);color:#991b1b;border:1px solid rgba(239,68,68,0.25);">
        <div class="flex items-center gap-2 font-semibold text-sm mb-1">
          <i class="bi bi-exclamation-circle"></i> แก้ไขโปรไฟล์ไม่สำเร็จ — โปรดตรวจสอบข้อมูลด้านล่าง
        </div>
        <ul class="text-sm list-disc ms-5 mb-0">
          @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
        </ul>
      </div>
    @endif

    <form action="{{ route('photographer.profile.update') }}" method="POST" enctype="multipart/form-data" id="profile-form">
      @csrf
      @method('PUT')

      {{-- ═══════════════════════════════════════════════════════════════
           Avatar Upload — profile photo / logo
           • Client-side preview before save (no server round-trip)
           • "ลบรูป" sets a hidden flag so the controller nukes the file
           • Accepts jpg/png/webp/gif up to 4 MB (re-encoded to webp server-side)
           ═══════════════════════════════════════════════════════════════ --}}
      <div class="mb-6 pb-6 border-b border-gray-100">
        <label class="block text-sm font-medium text-gray-700 mb-3">
          <i class="bi bi-image mr-1 text-indigo-600"></i>รูปโปรไฟล์ / โลโก้
        </label>

        <div class="flex flex-col sm:flex-row items-start gap-5">
          {{-- Live preview --}}
          <div class="shrink-0 relative group">
            <div id="avatar-preview-ring"
                 class="relative w-32 h-32 rounded-full overflow-hidden border-4 border-white ring-2 ring-indigo-100 shadow-md bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center">
              @if($photographer->avatar)
                {{-- Route through the accessor so R2-hosted avatars resolve to
                     pub-*.r2.dev URLs, local-disk legacy avatars still work,
                     and OAuth pass-through URLs don't get double-prefixed. --}}
                <img id="avatar-preview-img"
                     src="{{ $photographer->avatar_url }}"
                     alt="Avatar preview"
                     class="w-full h-full object-cover">
              @else
                <span id="avatar-preview-initials" class="text-white font-bold text-4xl">
                  {{ mb_strtoupper(mb_substr($photographer->display_name ?? 'P', 0, 1)) }}
                </span>
                <img id="avatar-preview-img" src="" alt="" class="w-full h-full object-cover hidden">
              @endif
            </div>

            {{-- "Choose photo" overlay on hover --}}
            <label for="avatar-input"
                   class="absolute inset-0 rounded-full bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer flex flex-col items-center justify-center text-white text-xs font-medium">
              <i class="bi bi-camera-fill text-2xl mb-1"></i>
              เปลี่ยนรูป
            </label>
          </div>

          {{-- Action buttons + info --}}
          <div class="flex-1 w-full">
            <div class="flex flex-wrap gap-2 mb-3">
              <label for="avatar-input"
                     class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-indigo-200 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-sm font-medium cursor-pointer transition">
                <i class="bi bi-upload"></i>
                <span id="avatar-upload-label">เลือกรูปใหม่</span>
              </label>
              <input type="file" id="avatar-input" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden @error('avatar') ring-red-500 @enderror">

              @if($photographer->avatar)
                <button type="button" id="avatar-remove-btn"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-red-200 bg-red-50 hover:bg-red-100 text-red-700 text-sm font-medium transition">
                  <i class="bi bi-trash"></i>
                  ลบรูป
                </button>
              @endif

              <button type="button" id="avatar-cancel-btn"
                      class="hidden inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-gray-200 bg-gray-50 hover:bg-gray-100 text-gray-700 text-sm font-medium transition">
                <i class="bi bi-x-lg"></i>
                ยกเลิก
              </button>

              {{-- Hidden flag — set to 1 when user clicks "ลบรูป" --}}
              <input type="hidden" name="remove_avatar" id="remove-avatar-flag" value="0">
            </div>

            <div class="text-xs text-gray-500 space-y-1">
              <div><i class="bi bi-info-circle mr-1"></i>รองรับไฟล์ JPG, PNG, WebP, GIF ขนาดไม่เกิน <strong>4 MB</strong></div>
              <div><i class="bi bi-magic mr-1"></i>ระบบจะย่อและแปลงเป็น WebP อัตโนมัติ (400×400)</div>
              <div><i class="bi bi-eye mr-1"></i>รูปนี้จะแสดงในหน้าโปรไฟล์สาธารณะ, รายการอีเวนต์ และหน้าแรก</div>
            </div>

            @error('avatar')
              <p class="text-red-500 text-xs mt-2"><i class="bi bi-exclamation-triangle mr-1"></i>{{ $message }}</p>
            @enderror

            <p id="avatar-error" class="hidden text-red-500 text-xs mt-2">
              <i class="bi bi-exclamation-triangle mr-1"></i>
              <span id="avatar-error-msg"></span>
            </p>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">ชื่อที่แสดง</label>
          <input type="text" name="display_name" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('display_name') border-red-500 @enderror" value="{{ old('display_name', $photographer->display_name) }}">
          @error('display_name')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Portfolio URL</label>
          <input type="url" name="portfolio_url" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('portfolio_url') border-red-500 @enderror" value="{{ old('portfolio_url', $photographer->portfolio_url) }}" placeholder="https://">
          @error('portfolio_url')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">
            จังหวัด <span class="text-gray-400 text-xs">(โชว์บนโปรไฟล์ + ใช้ filter ค้นหา)</span>
          </label>
          @php
            // Cache province list 1 hour — table is static (~77 rows) and
            // the form opens often. CMS-style "set once, never refetch".
            $_provinces = \Illuminate\Support\Facades\Cache::remember(
                'thai_provinces_select', 3600,
                fn() => \App\Models\ThaiProvince::orderBy('name_th')->get(['id', 'name_th']),
            );
          @endphp
          <select name="province_id"
                  class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 @error('province_id') border-red-500 @enderror">
            <option value="">— ยังไม่ระบุ —</option>
            @foreach($_provinces as $_p)
              <option value="{{ $_p->id }}"
                {{ (int) old('province_id', $photographer->province_id) === (int) $_p->id ? 'selected' : '' }}>
                {{ $_p->name_th }}
              </option>
            @endforeach
          </select>
          @error('province_id')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1.5">หัวข้อสั้นๆ (Headline) <span class="text-xs text-gray-400 font-normal">— เช่น "ช่างภาพงานแต่งงาน กรุงเทพ"</span></label>
          <input type="text" name="headline" maxlength="200"
                 value="{{ old('headline', $photographer->headline) }}"
                 placeholder="ช่างภาพ Pre-wedding & งานแต่งงาน 5+ ปี"
                 class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          <p class="text-[11px] text-gray-400 mt-1">แสดงใต้ชื่อบนโปรไฟล์สาธารณะ + ใช้ใน SEO meta</p>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1.5">ประวัติย่อ (Bio)</label>
          <textarea name="bio" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('bio') border-red-500 @enderror" rows="3" placeholder="เล่าเกี่ยวกับสไตล์การถ่ายภาพ ประสบการณ์ จุดเด่น...">{{ old('bio', $photographer->bio) }}</textarea>
          @error('bio')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>

        {{-- ── Experience + Specialties ─────────────────────── --}}
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">ประสบการณ์ (ปี)</label>
          <input type="number" name="years_experience" min="0" max="60"
                 value="{{ old('years_experience', $photographer->years_experience) }}"
                 class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">เวลาตอบกลับ (ชั่วโมง)</label>
          <input type="number" name="response_time_hours" min="1" max="168"
                 value="{{ old('response_time_hours', $photographer->response_time_hours) }}"
                 placeholder="เช่น 24"
                 class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
          <p class="text-[11px] text-gray-400 mt-1">ใช้แสดง badge "ตอบกลับภายใน X ชม."</p>
        </div>

        {{-- ── Specialties (tag input) ─────────────────────── --}}
        <div class="md:col-span-2" x-data="{ items: {{ json_encode(old('specialties', $photographer->specialties ?? [])) }}, input: '' }">
          <label class="block text-sm font-medium text-gray-700 mb-1.5">ความเชี่ยวชาญ (Specialties)</label>
          <div class="flex flex-wrap gap-2 mb-2 min-h-[40px] p-2 border border-gray-200 rounded-lg bg-gray-50">
            <template x-for="(item, i) in items" :key="i">
              <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-indigo-100 text-indigo-700 text-xs font-medium">
                <span x-text="item"></span>
                <input type="hidden" :name="'specialties[]'" :value="item">
                <button type="button" @click="items.splice(i, 1)" class="hover:text-indigo-900"><i class="bi bi-x"></i></button>
              </span>
            </template>
            <span x-show="items.length === 0" class="text-xs text-gray-400">เพิ่มความเชี่ยวชาญ เช่น งานแต่ง, ปริญญา, สปอร์ต</span>
          </div>
          <div class="flex gap-2">
            <input type="text" x-model="input" @keydown.enter.prevent="if(input.trim()) { items.push(input.trim()); input='' }"
                   placeholder="พิมพ์แล้วกด Enter — เช่น งานแต่ง, Pre-wedding"
                   class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-sm">
            <button type="button" @click="if(input.trim()) { items.push(input.trim()); input='' }"
                    class="px-4 py-2 bg-indigo-500 text-white rounded-lg text-sm">+</button>
          </div>
        </div>

        {{-- ── Languages ─────────────────────── --}}
        <div class="md:col-span-2" x-data="{ items: {{ json_encode(old('languages', $photographer->languages ?? [])) }}, input: '' }">
          <label class="block text-sm font-medium text-gray-700 mb-1.5">ภาษาที่สื่อสารได้</label>
          <div class="flex flex-wrap gap-2 mb-2 min-h-[40px] p-2 border border-gray-200 rounded-lg bg-gray-50">
            <template x-for="(item, i) in items" :key="i">
              <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-medium">
                <span x-text="item.toUpperCase()"></span>
                <input type="hidden" :name="'languages[]'" :value="item">
                <button type="button" @click="items.splice(i, 1)"><i class="bi bi-x"></i></button>
              </span>
            </template>
            <span x-show="items.length === 0" class="text-xs text-gray-400">เลือกหรือพิมพ์</span>
          </div>
          <div class="flex flex-wrap gap-2">
            @foreach(['th' => 'ไทย', 'en' => 'English', 'zh' => '中文', 'ja' => '日本語', 'ko' => '한국어'] as $code => $label)
              <button type="button" @click="if(!items.includes('{{ $code }}')) items.push('{{ $code }}')"
                      class="px-3 py-1 text-xs rounded-full bg-white border border-gray-200 hover:bg-emerald-50">
                + {{ $label }}
              </button>
            @endforeach
          </div>
        </div>

        {{-- ── Equipment list ─────────────────────── --}}
        <div class="md:col-span-2" x-data="{ items: {{ json_encode(old('equipment', $photographer->equipment ?? [])) }}, input: '' }">
          <label class="block text-sm font-medium text-gray-700 mb-1.5">อุปกรณ์ (Equipment) <span class="text-xs text-gray-400 font-normal">— กล้อง, เลนส์</span></label>
          <div class="space-y-1 mb-2">
            <template x-for="(item, i) in items" :key="i">
              <div class="flex items-center gap-2">
                <input type="text" :value="item" @input="items[i] = $event.target.value" :name="'equipment[]'"
                       class="flex-1 px-3 py-1.5 border border-gray-200 rounded text-sm">
                <button type="button" @click="items.splice(i, 1)" class="px-2 py-1.5 text-red-500 hover:bg-red-50 rounded"><i class="bi bi-trash text-xs"></i></button>
              </div>
            </template>
          </div>
          <button type="button" @click="items.push('')" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded text-xs">+ เพิ่มอุปกรณ์</button>
          <p class="text-[11px] text-gray-400 mt-1">ตัวอย่าง: Canon R5, Sigma 85mm f/1.4 Art</p>
        </div>

        {{-- ── Social media + contact ─────────────────────── --}}
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5"><i class="bi bi-globe text-blue-500"></i> Website</label>
          <input type="url" name="website_url" maxlength="300"
                 value="{{ old('website_url', $photographer->website_url) }}"
                 placeholder="https://yourwebsite.com"
                 class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5"><i class="bi bi-instagram text-pink-500"></i> Instagram</label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">@</span>
            <input type="text" name="instagram_handle" maxlength="80"
                   value="{{ old('instagram_handle', $photographer->instagram_handle) }}"
                   placeholder="username"
                   class="w-full pl-7 pr-4 py-2.5 border border-gray-200 rounded-lg text-sm">
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5"><i class="bi bi-facebook text-blue-600"></i> Facebook URL</label>
          <input type="url" name="facebook_url" maxlength="300"
                 value="{{ old('facebook_url', $photographer->facebook_url) }}"
                 placeholder="https://facebook.com/yourpage"
                 class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5"><i class="bi bi-line text-green-500"></i> LINE ID <span class="text-xs text-gray-400 font-normal">(เก็บส่วนตัว)</span></label>
          <input type="text" name="line_id" maxlength="80"
                 value="{{ old('line_id', $photographer->line_id) }}"
                 class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm">
        </div>

        {{-- ── Booking acceptance toggle ─────────────────────── --}}
        <div class="md:col-span-2">
          <label class="flex items-start gap-3 p-3 rounded-lg bg-emerald-50 border border-emerald-200 cursor-pointer">
            <input type="hidden" name="accepts_bookings" value="0">
            <input type="checkbox" name="accepts_bookings" value="1" {{ ($photographer->accepts_bookings ?? true) ? 'checked' : '' }} class="w-5 h-5 mt-0.5">
            <div>
              <div class="text-sm font-semibold text-emerald-800"><i class="bi bi-check-circle-fill"></i> รับงานใหม่</div>
              <div class="text-xs text-emerald-700 mt-0.5">ลูกค้าจะเห็น badge "รับงาน" บนโปรไฟล์ — ปิดเมื่อยุ่งหรือกำลังหยุดพัก</div>
            </div>
          </label>
        </div>
      </div>

      {{-- ════════════════════════════════════════════════════════════
           Action checklist — items the photographer should fix to
           reach 100% completeness. Surfaces missing PromptPay / bank /
           bio / portfolio in one place so they don't have to hunt.
           Replaces the legacy "Pro Tier" panel which depended on the
           retired admin-promote-to-Pro flow.
           ════════════════════════════════════════════════════════════ --}}
      @php
        $todoItems = [];
        if (!$hasPromptPay) {
            $todoItems[] = ['icon' => 'bi-qr-code', 'label' => 'เพิ่ม PromptPay', 'note' => 'จำเป็นเพื่อรับเงินอัตโนมัติ', 'href' => route('photographer.setup-bank'), 'critical' => true];
        }
        if (!$hasBank) {
            $todoItems[] = ['icon' => 'bi-bank', 'label' => 'เชื่อมบัญชีธนาคาร', 'note' => 'สำรองช่องทางรับเงิน', 'href' => route('photographer.setup-bank'), 'critical' => false];
        }
        if (!$hasBio) {
            $todoItems[] = ['icon' => 'bi-card-text', 'label' => 'เขียนประวัติย่อ (Bio)', 'note' => 'ลูกค้าจะเห็นบนโปรไฟล์สาธารณะ', 'href' => null, 'critical' => false];
        }
        if (!$hasPortfolio) {
            $todoItems[] = ['icon' => 'bi-images', 'label' => 'เพิ่ม Portfolio', 'note' => 'ตัวอย่างผลงาน 3-5 รูป', 'href' => null, 'critical' => false];
        }
      @endphp

      @if(count($todoItems) > 0)
      <div class="mt-8 border-t border-gray-200 dark:border-white/10 pt-6">
        <h6 class="font-bold text-base text-gray-900 dark:text-white mb-3 flex items-center gap-2">
          <span class="w-7 h-7 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center text-xs">
            <i class="bi bi-check2-square"></i>
          </span>
          สิ่งที่ควรทำให้โปรไฟล์สมบูรณ์
          <span class="text-xs font-medium text-gray-500 dark:text-gray-400">({{ count($todoItems) }} รายการ)</span>
        </h6>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
          @foreach($todoItems as $todo)
            <div class="flex items-start gap-3 px-3 py-2.5 rounded-xl border {{ $todo['critical'] ? 'border-rose-200 bg-rose-50 dark:border-rose-500/20 dark:bg-rose-950/20' : 'border-gray-200 bg-gray-50 dark:border-white/[0.06] dark:bg-white/[0.03]' }}">
              <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ $todo['critical'] ? 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300' : 'bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-300' }}">
                <i class="bi {{ $todo['icon'] }}"></i>
              </div>
              <div class="flex-1 min-w-0">
                <p class="font-semibold text-sm text-gray-900 dark:text-white m-0 flex items-center gap-1.5">
                  {{ $todo['label'] }}
                  @if($todo['critical'])
                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-rose-500 text-white tracking-wider uppercase">ต้องทำ</span>
                  @endif
                </p>
                <p class="text-[11px] text-gray-500 dark:text-gray-400 m-0 mt-0.5">{{ $todo['note'] }}</p>
              </div>
              @if($todo['href'])
                <a href="{{ $todo['href'] }}" class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline shrink-0 self-center">
                  ไปทำ →
                </a>
              @endif
            </div>
          @endforeach
        </div>
      </div>
      @else
      <div class="mt-8 border-t border-gray-200 dark:border-white/10 pt-6">
        <div class="rounded-xl border border-emerald-200 dark:border-emerald-500/30 bg-emerald-50 dark:bg-emerald-950/30 p-4 flex items-center gap-3">
          <div class="w-10 h-10 rounded-full bg-emerald-500 flex items-center justify-center text-white shrink-0">
            <i class="bi bi-check-lg text-xl"></i>
          </div>
          <div>
            <p class="font-bold text-emerald-900 dark:text-emerald-200 m-0">โปรไฟล์สมบูรณ์ครบถ้วน 🎉</p>
            <p class="text-sm text-emerald-700 dark:text-emerald-300/80 m-0 mt-0.5">
              ข้อมูลครบทุกช่อง · พร้อมรับงานเต็มรูปแบบ
            </p>
          </div>
        </div>
      </div>
      @endif

      <div class="mt-6">
        <button type="submit" class="bg-gradient-to-br from-indigo-600 to-indigo-700 text-white font-medium px-6 py-2.5 rounded-lg border-none inline-flex items-center gap-1 transition hover:shadow-lg">
          <i class="bi bi-check-lg mr-1"></i> บันทึก
        </button>
      </div>
    </form>
  </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════
     Avatar behaviour: preview + client-side validation + remove flow.
     Intentionally plain JS — no Alpine / jQuery dependency so this
     keeps working even on pages that haven't loaded those libs.
     ═══════════════════════════════════════════════════════════════ --}}
<script>
  (function () {
    const MAX_BYTES = 4 * 1024 * 1024; // 4 MB (matches server-side max:4096)
    const input     = document.getElementById('avatar-input');
    const previewImg = document.getElementById('avatar-preview-img');
    const previewInitials = document.getElementById('avatar-preview-initials');
    const ring      = document.getElementById('avatar-preview-ring');
    const removeBtn = document.getElementById('avatar-remove-btn');
    const cancelBtn = document.getElementById('avatar-cancel-btn');
    const removeFlag = document.getElementById('remove-avatar-flag');
    const uploadLabel = document.getElementById('avatar-upload-label');
    const errBox    = document.getElementById('avatar-error');
    const errMsg    = document.getElementById('avatar-error-msg');
    if (!input) return;

    // Remember original state so we can restore on "Cancel"
    const originalSrc = previewImg ? previewImg.getAttribute('src') : '';
    const originalHadImage = !!(previewImg && originalSrc);

    function showError(msg) {
      errMsg.textContent = msg;
      errBox.classList.remove('hidden');
      ring.classList.add('ring-red-400');
    }
    function clearError() {
      errBox.classList.add('hidden');
      ring.classList.remove('ring-red-400');
    }
    function toggleCancel(show) {
      if (!cancelBtn) return;
      cancelBtn.classList.toggle('hidden', !show);
    }

    // ── File chosen → preview + size check ──────────────────────────
    input.addEventListener('change', function (e) {
      const file = e.target.files && e.target.files[0];
      if (!file) return;

      clearError();

      if (!file.type.startsWith('image/')) {
        showError('กรุณาเลือกไฟล์รูปภาพเท่านั้น');
        input.value = '';
        return;
      }
      if (file.size > MAX_BYTES) {
        const mb = (file.size / 1024 / 1024).toFixed(2);
        showError(`ไฟล์ใหญ่เกินไป (${mb} MB) — จำกัด 4 MB`);
        input.value = '';
        return;
      }

      // User is adding a new file → cancel any prior "remove" intent
      if (removeFlag) removeFlag.value = '0';

      const reader = new FileReader();
      reader.onload = function (evt) {
        if (previewImg) {
          previewImg.src = evt.target.result;
          previewImg.classList.remove('hidden');
        }
        if (previewInitials) previewInitials.style.display = 'none';
        uploadLabel.textContent = 'เลือกไฟล์อื่น';
        toggleCancel(true);
      };
      reader.readAsDataURL(file);
    });

    // ── Remove current avatar (marks flag; real delete happens server-side) ──
    if (removeBtn) {
      removeBtn.addEventListener('click', function () {
        if (!confirm('ต้องการลบรูปโปรไฟล์ใช่หรือไม่? รูปจะถูกลบเมื่อคุณกด "บันทึก"')) return;
        if (removeFlag) removeFlag.value = '1';
        input.value = '';
        if (previewImg) {
          previewImg.src = '';
          previewImg.classList.add('hidden');
        }
        if (previewInitials) previewInitials.style.display = '';
        uploadLabel.textContent = 'เลือกรูปใหม่';
        toggleCancel(true);
        clearError();
      });
    }

    // ── Cancel: restore the preview + inputs back to original state ──
    if (cancelBtn) {
      cancelBtn.addEventListener('click', function () {
        input.value = '';
        if (removeFlag) removeFlag.value = '0';
        if (previewImg) {
          if (originalHadImage) {
            previewImg.src = originalSrc;
            previewImg.classList.remove('hidden');
            if (previewInitials) previewInitials.style.display = 'none';
          } else {
            previewImg.src = '';
            previewImg.classList.add('hidden');
            if (previewInitials) previewInitials.style.display = '';
          }
        }
        uploadLabel.textContent = 'เลือกรูปใหม่';
        toggleCancel(false);
        clearError();
      });
    }
  })();
</script>
@endsection
