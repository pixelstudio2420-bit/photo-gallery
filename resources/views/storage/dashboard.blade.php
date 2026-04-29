@extends('layouts.app')

@section('title', 'คลาวด์ของฉัน')

@section('content')
@php
  $plan = $summary['plan'];
  $sub  = $summary['subscription'];
  $usedPct = $summary['storage_used_pct'] ?? 0;
  $barCls  = $summary['storage_critical'] ? 'bg-rose-500'
           : ($summary['storage_warn'] ? 'bg-amber-500' : 'bg-indigo-500');
@endphp

<div class="max-w-6xl mx-auto py-6">
  @if(session('success'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm px-4 py-3">
      <i class="bi bi-check-circle-fill mr-1.5"></i>{{ session('success') }}
    </div>
  @endif
  @if(session('error'))
    <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 text-rose-900 text-sm px-4 py-3">
      <i class="bi bi-exclamation-triangle-fill mr-1.5"></i>{{ session('error') }}
    </div>
  @endif

  <div class="flex items-center gap-3 mb-4">
    <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center">
      <i class="bi bi-cloud-fill text-xl"></i>
    </div>
    <div>
      <h1 class="text-xl font-bold text-gray-900">คลาวด์ของฉัน</h1>
      <p class="text-xs text-gray-500">จัดการพื้นที่เก็บไฟล์และแผนสมัครสมาชิก</p>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    {{-- Current plan + usage --}}
    <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="p-5 text-white"
           style="background: linear-gradient(135deg, {{ $plan->color_hex ?? '#6366f1' }}, {{ $plan->color_hex ?? '#6366f1' }}dd);">
        <div class="flex items-start justify-between">
          <div>
            <div class="text-xs opacity-80">แผนปัจจุบัน</div>
            <div class="text-2xl font-bold">{{ $plan->name }}</div>
            @if($plan->tagline)
              <div class="text-sm opacity-80 mt-0.5">{{ $plan->tagline }}</div>
            @endif
          </div>
          <div class="text-right">
            @if($plan->isFree())
              <div class="text-lg font-bold">ฟรี</div>
            @else
              <div class="text-lg font-bold">฿{{ number_format((float) $plan->price_thb, 0) }}/เดือน</div>
            @endif
            @if($sub && $sub->current_period_end)
              <div class="text-xs opacity-80 mt-1">
                {{ $summary['cancel_at_period_end'] ? 'สิ้นสุด' : 'ต่ออายุ' }}
                {{ $sub->current_period_end->format('d/m/Y') }}
              </div>
            @endif
          </div>
        </div>
      </div>

      <div class="p-5">
        <div class="flex justify-between text-sm mb-1.5">
          <span class="text-gray-600">
            ใช้ไป <span class="font-semibold text-gray-900">{{ number_format($summary['storage_used_gb'], 2) }} GB</span>
            / {{ number_format($summary['storage_quota_gb'], 0) }} GB
          </span>
          <span class="font-semibold text-gray-700">{{ $usedPct }}%</span>
        </div>
        <div class="h-2.5 w-full rounded-full bg-gray-100 overflow-hidden">
          <div class="h-full {{ $barCls }} transition-all" style="width: {{ min(100, $usedPct) }}%"></div>
        </div>

        @if($summary['storage_critical'])
          <div class="mt-3 text-xs text-rose-700 bg-rose-50 border border-rose-200 rounded p-2">
            <i class="bi bi-exclamation-triangle-fill mr-1"></i>
            พื้นที่เกือบเต็ม — ลบไฟล์เก่าหรืออัปเกรดแผน
          </div>
        @elseif($summary['storage_warn'])
          <div class="mt-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded p-2">
            <i class="bi bi-info-circle-fill mr-1"></i>
            พื้นที่เหลือน้อย — พิจารณาอัปเกรดแผน
          </div>
        @endif

        @if($summary['in_grace'])
          <div class="mt-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded p-2">
            <i class="bi bi-clock-fill mr-1"></i>
            การชำระเงินล่าสุดไม่สำเร็จ — จะหมดอายุในวันที่
            {{ optional($summary['grace_ends_at'])->format('d/m/Y') }}
          </div>
        @elseif($summary['cancel_at_period_end'])
          <div class="mt-3 text-xs text-gray-700 bg-gray-50 border border-gray-200 rounded p-2">
            <i class="bi bi-info-circle mr-1"></i>
            แผนจะสิ้นสุดเมื่อครบรอบ — กดกู้คืนเพื่อใช้ต่อ
          </div>
        @endif

        <div class="flex flex-wrap gap-2 mt-4">
          <a href="{{ route('storage.files.index') }}"
             class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-lg bg-gray-900 text-white hover:bg-gray-800 transition">
            <i class="bi bi-folder2-open"></i> จัดการไฟล์
          </a>
          <a href="{{ route('storage.plans') }}"
             class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-lg border border-indigo-200 text-indigo-700 hover:bg-indigo-50 transition">
            <i class="bi bi-lightning-charge"></i> อัปเกรด/เปลี่ยนแผน
          </a>
          <a href="{{ route('storage.invoices') }}"
             class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 transition">
            <i class="bi bi-receipt"></i> ใบเสร็จ
          </a>

          @if($sub && !$plan->isFree())
            @if($summary['cancel_at_period_end'])
              <form method="POST" action="{{ route('storage.resume') }}">
                @csrf
                <button class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-lg border border-emerald-200 text-emerald-700 hover:bg-emerald-50 transition">
                  <i class="bi bi-arrow-clockwise"></i> กู้คืนแผน
                </button>
              </form>
            @else
              <form method="POST" action="{{ route('storage.cancel') }}"
                    onsubmit="return confirm('ยืนยันยกเลิกแผน? แผนจะใช้งานได้จนสิ้นรอบบิล')">
                @csrf
                <button class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50 transition">
                  <i class="bi bi-x-circle"></i> ยกเลิกแผน
                </button>
              </form>
            @endif
          @endif
        </div>
      </div>
    </div>

    {{-- Feature sidebar --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
      <div class="font-semibold text-gray-900 mb-3">คุณสมบัติของแผน</div>
      <ul class="text-sm space-y-2">
        @php
          $featureLabels = [
            'sharing'          => 'แชร์ลิงก์พื้นฐาน',
            'password_links'   => 'ลิงก์พร้อมรหัสผ่าน',
            'access_logs'      => 'ประวัติการเข้าถึง',
            'expiring_links'   => 'ลิงก์หมดอายุ',
            'file_preview'     => 'ดูตัวอย่างไฟล์ในเว็บ',
            'public_links'     => 'Public links',
            'bulk_download'    => 'Bulk download (ZIP)',
            'advanced_audit'   => 'Audit log',
            'versioning'       => 'File versioning',
            'api_access'       => 'API access',
          ];
        @endphp
        @foreach($featureLabels as $key => $label)
          @php $enabled = in_array($key, (array) ($summary['features'] ?? []), true); @endphp
          <li class="flex items-center gap-2 {{ $enabled ? 'text-gray-900' : 'text-gray-400' }}">
            <i class="bi {{ $enabled ? 'bi-check-circle-fill text-emerald-500' : 'bi-dash-circle' }}"></i>
            <span>{{ $label }}</span>
          </li>
        @endforeach
      </ul>
    </div>
  </div>
</div>
@endsection
