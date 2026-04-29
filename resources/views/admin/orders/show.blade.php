@extends('layouts.admin')

@section('title', 'คำสั่งซื้อ #' . $order->id)

@php
    /**
     * Status presentation table — every status code the admin app may see
     * mapped to a colour scheme + Thai label. Centralised here so a future
     * status (e.g. partial_refund) is added in one place.
     */
    $statusMeta = [
        'paid'              => ['label' => 'ชำระเงินแล้ว',     'tone' => 'emerald', 'icon' => 'bi-check-circle-fill'],
        'completed'         => ['label' => 'เสร็จสิ้น',          'tone' => 'emerald', 'icon' => 'bi-check2-all'],
        'pending'           => ['label' => 'รอดำเนินการ',       'tone' => 'amber',   'icon' => 'bi-clock'],
        'pending_payment'   => ['label' => 'รอชำระเงิน',         'tone' => 'amber',   'icon' => 'bi-hourglass-split'],
        'pending_review'    => ['label' => 'รอตรวจสอบสลิป',     'tone' => 'amber',   'icon' => 'bi-eye'],
        'cancelled'         => ['label' => 'ยกเลิก',             'tone' => 'rose',    'icon' => 'bi-x-circle'],
        'failed'            => ['label' => 'ล้มเหลว',            'tone' => 'rose',    'icon' => 'bi-exclamation-triangle'],
        'refunded'          => ['label' => 'คืนเงินแล้ว',         'tone' => 'slate',   'icon' => 'bi-arrow-counterclockwise'],
        'cart'              => ['label' => 'ตะกร้า',             'tone' => 'slate',   'icon' => 'bi-cart'],
    ];
    $st = $statusMeta[$order->status] ?? ['label' => ucfirst($order->status), 'tone' => 'slate', 'icon' => 'bi-circle'];

    /** Thai label for order_type. */
    $typeLabel = match ($order->order_type ?? 'photo_package') {
        'photo_package'              => 'ซื้อภาพ',
        'credit_package'             => 'ซื้อเครดิต',
        'subscription'               => 'สมัครแผน',
        'user_storage_subscription'  => 'พื้นที่ผู้ใช้',
        'gift_card'                  => 'บัตรของขวัญ',
        'addon'                      => 'บริการเสริม',
        default                      => $order->order_type ?? 'ภาพ',
    };

    /**
     * Map tone → Tailwind classes for badges. Inline `style` colours
     * dodge the legacy darkmode.css `[data-bs-theme="dark"] .bg-{color}-50`
     * overrides that wash the badge into a flat slate block on dark mode.
     * Using rgba()-based backgrounds with opacity keeps the badge visible
     * on BOTH light slate and dark slate page bodies, so the same class
     * list works across themes.
     */
    $toneClasses = [
        'emerald' => 'text-emerald-700 dark:text-emerald-300 ring-emerald-300/50',
        'amber'   => 'text-amber-700   dark:text-amber-300   ring-amber-300/50',
        'rose'    => 'text-rose-700    dark:text-rose-300    ring-rose-300/50',
        'slate'   => 'text-slate-700   dark:text-slate-300   ring-slate-300/50',
        'indigo'  => 'text-indigo-700  dark:text-indigo-300  ring-indigo-300/50',
    ];
    $toneBg = [
        'emerald' => 'rgba(16,185,129,0.15)',
        'amber'   => 'rgba(245,158,11,0.18)',
        'rose'    => 'rgba(244, 63, 94,0.18)',
        'slate'   => 'rgba(100,116,139,0.18)',
        'indigo'  => 'rgba(99,102,241,0.15)',
    ];
    $statusBadge = $toneClasses[$st['tone']] ?? $toneClasses['slate'];
    $statusBadgeBg = $toneBg[$st['tone']] ?? $toneBg['slate'];

    /** Money + bytes helpers used by multiple sections. */
    $money = fn ($v) => '฿' . number_format((float) $v, 2);
@endphp

