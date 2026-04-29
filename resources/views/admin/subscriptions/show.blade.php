@extends('layouts.admin')
@section('title', 'Subscription Detail')

@php
  use App\Models\PhotographerSubscription;
  use App\Models\SubscriptionInvoice;
  $sub = $subscription;
@endphp

@section('content')
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-person-vcard text-indigo-500"></i>
        Subscription #{{ $sub->id }}
        <span class="text-xs font-normal text-gray-400 ml-2">/ รายละเอียดการสมัครสมาชิก</span>
    </h4>
    <a href="{{ route('admin.subscriptions.index') }}" class="px-3 py-1.5 bg-slate-600 hover:bg-slate-700 text-white rounded-lg text-sm">
        <i class="bi bi-arrow-left mr-1"></i>กลับ
    </a>
</div>

@if(session('success'))
  <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm px-4 py-3">
    <i class="bi bi-check-circle-fill mr-1.5"></i>{{ session('success') }}
  </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    {{-- Subscription summary --}}
    <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 p-5">
        <h5 class="font-semibold mb-4">ข้อมูลการสมัคร</h5>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <dt class="text-gray-500">ช่างภาพ</dt>
            <dd>
                <div class="font-medium">{{ $sub->photographer?->name }}</div>
                <div class="text-[11px] text-gray-400">{{ $sub->photographer?->email }}</div>
            </dd>

            <dt class="text-gray-500">แผน</dt>
            <dd>
                <span class="font-semibold" style="color: {{ $sub->plan?->color_hex ?: '#6366f1' }}">
                    {{ $sub->plan?->name ?? '—' }}
                </span>
                <div class="text-[11px] text-gray-400">
                    {{ number_format((int) ($sub->plan?->storage_bytes ?? 0) / (1024 ** 3), 0) }} GB ·
                    {{ (int) ($sub->plan?->commission_pct ?? 0) }}% commission
                </div>
            </dd>

            <dt class="text-gray-500">สถานะ</dt>
            <dd>
                @php
                    $badge = match($sub->status) {
                        PhotographerSubscription::STATUS_ACTIVE    => ['bg-emerald-100 text-emerald-700', 'active'],
                        PhotographerSubscription::STATUS_GRACE     => ['bg-rose-100 text-rose-700',       'grace'],
                        PhotographerSubscription::STATUS_PENDING   => ['bg-amber-100 text-amber-700',     'pending'],
                        PhotographerSubscription::STATUS_CANCELLED => ['bg-gray-100 text-gray-600',       'cancelled'],
                        PhotographerSubscription::STATUS_EXPIRED   => ['bg-gray-100 text-gray-600',       'expired'],
                        default                                    => ['bg-gray-100 text-gray-700',       $sub->status],
                    };
                @endphp
                <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $badge[0] }}">{{ $badge[1] }}</span>
                @if($sub->cancel_at_period_end)
                    <span class="ml-2 inline-block px-2 py-0.5 rounded text-[11px] bg-amber-100 text-amber-700">จะไม่ต่ออายุ</span>
                @endif
            </dd>

            <dt class="text-gray-500">เริ่ม</dt>
            <dd>{{ $sub->started_at?->format('d M Y H:i') ?? '—' }}</dd>

            <dt class="text-gray-500">รอบปัจจุบัน</dt>
            <dd>
                {{ $sub->current_period_start?->format('d M Y') ?? '—' }} –
                {{ $sub->current_period_end?->format('d M Y') ?? '—' }}
            </dd>

            <dt class="text-gray-500">ต่ออายุล่าสุด</dt>
            <dd>{{ $sub->last_renewed_at?->format('d M Y H:i') ?? '—' }}</dd>

            <dt class="text-gray-500">ความพยายามต่ออายุ</dt>
            <dd>{{ (int) $sub->renewal_attempts }}</dd>

            @if($sub->grace_ends_at)
                <dt class="text-gray-500">หมดช่วงผ่อนผัน</dt>
                <dd class="text-rose-600 font-medium">{{ $sub->grace_ends_at->format('d M Y') }}</dd>
            @endif

            <dt class="text-gray-500">Payment Method</dt>
            <dd>{{ $sub->payment_method_type ?? '—' }}</dd>

            <dt class="text-gray-500">Omise Customer</dt>
            <dd class="font-mono text-xs">{{ $sub->omise_customer_id ?? '—' }}</dd>

            <dt class="text-gray-500">Omise Schedule</dt>
            <dd class="font-mono text-xs">{{ $sub->omise_schedule_id ?? '—' }}</dd>
        </dl>
    </div>

    {{-- Admin actions --}}
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 p-5">
        <h5 class="font-semibold mb-4">การจัดการ</h5>
        <div class="space-y-3">
            @if($sub->isUsable())
                <form method="POST" action="{{ route('admin.subscriptions.cancel', $sub) }}"
                      onsubmit="return confirm('ยกเลิกการสมัครทันที? การใช้งานจะหยุดและดาวน์เกรดเป็นแผนฟรีทันที');">
                    @csrf
                    <button class="w-full px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-sm">
                        <i class="bi bi-x-circle mr-1"></i> ยกเลิกทันที (Hard Cancel)
                    </button>
                </form>
            @endif
            @if($sub->isGrace())
                <form method="POST" action="{{ route('admin.subscriptions.expire', $sub) }}"
                      onsubmit="return confirm('สิ้นสุดช่วงผ่อนผันตอนนี้? บัญชีจะถูกดาวน์เกรดเป็นแผนฟรี');">
                    @csrf
                    <button class="w-full px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-sm">
                        <i class="bi bi-fast-forward mr-1"></i> สิ้นสุดช่วงผ่อนผัน
                    </button>
                </form>
            @endif
            <p class="text-xs text-gray-500">
                การกระทำเหล่านี้จะ sync ไปยัง photographer_profile cache
                (storage_quota_bytes, subscription_plan_code) อัตโนมัติ
            </p>
        </div>
    </div>
