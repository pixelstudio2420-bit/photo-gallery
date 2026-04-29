@extends('layouts.admin')

@section('title', 'Automatic Payouts')

@section('content')
<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
  <h4 class="font-bold mb-0" style="letter-spacing:-0.02em;">
    <i class="bi bi-lightning-charge-fill mr-2" style="color:#6366f1;"></i>จ่ายเงินอัตโนมัติ (PromptPay)
  </h4>

  {{-- Quick status chips: provider health + global enable --}}
  <div class="flex items-center gap-2">
    @if($config['payout_enabled'])
      <span class="text-xs font-semibold px-2.5 py-1 rounded-md bg-emerald-100 text-emerald-700 inline-flex items-center gap-1">
        <i class="bi bi-check-circle-fill"></i>ระบบเปิดใช้งาน
      </span>
    @else
      <span class="text-xs font-semibold px-2.5 py-1 rounded-md bg-amber-100 text-amber-700 inline-flex items-center gap-1">
        <i class="bi bi-pause-circle-fill"></i>ระบบปิดอยู่
      </span>
    @endif
    <span class="text-xs font-semibold px-2.5 py-1 rounded-md inline-flex items-center gap-1
                 {{ $providerHealthy ? 'bg-sky-100 text-sky-700' : 'bg-rose-100 text-rose-700' }}">
      <i class="bi {{ $providerHealthy ? 'bi-shield-check' : 'bi-exclamation-triangle-fill' }}"></i>
      Provider: {{ strtoupper($activeProvider) }} — {{ $providerHealthy ? 'OK' : 'Down' }}
    </span>
  </div>
</div>

