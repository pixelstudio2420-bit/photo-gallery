@extends('layouts.admin')

@section('title', 'Settings')

{{-- =======================================================================
     ADMIN SETTINGS — LIGHT/DARK DUAL-THEME REDESIGN
     -------------------------------------------------------------------
     • Form names, values, and submit action unchanged (drop-in replacement).
     • Full light/dark mode support — every surface has dark: variants.
     • Unified card pattern: rounded-2xl + border-slate-200 dark:border-white/10.
     • Field focus states + toggle switch match /admin/settings/line design.
     ====================================================================== --}}

@section('content')
<div class="max-w-6xl mx-auto pb-16">

  {{-- ────────── PAGE HEADER ────────── --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h4 class="text-2xl font-bold text-slate-900 dark:text-white mb-1 flex items-center gap-3 tracking-tight">
        <span class="inline-flex items-center justify-center w-11 h-11 rounded-2xl shadow-md shadow-indigo-500/30"
              style="background:linear-gradient(135deg,#6366f1,#8b5cf6);">
          <i class="bi bi-gear-fill text-white text-xl"></i>
        </span>
        Settings
      </h4>
      <p class="text-sm text-slate-500 dark:text-slate-400 ml-14">
        ค่าพื้นฐานของระบบ — เช่น ข้อมูลเว็บ, การชำระเงิน, ลายน้ำ, Google Drive
      </p>
    </div>
  </div>

  {{-- ────────── FLASH MESSAGE ────────── --}}
  @if(session('success'))
    <div class="mb-5 px-4 py-3 rounded-xl flex items-start gap-2
                bg-emerald-50 dark:bg-emerald-500/10
                border border-emerald-200 dark:border-emerald-500/30
                text-emerald-800 dark:text-emerald-300">
      <i class="bi bi-check-circle-fill mt-0.5"></i>
      <div class="flex-1 text-sm">{{ session('success') }}</div>
    </div>
  @endif

  <form method="POST" action="{{ route('admin.settings.index') }}" enctype="multipart/form-data">
    @csrf

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

      {{-- ════════════════════════════════════════════════════════
           CARD 1 — General
           ════════════════════════════════════════════════════════ --}}
      <div class="rounded-2xl bg-white dark:bg-slate-900
                  border border-slate-200 dark:border-white/10
                  shadow-sm shadow-slate-900/5 dark:shadow-black/20
                  overflow-hidden h-fit">

        <div class="px-5 py-4 border-b border-slate-100 dark:border-white/5 flex items-center gap-3">
          <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl
                       bg-blue-500/15 text-blue-600 dark:text-blue-300 shrink-0">
            <i class="bi bi-house-door-fill text-lg"></i>
          </span>
          <div>
            <h6 class="font-bold text-slate-900 dark:text-white text-[15px] leading-tight">General</h6>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
              ข้อมูลพื้นฐานของเว็บไซต์
            </div>
          </div>
        </div>

        <div class="p-5 space-y-4">
          {{-- Site Name --}}
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              Site Name
            </label>
            <input type="text" name="settings[site_name]"
                   value="{{ $settings['site_name'] ?? config('app.name') }}"
                   class="w-full px-3 py-2.5 rounded-lg text-sm
                          bg-white dark:bg-slate-800
                          border border-slate-300 dark:border-white/10
                          text-slate-900 dark:text-white
                          placeholder:text-slate-400 dark:placeholder:text-slate-500
                          focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none">
          </div>

          {{-- Site Logo — file upload, stored on the current upload driver
               (R2 in production, local public in dev). The resolved public
               URL is rendered in the navbar + footer brand areas, with a
               fallback camera icon when no logo is set or the file 404s.
               Keep the form as multipart/form-data for this to work. --}}
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              Site Logo
              <span class="text-slate-400 dark:text-slate-500 font-normal">(แสดงใน navbar/footer — PNG/SVG แนะนำ)</span>
            </label>

            @php
              $_currentLogoKey = (string) ($settings['site_logo'] ?? '');
              $_currentLogoUrl = null;
              if ($_currentLogoKey !== '') {
                try {
                  $_currentLogoUrl = app(\App\Services\StorageManager::class)->resolveUrl($_currentLogoKey);
                } catch (\Throwable) {}
              }
            @endphp

            @if($_currentLogoUrl)
              <div class="flex items-center gap-3 mb-2 p-2 rounded-lg
                          bg-slate-50 dark:bg-slate-800/50
                          border border-slate-200 dark:border-white/10">
                <img src="{{ $_currentLogoUrl }}" alt="Current logo"
                     class="h-10 w-auto max-w-[120px] object-contain bg-white rounded">
                <div class="text-xs text-slate-500 dark:text-slate-400 flex-1 truncate font-mono">
                  {{ $_currentLogoKey }}
                </div>
                <label class="inline-flex items-center gap-1.5 text-xs text-rose-600 dark:text-rose-400 cursor-pointer">
                  <input type="checkbox" name="remove_site_logo" value="1"
                         class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                  ลบโลโก้
                </label>
              </div>
            @endif

            <input type="file" name="site_logo_file"
                   accept="image/png,image/jpeg,image/webp,image/svg+xml"
                   class="w-full text-sm text-slate-600 dark:text-slate-300
                          file:mr-3 file:py-2 file:px-4 file:rounded-lg
                          file:border-0 file:text-sm file:font-semibold
                          file:bg-indigo-50 dark:file:bg-indigo-500/20
                          file:text-indigo-600 dark:file:text-indigo-300
                          hover:file:bg-indigo-100 dark:hover:file:bg-indigo-500/30
                          cursor-pointer">
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
              อัปโหลดรูปใหม่เพื่อเปลี่ยน (สูงสุด 2 MB) — เว้นว่างเพื่อคงค่าเดิม
            </p>
          </div>

          {{-- Description --}}
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              Site Description
            </label>
            <textarea name="settings[site_description]" rows="2"
                      class="w-full px-3 py-2.5 rounded-lg text-sm
                             bg-white dark:bg-slate-800
                             border border-slate-300 dark:border-white/10
                             text-slate-900 dark:text-white
                             placeholder:text-slate-400 dark:placeholder:text-slate-500
                             focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none">{{ $settings['site_description'] ?? '' }}</textarea>
          </div>

          {{-- Contact Email --}}
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              Contact Email
            </label>
            <div class="relative">
              <i class="bi bi-envelope absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500"></i>
              <input type="email" name="settings[contact_email]"
                     value="{{ $settings['contact_email'] ?? '' }}"
                     placeholder="hello@example.com"
                     class="w-full pl-9 pr-3 py-2.5 rounded-lg text-sm
                            bg-white dark:bg-slate-800
                            border border-slate-300 dark:border-white/10
                            text-slate-900 dark:text-white
                            placeholder:text-slate-400 dark:placeholder:text-slate-500
                            focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none">
            </div>
          </div>

          {{-- Contact Phone --}}
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              Contact Phone
            </label>
            <div class="relative">
              <i class="bi bi-telephone absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500"></i>
              <input type="text" name="settings[contact_phone]"
                     value="{{ $settings['contact_phone'] ?? '' }}"
                     placeholder="02-xxx-xxxx"
                     class="w-full pl-9 pr-3 py-2.5 rounded-lg text-sm
                            bg-white dark:bg-slate-800
                            border border-slate-300 dark:border-white/10
                            text-slate-900 dark:text-white
                            placeholder:text-slate-400 dark:placeholder:text-slate-500
                            focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none">
            </div>
          </div>

          {{-- Default Language --}}
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              Default Language
            </label>
            <select name="settings[default_language]"
                    class="w-full px-3 py-2.5 rounded-lg text-sm
                           bg-white dark:bg-slate-800
                           border border-slate-300 dark:border-white/10
                           text-slate-900 dark:text-white
                           focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none">
              <option value="th" {{ ($settings['default_language'] ?? 'th') === 'th' ? 'selected' : '' }}>🇹🇭 Thai</option>
              <option value="en" {{ ($settings['default_language'] ?? '') === 'en' ? 'selected' : '' }}>🇬🇧 English</option>
            </select>
          </div>
        </div>
      </div>

      {{-- ════════════════════════════════════════════════════════
           Right column — Payment + Photos stacked
           ════════════════════════════════════════════════════════ --}}
      <div class="space-y-5">

        {{-- CARD 2 — Payment --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900
                    border border-slate-200 dark:border-white/10
                    shadow-sm shadow-slate-900/5 dark:shadow-black/20
                    overflow-hidden">

          <div class="px-5 py-4 border-b border-slate-100 dark:border-white/5 flex items-center gap-3">
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl
                         bg-emerald-500/15 text-emerald-600 dark:text-emerald-300 shrink-0">
              <i class="bi bi-credit-card-fill text-lg"></i>
            </span>
            <div>
              <h6 class="font-bold text-slate-900 dark:text-white text-[15px] leading-tight">Payment</h6>
              <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                PromptPay + commission ให้ช่างภาพ
              </div>
            </div>
          </div>

          <div class="p-5 space-y-4">
            {{-- PromptPay Number --}}
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                PromptPay Number
              </label>
              <input type="text" name="settings[promptpay_number]"
                     value="{{ $settings['promptpay_number'] ?? '' }}"
                     placeholder="0812345678"
                     class="w-full px-3 py-2.5 rounded-lg text-sm font-mono
                            bg-white dark:bg-slate-800
                            border border-slate-300 dark:border-white/10
                            text-slate-900 dark:text-white
                            placeholder:text-slate-400 dark:placeholder:text-slate-500
                            focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 focus:outline-none">
            </div>

            {{-- PromptPay Name --}}
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                PromptPay Name
              </label>
              <input type="text" name="settings[promptpay_name]"
                     value="{{ $settings['promptpay_name'] ?? '' }}"
                     class="w-full px-3 py-2.5 rounded-lg text-sm
                            bg-white dark:bg-slate-800
                            border border-slate-300 dark:border-white/10
                            text-slate-900 dark:text-white
                            focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 focus:outline-none">
            </div>

            {{-- Commission --}}
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                Commission Rate (%)
                <span class="font-normal text-slate-400 dark:text-slate-500">— ช่างภาพได้รับเปอร์เซ็นต์นี้</span>
              </label>
              <div class="relative">
                <input type="number" name="settings[photographer_commission_rate]"
                       value="{{ $settings['photographer_commission_rate'] ?? 70 }}"
                       min="0" max="100"
                       class="w-full pl-3 pr-10 py-2.5 rounded-lg text-sm font-mono
                              bg-white dark:bg-slate-800
                              border border-slate-300 dark:border-white/10
                              text-slate-900 dark:text-white
                              focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 focus:outline-none">
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-slate-400 dark:text-slate-500">%</span>
              </div>
            </div>
          </div>
        </div>

        {{-- CARD — Photographer Tiers --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900
                    border border-slate-200 dark:border-white/10
                    shadow-sm shadow-slate-900/5 dark:shadow-black/20
                    overflow-hidden">

          <div class="px-5 py-4 border-b border-slate-100 dark:border-white/5 flex items-center gap-3">
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl
                         bg-amber-500/15 text-amber-600 dark:text-amber-300 shrink-0">
              <i class="bi bi-patch-check-fill text-lg"></i>
            </span>
            <div>
              <h6 class="font-bold text-slate-900 dark:text-white text-[15px] leading-tight">Photographer Tiers</h6>
              <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                ระดับ Creator / Seller / Pro
              </div>
            </div>
          </div>

          <div class="p-5 space-y-4">
            {{-- Pro Tier toggle --}}
            <div class="flex items-start justify-between gap-4 p-3 rounded-xl
                        bg-slate-50 dark:bg-slate-800/50
                        border border-slate-200 dark:border-white/10">
              <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold text-slate-900 dark:text-white">
                  เปิดใช้งาน Pro Tier
                </div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                  <strong>เปิด</strong> — ช่างภาพต้องส่งบัตรประชาชน + เซ็นสัญญาเพื่อปลดล็อก Pro (ไม่ลิมิต, badge verified)<br>
                  <strong>ปิด</strong> — Seller คือเพดานสุด ขายได้ไม่จำกัดโดยไม่ต้องมีเอกสาร เหมาะกับช่วงยังไม่มีทีม review
                </div>
              </div>
              <label class="relative inline-flex items-center cursor-pointer shrink-0 mt-0.5">
                <input type="checkbox" name="settings[photographer_pro_tier_enabled]" value="1" class="sr-only peer"
                       {{ ($settings['photographer_pro_tier_enabled'] ?? '1') == '1' ? 'checked' : '' }}>
                <div class="w-11 h-6 bg-slate-300 dark:bg-slate-700 rounded-full
                            peer-checked:bg-gradient-to-r peer-checked:from-amber-500 peer-checked:to-orange-500
                            peer-focus:ring-2 peer-focus:ring-amber-500/30 transition
                            after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                            after:bg-white after:rounded-full after:h-5 after:w-5 after:shadow
                            after:transition-all peer-checked:after:translate-x-5"></div>
              </label>
            </div>

            {{-- Info strip --}}
            <div class="rounded-xl px-3 py-2.5 text-[11px] leading-relaxed
                        bg-indigo-50 dark:bg-indigo-500/10
                        border border-indigo-200 dark:border-indigo-500/30
                        text-indigo-800 dark:text-indigo-200 flex items-start gap-2">
              <i class="bi bi-info-circle-fill mt-0.5"></i>
              <div>
                ปิดชั่วคราวได้ปลอดภัย — ช่างภาพที่เป็น Pro อยู่แล้วจะถูกแสดงเป็น Seller ในตอนนี้
                แต่ข้อมูลบัตร + สัญญายังเก็บไว้ ถ้าเปิดใหม่กลับเข้า Pro ทันที
              </div>
            </div>
          </div>
        </div>

        {{-- CARD — Subscriptions (Plan + AI features) --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900
                    border border-slate-200 dark:border-white/10
                    shadow-sm shadow-slate-900/5 dark:shadow-black/20
                    overflow-hidden">

          <div class="px-5 py-4 border-b border-slate-100 dark:border-white/5 flex items-center gap-3">
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl
                         bg-indigo-500/15 text-indigo-600 dark:text-indigo-300 shrink-0">
              <i class="bi bi-stars text-lg"></i>
            </span>
            <div>
              <h6 class="font-bold text-slate-900 dark:text-white text-[15px] leading-tight">Subscriptions</h6>
              <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                แผนสมัครสมาชิกรายเดือน + ฟีเจอร์ AI สำหรับช่างภาพ
              </div>
            </div>
          </div>

          <div class="p-5 space-y-4">
            {{-- Subscriptions toggle --}}
            <div class="flex items-start justify-between gap-4 p-3 rounded-xl
                        bg-slate-50 dark:bg-slate-800/50
                        border border-slate-200 dark:border-white/10">
              <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold text-slate-900 dark:text-white">
                  เปิดใช้งานระบบสมัครสมาชิก
                </div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                  <strong>เปิด</strong> — ช่างภาพเลือกแผนสมัครสมาชิกเพื่อปลดล็อกพื้นที่เก็บรูปและฟีเจอร์ AI (Face Search, Quality Filter ฯลฯ)<br>
                  <strong>ปิด</strong> — ระบบสมัครสมาชิกถูกปิดทั้งหมด: หน้าจัดการแผน, การคิดเงินอัตโนมัติ, และเมนูในแถบช่างภาพจะถูกซ่อน คำสั่ง renew/grace ก็จะหยุดทำงาน
                </div>
              </div>
              <label class="relative inline-flex items-center cursor-pointer shrink-0 mt-0.5">
                <input type="checkbox" name="settings[subscriptions_enabled]" value="1" class="sr-only peer"
                       {{ ($settings['subscriptions_enabled'] ?? '1') == '1' ? 'checked' : '' }}>
                <div class="w-11 h-6 bg-slate-300 dark:bg-slate-700 rounded-full
                            peer-checked:bg-gradient-to-r peer-checked:from-indigo-500 peer-checked:to-violet-500
                            peer-focus:ring-2 peer-focus:ring-indigo-500/30 transition
                            after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                            after:bg-white after:rounded-full after:h-5 after:w-5 after:shadow
                            after:transition-all peer-checked:after:translate-x-5"></div>
              </label>
            </div>

            {{-- Info strip --}}
            <div class="rounded-xl px-3 py-2.5 text-[11px] leading-relaxed
                        bg-indigo-50 dark:bg-indigo-500/10
                        border border-indigo-200 dark:border-indigo-500/30
                        text-indigo-800 dark:text-indigo-200 flex items-start gap-2">
              <i class="bi bi-info-circle-fill mt-0.5"></i>
              <div>
                ปิดได้ปลอดภัย — แผนปัจจุบันของช่างภาพยังคงอยู่ในฐานข้อมูล แต่จะไม่มีการเรียกเก็บเงินรอบใหม่
                และวิดเจ็ตในแดชบอร์ดช่างภาพจะถูกซ่อน เปิดใหม่กลับมาทำงานต่อทันที
              </div>
            </div>
          </div>
        </div>

        {{-- CARD 3 — Photos --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900
                    border border-slate-200 dark:border-white/10
                    shadow-sm shadow-slate-900/5 dark:shadow-black/20
                    overflow-hidden">

          <div class="px-5 py-4 border-b border-slate-100 dark:border-white/5 flex items-center gap-3">
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl
                         bg-violet-500/15 text-violet-600 dark:text-violet-300 shrink-0">
              <i class="bi bi-images text-lg"></i>
            </span>
            <div>
              <h6 class="font-bold text-slate-900 dark:text-white text-[15px] leading-tight">Photos</h6>
              <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                ลายน้ำ + Google Drive integration
              </div>
            </div>
          </div>

          <div class="p-5 space-y-4">
            {{-- Watermark toggle --}}
            <div class="flex items-center justify-between gap-4 p-3 rounded-xl
                        bg-slate-50 dark:bg-slate-800/50
                        border border-slate-200 dark:border-white/10">
              <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold text-slate-900 dark:text-white">Enable Watermark</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                  เพิ่มลายน้ำบนภาพ preview เพื่อป้องกันการก็อปปี้
                </div>
              </div>
              <label class="relative inline-flex items-center cursor-pointer shrink-0">
                <input type="checkbox" name="settings[watermark_enabled]" value="1" class="sr-only peer"
                       {{ ($settings['watermark_enabled'] ?? '1') == '1' ? 'checked' : '' }}>
                <div class="w-11 h-6 bg-slate-300 dark:bg-slate-700 rounded-full
                            peer-checked:bg-gradient-to-r peer-checked:from-violet-500 peer-checked:to-fuchsia-500
                            peer-focus:ring-2 peer-focus:ring-violet-500/30 transition
                            after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                            after:bg-white after:rounded-full after:h-5 after:w-5 after:shadow
                            after:transition-all peer-checked:after:translate-x-5"></div>
              </label>
            </div>

            {{-- Watermark Text --}}
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                Watermark Text
              </label>
              <input type="text" name="settings[watermark_text]"
                     value="{{ $settings['watermark_text'] ?? '' }}"
                     placeholder="© {{ $settings['site_name'] ?? config('app.name') }}"
                     class="w-full px-3 py-2.5 rounded-lg text-sm
                            bg-white dark:bg-slate-800
                            border border-slate-300 dark:border-white/10
                            text-slate-900 dark:text-white
                            placeholder:text-slate-400 dark:placeholder:text-slate-500
                            focus:border-violet-500 focus:ring-2 focus:ring-violet-500/30 focus:outline-none">
            </div>

            {{-- Google Drive API Key --}}
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                Google Drive API Key
                <span class="font-normal text-slate-400 dark:text-slate-500">— สำหรับ import ภาพจาก Drive</span>
              </label>
              <div class="relative">
                <i class="bi bi-key absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500"></i>
                <input type="text" name="settings[google_drive_api_key]"
                       value="{{ $settings['google_drive_api_key'] ?? '' }}"
                       placeholder="AIzaSy..."
                       autocomplete="off" spellcheck="false"
                       class="w-full pl-9 pr-3 py-2.5 rounded-lg text-sm font-mono
                              bg-white dark:bg-slate-800
                              border border-slate-300 dark:border-white/10
                              text-slate-900 dark:text-white
                              placeholder:text-slate-400 dark:placeholder:text-slate-500
                              focus:border-violet-500 focus:ring-2 focus:ring-violet-500/30 focus:outline-none">
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    {{-- ────────── SAVE BUTTON ────────── --}}
    <div class="mt-6 flex justify-end">
      <button type="submit"
              class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-semibold text-white transition
                     bg-gradient-to-br from-indigo-600 to-violet-600
                     shadow-md shadow-indigo-500/30
                     hover:shadow-lg hover:shadow-indigo-500/40 hover:-translate-y-0.5
                     active:scale-[0.98]">
        <i class="bi bi-floppy-fill"></i>
        บันทึกการตั้งค่า
      </button>
    </div>
  </form>
</div>
@endsection
