@extends('layouts.photographer')

@section('title', 'ตารางเวลาทำงาน')

@section('content')
<div class="max-w-5xl mx-auto pb-12">

  <div class="mb-5">
    <h1 class="text-xl md:text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">
      <i class="bi bi-clock-history text-indigo-500"></i> ตารางเวลาทำงาน
    </h1>
    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">ตั้งช่วงเวลาที่รับงาน — ลูกค้าจะจองได้เฉพาะช่วงนี้</p>
  </div>

  @if(session('success'))
    <div class="mb-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 text-sm">
      <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
    </div>
  @endif
  @if($errors->any())
    <div class="mb-4 p-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-800 dark:text-rose-200 text-sm">
      <ul class="list-disc list-inside m-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- Form --}}
    <div class="lg:col-span-1">
      <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 p-5 sticky top-4"
           x-data="{ type: 'recurring' }">
        <h2 class="font-bold text-base text-slate-900 dark:text-white mb-3">
          <i class="bi bi-plus-circle text-indigo-500"></i> เพิ่มกฎเวลา
        </h2>
        <form action="{{ route('photographer.availability.store') }}" method="POST" class="space-y-3">
          @csrf

          <div>
            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-200 mb-1">ประเภท</label>
            <div class="grid grid-cols-2 gap-2">
              <label class="cursor-pointer">
                <input type="radio" name="type" value="recurring" x-model="type" class="peer sr-only">
                <div class="text-center px-2 py-2 rounded-lg border text-xs font-semibold transition
                            border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-300
                            peer-checked:border-indigo-500 peer-checked:bg-indigo-50 dark:peer-checked:bg-indigo-500/15 peer-checked:text-indigo-600">
                  🔁 ทุกสัปดาห์
                </div>
              </label>
              <label class="cursor-pointer">
                <input type="radio" name="type" value="override" x-model="type" class="peer sr-only">
                <div class="text-center px-2 py-2 rounded-lg border text-xs font-semibold transition
                            border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-300
                            peer-checked:border-rose-500 peer-checked:bg-rose-50 dark:peer-checked:bg-rose-500/15 peer-checked:text-rose-600">
                  📅 วันเดียว
                </div>
              </label>
            </div>
          </div>

          <div x-show="type === 'recurring'">
            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-200 mb-1">วันในสัปดาห์</label>
            <select name="day_of_week" class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-800 text-sm">
              <option value="1">จันทร์</option>
              <option value="2">อังคาร</option>
              <option value="3">พุธ</option>
              <option value="4">พฤหัสบดี</option>
              <option value="5">ศุกร์</option>
              <option value="6">เสาร์</option>
              <option value="0">อาทิตย์</option>
            </select>
          </div>

          <div x-show="type === 'override'" x-cloak>
            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-200 mb-1">วันที่</label>
            <input type="date" name="specific_date" min="{{ date('Y-m-d') }}"
                   class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-800 text-sm">
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-200 mb-1">เริ่ม</label>
              <input type="time" name="time_start" required value="09:00"
                     class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-800 text-sm">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-200 mb-1">สิ้นสุด</label>
              <input type="time" name="time_end" required value="17:00"
                     class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-800 text-sm">
            </div>
          </div>

          <div>
            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-200 mb-1">ผล</label>
            <div class="grid grid-cols-2 gap-2">
              <label class="cursor-pointer">
                <input type="radio" name="effect" value="available" checked class="peer sr-only">
                <div class="text-center px-2 py-2 rounded-lg border text-xs font-semibold transition
                            border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-300
                            peer-checked:border-emerald-500 peer-checked:bg-emerald-50 dark:peer-checked:bg-emerald-500/15 peer-checked:text-emerald-600">
                  ✓ รับงาน
                </div>
              </label>
              <label class="cursor-pointer">
                <input type="radio" name="effect" value="blocked" class="peer sr-only">
                <div class="text-center px-2 py-2 rounded-lg border text-xs font-semibold transition
                            border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-300
                            peer-checked:border-rose-500 peer-checked:bg-rose-50 dark:peer-checked:bg-rose-500/15 peer-checked:text-rose-600">
                  ✗ บล็อก
                </div>
              </label>
            </div>
          </div>

          <div>
            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-200 mb-1">ป้าย (optional)</label>
            <input type="text" name="label" maxlength="100" placeholder="เช่น Wedding hours, ลาพักร้อน"
                   class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-800 text-sm">
          </div>

          <button type="submit" class="w-full px-4 py-2 rounded-xl text-sm font-bold text-white bg-indigo-500 hover:bg-indigo-600 transition">
            <i class="bi bi-plus-lg"></i> เพิ่มกฎ
          </button>
        </form>
      </div>
    </div>

    {{-- Existing rules --}}
    <div class="lg:col-span-2 space-y-4">

      <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-white/5">
          <h2 class="font-bold text-sm text-slate-900 dark:text-white flex items-center gap-2">
            <i class="bi bi-arrow-repeat text-indigo-500"></i> กฎประจำสัปดาห์ ({{ $recurring->count() }})
          </h2>
        </div>
        @if($recurring->count() === 0)
          <div class="p-6 text-center text-sm text-slate-500">
            ยังไม่ได้ตั้งกฎ — ลูกค้าจะมองเห็นว่าคุณว่างทุกเวลา (default)
          </div>
        @else
          <div class="divide-y divide-slate-100 dark:divide-white/5">
            @foreach($recurring as $r)
              <div class="px-4 py-3 flex items-center gap-3">
                <div class="w-12 text-center">
                  <div class="text-[10px] uppercase font-bold text-slate-500">{{ \App\Models\PhotographerAvailability::dayOfWeekLabel($r->day_of_week) }}</div>
                </div>
                <div class="flex-1">
                  <div class="font-mono text-sm font-semibold text-slate-700 dark:text-slate-200">
                    {{ \Carbon\Carbon::parse($r->time_start)->format('H:i') }} – {{ \Carbon\Carbon::parse($r->time_end)->format('H:i') }}
                  </div>
                  @if($r->label)<div class="text-[11px] text-slate-500">{{ $r->label }}</div>@endif
                </div>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold {{ $r->effect === 'available' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300' }}">
                  {{ $r->effect === 'available' ? '✓ รับงาน' : '✗ บล็อก' }}
                </span>
                <form action="{{ route('photographer.availability.delete', $r->id) }}" method="POST" class="inline">
                  @csrf @method('DELETE')
                  <button type="submit" class="w-7 h-7 rounded-md text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/15 transition">
                    <i class="bi bi-trash text-xs"></i>
                  </button>
                </form>
              </div>
            @endforeach
          </div>
        @endif
      </div>

      <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-white/5">
          <h2 class="font-bold text-sm text-slate-900 dark:text-white flex items-center gap-2">
            <i class="bi bi-calendar-x text-rose-500"></i> วันพิเศษ / วันลา ({{ $overrides->count() }})
          </h2>
        </div>
        @if($overrides->count() === 0)
          <div class="p-6 text-center text-sm text-slate-500">
            ไม่มีวันลา หรือวันที่ตั้งกฎพิเศษ
          </div>
        @else
          <div class="divide-y divide-slate-100 dark:divide-white/5">
            @foreach($overrides as $r)
              <div class="px-4 py-3 flex items-center gap-3">
                <div class="font-bold text-sm text-rose-600 w-24">{{ $r->specific_date->format('d M Y') }}</div>
                <div class="flex-1 font-mono text-sm">
                  {{ \Carbon\Carbon::parse($r->time_start)->format('H:i') }} – {{ \Carbon\Carbon::parse($r->time_end)->format('H:i') }}
                  @if($r->label)<span class="text-[11px] text-slate-500 font-sans ml-2">{{ $r->label }}</span>@endif
                </div>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold {{ $r->effect === 'available' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                  {{ $r->effect === 'available' ? '✓' : '✗' }}
                </span>
                <form action="{{ route('photographer.availability.delete', $r->id) }}" method="POST" class="inline">
                  @csrf @method('DELETE')
                  <button type="submit" class="w-7 h-7 rounded-md text-rose-500 hover:bg-rose-50 transition"><i class="bi bi-trash text-xs"></i></button>
                </form>
              </div>
            @endforeach
          </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
