@extends('layouts.app')

@section('title', 'คำสั่งซื้อ #' . $order->id)

@section('content')
{{-- Breadcrumb --}}
<nav aria-label="breadcrumb" class="mb-4">
  <ol class="flex items-center gap-2 text-sm">
    <li><a href="{{ route('orders.index') }}" class="no-underline" style="color:#6366f1;">คำสั่งซื้อ</a></li>
    <li class="text-gray-400">/</li>
    <li class="text-gray-500">คำสั่งซื้อ #{{ $order->id }}</li>
  </ol>
</nav>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
  <div class="lg:col-span-2">
    <div class="bg-white rounded-2xl shadow-sm overflow-hidden" style="border-radius:16px;">
      <div class="px-4 pt-4">
        <h6 class="mb-0 font-semibold"><i class="bi bi-list-ul mr-1" style="color:#6366f1;"></i>รายการสินค้า</h6>
      </div>
      <div class="overflow-x-auto mt-3">
        <table class="w-full text-sm">
          <thead style="background:rgba(99,102,241,0.04);">
            <tr>
              <th class="pl-4 px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">รายการ</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">จำนวน</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ราคา</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ดาวน์โหลด</th>
            </tr>
          </thead>
          <tbody>
            @foreach($order->items as $item)
              @php
                // Look up the EventPhoto for this row (controller pre-loaded
                // these into $photoLookup). Falls back gracefully when the
                // photo was deleted from the event after purchase.
                $photo  = isset($photoLookup) && is_numeric($item->photo_id)
                            ? ($photoLookup->get((int) $item->photo_id))
                            : null;
                $name   = $photo->original_filename ?? ('Photo #' . $item->photo_id);
                $thumb  = $item->thumbnail_url
                       ?: ($photo?->thumbnail_url ?? '');
                $token  = isset($tokenLookup) && is_numeric($item->photo_id)
                            ? optional($tokenLookup->get((int) $item->photo_id))->token
                            : null;
              @endphp
              <tr class="border-t border-gray-50">
                <td class="pl-4 px-4 py-3 font-medium">
                  <div class="flex items-center gap-3">
                    @if($thumb)
                      <img src="{{ $thumb }}" alt="thumbnail" loading="lazy"
                           class="w-12 h-12 rounded-lg object-cover flex-shrink-0"
                           onerror="this.style.display='none';">
                    @endif
                    <span class="truncate" style="max-width:240px;">{{ $name }}</span>
                  </div>
                </td>
                <td class="px-4 py-3">1</td>
                <td class="px-4 py-3"><span style="color:#6366f1;font-weight:500;">{{ number_format($item->price, 0) }} ฿</span></td>
                <td class="px-4 py-3">
                  @if($order->status === 'paid' && $token)
                    <a href="{{ route('download.show', ['token' => $token]) }}"
                       class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                       style="background:rgba(16,185,129,0.1);color:#10b981;font-size:0.8rem;">
                      <i class="bi bi-download mr-1"></i> ดาวน์โหลด
                    </a>
                  @elseif($order->status === 'paid')
                    {{-- Token row missing for this paid item — shouldn't happen
                         (PhotoDeliveryService creates them on payment), but if
                         it does, the ZIP download covers the buyer. Keep a
                         visible hint instead of silently showing "-". --}}
                    <span class="text-gray-400 text-xs" title="ใช้ปุ่ม ดาวน์โหลดทั้งหมด ZIP ด้านขวา">
                      <i class="bi bi-archive mr-1"></i>ดาวน์โหลด ZIP
                    </span>
                  @else
                    <span class="text-gray-500 text-sm">-</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div>
    <div class="bg-white rounded-2xl shadow-sm" style="border-radius:16px;">
      <div class="p-4">
        <h6 class="font-semibold mb-3"><i class="bi bi-receipt mr-1" style="color:#6366f1;"></i>สรุปคำสั่งซื้อ</h6>

        <div class="flex justify-between mb-3">
          <span class="text-gray-500">สถานะ</span>
          @php
            $statusColors = [
              'paid' => ['bg' => 'rgba(16,185,129,0.1)', 'color' => '#10b981'],
              'pending' => ['bg' => 'rgba(245,158,11,0.1)', 'color' => '#f59e0b'],
              'pending_payment' => ['bg' => 'rgba(245,158,11,0.1)', 'color' => '#f59e0b'],
              'pending_review' => ['bg' => 'rgba(99,102,241,0.1)', 'color' => '#6366f1'],
              'cancelled' => ['bg' => 'rgba(239,68,68,0.1)', 'color' => '#ef4444'],
            ];
            $sc = $statusColors[$order->status] ?? ['bg' => 'rgba(107,114,128,0.1)', 'color' => '#6b7280'];
            $statusLabels = ['paid' => 'ชำระแล้ว', 'pending' => 'รอชำระ', 'pending_payment' => 'รอชำระเงิน', 'pending_review' => 'กำลังตรวจสอบ', 'cancelled' => 'ยกเลิก'];
          @endphp
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" style="background:{{ $sc['bg'] }};color:{{ $sc['color'] }};padding:0.35rem 0.8rem;font-size:0.75rem;">
            {{ $statusLabels[$order->status] ?? ucfirst($order->status) }}
          </span>
        </div>
        <div class="flex justify-between mb-3">
          <span class="text-gray-500">วันที่สั่ง</span>
          <span style="font-size:0.9rem;">{{ $order->created_at->format('d/m/Y') }}</span>
        </div>
        <hr style="border-color:#f1f5f9;">
        <div class="flex justify-between mt-3">
          <span class="font-bold text-xl">ยอดรวม</span>
          <span class="font-bold text-xl" style="color:#6366f1;">{{ number_format($order->total, 0) }} ฿</span>
        </div>

        @if(in_array($order->status, ['pending', 'pending_payment']))
        <a href="{{ route('payment.checkout', $order->id) }}" class="block w-full mt-3 py-2 font-semibold text-white text-center" style="background:linear-gradient(135deg,#6366f1,#4f46e5);border-radius:12px;border:none;">
          <i class="bi bi-credit-card mr-1"></i> ชำระเงินเลย
        </a>
        @endif

        @if($order->status === 'paid')
        {{-- ── Delivery status card ────────────────────────────────────── --}}
        @php
          $dm = $order->delivery_method ?: 'web';
          $ds = $order->delivery_status ?: null;
          $deliveryChannelMap = [
            'web'   => ['label' => 'เว็บไซต์',      'icon' => 'bi-globe2',        'color' => '#6366f1'],
            'line'  => ['label' => 'LINE',          'icon' => 'bi-chat-dots-fill','color' => '#10b981'],
            'email' => ['label' => 'อีเมล',         'icon' => 'bi-envelope-fill', 'color' => '#3b82f6'],
            'auto'  => ['label' => 'อัตโนมัติ',      'icon' => 'bi-magic',         'color' => '#8b5cf6'],
          ];
          $deliveryStatusMap = [
            'pending'   => ['label' => 'รอจัดส่ง',     'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.1)'],
            'sent'      => ['label' => 'จัดส่งแล้ว',   'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,0.1)'],
            'delivered' => ['label' => 'จัดส่งสำเร็จ', 'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.1)'],
            'failed'    => ['label' => 'จัดส่งไม่สำเร็จ','color' => '#ef4444', 'bg' => 'rgba(239,68,68,0.1)'],
            'partial'   => ['label' => 'สำรอง (เว็บ)', 'color' => '#8b5cf6', 'bg' => 'rgba(139,92,246,0.1)'],
          ];
          $ch = $deliveryChannelMap[$dm] ?? $deliveryChannelMap['web'];
          $st = $ds ? ($deliveryStatusMap[$ds] ?? null) : null;
        @endphp
        <div class="mt-3 p-3 rounded-xl" style="background:rgba(99,102,241,0.04);border:1px solid rgba(99,102,241,0.15);">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 text-sm">
              <i class="bi {{ $ch['icon'] }}" style="color:{{ $ch['color'] }};font-size:1.1rem;"></i>
              <span class="text-gray-600">รับรูปทาง</span>
              <span class="font-semibold" style="color:{{ $ch['color'] }};">{{ $ch['label'] }}</span>
            </div>
            @if($st)
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                    style="background:{{ $st['bg'] }};color:{{ $st['color'] }};font-size:0.7rem;">
                {{ $st['label'] }}
              </span>
            @endif
          </div>
          @if($order->delivered_at)
            <div class="mt-1.5 text-xs text-gray-500">
              <i class="bi bi-clock"></i>
              ส่งเมื่อ {{ \Carbon\Carbon::parse($order->delivered_at)->format('d/m/Y H:i') }}
            </div>
          @endif
          @if($ds === 'partial')
            <div class="mt-1.5 text-xs text-amber-700" style="color:#b45309;">
              <i class="bi bi-info-circle"></i>
              ส่งทางช่องทางหลักไม่สำเร็จ — ใช้การดาวน์โหลดจากเว็บไซต์แทน
            </div>
          @endif
        </div>

        <div class="flex flex-col gap-2 mt-3">
          <a href="{{ route('orders.invoice', $order->id) }}" class="block w-full py-2 font-semibold text-center" style="background:rgba(99,102,241,0.1);color:#6366f1;border-radius:12px;border:none;font-size:0.85rem;">
            <i class="bi bi-file-pdf mr-1"></i> ดาวน์โหลดใบเสร็จ (PDF)
          </a>
          <form method="POST" action="{{ route('orders.send-invoice', $order->id) }}" class="inline">
            @csrf
            <button type="submit" class="w-full py-2 font-semibold" style="background:rgba(16,185,129,0.1);color:#10b981;border-radius:12px;border:none;font-size:0.85rem;">
              <i class="bi bi-envelope mr-1"></i> ส่งใบเสร็จทางอีเมล
            </button>
          </form>
          <a href="{{ route('orders.download-zip', $order->id) }}" class="block w-full py-2 font-semibold text-center" style="background:rgba(245,158,11,0.1);color:#f59e0b;border-radius:12px;border:none;font-size:0.85rem;">
            <i class="bi bi-file-zip mr-1"></i> ดาวน์โหลดรูปทั้งหมด (ZIP)
          </a>

          {{-- ⭐ Review CTA — show prominently if no review yet, else show
               a small "already reviewed" indicator with stars.
               Drives the seller-flywheel: photographer reputation depends on
               reviews, so we make the path obvious post-purchase. --}}
          @if($existingReview ?? null)
            {{-- Already reviewed — show summary --}}
            <div class="block w-full py-2.5 px-3 mt-2 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-center"
                 style="border:1px solid rgba(16,185,129,0.2);">
              <div class="flex items-center justify-center gap-1 mb-0.5">
                @for($i = 1; $i <= 5; $i++)
                  <i class="bi bi-star{{ $i <= $existingReview->rating ? '-fill' : '' }}"
                     style="color:{{ $i <= $existingReview->rating ? '#f59e0b' : '#cbd5e1' }};font-size:0.8rem;"></i>
                @endfor
              </div>
              <div class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                <i class="bi bi-check-circle-fill"></i> คุณได้รีวิวแล้ว
              </div>
            </div>
          @else
            {{-- Not reviewed yet — prominent CTA --}}
            <a href="{{ route('reviews.create', $order->id) }}"
               class="block w-full py-2.5 font-semibold text-center mt-2 transition hover:scale-[1.02]"
               style="background:linear-gradient(135deg,#f59e0b,#fb923c);color:#fff;border-radius:12px;border:none;font-size:0.85rem;box-shadow:0 4px 12px -2px rgba(245,158,11,0.4);">
              <i class="bi bi-star-fill mr-1"></i> เขียนรีวิวให้ช่างภาพ
            </a>
          @endif

          {{-- Request Refund button --}}
          @php
            $_refundEligibility = app(\App\Services\RefundService::class)->canRequestRefund($order);
          @endphp
          @if($_refundEligibility['allowed'])
          <a href="{{ route('refunds.create', $order->id) }}" class="block w-full py-2 font-semibold text-center mt-2"
             style="background:rgba(239,68,68,0.1);color:#ef4444;border-radius:12px;border:none;font-size:0.85rem;">
            <i class="bi bi-arrow-counterclockwise mr-1"></i> ขอคืนเงิน
          </a>
          @endif
        </div>
        @endif
      </div>
    </div>
  </div>

  {{-- Order Status Timeline --}}
  <div class="mt-5">
    @include('public.orders._timeline', ['order' => $order])
  </div>
</div>
@endsection
