@extends('layouts.admin')

@section('title', 'ภาษีและต้นทุน')

@section('content')
<div class="flex justify-between items-center mb-6 flex-wrap gap-2">
  <div>
    <h4 class="font-bold mb-1 text-xl tracking-tight">
      <i class="bi bi-calculator mr-2 text-indigo-500"></i>แดชบอร์ดภาษีและรายได้
    </h4>
    <p class="text-gray-500 dark:text-gray-400 mb-0 text-sm">ภาพรวมรายได้ ค่าใช้จ่าย กำไร และการตั้งค่า VAT</p>
  </div>
  <a href="{{ route('admin.tax.costs') }}" class="inline-flex items-center bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-lg font-medium px-4 py-2 text-sm transition hover:from-indigo-600 hover:to-indigo-700 no-underline">
    <i class="bi bi-bar-chart-line mr-1.5"></i> วิเคราะห์ต้นทุน
  </a>
</div>

{{-- VAT Warning Banner --}}
@if($vatWarning)
<div class="bg-gradient-to-r from-red-500/10 via-amber-500/10 to-red-500/10 border border-red-300 dark:border-red-500/30 rounded-2xl p-5 mb-6">
  <div class="flex items-start gap-4">
    <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-red-500/15 shrink-0">
      <i class="bi bi-exclamation-triangle-fill text-red-500 text-xl"></i>
    </div>
    <div class="flex-1">
      <h5 class="font-bold text-red-700 dark:text-red-400 mb-1">รายได้เกินเกณฑ์จดทะเบียน VAT</h5>
      <p class="text-red-600/80 dark:text-red-400/70 text-sm mb-2">
        รายได้ปีนี้ (฿{{ number_format($stats['year_revenue'], 2) }}) เกินเกณฑ์ ฿{{ number_format($vatSettings['vat_threshold'], 0) }} แล้ว
        กรุณาเปิดใช้งาน VAT และจดทะเบียนภาษีมูลค่าเพิ่มตามกฎหมาย
      </p>
      <a href="#vat-settings" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-red-500 text-white text-sm font-medium hover:bg-red-600 transition no-underline">
        <i class="bi bi-gear"></i> ตั้งค่า VAT เลย
      </a>
    </div>
  </div>
</div>
@endif

{{-- Stats Cards --}}
<div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
  @php
    $cards = [
      ['icon' => 'bi-cash-stack', 'color' => 'indigo', 'hex' => '#6366f1', 'value' => $stats['total_revenue'], 'label' => 'รายได้รวม', 'prefix' => '฿'],
      ['icon' => 'bi-arrow-down-circle', 'color' => 'red', 'hex' => '#ef4444', 'value' => $stats['total_expenses'], 'label' => 'ค่าใช้จ่าย', 'prefix' => '฿'],
      ['icon' => 'bi-trophy', 'color' => 'emerald', 'hex' => '#10b981', 'value' => $stats['profit'], 'label' => 'กำไร', 'prefix' => '฿'],
      ['icon' => 'bi-percent', 'color' => 'blue', 'hex' => '#3b82f6', 'value' => $stats['profit_margin'], 'label' => 'อัตรากำไร', 'suffix' => '%', 'decimal' => 1],
      ['icon' => 'bi-calendar-check', 'color' => 'violet', 'hex' => '#8b5cf6', 'value' => $stats['year_revenue'], 'label' => 'รายได้ปีนี้', 'prefix' => '฿'],
      ['icon' => 'bi-building', 'color' => 'cyan', 'hex' => '#06b6d4', 'value' => $stats['platform_fees'], 'label' => 'ค่าธรรมเนียม', 'prefix' => '฿'],
    ];
  @endphp
  @foreach($cards as $c)
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="py-4 px-4">
      <div class="flex items-center gap-3">
        <div class="flex items-center justify-center w-11 h-11 rounded-xl shrink-0" style="background:{{ $c['hex'] }}12;">
          <i class="bi {{ $c['icon'] }} text-lg" style="color:{{ $c['hex'] }};"></i>
        </div>
        <div class="min-w-0">
          <div class="font-bold text-lg text-gray-900 dark:text-white truncate">
            {{ $c['prefix'] ?? '' }}{{ number_format($c['value'], $c['decimal'] ?? 0) }}{{ $c['suffix'] ?? '' }}
          </div>
          <small class="text-gray-500 dark:text-gray-400">{{ $c['label'] }}</small>
        </div>
      </div>
    </div>
  </div>
  @endforeach
