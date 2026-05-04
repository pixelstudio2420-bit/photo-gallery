@php
// Load footer settings from DB
$_footerKeys = [
  'footer_social_enabled','footer_social_facebook','footer_social_instagram',
  'footer_social_line','footer_social_tiktok','footer_social_youtube','footer_social_twitter',
  'footer_contact_enabled','footer_contact_email','footer_contact_phone',
  'footer_contact_line_id','footer_contact_address',
];
$_footerRows = \DB::table('app_settings')
  ->whereIn('key', $_footerKeys)
  ->pluck('value', 'key')
  ->toArray();

$_socialEnabled = ($_footerRows['footer_social_enabled'] ?? '1') === '1';
$_contactEnabled = ($_footerRows['footer_contact_enabled'] ?? '1') === '1';

// Legal pages — list published canonical + custom pages for the footer nav.
// PostgreSQL doesn't support MySQL's FIELD() function, so we order with a
// CASE expression instead. The 3 canonical slugs surface first; everything
// else falls through to alphabetical.
try {
  $_legalPages = \App\Models\LegalPage::published()
      ->orderByRaw("CASE slug WHEN 'privacy-policy' THEN 1 WHEN 'terms-of-service' THEN 2 WHEN 'refund-policy' THEN 3 ELSE 99 END")
      ->orderBy('title')
      ->get(['slug', 'title']);
} catch (\Throwable $e) {
  $_legalPages = collect(); // Table not yet migrated — render nothing rather than crash
}

$_socialLinks = [];
$_socialMap = [
  'footer_social_facebook' => ['icon' => 'bi-facebook', 'label' => 'Facebook'],
  'footer_social_instagram' => ['icon' => 'bi-instagram', 'label' => 'Instagram'],
  'footer_social_line'   => ['icon' => 'bi-line',   'label' => 'LINE'],
  'footer_social_tiktok'  => ['icon' => 'bi-tiktok',  'label' => 'TikTok'],
  'footer_social_youtube'  => ['icon' => 'bi-youtube',  'label' => 'YouTube'],
  'footer_social_twitter'  => ['icon' => 'bi-twitter-x', 'label' => 'X (Twitter)'],
];
foreach ($_socialMap as $key => $info) {
  $url = trim($_footerRows[$key] ?? '');
  if ($url !== '') {
    $_socialLinks[] = ['url' => $url, 'icon' => $info['icon'], 'label' => $info['label']];
  }
}

// Brand resolution — mirror navbar logic so footer + navbar stay in sync.
$_footerLogoUrl = null;
if (!empty($siteLogo)) {
  try {
    $_footerLogoUrl = app(\App\Services\StorageManager::class)->resolveUrl($siteLogo);
  } catch (\Throwable) { /* fall through to icon */ }
}
$_footerBrandName = $siteName ?: config('app.name');
@endphp

