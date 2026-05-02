@extends('layouts.admin')

@section('title', 'การกำหนดเส้นทางการแจ้งเตือน')

@section('content')
@php
  // Group catalogue entries by their `group` for clean section headers.
  $byGroup = collect($catalogue)->groupBy(fn ($v) => $v['group']);

  // Channel labels + icons + colours — keeps view tidy.
  $channelMeta = [
    'in_app' => ['label' => 'In-App',   'icon' => 'bi-bell-fill',         'color' => 'indigo'],
    'email'  => ['label' => 'Email',    'icon' => 'bi-envelope-fill',     'color' => 'blue'],
    'line'   => ['label' => 'LINE',     'icon' => 'bi-line',              'color' => 'emerald'],
    'sms'    => ['label' => 'SMS',      'icon' => 'bi-phone-vibrate-fill','color' => 'amber'],
    'push'   => ['label' => 'Push',     'icon' => 'bi-broadcast-pin',     'color' => 'rose'],
  ];

  $audienceMeta = [
    'customer'     => ['label' => 'ลูกค้า',   'icon' => 'bi-person-fill',         'color' => 'sky'],
    'photographer' => ['label' => 'ช่างภาพ',  'icon' => 'bi-camera-fill',         'color' => 'violet'],
    'admin'        => ['label' => 'แอดมิน',   'icon' => 'bi-person-badge-fill',   'color' => 'rose'],
  ];

  // Render helper to look up an existing rule's value (or default).
  $valueFor = function (string $key, string $audience, string $field) use ($existing) {
      $row = $existing->get($key . '|' . $audience);
      if (!$row) {
          // Defaults: in_app=true, others=false, master=true
          return $field === 'in_app' || $field === 'enabled';
      }
      $col = $field === 'enabled' ? 'is_enabled' : ($field . '_enabled');
      return (bool) $row->{$col};
  };
@endphp

<div class="flex justify-between items-center mb-6 flex-wrap gap-3">
  <div>
    <h4 class="font-bold text-xl tracking-tight">
      <i class="bi bi-bell-fill mr-2 text-indigo-500"></i>
      การกำหนดเส้นทางการแจ้งเตือน
    </h4>
    <p class="text-sm text-gray-500 mt-1">
      เลือกว่าใครจะได้รับการแจ้งเตือนแต่ละแบบ และผ่านช่องทางใด
    </p>
  </div>
  <a href="{{ route('admin.notifications.index') }}"
     class="bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-200 rounded-lg font-medium px-4 py-2 inline-flex items-center gap-1 transition">
    <i class="bi bi-arrow-left"></i> กลับ
  </a>
</div>

{{-- ── Tabs nav ──────────────────────────────────────────────────── --}}
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/10 mb-4">
  <div class="py-2 px-3">
    <div class="flex gap-1 flex-wrap">
      <a href="{{ route('admin.notifications.index') }}" class="text-sm px-4 py-1.5 rounded-lg bg-indigo-500/[0.08] text-indigo-500 font-medium hover:bg-indigo-500/[0.15] transition">
        <i class="bi bi-list-ul mr-1"></i> รายการแจ้งเตือน
      </a>
      <a href="{{ route('admin.notifications.routing') }}" class="text-sm px-4 py-1.5 rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-600 text-white font-medium">
        <i class="bi bi-diagram-3-fill mr-1"></i> เส้นทางการแจ้งเตือน
      </a>
    </div>
  </div>
</div>