</div>

{{-- Invoices list --}}
<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 dark:border-white/5">
        <h5 class="font-semibold">ประวัติใบเสร็จ</h5>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-slate-900/40 text-gray-500 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-5 py-3 text-left">เลขที่</th>
                    <th class="px-5 py-3 text-left">ช่วงเวลา</th>
                    <th class="px-5 py-3 text-right">ยอด</th>
                    <th class="px-5 py-3 text-left">สถานะ</th>
                    <th class="px-5 py-3 text-left">ชำระเมื่อ</th>
                    <th class="px-5 py-3 text-right">Order</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse($sub->invoices()->orderByDesc('id')->get() as $inv)
                    <tr>
                        <td class="px-5 py-3 font-mono text-xs">{{ $inv->invoice_number }}</td>
                        <td class="px-5 py-3 text-xs text-gray-500">
                            {{ $inv->period_start?->format('d M Y') ?? '—' }} – {{ $inv->period_end?->format('d M Y') ?? '—' }}
                        </td>
                        <td class="px-5 py-3 text-right font-medium">฿{{ number_format((float) $inv->amount_thb, 2) }}</td>
                        <td class="px-5 py-3">
                            @php
                                $b = match($inv->status) {
                                    SubscriptionInvoice::STATUS_PAID     => ['bg-emerald-100 text-emerald-700', 'paid'],
                                    SubscriptionInvoice::STATUS_PENDING  => ['bg-amber-100 text-amber-700',   'pending'],
                                    SubscriptionInvoice::STATUS_FAILED   => ['bg-rose-100 text-rose-700',     'failed'],
                                    default                              => ['bg-gray-100 text-gray-700',     $inv->status],
                                };
                            @endphp
                            <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $b[0] }}">{{ $b[1] }}</span>
                        </td>
                        <td class="px-5 py-3 text-xs text-gray-500">{{ $inv->paid_at?->format('d M Y H:i') ?? '—' }}</td>
                        <td class="px-5 py-3 text-right">
                            @if($inv->order_id)
                                <a href="{{ url('/admin/orders/' . $inv->order_id) }}" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">#{{ $inv->order_id }}</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-8 text-center text-gray-500">ยังไม่มีใบเสร็จ</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