<footer class="relative bg-gradient-to-b from-slate-900 to-slate-950 text-white pt-16 pb-8 overflow-hidden">

  {{-- Decorative blobs (subtle, behind content) --}}
  <div class="absolute -top-32 -right-20 w-96 h-96 rounded-full pointer-events-none"
       style="background:radial-gradient(circle,rgba(99,102,241,0.10) 0%,transparent 70%);"></div>
  <div class="absolute -bottom-32 -left-20 w-96 h-96 rounded-full pointer-events-none"
       style="background:radial-gradient(circle,rgba(244,63,94,0.06) 0%,transparent 70%);"></div>

  <div class="relative max-w-7xl mx-auto px-4">

    {{-- ═══════════════════════════════════════════════════════════════
         CTA Strip — drives photographer signups + customer pricing CTA
         (visible above the column links since this is the highest-intent
         action a footer-scrolling visitor is likely to take)
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="rounded-2xl bg-gradient-to-r from-indigo-600/20 via-violet-600/20 to-pink-600/20 border border-white/10 backdrop-blur-sm p-5 sm:p-6 mb-12 flex flex-col sm:flex-row items-center justify-between gap-4">
      <div class="flex items-center gap-4 text-center sm:text-left">
        <div class="hidden sm:flex w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 items-center justify-center shadow-lg shadow-indigo-500/30 shrink-0">
          <i class="bi bi-camera-fill text-white text-xl"></i>
        </div>
        <div>
          <p class="font-bold text-white text-base">เป็นช่างภาพ? เริ่มขายรูปฟรีตลอดชีพ</p>
          <p class="text-xs text-white/70 mt-0.5">ไม่ใช้บัตรเครดิต · commission 0% บน Pro · ส่งรูปเข้า LINE อัตโนมัติ</p>
        </div>
      </div>
      <div class="flex flex-wrap gap-2 shrink-0">
        {{-- Inline style dodges darkmode.css's [data-bs-theme="dark"] .bg-white
             override that would re-tint this white button to slate-800 on
             dark mode (making the indigo-700 text unreadable on dark footer). --}}
        <a href="{{ route('photographer-onboarding.quick') }}"
           class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-bold ring-1 ring-inset ring-white/40 shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all"
           style="background:rgb(255,255,255);color:#4338ca;">
          <i class="bi bi-rocket-takeoff"></i>ลงทะเบียนช่างภาพ
        </a>
        <a href="{{ route('pricing') }}"
           class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-bold text-white border border-white/30 hover:bg-white/10 transition-all">
          <i class="bi bi-tag-fill"></i>ดูราคา
        </a>
      </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         Main column grid
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-8 mb-10">

      {{-- ─── Column 1: Brand + value prop + social (4 cols) ─── --}}
      <div class="lg:col-span-4">
        <div class="flex items-center gap-2.5 mb-4">
          @if($_footerLogoUrl)
            <img src="{{ $_footerLogoUrl }}" alt="{{ $_footerBrandName }}"
                 class="h-9 w-auto max-w-[140px] object-contain"
                 onerror="this.onerror=null;this.replaceWith(Object.assign(document.createElement('i'),{className:'bi bi-camera2 text-2xl'}));">
          @else
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 shadow-md shadow-indigo-500/30">
              <i class="bi bi-camera2 text-white text-lg"></i>
            </span>
          @endif
          <h5 class="font-bold mb-0 text-white text-lg tracking-tight">{{ $_footerBrandName }}</h5>
        </div>
        <p class="text-gray-300/90 mb-4 text-sm leading-relaxed">
          แพลตฟอร์มซื้อขายรูปงานอีเวนต์อันดับ 1 ในไทย —
          <span class="text-white font-medium">AI Face Search</span> หาตัวเองใน 3 วินาที, จ่าย <span class="text-white font-medium">PromptPay</span> → รับรูปเข้า <span class="text-white font-medium">LINE</span> ทันที
        </p>

        {{-- Trust badges --}}
        <div class="flex flex-wrap gap-1.5 mb-5">
          <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-medium bg-emerald-500/10 text-emerald-300 border border-emerald-500/20">
            <i class="bi bi-shield-check"></i>SSL Secured
          </span>
          <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-medium bg-blue-500/10 text-blue-300 border border-blue-500/20">
            <i class="bi bi-receipt"></i>AI ตรวจสลิป
          </span>
          <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-medium bg-amber-500/10 text-amber-300 border border-amber-500/20">
            <i class="bi bi-flag-fill"></i>Made in Thailand
          </span>
        </div>

        {{-- Social --}}
        @if($_socialEnabled && !empty($_socialLinks))
        <div class="flex flex-wrap gap-2">
          @foreach($_socialLinks as $sl)
          <a href="{{ $sl['url'] }}" target="_blank" rel="noopener noreferrer" title="{{ $sl['label'] }}"
             class="w-9 h-9 flex items-center justify-center rounded-lg bg-white/5 border border-white/10 hover:bg-white/15 hover:border-white/30 hover:-translate-y-0.5 text-white transition-all">
            <i class="bi {{ $sl['icon'] }}"></i>
          </a>
          @endforeach
        </div>
        @endif
      </div>

      {{-- ─── Column 2: Discover (2 cols) ─── --}}
      <div class="lg:col-span-2">
        <h6 class="font-semibold mb-3 text-white text-sm uppercase tracking-wider">{{ __('common.see_more') }}</h6>
        <ul class="list-none space-y-2 text-sm">
          <li><a href="{{ route('home') }}" class="text-gray-400 hover:text-white transition">{{ __('nav.home') }}</a></li>
          <li><a href="{{ route('events.index') }}" class="text-gray-400 hover:text-white transition">{{ __('nav.events') }}</a></li>
          <li><a href="{{ route('photographers.index') }}" class="text-gray-400 hover:text-white transition">ช่างภาพ</a></li>
          @if(\App\Support\Features::blogEnabled())
          <li><a href="{{ route('blog.index') }}" class="text-gray-400 hover:text-white transition">{{ __('nav.blog') }}</a></li>
          @endif
          <li><a href="{{ route('pricing') }}" class="text-gray-400 hover:text-white transition flex items-center gap-1">ราคา<span class="text-[9px] px-1 py-0.5 rounded bg-amber-500/20 text-amber-300 font-bold">NEW</span></a></li>
          <li><a href="{{ route('help') }}" class="text-gray-400 hover:text-white transition">{{ __('nav.help') }}</a></li>
        </ul>
      </div>

      {{-- ─── Column 3: For photographers (2 cols) ─── --}}
      <div class="lg:col-span-2">
        <h6 class="font-semibold mb-3 text-white text-sm uppercase tracking-wider">สำหรับช่างภาพ</h6>
        <ul class="list-none space-y-2 text-sm">
          @auth
            @if(Auth::user()->photographerProfile && Auth::user()->photographerProfile->status === 'approved')
            <li><a href="{{ route('photographer.dashboard') }}" class="text-gray-400 hover:text-white transition">{{ __('nav.photographer_dashboard') }}</a></li>
            <li><a href="{{ route('photographer.events.index') }}" class="text-gray-400 hover:text-white transition">{{ __('photographer.my_events') }}</a></li>
            <li><a href="{{ route('photographer.subscription.plans') }}" class="text-gray-400 hover:text-white transition">แพ็กเกจ</a></li>
            @else
            <li><a href="{{ route('photographer-onboarding.quick') }}" class="text-gray-400 hover:text-white transition">{{ __('nav.become_photographer') }}</a></li>
            <li><a href="{{ route('sell-photos') }}" class="text-gray-400 hover:text-white transition">ทำไมต้องเลือกเรา</a></li>
            @endif
          @else
          <li><a href="{{ route('photographer-onboarding.quick') }}" class="text-gray-400 hover:text-white transition">{{ __('nav.become_photographer') }}</a></li>
          <li><a href="{{ route('sell-photos') }}" class="text-gray-400 hover:text-white transition">ทำไมต้องเลือกเรา</a></li>
          <li><a href="{{ route('photographer.login') }}" class="text-gray-400 hover:text-white transition">เข้าสู่ระบบช่างภาพ</a></li>
          @endauth
          <li><a href="{{ route('api.docs') }}" class="text-gray-400 hover:text-white transition flex items-center gap-1"><i class="bi bi-code-slash text-xs"></i>API Docs</a></li>
        </ul>
      </div>

      {{-- ─── Column 4: Newsletter + contact (4 cols) ─── --}}
      <div class="lg:col-span-4">
        <h6 class="font-semibold mb-3 text-white text-sm uppercase tracking-wider">รับข่าวสาร & โปรโมชั่น</h6>
        <p class="text-xs text-gray-400 mb-3 leading-relaxed">
          อีเวนต์ใหม่ + โปรโมชั่น + เคล็ดลับช่างภาพ — ส่งให้สัปดาห์ละครั้ง ยกเลิกได้ทุกเมื่อ
        </p>
        <form method="POST" action="{{ route('newsletter.subscribe') }}" class="mb-5"
              x-data="{ status: 'idle', msg: '' }"
              @submit.prevent="
                status = 'loading';
                fetch($el.action, {
                  method: 'POST',
                  body: new FormData($el),
                  headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                })
                .then(r => r.json().catch(() => ({ success: r.ok })))
                .then(d => { status = (d.success || d.ok) ? 'ok' : 'err'; msg = d.message || ''; if (status==='ok') $el.reset(); })
                .catch(() => { status = 'err'; });
              ">
          @csrf
          <div class="relative">
            <input type="email" name="email" required placeholder="email@example.com"
                   class="w-full pl-3 pr-28 py-2.5 rounded-xl bg-white/5 border border-white/15 text-white text-sm placeholder-gray-500
                          focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 transition-colors">
            <button type="submit" :disabled="status === 'loading'"
                    class="absolute right-1.5 top-1/2 -translate-y-1/2 px-4 py-1.5 rounded-lg text-xs font-bold text-white bg-gradient-to-r from-indigo-500 to-violet-600 hover:from-indigo-600 hover:to-violet-700 disabled:opacity-50 transition-all">
              <span x-show="status === 'idle' || status === 'err'">สมัคร</span>
              <span x-show="status === 'loading'" x-cloak><i class="bi bi-hourglass-split"></i></span>
              <span x-show="status === 'ok'" x-cloak><i class="bi bi-check-lg"></i></span>
            </button>
          </div>
          <p x-show="status === 'ok'" x-cloak class="text-xs text-emerald-400 mt-2">
            <i class="bi bi-check-circle-fill"></i> สมัครสำเร็จ — ตรวจอีเมลเพื่อยืนยัน
          </p>
          <p x-show="status === 'err'" x-cloak class="text-xs text-rose-400 mt-2">
            <i class="bi bi-exclamation-circle-fill"></i> สมัครไม่สำเร็จ ลองใหม่หรือติดต่อ support
          </p>
        </form>

        {{-- Contact info (compact, only when contact enabled + has data) --}}
        @if($_contactEnabled)
          @php
            $_hasContact = !empty($_footerRows['footer_contact_email'])
                        || !empty($_footerRows['footer_contact_phone'])
                        || !empty($_footerRows['footer_contact_line_id']);
          @endphp
          @if($_hasContact)
          <h6 class="font-semibold mb-2 text-white text-sm uppercase tracking-wider">ติดต่อเรา</h6>
          <ul class="list-none space-y-1.5 text-xs">
            @if(!empty($_footerRows['footer_contact_email']))
            <li><a href="mailto:{{ $_footerRows['footer_contact_email'] }}" class="text-gray-400 hover:text-white transition inline-flex items-center gap-1.5"><i class="bi bi-envelope"></i>{{ $_footerRows['footer_contact_email'] }}</a></li>
            @endif
            @if(!empty($_footerRows['footer_contact_line_id']))
            <li><a href="https://line.me/ti/p/~{{ $_footerRows['footer_contact_line_id'] }}" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-white transition inline-flex items-center gap-1.5"><i class="bi bi-line text-emerald-400"></i>{{ $_footerRows['footer_contact_line_id'] }}</a></li>
            @endif
            @if(!empty($_footerRows['footer_contact_phone']))
            <li><a href="tel:{{ $_footerRows['footer_contact_phone'] }}" class="text-gray-400 hover:text-white transition inline-flex items-center gap-1.5"><i class="bi bi-telephone"></i>{{ $_footerRows['footer_contact_phone'] }}</a></li>
            @endif
          </ul>
          @endif
        @endif
      </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         Payment methods strip — quick trust signal: "we accept these"
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="flex flex-wrap items-center justify-center sm:justify-between gap-3 py-4 border-y border-white/10">
      <span class="text-[11px] uppercase tracking-wider text-gray-500 font-semibold">รับชำระ</span>
      <div class="flex flex-wrap items-center gap-2">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-white/5 border border-white/10 text-gray-300">
          <i class="bi bi-qr-code text-emerald-400"></i>PromptPay
        </span>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-white/5 border border-white/10 text-gray-300">
          <i class="bi bi-bank2 text-blue-400"></i>โอนผ่านธนาคาร
        </span>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-white/5 border border-white/10 text-gray-300">
          <i class="bi bi-credit-card-2-front text-violet-400"></i>บัตรเครดิต
        </span>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-white/5 border border-white/10 text-gray-300">
          <i class="bi bi-receipt text-amber-400"></i>ตรวจสลิปอัตโนมัติ
        </span>
      </div>
    </div>

    {{-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        Internal-linking strip — points crawlers (and users) at the
        programmatic SEO landings. Listed by category for skim-readability.
        Only renders when the seo_landings config is present.
        ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ --}}
    @php
      $_seoCfg = config('seo_landings');
      $_topProvinces = ['bangkok' => 'กรุงเทพ', 'chiang-mai' => 'เชียงใหม่', 'phuket' => 'ภูเก็ต', 'pattaya' => 'พัทยา'];
    @endphp
    @if(!empty($_seoCfg) && !empty($_seoCfg['niches']))
    <div class="mt-6 pt-6 border-t border-white/10">
      <h6 class="font-semibold mb-3 text-white text-sm uppercase tracking-wider">เลือกประเภทช่างภาพ · ทุกจังหวัดทั่วไทย</h6>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-1 text-xs">
        @foreach($_seoCfg['niches'] as $_nSlug => $_n)
          <div class="leading-relaxed">
            <a href="{{ route('seo.landing.niche', ['niche' => $_nSlug]) }}" class="text-gray-300 hover:text-white font-medium">
              <i class="bi {{ $_n['icon'] }} mr-1"></i>{{ $_n['label'] }}
            </a>
            <span class="text-gray-600">·</span>
            @foreach($_topProvinces as $_pSlug => $_pLabel)
              <a href="{{ route('seo.landing.province', ['niche' => $_nSlug, 'province' => $_pSlug]) }}" class="text-gray-500 hover:text-gray-300">{{ $_pLabel }}</a>@if(!$loop->last)<span class="text-gray-700">,</span> @endif
            @endforeach
          </div>
        @endforeach
      </div>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════
         Bottom bar — copyright, legal, made-with
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:flex-wrap justify-between items-center gap-3 mt-8 pt-6 border-t border-white/10 text-center sm:text-left">
      <small class="text-gray-500 order-2 sm:order-1">
        &copy; {{ date('Y') }} {{ $siteName ?? config('app.name') }}. {{ __('nav.all_rights_reserved') }}
      </small>
      @if($_legalPages->isNotEmpty())
      <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-xs order-1 sm:order-2">
        @foreach($_legalPages as $_lp)
          <a href="{{ route('legal.show', $_lp->slug) }}" class="text-gray-500 hover:text-gray-300 transition">{{ $_lp->title }}</a>
          @if(!$loop->last)<span class="text-gray-700">·</span>@endif
        @endforeach
        <span class="text-gray-700">·</span>
        <a href="{{ route('sitemap') }}" class="text-gray-500 hover:text-gray-300 transition">Sitemap</a>
      </div>
      @endif
      <small class="text-gray-600 order-3 inline-flex items-center gap-1">
        Made with <i class="bi bi-heart-fill text-red-500"></i> in Thailand
      </small>
    </div>
  </div>
</footer>
