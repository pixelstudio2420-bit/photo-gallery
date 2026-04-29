@extends('layouts.app')

@section('title', 'Invite Friends')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">

    <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-2">
        <i class="bi bi-people-fill text-teal-500"></i>
        แนะนำเพื่อน รับส่วนลด
    </h1>
    <p class="text-sm text-slate-600 dark:text-slate-400 mb-6">
        แชร์รหัสของคุณ — เพื่อนได้ส่วนลด {{ $code->discount_type === 'percent' ? $code->discount_value . '%' : '฿' . number_format($code->discount_value) }} คุณได้ <strong>฿{{ number_format($code->reward_value) }}</strong> ทุกครั้งที่เพื่อนซื้อ
    </p>

    {{-- Referral code card --}}
    <div class="rounded-2xl border border-teal-500/30 bg-gradient-to-br from-teal-500/10 to-emerald-500/10 p-6 mb-6" x-data="{ copied: false }">
        <div class="text-xs uppercase tracking-widest text-teal-600 dark:text-teal-400 font-semibold mb-2">รหัสแนะนำของคุณ</div>
        <div class="flex items-center gap-3 flex-wrap">
            <code class="text-3xl md:text-4xl font-bold font-mono text-slate-900 dark:text-white tracking-wider bg-white dark:bg-slate-900 px-6 py-3 rounded-lg border border-teal-500/40">
                {{ $code->code }}
            </code>
            <button @click="navigator.clipboard.writeText('{{ $code->code }}'); copied = true; setTimeout(() => copied = false, 2000)"
                    class="px-4 py-3 rounded-lg bg-teal-600 hover:bg-teal-500 text-white text-sm font-semibold">
                <span x-show="!copied"><i class="bi bi-clipboard"></i> Copy</span>
                <span x-show="copied" x-cloak><i class="bi bi-check-lg"></i> คัดลอกแล้ว</span>
            </button>
        </div>

        <div class="mt-4 pt-4 border-t border-teal-500/20">
            <div class="text-xs text-slate-600 dark:text-slate-400 mb-1">หรือแชร์ link นี้:</div>
            <div class="flex items-center gap-2 flex-wrap" x-data="{ linkCopied: false }">
                <input type="text" readonly value="{{ $shareUrl }}"
                    class="flex-1 min-w-[200px] px-3 py-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs text-slate-700 dark:text-slate-300">
                <button @click="navigator.clipboard.writeText('{{ $shareUrl }}'); linkCopied = true; setTimeout(() => linkCopied = false, 2000)"
                        class="px-3 py-2 rounded-lg bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-900 dark:text-white text-xs">
                    <span x-show="!linkCopied"><i class="bi bi-link-45deg"></i></span>
                    <span x-show="linkCopied" x-cloak><i class="bi bi-check-lg"></i></span>
                </button>
            </div>
        </div>

        {{-- Share buttons --}}
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="https://social-plugins.line.me/lineit/share?url={{ urlencode($shareUrl) }}" target="_blank"
               class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-green-500 hover:bg-green-600 text-white text-xs">
                <i class="bi bi-chat-dots-fill"></i> LINE
            </a>
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}" target="_blank"
               class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs">
                <i class="bi bi-facebook"></i> Facebook
            </a>
            <a href="https://twitter.com/intent/tweet?url={{ urlencode($shareUrl) }}&text={{ urlencode('ใช้รหัส ' . $code->code . ' รับส่วนลด!') }}" target="_blank"
               class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-900 hover:bg-black text-white text-xs">
                <i class="bi bi-twitter-x"></i> X
            </a>
            <a href="https://wa.me/?text={{ urlencode('ใช้รหัส ' . $code->code . ' รับส่วนลด! ' . $shareUrl) }}" target="_blank"
               class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-xs">
                <i class="bi bi-whatsapp"></i> WhatsApp
            </a>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-3 mb-6">
        <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 text-center">
            <div class="text-xs text-slate-500 uppercase">ใช้แล้ว</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $stats['uses'] }}</div>
        </div>
        <div class="rounded-xl border border-emerald-200 dark:border-emerald-500/20 bg-emerald-50 dark:bg-emerald-500/5 p-4 text-center">
            <div class="text-xs text-emerald-600 dark:text-emerald-400 uppercase">สำเร็จ</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $stats['rewarded'] }}</div>
        </div>
        <div class="rounded-xl border border-amber-200 dark:border-amber-500/20 bg-amber-50 dark:bg-amber-500/5 p-4 text-center">
            <div class="text-xs text-amber-600 dark:text-amber-400 uppercase">ได้ค่าตอบแทน</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">฿{{ number_format($stats['total_reward'], 0) }}</div>
        </div>
    </div>

    {{-- How it works --}}
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6">
        <h3 class="font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
            <i class="bi bi-info-circle text-indigo-500"></i> วิธีการทำงาน
        </h3>
        <ol class="list-decimal list-inside space-y-2 text-sm text-slate-700 dark:text-slate-300 ml-2">
            <li>แชร์รหัส <code class="text-teal-600">{{ $code->code }}</code> ให้เพื่อน</li>
            <li>เพื่อนใช้รหัสตอนซื้อ → ได้ส่วนลด {{ $code->discount_type === 'percent' ? $code->discount_value . '%' : '฿' . number_format($code->discount_value) }}</li>
            <li>เมื่อเพื่อนจ่ายเงินสำเร็จ → คุณได้ <strong class="text-amber-600 dark:text-amber-400">฿{{ number_format($code->reward_value) }}</strong> (ในรูปแต้ม loyalty)</li>
            <li>ไม่จำกัดจำนวนเพื่อน — ยิ่งแชร์ ยิ่งได้</li>
        </ol>
    </div>
</div>
@endsection
