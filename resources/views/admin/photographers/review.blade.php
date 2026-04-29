@extends('layouts.admin')
@section('title', 'ตรวจสอบช่างภาพ: ' . $profile->display_name)

@section('content')
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-person-check text-indigo-500"></i>
        ตรวจสอบใบสมัคร: {{ $profile->display_name }}
    </h4>
    <a href="{{ route('admin.photographer-onboarding.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-indigo-500"><i class="bi bi-arrow-left mr-1"></i>กลับ</a>
</div>

@if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 rounded-xl p-3 mb-4 text-sm">{{ session('success') }}</div>
@endif

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="md:col-span-2 bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-100 dark:border-white/5 space-y-3">
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div><span class="text-gray-500">ชื่อที่แสดง:</span><br><strong>{{ $profile->display_name }}</strong></div>
            <div><span class="text-gray-500">โทร:</span><br>{{ $profile->phone ?? '—' }}</div>
            <div><span class="text-gray-500">อีเมล:</span><br>{{ $profile->user?->email }}</div>
            <div><span class="text-gray-500">ประสบการณ์:</span><br>{{ $profile->years_experience ?? '—' }} ปี</div>
            <div><span class="text-gray-500">รหัส:</span><br>{{ $profile->photographer_code }}</div>
            <div><span class="text-gray-500">Commission:</span><br>{{ $profile->commission_rate }}%</div>
        </div>

        @if($profile->bio)
            <div>
                <div class="text-sm text-gray-500">Bio</div>
                <div class="mt-1 bg-gray-50 dark:bg-slate-900 p-3 rounded text-sm">{{ $profile->bio }}</div>
            </div>
        @endif

        @if(!empty($profile->specialties))
            <div>
                <div class="text-sm text-gray-500">ประเภทงาน</div>
                <div class="flex flex-wrap gap-1 mt-1">
                    @foreach($profile->specialties as $s)
                        @php $opts = \App\Models\PhotographerProfile::specialtyOptions(); @endphp
                        <span class="px-2 py-0.5 rounded bg-indigo-500/15 text-indigo-700 dark:text-indigo-200 text-xs">{{ $opts[$s] ?? $s }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3 text-sm pt-3 border-t border-gray-100 dark:border-white/5">
            <div>
                <div class="text-gray-500">Portfolio Link</div>
                @if($profile->portfolio_url)
                    <a href="{{ $profile->portfolio_url }}" target="_blank" class="text-indigo-500 hover:underline text-sm">{{ $profile->portfolio_url }} <i class="bi bi-box-arrow-up-right"></i></a>
                @else
                    —
                @endif
            </div>
            <div>
                <div class="text-gray-500">บัญชีธนาคาร</div>
                <div>{{ $profile->bank_name ?? '—' }} — {{ $profile->bank_account_number ?? '—' }}</div>
                @if($profile->promptpay_number)<div class="text-xs">PromptPay: {{ $profile->promptpay_number }}</div>@endif
            </div>
        </div>

        @if(!empty($profile->portfolio_samples))
            <div>
                <div class="text-sm text-gray-500 mb-2">รูปตัวอย่าง Portfolio</div>
                <div class="grid grid-cols-5 gap-2">
                    @foreach($profile->portfolio_samples as $s)
                        <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($s) }}" target="_blank">
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($s) }}" class="rounded border border-gray-200 dark:border-white/10 aspect-square object-cover" alt="">
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

    </div>

    {{-- Action column --}}
    <div class="space-y-3">
        <div class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-100 dark:border-white/5">
            <div class="text-sm text-gray-500">สถานะปัจจุบัน</div>
            <div class="text-xl font-bold mt-1">{{ $stages[$profile->onboarding_stage] ?? $profile->onboarding_stage }}</div>
        </div>

        @if($profile->onboarding_stage === 'submitted')
            <form action="{{ route('admin.photographer-onboarding.mark-reviewing', $profile) }}" method="POST">
                @csrf
                <button class="w-full px-4 py-2 bg-sky-600 text-white rounded-lg text-sm">
                    <i class="bi bi-eye mr-1"></i>เริ่มตรวจสอบ
                </button>
            </form>
        @endif

        @if(in_array($profile->onboarding_stage, ['submitted', 'under_review'], true))
            <form action="{{ route('admin.photographer-onboarding.approve', $profile) }}" method="POST">
                @csrf
                <button class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm">
                    <i class="bi bi-check-circle mr-1"></i>อนุมัติ
                </button>
            </form>

            <form action="{{ route('admin.photographer-onboarding.reject', $profile) }}" method="POST" class="space-y-2">
                @csrf
                <textarea name="reason" required rows="3" placeholder="เหตุผลที่ปฏิเสธ" class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm"></textarea>
                <button class="w-full px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-sm">
                    <i class="bi bi-x-circle mr-1"></i>ปฏิเสธ
                </button>
            </form>
        @endif

        @if($profile->onboarding_stage === 'rejected' && $profile->rejection_reason)
            <div class="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-500/30 rounded-xl p-3 text-sm text-rose-700 dark:text-rose-200">
                <strong>เหตุผลปฏิเสธ:</strong><br>{{ $profile->rejection_reason }}
            </div>
        @endif

        @if($profile->approved_at)
            <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-500/30 rounded-xl p-3 text-sm">
                <div><strong>อนุมัติแล้ว:</strong> {{ $profile->approved_at->format('d M Y H:i') }}</div>
                @if($profile->approved_by)
                    <div class="text-[11px] text-gray-500">โดย Admin #{{ $profile->approved_by }}</div>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