{{-- ── How it works (collapsible explainer) ─────────────────────── --}}
<details class="bg-indigo-50 dark:bg-indigo-500/10 rounded-xl border border-indigo-100 dark:border-indigo-500/20 mb-4">
  <summary class="px-4 py-3 cursor-pointer font-semibold text-indigo-900 dark:text-indigo-300 text-sm flex items-center gap-2">
    <i class="bi bi-info-circle-fill"></i>
    วิธีใช้งาน
    <i class="bi bi-chevron-down ml-auto text-xs"></i>
  </summary>
  <div class="px-4 pb-4 text-xs text-indigo-900/80 dark:text-indigo-300/80 space-y-2 leading-relaxed">
    <p><strong>แต่ละแถว</strong> = หนึ่งเหตุการณ์ที่ระบบส่งแจ้งเตือน (เช่น ลูกค้าสั่งซื้อใหม่ / สลิปอนุมัติ)</p>
    <p><strong>3 กลุ่มผู้รับ:</strong> ลูกค้า / ช่างภาพ / แอดมิน — แต่ละกลุ่มมี 5 ช่องทาง (In-App / Email / LINE / SMS / Push) ที่เปิด/ปิดได้แยก</p>
    <p><strong>Master toggle</strong> ที่ด้านซ้ายของแต่ละผู้รับ = ปิด = หยุดส่งทุกช่องทางทันที (ไม่ต้องคลิกทีละช่อง)</p>
    <p>📌 <strong>หมายเหตุ:</strong> ระบบใช้ค่าเริ่มต้น <strong>In-App = เปิด</strong> และช่องอื่น = ปิด สำหรับเหตุการณ์ที่ยังไม่เคยถูกแก้ไข</p>
  </div>
</details>

{{-- ── Routing matrix form ───────────────────────────────────────── --}}
<form method="POST" action="{{ route('admin.notifications.routing.update') }}">
  @csrf

  @foreach($byGroup as $groupName => $events)
  <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/10 mb-4 overflow-hidden">
    <div class="px-5 py-3 bg-gradient-to-r from-slate-50 to-slate-100 dark:from-slate-700/40 dark:to-slate-700/20 border-b border-slate-200 dark:border-white/10">
      <h3 class="font-semibold text-slate-900 dark:text-slate-100 text-sm flex items-center gap-2">
        <i class="bi bi-folder-fill text-indigo-500"></i>
        {{ $groupName }}
        <span class="text-xs text-slate-400 font-normal">· {{ count($events) }} เหตุการณ์</span>
      </h3>
    </div>

    <div class="divide-y divide-slate-200 dark:divide-white/10">
      @foreach($events as $eventKey => $meta)
      <div class="p-5">
        <div class="flex items-start gap-2 mb-3">
          <i class="bi bi-bell text-indigo-500 mt-0.5"></i>
          <div class="flex-1 min-w-0">
            <div class="font-semibold text-slate-900 dark:text-slate-100 text-sm">{{ $meta['label'] }}</div>
            <code class="text-[10px] text-slate-400 font-mono">{{ $eventKey }}</code>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          @foreach($audiences as $audience)
            @if(in_array($audience, $meta['audiences']))
            @php $am = $audienceMeta[$audience]; @endphp
            <div class="rounded-lg border border-slate-200 dark:border-white/10 bg-slate-50/50 dark:bg-slate-900/30 p-3
                        hover:border-{{ $am['color'] }}-300 dark:hover:border-{{ $am['color'] }}-500/40 transition">
              {{-- Audience header + master toggle --}}
              <div class="flex items-center justify-between mb-2 pb-2 border-b border-slate-200 dark:border-white/10">
                <div class="flex items-center gap-1.5 text-sm font-medium">
                  <i class="bi {{ $am['icon'] }} text-{{ $am['color'] }}-500"></i>
                  <span class="text-slate-700 dark:text-slate-200">{{ $am['label'] }}</span>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox"
                         name="rules[{{ $eventKey }}][{{ $audience }}][enabled]"
                         value="1"
                         {{ $valueFor($eventKey, $audience, 'enabled') ? 'checked' : '' }}
                         class="sr-only peer">
                  <div class="w-9 h-5 bg-slate-300 dark:bg-slate-600 peer-focus:ring-2 peer-focus:ring-{{ $am['color'] }}-300
                              rounded-full peer-checked:bg-{{ $am['color'] }}-500
                              after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                              after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all
                              peer-checked:after:translate-x-4"></div>
                </label>
              </div>

              {{-- Channels grid --}}
              <div class="grid grid-cols-5 gap-1">
                @foreach($channels as $channel)
                @php $cm = $channelMeta[$channel]; @endphp
                <label class="cursor-pointer text-center group" title="{{ $cm['label'] }}">
                  <input type="checkbox"
                         name="rules[{{ $eventKey }}][{{ $audience }}][{{ $channel }}]"
                         value="1"
                         {{ $valueFor($eventKey, $audience, $channel) ? 'checked' : '' }}
                         class="sr-only peer">
                  <div class="aspect-square rounded-md flex items-center justify-center transition
                              bg-slate-200/50 dark:bg-slate-700/50 text-slate-400 dark:text-slate-500
                              peer-checked:bg-{{ $cm['color'] }}-500 peer-checked:text-white peer-checked:shadow-sm
                              group-hover:bg-slate-300 dark:group-hover:bg-slate-700">
                    <i class="bi {{ $cm['icon'] }} text-sm"></i>
                  </div>
                  <div class="text-[9px] mt-0.5 text-slate-500 dark:text-slate-400 font-medium truncate">
                    {{ $cm['label'] }}
                  </div>
                </label>
                @endforeach
              </div>
            </div>
            @endif
          @endforeach
        </div>
      </div>
      @endforeach
    </div>
  </div>
  @endforeach

  {{-- Sticky save bar --}}
  <div class="sticky bottom-0 bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-white/10 p-4 flex items-center justify-between gap-3 mb-0 shadow-lg z-10 -mx-4 lg:-mx-6">
    <div class="text-xs text-slate-500 dark:text-slate-400">
      <i class="bi bi-info-circle mr-1"></i>
      การเปลี่ยนแปลงจะมีผลทันทีหลังกดบันทึก · ระบบจะใช้กับการแจ้งเตือนใหม่ทั้งหมด
    </div>
    <button type="submit"
            class="bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700
                   text-white font-semibold px-6 py-2.5 rounded-lg shadow-md inline-flex items-center gap-2 transition">
      <i class="bi bi-save-fill"></i>
      บันทึกการตั้งค่า
    </button>
  </div>