</div>

{{-- Monthly Revenue/Expense Table --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06] mb-6">
  <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06] flex items-center justify-between">
    <h6 class="font-semibold text-sm mb-0">
      <i class="bi bi-table mr-1.5 text-indigo-500"></i>สรุปรายเดือน (12 เดือนล่าสุด)
    </h6>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="bg-gray-50 dark:bg-white/[0.03]">
          <th class="px-6 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">เดือน</th>
          <th class="px-6 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">รายรับ</th>
          <th class="px-6 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">รายจ่าย</th>
          <th class="px-6 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">กำไร</th>
        </tr>
      </thead>
      <tbody>
        @php $totalR = 0; $totalE = 0; $totalP = 0; @endphp
        @foreach($monthlyData as $m)
        @php $totalR += $m['revenue']; $totalE += $m['expenses']; $totalP += $m['profit']; @endphp
        <tr class="border-b border-gray-50 dark:border-white/[0.04] hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
          <td class="px-6 py-3 font-medium text-gray-700 dark:text-gray-300">{{ $m['label'] }}</td>
          <td class="px-6 py-3 text-right text-gray-700 dark:text-gray-300">฿{{ number_format($m['revenue'], 2) }}</td>
          <td class="px-6 py-3 text-right text-gray-700 dark:text-gray-300">฿{{ number_format($m['expenses'], 2) }}</td>
          <td class="px-6 py-3 text-right font-semibold {{ $m['profit'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400' }}">
            {{ $m['profit'] >= 0 ? '+' : '' }}฿{{ number_format($m['profit'], 2) }}
          </td>
        </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr class="bg-gray-50 dark:bg-white/[0.04] font-bold">
          <td class="px-6 py-3 text-gray-800 dark:text-gray-200">รวมทั้งหมด</td>
          <td class="px-6 py-3 text-right text-gray-800 dark:text-gray-200">฿{{ number_format($totalR, 2) }}</td>
          <td class="px-6 py-3 text-right text-gray-800 dark:text-gray-200">฿{{ number_format($totalE, 2) }}</td>
          <td class="px-6 py-3 text-right {{ $totalP >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400' }}">
            {{ $totalP >= 0 ? '+' : '' }}฿{{ number_format($totalP, 2) }}
          </td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