{{-- ────── Tabs (follow existing admin/payments style) ────── --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-3">
  <div class="py-2 px-3">
    <div class="flex gap-1 flex-wrap">
      <a href="{{ route('admin.payments.index') }}" class="text-sm px-4 py-1.5 rounded-lg bg-indigo-500/[0.08] text-indigo-500 font-medium transition hover:bg-indigo-500/[0.15]">
        <i class="bi bi-receipt mr-1"></i> ธุรกรรม
      </a>
      <a href="{{ route('admin.payments.payouts') }}" class="text-sm px-4 py-1.5 rounded-lg bg-indigo-500/[0.08] text-indigo-500 font-medium transition hover:bg-indigo-500/[0.15]">
        <i class="bi bi-cash-stack mr-1"></i> การจ่าย (Manual)
      </a>
      <a href="{{ route('admin.payments.payouts.automation') }}" class="text-sm px-4 py-1.5 rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-600 text-white font-medium">
        <i class="bi bi-lightning-charge-fill mr-1"></i> Auto-Payout
      </a>
    </div>
  </div>
</div>

{{-- Flash --}}
@foreach(['success' => 'emerald', 'info' => 'sky', 'error' => 'rose'] as $key => $color)
  @if(session($key))
    <div class="flex items-center gap-2 mb-3 px-4 py-3 rounded-xl bg-{{ $color }}-500/10 text-{{ $color }}-800">
      <i class="bi bi-info-circle-fill"></i> {{ session($key) }}
    </div>
  @endif
@endforeach

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
  {{-- Settings form (2/3 wide) --}}
  <div class="lg:col-span-2">
    <form method="POST" action="{{ route('admin.payments.payouts.automation.save') }}"
          x-data="{ schedule: '{{ old('payout_schedule', $config['payout_schedule']) }}' }"
          class="bg-white rounded-xl shadow-sm border border-gray-100">
      @csrf
      <div class="p-5 border-b border-gray-100">
        <h5 class="font-bold text-base mb-1">การตั้งค่าการจ่ายเงินอัตโนมัติ</h5>
        <p class="text-xs text-gray-500">ระบบจะตรวจสอบทุกชั่วโมงและโอนเงินให้ช่างภาพเมื่อเข้าเงื่อนไข</p>
      </div>

      <div class="p-5 space-y-5">
        {{-- Big master switch --}}
        <div class="flex items-center justify-between p-4 rounded-xl border-2 {{ $config['payout_enabled'] ? 'border-emerald-300 bg-emerald-50/50' : 'border-gray-200 bg-gray-50' }}">
          <div>
            <div class="font-semibold text-gray-900">เปิดใช้งานการจ่ายเงินอัตโนมัติ</div>
            <div class="text-xs text-gray-600 mt-0.5">เมื่อปิด ระบบจะหยุดจ่ายทั้งหมด (แต่ยังบันทึกรายได้ปกติ)</div>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="hidden" name="payout_enabled" value="0">
            <input type="checkbox" name="payout_enabled" value="1" class="sr-only peer" {{ $config['payout_enabled'] ? 'checked' : '' }}>
            <div class="w-14 h-7 bg-gray-300 rounded-full peer peer-focus:ring-2 peer-focus:ring-indigo-500 peer-checked:bg-emerald-500 transition
                        after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-6 after:w-6 after:transition
                        peer-checked:after:translate-x-7"></div>
          </label>
        </div>

        {{-- Provider --}}
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">ผู้ให้บริการโอนเงิน</label>
          <select name="payout_provider" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm">
            @foreach($providers as $key => $label)
              <option value="{{ $key }}" {{ $config['payout_provider'] === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
          <p class="text-xs text-gray-500 mt-1">Mock = จำลองผลสำเร็จเพื่อทดสอบ — ห้ามใช้จริง</p>
        </div>

        {{-- Threshold --}}
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">ยอดขั้นต่ำในการจ่ายเงิน (บาท)</label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-medium">฿</span>
            <input type="number" name="payout_min_amount" min="0" max="100000" step="50"
                   value="{{ old('payout_min_amount', $config['payout_min_amount']) }}"
                   class="w-full pl-8 pr-4 py-2.5 border border-gray-200 rounded-lg text-sm font-mono">
          </div>
          <p class="text-xs text-gray-500 mt-1">
            ใส่ <code class="bg-gray-100 px-1 rounded text-[11px]">0</code> = ปิดเงื่อนไขขั้นต่ำ จ่ายตามรอบเวลาอย่างเดียว
            (ค่าเริ่มต้น ฿500 — ใช้ป้องกันการโอนทีละน้อย)
          </p>
        </div>

        {{-- Schedule --}}
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">รอบการจ่ายเงินตามตาราง</label>
          <select name="payout_schedule" x-model="schedule" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm">
            @foreach($scheduleOptions as $key => $label)
              <option value="{{ $key }}" {{ $config['payout_schedule'] === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>

          {{-- Day-of-month picker — only meaningful when schedule = 'monthly' --}}
          <div x-show="schedule === 'monthly'" x-cloak x-transition class="mt-3 p-3 rounded-lg bg-indigo-50 border border-indigo-200">
            <label class="block text-xs font-semibold text-indigo-900 mb-1.5">
              <i class="bi bi-calendar-event mr-1"></i> วันในเดือนที่จะจ่าย
            </label>
            <div class="flex items-center gap-2">
              <input type="number" name="payout_day_of_month" min="1" max="31" step="1"
                     value="{{ old('payout_day_of_month', $config['payout_day_of_month']) }}"
                     class="w-24 px-3 py-2 border border-indigo-200 rounded-lg text-sm font-mono text-center">
              <span class="text-xs text-indigo-700">ของทุกเดือน (เช่น 15 = วันที่ 15 / 16 = วันที่ 16)</span>
            </div>
            <p class="text-[11px] text-indigo-700/80 mt-2 leading-relaxed">
              <i class="bi bi-info-circle"></i>
              ถ้าตั้ง 29-31 ในเดือนกุมภาพันธ์/เดือนที่มีน้อยกว่า ระบบจะเลื่อนเป็น
              <strong>วันสุดท้ายของเดือนนั้น</strong> อัตโนมัติ (ไม่ข้ามเดือน)
            </p>
          </div>
        </div>

        {{-- Trigger logic --}}
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">เงื่อนไขการทริกเกอร์</label>
          <select name="payout_trigger_logic" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm">
            @foreach($triggerOptions as $key => $label)
              <option value="{{ $key }}" {{ $config['payout_trigger_logic'] === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        {{-- Delay --}}
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">ดีเลย์การจ่าย (ชั่วโมง)</label>
          <input type="number" name="payout_delay_hours" min="0" max="168"
                 value="{{ old('payout_delay_hours', $config['payout_delay_hours']) }}"
                 class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm font-mono">
          <p class="text-xs text-gray-500 mt-1">ไม่จ่ายรายการที่เพิ่งเกิดภายใน N ชั่วโมง — เผื่อเวลาให้ลูกค้าขอคืนเงิน (0 = จ่ายทันที)</p>
        </div>

        {{-- Omise transfer webhook secret (optional but recommended for prod) --}}
        <div class="pt-4 border-t border-dashed border-gray-200">
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">
            Omise Webhook Secret <span class="text-xs font-normal text-gray-400">(ไม่บังคับ)</span>
          </label>
          <input type="text" name="omise_webhook_secret" autocomplete="off"
                 value="{{ old('omise_webhook_secret', $config['omise_webhook_secret'] ?? '') }}"
                 class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm font-mono"
                 placeholder="ไม่ต้องใส่ ถ้ายังไม่ได้ตั้งใน Omise Dashboard">
          <div class="mt-2 p-3 rounded-lg bg-sky-50 border border-sky-200 text-xs text-sky-900 leading-relaxed">
            <div class="font-semibold mb-1"><i class="bi bi-plug-fill mr-1"></i> Endpoint สำหรับตั้งใน Omise Dashboard</div>
            <code class="block bg-white border border-sky-200 rounded px-2 py-1 font-mono text-[11px] break-all">
              {{ url('/api/webhooks/omise/transfers') }}
            </code>
            <p class="mt-2">
              ตั้ง secret เดียวกันที่นี่ → ระบบตรวจสอบลายเซ็น HMAC-SHA256 ก่อนยอมรับ event
              ถ้าเว้นว่างไว้ webhook จะทำงานโดยไม่ตรวจสอบ (เหมาะกับ dev เท่านั้น)
            </p>
          </div>
        </div>
      </div>

      <div class="px-5 py-4 bg-gray-50 border-t border-gray-100 rounded-b-xl flex justify-end gap-2">
        <button type="submit" class="bg-gradient-to-br from-indigo-600 to-indigo-700 text-white font-semibold px-5 py-2 rounded-lg inline-flex items-center gap-1.5 hover:shadow-md">
          <i class="bi bi-save"></i> บันทึกการตั้งค่า
        </button>
      </div>
    </form>
  </div>

  {{-- Preview / dry-run panel (1/3 wide) --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="p-5 border-b border-gray-100">
      <h5 class="font-bold text-base mb-1">จะเกิดอะไรขึ้นถ้ารันตอนนี้</h5>
      <p class="text-xs text-gray-500">ตัวอย่างแบบไม่เขียนข้อมูล (Dry-run) ใช้ค่าที่บันทึกล่าสุด</p>
    </div>
    <div class="p-5">
      <div class="text-3xl font-bold text-gray-900 mb-1">
        ฿{{ number_format($dryRun['eligible_amount'], 0) }}
      </div>
      <div class="text-xs text-gray-500 mb-4">
        จ่ายให้ช่างภาพ <strong class="text-gray-700">{{ $dryRun['eligible_count'] }}</strong> คน
        · รอบปัจจุบัน{{ $dryRun['schedule_open'] ? ' เปิด' : ' ปิด' }}
      </div>

      <form method="POST" action="{{ route('admin.payments.payouts.automation.run-now') }}" onsubmit="return confirm('เริ่มรันทันที?');">
        @csrf
        <button type="submit" class="w-full bg-gradient-to-br from-amber-500 to-orange-600 text-white font-semibold px-4 py-2.5 rounded-lg inline-flex items-center justify-center gap-1.5 hover:shadow-md">
          <i class="bi bi-lightning-charge-fill"></i> รันทันที (Manual)
        </button>
      </form>
      <p class="text-[11px] text-gray-500 mt-2 leading-relaxed">
        การรันด้วยตนเองจะ bypass เงื่อนไขรอบ/ยอดขั้นต่ำ แต่ยังต้องมีเลข PromptPay
      </p>
    </div>
  </div>
</div>

{{-- ────── Preview table ────── --}}
@if($dryRun['rows']->isNotEmpty())
<div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-4">
  <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
    <h5 class="font-bold text-base mb-0">ช่างภาพที่มียอดค้าง (Top 50)</h5>
    <span class="text-xs text-gray-500">เฉพาะก่อน {{ $dryRun['window_end']->translatedFormat('j M · H:i') }}</span>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50">
        <tr class="text-left text-xs uppercase text-gray-500">
          <th class="px-4 py-2">ช่างภาพ</th>
          <th class="px-4 py-2">Tier</th>
          <th class="px-4 py-2">PromptPay</th>
          <th class="px-4 py-2 text-right">ยอดค้าง</th>
          <th class="px-4 py-2 text-center">รายการ</th>
          <th class="px-4 py-2 text-center">รอบนี้?</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @foreach($dryRun['rows'] as $row)
        <tr class="{{ $row->would_fire ? 'bg-emerald-50/30' : '' }}">
          <td class="px-4 py-2 font-medium">{{ $row->display_name ?? '—' }}</td>
          <td class="px-4 py-2">
            <span class="text-[11px] font-bold px-2 py-0.5 rounded-md bg-gray-100 text-gray-700">{{ strtoupper($row->tier ?? 'creator') }}</span>
          </td>
          <td class="px-4 py-2 font-mono text-xs text-gray-600">
            @if($row->promptpay_number)
              {{ $row->promptpay_verified_name ? $row->promptpay_verified_name . ' · ' : '' }}***{{ substr($row->promptpay_number, -4) }}
            @else
              <span class="text-rose-500">— ยังไม่ได้กรอก</span>
            @endif
          </td>
          <td class="px-4 py-2 text-right font-mono font-semibold">฿{{ number_format((float) $row->pending_amount, 2) }}</td>
          <td class="px-4 py-2 text-center text-gray-500">{{ $row->payout_count }}</td>
          <td class="px-4 py-2 text-center">
            @if($row->would_fire)
              <i class="bi bi-check-circle-fill text-emerald-500"></i>
            @elseif(!$row->promptpay_number)
              <span class="text-[11px] text-rose-500">ต้องกรอก PromptPay</span>
            @else
              <span class="text-[11px] text-gray-400">ยังไม่ถึงเกณฑ์</span>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endif

{{-- ────── Recent disbursements ────── --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100">
  <div class="px-5 py-3 border-b border-gray-100">
    <h5 class="font-bold text-base mb-0">การจ่ายล่าสุด (20 รายการ)</h5>
  </div>
  @if($recent->isEmpty())
    <div class="p-8 text-center text-gray-500 text-sm">
      <i class="bi bi-inbox text-3xl text-gray-300 mb-2 block"></i>
      ยังไม่มีการจ่ายเงินอัตโนมัติในระบบ
    </div>
  @else
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr class="text-left text-xs uppercase text-gray-500">
            <th class="px-4 py-2">เวลา</th>
            <th class="px-4 py-2">ช่างภาพ</th>
            <th class="px-4 py-2 text-right">จำนวน</th>
            <th class="px-4 py-2 text-center">รายการ</th>
            <th class="px-4 py-2">Provider</th>
            <th class="px-4 py-2">สถานะ</th>
            <th class="px-4 py-2">ทริกเกอร์</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @foreach($recent as $d)
          <tr>
            <td class="px-4 py-2 text-xs text-gray-500 whitespace-nowrap">{{ $d->created_at?->translatedFormat('j M · H:i') }}</td>
            <td class="px-4 py-2">{{ $d->photographerProfile?->display_name ?? '#' . $d->photographer_id }}</td>
            <td class="px-4 py-2 text-right font-mono font-semibold">฿{{ number_format((float) $d->amount_thb, 2) }}</td>
            <td class="px-4 py-2 text-center text-gray-500">{{ $d->payout_count }}</td>
            <td class="px-4 py-2 text-xs uppercase font-mono text-gray-600">{{ $d->provider }}</td>
            <td class="px-4 py-2">
              @php
                $colors = [
                  'pending'    => 'bg-gray-100 text-gray-700',
                  'processing' => 'bg-sky-100 text-sky-700',
                  'succeeded'  => 'bg-emerald-100 text-emerald-700',
                  'failed'     => 'bg-rose-100 text-rose-700',
                ];
                $cls = $colors[$d->status] ?? 'bg-gray-100 text-gray-700';
              @endphp
              <span class="text-[11px] font-bold px-2 py-0.5 rounded-md {{ $cls }}">{{ strtoupper($d->status) }}</span>
              @if($d->status === 'failed' && $d->status_reason)
                <div class="text-[11px] text-rose-600 mt-0.5">{{ $d->status_reason }}</div>
              @endif
            </td>
            <td class="px-4 py-2 text-xs text-gray-500">{{ $d->trigger_type }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