@section('content')
<div class="max-w-7xl mx-auto p-4 md:p-6 space-y-6">

    {{-- ─────────────────── Header ─────────────────── --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ route('admin.orders.index') }}"
               class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-indigo-600 mb-2">
                <i class="bi bi-arrow-left"></i> รายการคำสั่งซื้อทั้งหมด
            </a>
            <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                คำสั่งซื้อ #{{ $order->id }}
            </h1>
            <div class="flex flex-wrap items-center gap-2 mt-2 text-sm">
                <code class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-mono text-xs">
                    {{ $order->order_number ?? 'O-' . $order->id }}
                </code>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset {{ $statusBadge }}"
                      style="background:{{ $statusBadgeBg }};">
                    <i class="bi {{ $st['icon'] }}"></i> {{ $st['label'] }}
                </span>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset {{ $toneClasses['indigo'] }}"
                      style="background:{{ $toneBg['indigo'] }};">
                    <i class="bi bi-tag-fill"></i> {{ $typeLabel }}
                </span>
                <span class="text-slate-500 dark:text-slate-400 text-xs">
                    สั่งซื้อ {{ $order->created_at?->format('d/m/Y H:i') ?? '-' }}
                </span>
            </div>
        </div>

        {{-- Quick actions — change status. Forms all post to the same
             update endpoint; the controller validates the target value. --}}
        <div class="flex flex-wrap items-center gap-2">
            @if(!in_array($order->status, ['paid','completed','cancelled','refunded']))
                <form method="POST" action="{{ route('admin.orders.update', $order->id) }}" class="inline">
                    @csrf @method('PUT')
                    <input type="hidden" name="status" value="paid">
                    <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm transition">
                        <i class="bi bi-check-circle"></i> ทำเครื่องหมายชำระแล้ว
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.orders.update', $order->id) }}" class="inline">
                    @csrf @method('PUT')
                    <input type="hidden" name="status" value="cancelled">
                    {{-- Inline rgba bg dodges the darkmode.css override that
                         flattens bg-rose-50 to slate; ring stays subtle in
                         both modes via the /50 alpha. --}}
                    <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg ring-1 ring-rose-300/60
                                   text-rose-700 dark:text-rose-300 font-semibold text-sm transition
                                   hover:ring-rose-400"
                            style="background:rgba(244,63,94,0.15);"
                            onclick="return confirm('ยืนยันยกเลิกคำสั่งซื้อนี้?');">
                        <i class="bi bi-x-circle"></i> ยกเลิก
                    </button>
                </form>
            @endif
            @if($order->status === 'paid')
                <form method="POST" action="{{ route('admin.orders.update', $order->id) }}" class="inline">
                    @csrf @method('PUT')
                    <input type="hidden" name="status" value="completed">
                    <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm transition">
                        <i class="bi bi-check2-all"></i> ทำเครื่องหมายเสร็จสิ้น
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 text-emerald-700 px-4 py-3 text-sm">
            <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-xl bg-rose-50 ring-1 ring-rose-200 text-rose-700 px-4 py-3 text-sm">
            <i class="bi bi-exclamation-triangle-fill"></i> {{ session('error') }}
        </div>
    @endif

    {{-- ─────────────────── KPI strip ─────────────────── --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 ring-1 ring-slate-100 dark:ring-slate-700">
            <div class="text-xs uppercase tracking-wide text-slate-500 mb-1">ยอดสุทธิ</div>
            <div class="text-2xl font-extrabold text-indigo-600">{{ $money($order->total) }}</div>
            @if((float) ($order->discount_amount ?? 0) > 0)
                <div class="text-xs text-slate-400 mt-1">
                    ก่อนส่วนลด: {{ $money($order->subtotal ?? $order->total) }}
                </div>
            @endif
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 ring-1 ring-slate-100 dark:ring-slate-700">
            <div class="text-xs uppercase tracking-wide text-slate-500 mb-1">รายการ</div>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white">
                {{ $order->items->count() ?? 0 }}
            </div>
            <div class="text-xs text-slate-400 mt-1">{{ $typeLabel }}</div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 ring-1 ring-slate-100 dark:ring-slate-700">
            <div class="text-xs uppercase tracking-wide text-slate-500 mb-1">ชำระเงิน</div>
            <div class="text-2xl font-extrabold {{ $order->paid_at ? 'text-emerald-600' : 'text-amber-600' }}">
                @if($order->paid_at)
                    <i class="bi bi-check-circle-fill"></i> ชำระแล้ว
                @else
                    <i class="bi bi-clock"></i> ยังไม่ชำระ
                @endif
            </div>
            @if($order->paid_at)
                <div class="text-xs text-slate-400 mt-1">{{ \Carbon\Carbon::parse($order->paid_at)->format('d/m/Y H:i') }}</div>
            @endif
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 ring-1 ring-slate-100 dark:ring-slate-700">
            <div class="text-xs uppercase tracking-wide text-slate-500 mb-1">ส่งมอบ</div>
            <div class="text-2xl font-extrabold {{ $order->delivered_at ? 'text-emerald-600' : 'text-slate-400' }}">
                @if($order->delivered_at)
                    <i class="bi bi-truck"></i> ส่งแล้ว
                @else
                    <i class="bi bi-dash-circle"></i> รอส่ง
                @endif
            </div>
            @if($order->delivered_at)
                <div class="text-xs text-slate-400 mt-1">{{ $order->delivered_at->format('d/m/Y H:i') }}</div>
            @else
                <div class="text-xs text-slate-400 mt-1">{{ $order->delivery_method ?? 'auto' }}</div>
            @endif
        </div>
    </div>

    {{-- ─────────────────── Main two-column grid ─────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ════════════ LEFT (2/3) — order body ════════════ --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- ── Order details ── --}}
            <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm overflow-hidden">
                <header class="px-5 py-4 border-b border-slate-100 dark:border-slate-700">
                    <h2 class="font-bold text-slate-900 dark:text-white">
                        <i class="bi bi-receipt text-indigo-500"></i> ข้อมูลคำสั่งซื้อ
                    </h2>
                </header>
                <dl class="divide-y divide-slate-100 dark:divide-slate-700">
                    <div class="grid grid-cols-3 gap-4 px-5 py-3 text-sm">
                        <dt class="text-slate-500">หมายเลข</dt>
                        <dd class="col-span-2 font-mono font-semibold text-slate-900 dark:text-white">
                            {{ $order->order_number ?? '#' . $order->id }}
                        </dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-5 py-3 text-sm">
                        <dt class="text-slate-500">ประเภท</dt>
                        <dd class="col-span-2 text-slate-900 dark:text-white">{{ $typeLabel }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-5 py-3 text-sm">
                        <dt class="text-slate-500">วันที่สั่งซื้อ</dt>
                        <dd class="col-span-2 text-slate-900 dark:text-white">
                            {{ $order->created_at?->format('d/m/Y H:i') ?? '-' }}
                            @if($order->created_at)
                                <span class="text-slate-400 ml-1">({{ $order->created_at->diffForHumans() }})</span>
                            @endif
                        </dd>
                    </div>
                    @if($order->paid_at)
                        <div class="grid grid-cols-3 gap-4 px-5 py-3 text-sm">
                            <dt class="text-slate-500">ชำระเมื่อ</dt>
                            <dd class="col-span-2 text-emerald-600 dark:text-emerald-400 font-medium">
                                {{ \Carbon\Carbon::parse($order->paid_at)->format('d/m/Y H:i') }}
                            </dd>
                        </div>
                    @endif
                    @if((float) ($order->discount_amount ?? 0) > 0)
                        <div class="grid grid-cols-3 gap-4 px-5 py-3 text-sm">
                            <dt class="text-slate-500">ส่วนลด</dt>
                            <dd class="col-span-2 text-slate-900 dark:text-white">
                                <span class="text-rose-600 font-medium">−{{ $money($order->discount_amount) }}</span>
                                @if($order->coupon_code)
                                    <code class="ml-2 px-2 py-0.5 rounded bg-rose-50 text-rose-700 text-xs">{{ $order->coupon_code }}</code>
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if($order->note)
                        <div class="grid grid-cols-3 gap-4 px-5 py-3 text-sm">
                            <dt class="text-slate-500">หมายเหตุ</dt>
                            <dd class="col-span-2 text-slate-900 dark:text-white whitespace-pre-line">{{ $order->note }}</dd>
                        </div>
                    @endif
                    @if($order->delivery_method)
                        <div class="grid grid-cols-3 gap-4 px-5 py-3 text-sm">
                            <dt class="text-slate-500">ช่องทางส่งมอบ</dt>
                            <dd class="col-span-2 text-slate-900 dark:text-white">
                                <code class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-xs">{{ $order->delivery_method }}</code>
                                @if($order->delivery_status)
                                    <span class="text-xs text-slate-500 ml-2">{{ $order->delivery_status }}</span>
                                @endif
                            </dd>
                        </div>
                    @endif
                </dl>
            </section>

            {{-- ── Items ── --}}
            @if($order->items && $order->items->count())
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm overflow-hidden">
                    <header class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                        <h2 class="font-bold text-slate-900 dark:text-white">
                            <i class="bi bi-images text-indigo-500"></i> รายการ
                            <span class="text-sm font-normal text-slate-500 ml-1">({{ $order->items->count() }})</span>
                        </h2>
                    </header>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="text-left px-5 py-2.5 font-semibold">#</th>
                                    <th class="text-left px-2 py-2.5 font-semibold">รายการ</th>
                                    <th class="text-right px-2 py-2.5 font-semibold">จำนวน</th>
                                    <th class="text-right px-2 py-2.5 font-semibold">ราคา</th>
                                    <th class="text-right px-5 py-2.5 font-semibold">รวม</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                @foreach($order->items as $item)
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/30">
                                        <td class="px-5 py-3 text-slate-400">{{ $loop->iteration }}</td>
                                        <td class="px-2 py-3 text-slate-900 dark:text-white">
                                            {{ $item->description ?? ($item->photo_id ? 'รูป #' . $item->photo_id : 'รายการ #' . $item->id) }}
                                        </td>
                                        <td class="px-2 py-3 text-right">{{ $item->quantity ?? 1 }}</td>
                                        <td class="px-2 py-3 text-right text-slate-600">{{ $money($item->price ?? 0) }}</td>
                                        <td class="px-5 py-3 text-right font-semibold text-slate-900 dark:text-white">
                                            {{ $money(($item->price ?? 0) * ($item->quantity ?? 1)) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-slate-50 dark:bg-slate-900/50 text-sm">
                                @if((float) ($order->subtotal ?? 0) > 0 && (float) $order->subtotal != (float) $order->total)
                                    <tr>
                                        <td colspan="4" class="px-5 py-2 text-right text-slate-500">รวมก่อนส่วนลด</td>
                                        <td class="px-5 py-2 text-right text-slate-900 dark:text-white">{{ $money($order->subtotal) }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="px-5 py-2 text-right text-slate-500">ส่วนลด</td>
                                        <td class="px-5 py-2 text-right text-rose-600">−{{ $money($order->discount_amount) }}</td>
                                    </tr>
                                @endif
                                <tr class="border-t border-slate-200 dark:border-slate-600">
                                    <td colspan="4" class="px-5 py-3 text-right font-bold text-slate-900 dark:text-white">ยอดสุทธิ</td>
                                    <td class="px-5 py-3 text-right font-extrabold text-indigo-600 text-lg">{{ $money($order->total) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            @endif

            {{-- ── Payment slips ── --}}
            @if($order->slips && $order->slips->count())
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm overflow-hidden">
                    <header class="px-5 py-4 border-b border-slate-100 dark:border-slate-700">
                        <h2 class="font-bold text-slate-900 dark:text-white">
                            <i class="bi bi-receipt-cutoff text-emerald-500"></i> สลิปชำระเงิน
                            <span class="text-sm font-normal text-slate-500 ml-1">({{ $order->slips->count() }})</span>
                        </h2>
                    </header>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach($order->slips as $slip)
                            @php
                                $slipMeta = $statusMeta[$slip->verify_status] ?? ['label' => $slip->verify_status, 'tone' => 'slate', 'icon' => 'bi-circle'];
                                $slipBadge   = $toneClasses[$slipMeta['tone']] ?? $toneClasses['slate'];
                                $slipBadgeBg = $toneBg[$slipMeta['tone']]      ?? $toneBg['slate'];
                            @endphp
                            <li class="px-5 py-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="text-xs font-mono text-slate-400">#{{ $slip->id }}</span>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset {{ $slipBadge }}"
                                                  style="background:{{ $slipBadgeBg }};">
                                                {{ $slipMeta['label'] }}
                                            </span>
                                            @if(!is_null($slip->verify_score))
                                                <span class="text-xs text-slate-500">คะแนน {{ $slip->verify_score }}/100</span>
                                            @endif
                                        </div>
                                        <div class="text-sm text-slate-700 dark:text-slate-300">
                                            <span class="font-semibold">{{ $money($slip->amount) }}</span>
                                            @if($slip->transfer_date)
                                                <span class="text-slate-500">โอนเมื่อ {{ \Carbon\Carbon::parse($slip->transfer_date)->format('d/m/Y H:i') }}</span>
                                            @endif
                                        </div>
                                        @if($slip->slipok_trans_ref)
                                            <div class="text-xs text-slate-500 mt-1">
                                                SlipOK Ref: <code class="font-mono">{{ $slip->slipok_trans_ref }}</code>
                                            </div>
                                        @endif
                                        @if(!empty($slip->fraud_flags))
                                            <div class="mt-2 flex flex-wrap gap-1">
                                                @foreach((array) $slip->fraud_flags as $flag)
                                                    <span class="px-2 py-0.5 rounded text-xs ring-1 ring-rose-300/60 text-rose-700 dark:text-rose-300"
                                                          style="background:rgba(244,63,94,0.15);">{{ $flag }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    @if($slip->slip_path)
                                        <a href="{{ \Illuminate\Support\Facades\Storage::disk(config('media.disk', 'r2'))->url($slip->slip_path) }}"
                                           target="_blank"
                                           class="text-xs text-indigo-600 hover:text-indigo-700 font-semibold inline-flex items-center gap-1">
                                            <i class="bi bi-image"></i> ดูสลิป
                                        </a>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- ── Payment transactions ── --}}
            @if($order->transactions && $order->transactions->count())
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm overflow-hidden">
                    <header class="px-5 py-4 border-b border-slate-100 dark:border-slate-700">
                        <h2 class="font-bold text-slate-900 dark:text-white">
                            <i class="bi bi-credit-card text-indigo-500"></i> ธุรกรรม
                            <span class="text-sm font-normal text-slate-500 ml-1">({{ $order->transactions->count() }})</span>
                        </h2>
                    </header>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="text-left px-5 py-2.5 font-semibold">Txn ID</th>
                                    <th class="text-left px-2 py-2.5 font-semibold">Gateway</th>
                                    <th class="text-right px-2 py-2.5 font-semibold">ยอด</th>
                                    <th class="text-left px-2 py-2.5 font-semibold">สถานะ</th>
                                    <th class="text-left px-5 py-2.5 font-semibold">เวลา</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                @foreach($order->transactions as $txn)
                                    @php
                                        $txnMeta = $statusMeta[$txn->status] ?? ['label' => $txn->status, 'tone' => 'slate', 'icon' => 'bi-circle'];
                                        $txnBadge   = $toneClasses[$txnMeta['tone']] ?? $toneClasses['slate'];
                                        $txnBadgeBg = $toneBg[$txnMeta['tone']]      ?? $toneBg['slate'];
                                    @endphp
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/30">
                                        <td class="px-5 py-3 font-mono text-xs">{{ $txn->transaction_id }}</td>
                                        <td class="px-2 py-3">{{ $txn->payment_gateway ?? '-' }}</td>
                                        <td class="px-2 py-3 text-right font-medium">{{ $money($txn->amount) }}</td>
                                        <td class="px-2 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset {{ $txnBadge }}"
                                                  style="background:{{ $txnBadgeBg }};">
                                                {{ $txnMeta['label'] }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 text-xs text-slate-500">{{ $txn->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            {{-- ── Timeline / Audit trail ── --}}
            @if(($timeline ?? collect())->count() > 0 || ($activity ?? collect())->count() > 0)
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm overflow-hidden">
                    <header class="px-5 py-4 border-b border-slate-100 dark:border-slate-700">
                        <h2 class="font-bold text-slate-900 dark:text-white">
                            <i class="bi bi-clock-history text-indigo-500"></i> Timeline
                        </h2>
                    </header>
                    <ol class="px-5 py-4 space-y-3 text-sm">
                        @php
                            // Merge audit + activity logs into one chronological feed.
                            $events = collect();
                            foreach ($timeline ?? [] as $row) {
                                $events->push((object) [
                                    'when'   => $row->created_at,
                                    'kind'   => 'audit',
                                    'action' => $row->action,
                                    'actor'  => $row->actor_type . ($row->actor_id ? "#{$row->actor_id}" : ''),
                                    'detail' => $row->new_values,
                                ]);
                            }
                            foreach ($activity ?? [] as $row) {
                                $events->push((object) [
                                    'when'   => $row->created_at,
                                    'kind'   => 'activity',
                                    'action' => $row->action,
                                    'actor'  => 'admin',
                                    'detail' => $row->description ?? null,
                                ]);
                            }
                            $events = $events->sortByDesc('when')->values();
                        @endphp
                        @foreach($events as $ev)
                            <li class="flex gap-3">
                                <div class="flex flex-col items-center pt-0.5">
                                    <div class="w-2 h-2 rounded-full {{ $ev->kind === 'activity' ? 'bg-indigo-500' : 'bg-slate-400' }}"></div>
                                    @if(!$loop->last)
                                        <div class="w-px flex-1 bg-slate-200 dark:bg-slate-600 mt-1"></div>
                                    @endif
                                </div>
                                <div class="flex-1 pb-3">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <code class="text-xs font-mono px-1.5 py-0.5 rounded
                                            {{ $ev->kind === 'activity' ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-600' }}">
                                            {{ $ev->action }}
                                        </code>
                                        <span class="text-xs text-slate-500">{{ $ev->actor }}</span>
                                        <span class="text-xs text-slate-400 ml-auto">
                                            {{ \Carbon\Carbon::parse($ev->when)->format('d/m/Y H:i') }}
                                        </span>
                                    </div>
                                    @if(!empty($ev->detail))
                                        <div class="text-xs text-slate-600 dark:text-slate-400 mt-1 truncate" title="{{ $ev->detail }}">
                                            {{ \Illuminate\Support\Str::limit($ev->detail, 200) }}
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif

        </div>

        {{-- ════════════ RIGHT (1/3) — sidebar ════════════ --}}
        <aside class="space-y-6">

            {{-- ── Customer ── --}}
            @if($order->user)
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-5">
                    <h2 class="font-bold text-slate-900 dark:text-white mb-3">
                        <i class="bi bi-person-circle text-indigo-500"></i> ลูกค้า
                    </h2>
                    <div class="flex items-start gap-3 mb-3">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white font-bold flex items-center justify-center text-lg">
                            {{ mb_strtoupper(mb_substr($order->user->first_name ?? 'U', 0, 1, 'UTF-8'), 'UTF-8') }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-slate-900 dark:text-white truncate">
                                {{ trim(($order->user->first_name ?? '') . ' ' . ($order->user->last_name ?? '')) ?: 'ไม่ระบุชื่อ' }}
                            </div>
                            <div class="text-xs text-slate-500 truncate">{{ $order->user->email }}</div>
                            @if($order->user->phone ?? null)
                                <div class="text-xs text-slate-500 truncate">{{ $order->user->phone }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-lg bg-slate-50 dark:bg-slate-900/40 px-3 py-2">
                            <div class="text-slate-500">User ID</div>
                            <div class="font-mono font-semibold">#{{ $order->user->id }}</div>
                        </div>
                        @if($order->user->created_at)
                            <div class="rounded-lg bg-slate-50 dark:bg-slate-900/40 px-3 py-2">
                                <div class="text-slate-500">สมัครเมื่อ</div>
                                <div class="font-semibold">{{ $order->user->created_at->format('d/m/y') }}</div>
                            </div>
                        @endif
                    </div>
                </section>
            @elseif($order->guest_email)
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-5">
                    <h2 class="font-bold text-slate-900 dark:text-white mb-3">
                        <i class="bi bi-person-circle text-slate-500"></i> ลูกค้า (Guest)
                    </h2>
                    <div class="text-sm text-slate-700 dark:text-slate-300">{{ $order->guest_email }}</div>
                </section>
            @endif

            {{-- ── Event (photo orders) ── --}}
            @if($order->event)
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-5">
                    <h2 class="font-bold text-slate-900 dark:text-white mb-3">
                        <i class="bi bi-calendar-event text-emerald-500"></i> อีเวนต์
                    </h2>
                    <div class="font-semibold text-slate-900 dark:text-white mb-1">{{ $order->event->name }}</div>
                    @if($order->event->shoot_date)
                        <div class="text-sm text-slate-500">
                            <i class="bi bi-camera"></i> ถ่ายเมื่อ {{ \Carbon\Carbon::parse($order->event->shoot_date)->format('d/m/Y') }}
                        </div>
                    @endif
                    @if($order->event->slug ?? null)
                        <a href="{{ url('/events/' . $order->event->slug) }}" target="_blank"
                           class="text-xs text-indigo-600 hover:text-indigo-700 inline-flex items-center gap-1 mt-2">
                            <i class="bi bi-box-arrow-up-right"></i> ดูหน้าอีเวนต์
                        </a>
                    @endif
                </section>
            @endif

            {{-- ── Add-on linkage ── --}}
            @if($addonPurchase ?? null)
                @php
                    $snap = json_decode((string) $addonPurchase->snapshot, true) ?: [];
                    $addonStatusMeta = $statusMeta[$addonPurchase->status] ?? [
                        'label' => $addonPurchase->status, 'tone' => 'slate', 'icon' => 'bi-circle',
                    ];
                    $addonBadge   = $toneClasses[$addonStatusMeta['tone']] ?? $toneClasses['slate'];
                    $addonBadgeBg = $toneBg[$addonStatusMeta['tone']]      ?? $toneBg['slate'];
                @endphp
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-5">
                    <h2 class="font-bold text-slate-900 dark:text-white mb-3">
                        <i class="bi bi-stars text-amber-500"></i> บริการเสริม
                    </h2>
                    <div class="font-semibold text-slate-900 dark:text-white">{{ $snap['label'] ?? $addonPurchase->sku }}</div>
                    <div class="text-xs text-slate-500 mt-0.5">SKU: <code>{{ $addonPurchase->sku }}</code></div>
                    <div class="mt-3 flex items-center gap-2 text-xs">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full font-semibold ring-1 ring-inset {{ $addonBadge }}"
                              style="background:{{ $addonBadgeBg }};">
                            {{ $addonStatusMeta['label'] }}
                        </span>
                        @if($addonPurchase->expires_at)
                            <span class="text-slate-500">หมดอายุ {{ \Carbon\Carbon::parse($addonPurchase->expires_at)->format('d/m/Y') }}</span>
                        @endif
                    </div>
                </section>
            @endif

            {{-- ── Subscription invoice linkage ── --}}
            @if($order->subscriptionInvoice)
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-5">
                    <h2 class="font-bold text-slate-900 dark:text-white mb-3">
                        <i class="bi bi-stack text-indigo-500"></i> ใบแจ้งหนี้แผน
                    </h2>
                    <div class="text-sm text-slate-700 dark:text-slate-300">
                        Invoice #{{ $order->subscriptionInvoice->id }}
                    </div>
                    @if($order->subscriptionInvoice->period_start)
                        <div class="text-xs text-slate-500 mt-1">
                            รอบ
                            {{ \Carbon\Carbon::parse($order->subscriptionInvoice->period_start)->format('d/m/y') }}
                            —
                            {{ \Carbon\Carbon::parse($order->subscriptionInvoice->period_end)->format('d/m/y') }}
                        </div>
                    @endif
                </section>
            @endif

            {{-- ── Photographer payout (photo_package only) ── --}}
            @if($order->payout)
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-5">
                    <h2 class="font-bold text-slate-900 dark:text-white mb-3">
                        <i class="bi bi-cash-stack text-emerald-500"></i> ค่าคอมช่างภาพ
                    </h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-500">ยอดรวม</dt>
                            <dd class="font-medium">{{ $money($order->payout->gross_amount) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">ค่าคอม ({{ number_format(100 - (float) $order->payout->commission_rate, 0) }}%)</dt>
                            <dd class="text-rose-600">−{{ $money($order->payout->platform_fee) }}</dd>
                        </div>
                        <div class="flex justify-between border-t border-slate-100 dark:border-slate-700 pt-2 font-bold">
                            <dt class="text-slate-900 dark:text-white">ยอดสุทธิช่างภาพ</dt>
                            <dd class="text-emerald-600 text-lg">{{ $money($order->payout->payout_amount) }}</dd>
                        </div>
                        <div class="flex justify-between text-xs">
                            <dt class="text-slate-500">สถานะ</dt>
                            <dd>
                                @php
                                    $poStatus = $order->payout->status;
                                    $poTone = match ($poStatus) {
                                        'paid'      => 'emerald',
                                        'pending'   => 'amber',
                                        'reversed'  => 'rose',
                                        default     => 'slate',
                                    };
                                @endphp
                                <span class="inline-flex px-2 py-0.5 rounded-full font-semibold ring-1 ring-inset {{ $toneClasses[$poTone] }}"
                                      style="background:{{ $toneBg[$poTone] }};">
                                    {{ $poStatus }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </section>
            @endif

            {{-- ── Refund ── --}}
            @if($order->refund)
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-5 ring-1 ring-rose-100">
                    <h2 class="font-bold text-rose-700 mb-3">
                        <i class="bi bi-arrow-counterclockwise"></i> คืนเงิน
                    </h2>
                    <div class="text-sm">
                        <div class="font-semibold">{{ $money($order->refund->amount) }}</div>
                        <div class="text-xs text-slate-500 mt-1">
                            สถานะ: {{ $order->refund->status }}
                            · {{ $order->refund->created_at?->diffForHumans() }}
                        </div>
                        @if($order->refund->reason ?? null)
                            <div class="text-xs text-slate-600 mt-1">{{ $order->refund->reason }}</div>
                        @endif
                    </div>
                </section>
            @endif

            {{-- ── Download tokens (photo deliveries) ── --}}
            @if($order->downloadTokens && $order->downloadTokens->count())
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-5">
                    <h2 class="font-bold text-slate-900 dark:text-white mb-3">
                        <i class="bi bi-cloud-download text-sky-500"></i> ลิงก์ดาวน์โหลด
                        <span class="text-sm font-normal text-slate-500 ml-1">({{ $order->downloadTokens->count() }})</span>
                    </h2>
                    <ul class="space-y-1 text-xs">
                        @foreach($order->downloadTokens->take(5) as $tok)
                            <li class="flex justify-between gap-2">
                                <code class="font-mono text-slate-500 truncate">{{ \Illuminate\Support\Str::limit($tok->token, 16) }}</code>
                                <span class="text-slate-400 shrink-0">
                                    @if($tok->expires_at) หมด {{ \Carbon\Carbon::parse($tok->expires_at)->format('d/m/y') }} @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

        </aside>
    </div>
</div>
@endsection
