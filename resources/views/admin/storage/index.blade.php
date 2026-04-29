@extends('layouts.admin')

@section('title', 'ภาพรวมพื้นที่จัดเก็บ')

@php
  function formatBytes($bytes, $precision = 2) {
      if ($bytes <= 0) return '0 B';
      $units = ['B', 'KB', 'MB', 'GB', 'TB'];
      $bytes = max($bytes, 0);
      $pow = floor(log($bytes, 1024));
      $pow = min($pow, count($units) - 1);
      return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
  }
  $total = max(1, (int) ($overview['total_size'] ?? 0));
  $colors = [
    'photos' => '#6366f1',
    'slips'  => '#f59e0b',
    'blog'   => '#10b981',
    'chat'   => '#ec4899',
    'other'  => '#9ca3af',
  ];
  $labels = [
    'photos' => 'ภาพ (Photos)',
    'slips'  => 'สลิปการโอนเงิน',
    'blog'   => 'บล็อก',
    'chat'   => 'ข้อความแชต',
    'other'  => 'อื่นๆ',
  ];
@endphp

@section('content')
<div class="flex justify-between items-center mb-6 flex-wrap gap-2">
  <div>
    <h4 class="font-bold mb-1 text-xl tracking-tight">
      <i class="bi bi-hdd-stack mr-2 text-indigo-500"></i>ภาพรวมพื้นที่จัดเก็บ
    </h4>
    <p class="text-gray-500 mb-0 text-sm">ติดตามการใช้งานพื้นที่ไฟล์ แคช และสถานะการ sync</p>
  </div>
  <div class="flex items-center gap-2">
    @if($overview['cdn_enabled'])
      <span class="bg-emerald-50 text-emerald-600 text-sm font-medium px-3 py-1.5 rounded-full">
        <i class="bi bi-lightning-fill mr-1"></i>CDN เปิดใช้งาน
      </span>
    @else
      <span class="bg-gray-100 text-gray-500 text-sm font-medium px-3 py-1.5 rounded-full">
        <i class="bi bi-lightning mr-1"></i>CDN ปิดอยู่
      </span>
    @endif
  </div>
</div>

