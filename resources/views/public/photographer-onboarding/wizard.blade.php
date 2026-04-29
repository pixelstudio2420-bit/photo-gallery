@extends('layouts.app')
@section('title', 'สมัครเป็นช่างภาพ')

@php
    $stageLabel = \App\Models\PhotographerProfile::onboardingStages()[$profile->onboarding_stage] ?? $profile->onboarding_stage;
    $stepTitles = [
        'basic'    => ['ข้อมูลพื้นฐาน', 'bi-person-vcard'],
        'contract' => ['เริ่มขาย',       'bi-rocket-takeoff'],
    ];
    $stepIdx = array_search($step, $steps, true);
    $proTierEnabled = \App\Models\PhotographerProfile::isProTierEnabled();
@endphp

@section('content')
<div class="max-w-2xl mx-auto py-8 px-4">
    <div class="text-center mb-6">
        <div class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 items-center justify-center shadow-lg shadow-indigo-500/30 mb-3">
            <i class="bi bi-camera text-2xl text-white"></i>
        </div>
        <h2 class="text-2xl font-bold">สมัครเป็นช่างภาพ</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            กรอกแค่ 2 ขั้น ใช้เวลา ~1 นาที — เริ่มขายรูปได้ทันที
        </p>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 rounded-xl p-3 mb-4 text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-500/30 text-rose-800 dark:text-rose-200 rounded-xl p-3 mb-4 text-sm">
            @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
        </div>
    @endif

    {{-- Progress bar (2 steps) --}}
    @if(in_array($step, ['basic', 'contract'], true))
    <div class="flex items-center gap-2 mb-6">
        @foreach($steps as $i => $s)
            @php
                $cfg = $stepTitles[$s];
                $active = $step === $s;
                $done = $stepIdx !== false && $stepIdx > $i;
            @endphp
            <div class="flex-1 flex items-center gap-2">
                <div class="flex items-center gap-2 flex-1 px-3 py-2 rounded-xl text-sm font-medium
                            {{ $active ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/30' : ($done ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-200' : 'bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-gray-400') }}">
                    <span class="w-6 h-6 rounded-full bg-white/20 flex items-center justify-center text-xs font-bold">
                        @if($done)
                            <i class="bi bi-check text-base"></i>
                        @else
                            {{ $i + 1 }}
                        @endif
                    </span>
                    <span class="hidden sm:inline">{{ $cfg[0] }}</span>
                    <i class="bi {{ $cfg[1] }} sm:hidden"></i>
                </div>
                @if($i < count($steps) - 1)
                    <i class="bi bi-arrow-right text-gray-300 dark:text-gray-600"></i>
                @endif
            </div>
        @endforeach
    </div>
    @endif

    <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 border border-gray-100 dark:border-white/5 shadow-sm">
        {{-- ══ Step 1: Basic (identity + payout in one screen) ══ --}}
        @if($step === 'basic')
            <form method="POST" action="{{ route('photographer-onboarding.save', 'basic') }}" class="space-y-5">
                @csrf

                <div class="flex items-start gap-3 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-500/30 rounded-xl p-3 text-sm text-indigo-900 dark:text-indigo-100">
                    <i class="bi bi-info-circle-fill text-indigo-500 mt-0.5"></i>
                    <div>
                        <strong>ขั้นตอนนี้ ~30 วินาที</strong>
                        — กรอกแค่ช่องที่มีดอกจัน (<span class="text-rose-500">*</span>) ก็พอ
                        ที่เหลือกรอกเพิ่มทีหลังที่หน้าโปรไฟล์ได้
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">ชื่อที่แสดง <span class="text-rose-500">*</span></label>
                    <input type="text" name="display_name" required maxlength="200"
                           value="{{ old('display_name', $profile->display_name) }}"
                           placeholder="เช่น Tanya Photo Studio"
                           class="w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">PromptPay <span class="text-rose-500">*</span></label>
                        <input type="text" name="promptpay_number" required
                               value="{{ old('promptpay_number', $profile->promptpay_number) }}"
                               placeholder="เบอร์หรือเลขบัตรประชาชน"
                               class="w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">ใช้รับเงินจากการขายรูป</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">ชื่อบัญชี (ใช้โอนเงิน)</label>
                        <input type="text" name="bank_account_name"
                               value="{{ old('bank_account_name', $profile->bank_account_name) }}"
                               placeholder="ชื่อ-นามสกุลเจ้าของ PromptPay"
                               class="w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>

                <details class="group">
                    <summary class="cursor-pointer text-sm font-medium text-indigo-600 dark:text-indigo-300 hover:text-indigo-700 flex items-center gap-1 list-none">
                        <i class="bi bi-chevron-right transition-transform group-open:rotate-90"></i>
                        เพิ่มข้อมูลเสริม (เบอร์โทร, Bio, ประเภทงาน) — ไม่บังคับ
                    </summary>
                    <div class="mt-4 space-y-4 pl-5 border-l-2 border-indigo-100 dark:border-indigo-500/20">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">เบอร์โทร</label>
                                <input type="tel" name="phone"
                                       value="{{ old('phone', $profile->phone) }}"
                                       class="w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">ประสบการณ์ (ปี)</label>
                                <input type="number" min="0" max="80" name="years_experience"
                                       value="{{ old('years_experience', $profile->years_experience) }}"
                                       class="w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">ประเภทงานที่ถนัด</label>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                @foreach($specialties as $key => $label)
                                    <label class="flex items-center gap-2 border border-gray-200 dark:border-white/10 rounded-lg px-3 py-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-700 text-sm">
                                        <input type="checkbox" name="specialties[]" value="{{ $key }}" @checked(in_array($key, (array) ($profile->specialties ?? []), true))>
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">แนะนำตัว / Bio</label>
                            <textarea name="bio" rows="3" maxlength="2000"
                                      placeholder="เล่าสไตล์การถ่ายของคุณสั้นๆ"
                                      class="w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">{{ old('bio', $profile->bio) }}</textarea>
                        </div>
                    </div>
                </details>

                <button class="w-full px-4 py-3 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white rounded-xl text-sm font-semibold shadow-md shadow-indigo-500/30 transition-all">
                    ถัดไป <i class="bi bi-arrow-right ml-1"></i>
                </button>
            </form>

        {{-- ══ Step 2: Contract + instant activation ══ --}}
        @elseif($step === 'contract')
            <form method="POST" action="{{ route('photographer-onboarding.save', 'contract') }}" class="space-y-5">
                @csrf
                <div class="flex items-start gap-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-500/30 rounded-xl p-3 text-sm text-emerald-900 dark:text-emerald-100">
                    <i class="bi bi-check-circle-fill text-emerald-500 mt-0.5"></i>
                    <div>
                        <strong>เกือบเสร็จแล้ว!</strong>
                        กดยืนยันเงื่อนไขข้างล่าง แล้วเริ่มขายรูปได้ทันที — ไม่ต้องรอแอดมิน
                    </div>
                </div>

                <div class="bg-gray-50 dark:bg-slate-900 rounded-xl p-4 text-sm space-y-2">
                    <h4 class="font-semibold flex items-center gap-2">
                        <i class="bi bi-file-earmark-text text-indigo-500"></i>
                        เงื่อนไขการใช้งาน (อ่านเร็วๆ)
                    </h4>
                    <ul class="space-y-1.5 text-gray-700 dark:text-gray-300 list-disc list-inside">
                        <li>คุณยังเป็นเจ้าของลิขสิทธิ์ภาพ แพลตฟอร์มเป็นช่องทางขายเท่านั้น</li>
                        <li>แพลตฟอร์มหัก commission <strong>{{ (int) $profile->commission_rate }}%</strong> จากยอดขายแต่ละรูป</li>
                        <li>ภาพต้องไม่ละเมิดลิขสิทธิ์/PDPA และไม่มีเนื้อหาผิดกฎหมาย</li>
                        <li>ขอ payout ได้ทุกสัปดาห์ผ่าน PromptPay ที่คุณกรอก</li>
                        @if($proTierEnabled)
                        <li>อัปโหลดบัตรประชาชนที่หน้าโปรไฟล์ภายหลังเพื่อปลดล็อก "Pro Tier" (แอดมินคัดเลือก)</li>
                        @endif
                    </ul>
                </div>

                <label class="flex items-start gap-3 p-4 rounded-xl border-2 border-indigo-200 dark:border-indigo-500/30 bg-indigo-50/50 dark:bg-indigo-900/10 cursor-pointer hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors">
                    <input type="checkbox" name="agree" value="1" required class="mt-0.5 w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm">
                        <strong>ฉันอ่านและยอมรับเงื่อนไข</strong>
                        <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            (คลิกยืนยันเท่ากับการเซ็นสัญญาอิเล็กทรอนิกส์)
                        </span>
                    </span>
                </label>

                <button class="w-full px-4 py-3.5 bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 text-white rounded-xl text-base font-bold shadow-lg shadow-emerald-500/30 transition-all">
                    <i class="bi bi-rocket-takeoff mr-1"></i>
                    เริ่มขายรูปเลย
                </button>

                <a href="{{ route('photographer-onboarding.index', ['step' => 'basic']) }}"
                   class="block text-center text-xs text-gray-500 dark:text-gray-400 hover:text-indigo-600 hover:underline">
                    ← แก้ไขข้อมูลพื้นฐาน
                </a>
            </form>

        {{-- ══ Done: active ══ --}}
        @elseif($step === 'done')
            <div class="text-center py-6">
                <div class="inline-flex w-20 h-20 rounded-full bg-emerald-100 dark:bg-emerald-900/30 items-center justify-center mb-4">
                    <i class="bi bi-patch-check-fill text-5xl text-emerald-500"></i>
                </div>
                <h3 class="font-bold text-2xl">ยินดีด้วย! คุณเป็นช่างภาพของเราแล้ว</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                    เริ่มสร้าง event แรกเพื่ออัปโหลดรูปและเปิดขายได้เลย
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-6">
                    <a href="{{ route('photographer.events.create') }}"
                       class="px-4 py-3 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white rounded-xl text-sm font-semibold shadow-md shadow-indigo-500/30 flex items-center justify-center gap-2">
                        <i class="bi bi-plus-circle"></i>สร้าง Event แรก
                    </a>
                    <a href="{{ route('photographer.dashboard') }}"
                       class="px-4 py-3 bg-white dark:bg-slate-700 border border-gray-200 dark:border-white/10 hover:bg-gray-50 dark:hover:bg-slate-600 rounded-xl text-sm font-semibold flex items-center justify-center gap-2">
                        <i class="bi bi-speedometer2"></i>ไปที่แดชบอร์ด
                    </a>
                </div>

                @if($proTierEnabled && !$profile->isPro())
                <div class="mt-6 text-xs text-gray-500 dark:text-gray-400 bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-500/20 rounded-lg p-3 text-left">
                    <i class="bi bi-star-fill text-amber-500 mr-1"></i>
                    <strong>อยากอัปเกรดเป็น Pro?</strong>
                    ทำงานสม่ำเสมอแล้วติดต่อแอดมินเพื่อขอพิจารณาเลื่อนระดับ Pro —
                    ได้รับ badge ยืนยันและไม่มีลิมิตการขายรายเดือน
                </div>
                @endif
            </div>

        @elseif($step === 'rejected')
            <div class="text-center py-8">
                <i class="bi bi-x-circle-fill text-6xl text-rose-500"></i>
                <h3 class="font-semibold text-lg mt-3">บัญชีช่างภาพถูกระงับ</h3>
                @if($profile->rejection_reason)
                    <p class="text-sm text-rose-500 mt-2">{{ $profile->rejection_reason }}</p>
                @endif
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-4">ติดต่อแอดมินเพื่อขอข้อมูลเพิ่มเติม</p>
            </div>
        @endif
    </div>
</div>
@endsection
