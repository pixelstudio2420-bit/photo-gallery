@extends('layouts.admin')

@section('title', 'วิธีการชำระเงิน')

@push('styles')
@include('admin.settings._shared-styles')
@endpush

@php
  $typeIcons = [
    'bank_transfer' => 'bi-bank',
    'promptpay'     => 'bi-qr-code',
    'credit_card'   => 'bi-credit-card',
    'stripe'        => 'bi-stripe',
    'omise'         => 'bi-cash-coin',
    'wallet'        => 'bi-wallet2',
  ];
  $typeColors = [
    'bank_transfer' => 'bg-sky-100 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400',
    'promptpay'     => 'bg-cyan-100 dark:bg-cyan-500/15 text-cyan-600 dark:text-cyan-400',
    'credit_card'   => 'bg-violet-100 dark:bg-violet-500/15 text-violet-600 dark:text-violet-400',
    'stripe'        => 'bg-violet-100 dark:bg-violet-500/15 text-violet-600 dark:text-violet-400',
    'omise'         => 'bg-indigo-100 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400',
    'wallet'        => 'bg-amber-100 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400',
  ];
@endphp

@section('content')
<div class="max-w-7xl mx-auto pb-16">

  {{-- ═══════════════════ Header ═══════════════════ --}}
  <div class="mb-6">
    <div class="flex items-start gap-4">
      <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
        <i class="bi bi-wallet2 text-white text-xl"></i>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
          วิธีการชำระเงิน
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          จัดการวิธีการชำระเงิน เปิด/ปิดใช้งาน และลำดับการแสดงผล
        </p>
      </div>
    </div>
  </div>

  {{-- ═══════════════════ Navigation Tabs ═══════════════════ --}}
  <div class="mb-6 bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm p-2">
    <div class="flex gap-1 flex-wrap">
      <a href="{{ route('admin.payments.index') }}"
         class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/5 transition">
        <i class="bi bi-receipt"></i>
        <span>ธุรกรรม</span>
      </a>
      <a href="{{ route('admin.payments.methods') }}"
         class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-violet-600 shadow-md shadow-indigo-500/20">
        <i class="bi bi-wallet2"></i>
        <span>วิธีการชำระ</span>
      </a>
      <a href="{{ route('admin.payments.slips') }}"
         class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/5 transition">
        <i class="bi bi-image"></i>
        <span>สลิปโอน</span>
      </a>
      <a href="{{ route('admin.payments.banks') }}"
         class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/5 transition">
        <i class="bi bi-bank"></i>
        <span>บัญชีธนาคาร</span>
      </a>
      <a href="{{ route('admin.payments.payouts') }}"
         class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-white/5 transition">
        <i class="bi bi-cash-stack"></i>
        <span>การจ่ายช่างภาพ</span>
      </a>
    </div>
  </div>

  {{-- ═══════════════════ Flash Messages ═══════════════════ --}}
  @if(session('success'))
    <div class="mb-6 flex items-start gap-3 px-4 py-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30">
      <i class="bi bi-check-circle-fill text-emerald-600 dark:text-emerald-400 text-lg"></i>
      <div class="flex-1 text-sm font-medium text-emerald-800 dark:text-emerald-300">{{ session('success') }}</div>
    </div>
  @endif
  @if(session('error'))
    <div class="mb-6 flex items-start gap-3 px-4 py-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30">
      <i class="bi bi-exclamation-triangle-fill text-rose-600 dark:text-rose-400 text-lg"></i>
      <div class="flex-1 text-sm font-medium text-rose-800 dark:text-rose-300">{{ session('error') }}</div>
    </div>
  @endif

  {{-- ═══════════════════ Methods Grid ═══════════════════ --}}
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    @forelse($methods as $method)
      @php
        $type = $method->method_type ?? '';
        $icon = $typeIcons[$type] ?? 'bi-cash';
        $iconColor = $typeColors[$type] ?? 'bg-indigo-100 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400';
      @endphp
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm overflow-hidden hover:shadow-md dark:hover:border-white/20 transition">
        <div class="p-5">
          {{-- Header: icon + name + toggle --}}
          <div class="flex items-start justify-between gap-3 mb-4">
            <div class="flex items-center gap-3 min-w-0">
              <div class="flex-shrink-0 w-12 h-12 rounded-xl {{ $iconColor }} flex items-center justify-center">
                <i class="bi {{ $icon }} text-xl"></i>
              </div>
              <div class="min-w-0">
                <div class="font-bold text-slate-900 dark:text-white truncate">
                  {{ $method->method_name ?? $method->name ?? '-' }}
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 font-mono truncate">
                  {{ $method->method_type ?? '-' }}
                </div>
              </div>
            </div>
            <label class="tw-switch flex-shrink-0">
              <input class="toggle-method" type="checkbox" role="switch"
                     data-id="{{ $method->id }}"
                     {{ $method->is_active ? 'checked' : '' }}>
              <span class="tw-switch-track"></span>
              <span class="tw-switch-knob"></span>
            </label>
          </div>

          {{-- Config details --}}
          @if($method->method_type === 'promptpay' && ($method->promptpay_number ?? $method->config))
            <div class="p-3 rounded-xl mb-3 bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-white/10">
              <div class="text-[10px] font-semibold tracking-widest uppercase text-slate-500 dark:text-slate-400 mb-1">PromptPay Number</div>
              <code class="text-sm font-mono text-indigo-600 dark:text-indigo-400 font-semibold">
                {{ $method->promptpay_number ?? (is_array($method->config) ? ($method->config['promptpay_number'] ?? '-') : '-') }}
              </code>
            </div>
          @elseif($method->method_type === 'bank_transfer' && $method->config)
            @php $cfg = is_string($method->config) ? json_decode($method->config, true) : (array)$method->config; @endphp
            <div class="p-3 rounded-xl mb-3 bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-white/10 space-y-1.5">
              @if(!empty($cfg['bank_name']))
                <div class="flex items-center justify-between gap-2 text-xs">
                  <span class="text-slate-500 dark:text-slate-400">ธนาคาร</span>
                  <span class="text-slate-900 dark:text-slate-100 font-semibold">{{ $cfg['bank_name'] }}</span>
                </div>
              @endif
              @if(!empty($cfg['account_number']))
                <div class="flex items-center justify-between gap-2 text-xs">
                  <span class="text-slate-500 dark:text-slate-400">เลขบัญชี</span>
                  <code class="text-slate-900 dark:text-slate-100 font-mono">{{ $cfg['account_number'] }}</code>
                </div>
              @endif
            </div>
          @elseif(in_array($method->method_type, ['stripe','omise']) && $method->config)
            @php $cfg = is_string($method->config) ? json_decode($method->config, true) : (array)$method->config; @endphp
            <div class="p-3 rounded-xl mb-3 bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-white/10">
              @if(!empty($cfg['public_key']))
                <div class="text-[10px] font-semibold tracking-widest uppercase text-slate-500 dark:text-slate-400 mb-1">Public Key</div>
                <code class="text-xs font-mono text-slate-700 dark:text-slate-300 break-all">{{ Str::limit($cfg['public_key'], 40) }}</code>
              @endif
            </div>
          @endif

          {{-- Footer: status + sort order --}}
          <div class="flex items-center justify-between gap-2 pt-3 border-t border-slate-200 dark:border-white/10">
            <span class="status-dot {{ $method->is_active ? 'connected' : 'unknown' }}">
              {{ $method->is_active ? 'ใช้งาน' : 'ปิดใช้งาน' }}
            </span>
            <div class="flex items-center gap-2">
              <label class="text-xs text-slate-500 dark:text-slate-400">ลำดับ</label>
              <input type="number" value="{{ $method->sort_order ?? 0 }}" min="0"
                     data-id="{{ $method->id }}"
                     class="sort-order-input w-16 text-center text-sm rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white px-2 py-1 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
            </div>
          </div>
        </div>
      </div>
    @empty
      <div class="col-span-full">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm">
          <div class="text-center py-16 px-6">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-800 mb-4">
              <i class="bi bi-wallet2 text-3xl text-slate-400 dark:text-slate-500"></i>
            </div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white mb-1">ยังไม่มีวิธีการชำระเงิน</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">
              เพิ่มวิธีการชำระเงินผ่าน
              <a href="{{ route('admin.settings.payment-gateways') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">Payment Gateways</a>
              เพื่อให้ลูกค้าสามารถชำระเงินได้
            </p>
          </div>
        </div>
      </div>
    @endforelse
  </div>

  {{-- ═══════════════════ Help box ═══════════════════ --}}
  <div class="mt-6 bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200 dark:border-indigo-500/30 rounded-2xl p-5">
    <div class="flex items-start gap-3">
      <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-500/20 flex items-center justify-center">
        <i class="bi bi-info-circle text-indigo-600 dark:text-indigo-400 text-lg"></i>
      </div>
      <div class="flex-1 min-w-0">
        <h3 class="text-sm font-semibold text-indigo-900 dark:text-indigo-200 mb-1">ตั้งค่า API Keys ของ Gateway</h3>
        <p class="text-xs text-indigo-700 dark:text-indigo-300/80">
          Stripe และ Omise ต้องตั้งค่า Public Key / Secret Key ที่
          <a href="{{ route('admin.settings.payment-gateways') }}" class="font-semibold underline hover:no-underline">Payment Gateways Settings</a>
          ก่อนเปิดใช้งาน
        </p>
      </div>
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script>
// ─── Toggle is_active + persist sort_order for each PaymentMethod card ───
// Both endpoints return JSON so we can live-update status + show inline
// errors (e.g. "missing Omise API key") without a full reload.
document.addEventListener('DOMContentLoaded', () => {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const baseUrl   = (document.querySelector('meta[name="base-url"]')?.content || '').replace(/\/$/, '');

  function toast(msg, type = 'success') {
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        toast: true, position: 'top-end', icon: type,
        title: msg, showConfirmButton: false, timer: type === 'error' ? 4000 : 2200,
      });
    } else {
      alert(msg);
    }
  }

  // ── Toggle active/inactive ─────────────────────────────────────
  document.querySelectorAll('.toggle-method').forEach(input => {
    input.addEventListener('change', async function () {
      const id     = this.dataset.id;
      const want   = this.checked;
      const card   = this.closest('.rounded-2xl');
      const statusEl = card?.querySelector('.status-dot');

      try {
        const res = await fetch(`${baseUrl}/admin/payments/methods/${id}/toggle`, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
          },
          body: JSON.stringify({ is_active: want ? 1 : 0 }),
        });
        const data = await res.json().catch(() => ({}));

        if (!res.ok || !data.success) {
          // Roll back the UI state
          this.checked = !want;
          const msg = data.message || 'บันทึกไม่สำเร็จ';
          if (data.configure_url) {
            if (typeof Swal !== 'undefined') {
              Swal.fire({
                icon: 'warning',
                title: 'ยังไม่ได้ตั้งค่า',
                text: msg,
                showCancelButton: true,
                confirmButtonText: 'ไปตั้งค่า',
                cancelButtonText: 'ปิด',
              }).then(r => {
                if (r.isConfirmed) window.location.href = data.configure_url;
              });
            } else {
              if (confirm(msg + '\n\nไปตั้งค่าเลยหรือไม่?')) window.location.href = data.configure_url;
            }
          } else {
            toast(msg, 'error');
          }
          return;
        }

        if (statusEl) {
          statusEl.textContent = data.is_active ? 'ใช้งาน' : 'ปิดใช้งาน';
          statusEl.classList.toggle('connected', !!data.is_active);
          statusEl.classList.toggle('unknown',  !data.is_active);
        }
        toast(data.message || 'บันทึกแล้ว', 'success');
      } catch (e) {
        this.checked = !want;
        toast('ข้อผิดพลาดเครือข่าย', 'error');
      }
    });
  });

  // ── Sort order — debounce so we don't POST on every keystroke ──
  const debounced = new Map();
  document.querySelectorAll('.sort-order-input').forEach(input => {
    input.addEventListener('input', function () {
      const id  = this.dataset.id;
      const val = this.value;
      clearTimeout(debounced.get(id));
      debounced.set(id, setTimeout(async () => {
        try {
          const res = await fetch(`${baseUrl}/admin/payments/methods/${id}/sort`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrfToken,
              'Accept': 'application/json',
            },
            body: JSON.stringify({ sort_order: parseInt(val, 10) || 0 }),
          });
          const data = await res.json().catch(() => ({}));
          if (!res.ok || !data.success) throw new Error(data.message || 'save failed');
        } catch (e) {
          toast('บันทึกลำดับไม่สำเร็จ', 'error');
        }
      }, 500));
    });
  });
});
</script>
@endpush