{{-- Top stat cards --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-indigo-500/10">
        <i class="bi bi-hdd-fill text-indigo-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl">{{ formatBytes($overview['total_size']) }}</div>
        <small class="text-gray-500">พื้นที่ทั้งหมด</small>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-emerald-500/10">
        <i class="bi bi-database-fill text-emerald-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl">{{ formatBytes($overview['event_cache_size']) }}</div>
        <small class="text-gray-500">Event Photo Cache</small>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-blue-500/10">
        <i class="bi bi-cloud-check-fill text-blue-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl">{{ number_format($overview['drive_synced_count']) }}</div>
        <small class="text-gray-500">อีเวนต์ที่ sync Drive</small>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-violet-500/10">
        <i class="bi bi-lightning-charge-fill text-violet-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-xl">{{ $overview['cdn_enabled'] ? 'ON' : 'OFF' }}</div>
        <small class="text-gray-500">CloudFront CDN</small>
      </div>
    </div>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
  {{-- Pie chart card (SVG-based) --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 lg:col-span-1">
    <div class="p-5">
      <h6 class="font-semibold mb-4 tracking-tight">
        <i class="bi bi-pie-chart-fill mr-2 text-indigo-500"></i>สัดส่วนพื้นที่ตามประเภท
      </h6>
      @php
        $cx = 100; $cy = 100; $r = 80;
        $cumulative = 0;
        $segments = [];
        foreach ($overview['by_type'] as $type => $size) {
          if ($size <= 0) continue;
          $pct = $size / $total;
          $start = $cumulative;
          $cumulative += $pct;
          $end = $cumulative;
          $startAngle = 2 * M_PI * $start - M_PI / 2;
          $endAngle   = 2 * M_PI * $end   - M_PI / 2;
          $x1 = $cx + $r * cos($startAngle);
          $y1 = $cy + $r * sin($startAngle);
          $x2 = $cx + $r * cos($endAngle);
          $y2 = $cy + $r * sin($endAngle);
          $largeArc = $pct > 0.5 ? 1 : 0;
          $d = "M {$cx},{$cy} L " . round($x1, 2) . "," . round($y1, 2) . " A {$r},{$r} 0 {$largeArc},1 " . round($x2, 2) . "," . round($y2, 2) . " Z";
          $segments[] = ['d' => $d, 'color' => $colors[$type] ?? '#9ca3af', 'type' => $type, 'pct' => $pct];
        }
      @endphp
      <div class="flex justify-center">
        @if(count($segments) > 0)
          <svg viewBox="0 0 200 200" width="180" height="180">
            @foreach($segments as $seg)
              <path d="{{ $seg['d'] }}" fill="{{ $seg['color'] }}" opacity="0.85"></path>
            @endforeach
            <circle cx="100" cy="100" r="40" fill="#fff"/>
            <text x="100" y="96" text-anchor="middle" font-size="10" fill="#6b7280">Total</text>
            <text x="100" y="112" text-anchor="middle" font-size="12" font-weight="700" fill="#111827">{{ formatBytes($overview['total_size'], 1) }}</text>
          </svg>
        @else
          <div class="text-gray-400 text-sm text-center py-10">ไม่มีข้อมูล</div>
        @endif
      </div>

      {{-- Legend --}}
      <div class="mt-4 space-y-2">
        @foreach($overview['by_type'] as $type => $size)
          <div class="flex items-center justify-between text-sm">
            <div class="flex items-center gap-2">
              <span class="inline-block w-3 h-3 rounded-sm" style="background:{{ $colors[$type] ?? '#9ca3af' }};"></span>
              <span class="text-gray-700">{{ $labels[$type] ?? $type }}</span>
            </div>
            <div class="text-right">
              <span class="font-semibold">{{ formatBytes($size) }}</span>
              <span class="text-gray-400 text-xs ml-1">({{ $total > 0 ? round($size / $total * 100, 1) : 0 }}%)</span>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Top photographers --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 lg:col-span-2">
    <div class="p-5">
      <h6 class="font-semibold mb-4 tracking-tight">
        <i class="bi bi-camera mr-2 text-indigo-500"></i>ช่างภาพ Top 10 (ตามการใช้พื้นที่)
      </h6>
      @if(!empty($photographers))
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">ช่างภาพ</th>
                <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">จำนวนภาพ</th>
                <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">ขนาด</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase w-1/3">สัดส่วน</th>
              </tr>
            </thead>
            <tbody>
              @php $topSize = collect($photographers)->max('total_size') ?: 1; @endphp
              @foreach($photographers as $i => $p)
                <tr class="hover:bg-gray-50 border-t border-gray-50">
                  <td class="px-3 py-2 font-semibold text-gray-500">#{{ $i + 1 }}</td>
                  <td class="px-3 py-2">
                    <div class="font-medium text-gray-800">{{ $p['name'] }}</div>
                    <div class="text-xs text-gray-500">{{ $p['email'] }}</div>
                  </td>
                  <td class="px-3 py-2 text-right font-mono">{{ number_format($p['photo_count']) }}</td>
                  <td class="px-3 py-2 text-right font-semibold">{{ formatBytes($p['total_size']) }}</td>
                  <td class="px-3 py-2">
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                      <div class="h-full rounded-full" style="width:{{ min(100, round($p['total_size'] / $topSize * 100)) }}%;background:linear-gradient(90deg,#6366f1,#8b5cf6);"></div>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="text-center text-gray-400 py-10">
          <i class="bi bi-camera" style="font-size:2rem;opacity:0.3;"></i>
          <p class="mt-2 mb-0 text-sm">ยังไม่มีข้อมูลการใช้พื้นที่</p>
        </div>
      @endif
    </div>
  </div>
</div>

{{-- Info footer --}}
<div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 text-sm text-gray-700">
  <p class="mb-0">
    <i class="bi bi-info-circle-fill text-indigo-500 mr-1"></i>
    ข้อมูลพื้นที่อ้างอิงจาก <code class="bg-white px-1.5 py-0.5 rounded border border-indigo-100">storage/app/public</code>
    @if($overview['cdn_enabled'])
      — ไฟล์บางส่วนเผยแพร่ผ่าน CloudFront CDN
    @endif
  </p>
</div>
@endsection