</form>

{{--
  Tailwind dynamic-class trap:
  We use bg-{{$color}}-500 etc. above which Tailwind's static scanner
  CAN'T see at compile time. Force the JIT to keep these classes by
  explicitly mentioning every dynamic class once below — the @source
  directive in app.css will pick them up too if needed.
  Hidden via display:none.
--}}
<div class="hidden">
  <span class="bg-indigo-500 bg-blue-500 bg-emerald-500 bg-amber-500 bg-rose-500 bg-sky-500 bg-violet-500"></span>
  <span class="hover:border-indigo-300 hover:border-blue-300 hover:border-emerald-300 hover:border-amber-300 hover:border-rose-300 hover:border-sky-300 hover:border-violet-300"></span>
  <span class="dark:hover:border-indigo-500/40 dark:hover:border-blue-500/40 dark:hover:border-emerald-500/40 dark:hover:border-amber-500/40 dark:hover:border-rose-500/40 dark:hover:border-sky-500/40 dark:hover:border-violet-500/40"></span>
  <span class="text-indigo-500 text-blue-500 text-emerald-500 text-amber-500 text-rose-500 text-sky-500 text-violet-500"></span>
  <span class="peer-checked:bg-indigo-500 peer-checked:bg-blue-500 peer-checked:bg-emerald-500 peer-checked:bg-amber-500 peer-checked:bg-rose-500 peer-checked:bg-sky-500 peer-checked:bg-violet-500"></span>
  <span class="peer-focus:ring-indigo-300 peer-focus:ring-blue-300 peer-focus:ring-emerald-300 peer-focus:ring-amber-300 peer-focus:ring-rose-300 peer-focus:ring-sky-300 peer-focus:ring-violet-300"></span>
</div>
@endsection