{{-- VAT Settings Section --}}
<div id="vat-settings" class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
  <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
    <h6 class="font-semibold text-sm mb-0">
      <i class="bi bi-receipt-cutoff mr-1.5 text-indigo-500"></i>ตั้งค่าภาษีมูลค่าเพิ่ม (VAT)
    </h6>
  </div>
  <form action="{{ route('admin.tax.vat-settings') }}" method="POST" class="p-6">
    @csrf

    {{-- Success Message --}}
    @if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-700 dark:text-emerald-400 rounded-xl px-4 py-3 mb-6 flex items-center gap-2 text-sm">
      <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
    </div>
    @endif

    {{-- Validation Errors --}}
    @if($errors->any())
    <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-700 dark:text-red-400 rounded-xl px-4 py-3 mb-6 text-sm">
      <div class="flex items-center gap-2 font-semibold mb-1"><i class="bi bi-x-circle-fill"></i> มีข้อผิดพลาด</div>
      <ul class="list-disc ml-5 space-y-0.5">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      {{-- Left Column --}}
      <div class="space-y-5">
        {{-- VAT Enabled Toggle --}}
        <div class="flex items-center justify-between p-4 rounded-xl bg-gray-50 dark:bg-white/[0.03] border border-gray-100 dark:border-white/[0.06]">
          <div>
            <div class="font-semibold text-gray-800 dark:text-gray-200 text-sm">เปิดใช้ VAT</div>
            <div class="text-gray-500 dark:text-gray-400 text-xs mt-0.5">เปิดคำนวณภาษีมูลค่าเพิ่มในระบบ</div>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" name="vat_enabled" value="1" class="sr-only peer"
                   {{ $vatSettings['vat_enabled'] === '1' ? 'checked' : '' }}>
            <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-500/30 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-500"></div>
          </label>
        </div>

        {{-- VAT Rate --}}
        <div>
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
            อัตรา VAT (%)
          </label>
          <input type="number" name="vat_rate" step="0.01" min="0" max="30"
                 value="{{ old('vat_rate', $vatSettings['vat_rate']) }}"
                 class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/[0.04] text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition text-sm"
                 placeholder="7">
          <p class="text-gray-400 text-xs mt-1">อัตรา VAT มาตรฐานของไทย = 7%</p>
        </div>

        {{-- VAT Threshold --}}
        <div>
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
            เกณฑ์รายได้ที่ต้องเปิด VAT (฿)
          </label>
          <input type="number" name="vat_threshold" step="1" min="0"
                 value="{{ old('vat_threshold', $vatSettings['vat_threshold']) }}"
                 class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/[0.04] text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition text-sm"
                 placeholder="1800000">
          <p class="text-gray-400 text-xs mt-1">ตามกฎหมายไทย รายได้เกิน 1.8 ล้านบาท/ปี ต้องจด VAT</p>
        </div>
      </div>

      {{-- Right Column --}}
      <div class="space-y-5">
        {{-- VAT Alert Toggle --}}
        <div class="flex items-center justify-between p-4 rounded-xl bg-gray-50 dark:bg-white/[0.03] border border-gray-100 dark:border-white/[0.06]">
          <div>
            <div class="font-semibold text-gray-800 dark:text-gray-200 text-sm">แจ้งเตือนเมื่อรายได้ถึงเกณฑ์</div>
            <div class="text-gray-500 dark:text-gray-400 text-xs mt-0.5">แสดงคำเตือนเมื่อรายได้ถึงเกณฑ์จด VAT</div>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" name="vat_alert_enabled" value="1" class="sr-only peer"
                   {{ $vatSettings['vat_alert_enabled'] === '1' ? 'checked' : '' }}>
            <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-500/30 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-500"></div>
          </label>
        </div>

        {{-- Company Tax ID --}}
        <div>
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
            เลขประจำตัวผู้เสียภาษี
          </label>
          <input type="text" name="company_tax_id" maxlength="20"
                 value="{{ old('company_tax_id', $vatSettings['company_tax_id']) }}"
                 class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/[0.04] text-gray-800 dark:text-gray-200 focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 transition text-sm"
                 placeholder="เลข 13 หลัก">
          <p class="text-gray-400 text-xs mt-1">เลขประจำตัวผู้เสียภาษีของบริษัท/ร้านค้า</p>
        </div>

        {{-- Info Box --}}
        <div class="p-4 rounded-xl bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20">
          <div class="flex items-start gap-3">
            <i class="bi bi-info-circle-fill text-blue-500 mt-0.5"></i>
            <div class="text-sm text-blue-700 dark:text-blue-400">
              <div class="font-semibold mb-1">ข้อมูล VAT ในประเทศไทย</div>
              <ul class="list-disc ml-4 space-y-0.5 text-blue-600 dark:text-blue-400/80 text-xs">
                <li>อัตรา VAT มาตรฐาน: 7% (รวมภาษีท้องถิ่น)</li>
                <li>ต้องจดทะเบียน VAT เมื่อรายได้เกิน 1.8 ล้านบาท/ปี</li>
                <li>ยื่น ภ.พ.30 ภายในวันที่ 15 ของเดือนถัดไป</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Save Button --}}
    <div class="flex justify-end mt-6 pt-6 border-t border-gray-100 dark:border-white/[0.06]">
      <button type="submit"
              class="inline-flex items-center gap-2 px-6 py-2.5 bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-xl font-medium text-sm hover:from-indigo-600 hover:to-indigo-700 transition shadow-sm">
        <i class="bi bi-check-lg"></i> บันทึกการตั้งค่า
      </button>
    </div>
  </form>
</div>
@endsection
