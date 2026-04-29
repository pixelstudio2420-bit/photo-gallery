@extends('layouts.admin')

@section('title', 'ภาพ #' . $photo->id)

@section('content')
@php
  $statusColor = match($photo->moderation_status) {
    'flagged'  => 'amber',
    'rejected' => 'red',
    'approved' => 'emerald',
    'skipped'  => 'gray',
    default    => 'blue',
  };
  $statusLabel = match($photo->moderation_status) {
    'flagged' => 'ติดธง — รอแอดมินตัดสิน',
    'rejected' => 'ถูกปฏิเสธ',
    'approved' => 'อนุมัติแล้ว',
    'skipped' => 'ยกเว้น',
    'pending' => 'รอการสแกน',
    default => $photo->moderation_status,
  };
@endphp

<div class="space-y-5">

  {{-- Back + Header --}}
  <div class="flex items-center gap-3">
    <a href="{{ url()->previous() }}" class="text-gray-500 hover:text-indigo-500">
      <i class="bi bi-arrow-left text-xl"></i>
    </a>
    <h1 class="text-xl font-bold text-slate-800 dark:text-gray-100">
      ตรวจสอบภาพ #{{ $photo->id }}
    </h1>
    <span class="ml-auto px-3 py-1 rounded-lg text-xs font-semibold
                 bg-{{ $statusColor }}-100 dark:bg-{{ $statusColor }}-500/20
                 text-{{ $statusColor }}-700 dark:text-{{ $statusColor }}-300">
      {{ $statusLabel }}
    </span>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- Preview (span 2) --}}
    <div class="lg:col-span-2 bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl overflow-hidden">
      <div class="relative bg-gray-50 dark:bg-slate-900" x-data="{ blur: {{ in_array($photo->moderation_status, ['flagged', 'rejected']) ? 'true' : 'false' }} }">
        @if($photo->watermarked_url)
        <img src="{{ $photo->watermarked_url }}" alt="Photo #{{ $photo->id }}"
             class="w-full max-h-[70vh] object-contain transition-all duration-300"
             :class="blur ? 'blur-3xl' : ''">
        @else
        <div class="aspect-video flex items-center justify-center text-gray-300 dark:text-gray-600">
          <i class="bi bi-image text-6xl"></i>
        </div>
        @endif
        <button @click="blur = !blur" type="button"
                class="absolute top-3 right-3 px-3 py-1.5 rounded-lg bg-black/70 text-white text-xs font-medium hover:bg-black/90">
          <i class="bi" :class="blur ? 'bi-eye' : 'bi-eye-slash'"></i>
          <span x-text="blur ? 'แสดงภาพ' : 'เบลอภาพ'"></span>
        </button>
      </div>

      <div class="p-4 space-y-2 text-sm">
        <div class="flex justify-between">
          <span class="text-gray-500 dark:text-gray-400">Event</span>
          <a href="{{ route('admin.events.show', optional($photo->event)->id ?? $photo->event_id) }}"
             class="text-indigo-500 hover:underline font-medium">
            {{ optional($photo->event)->name ?? 'Event #' . $photo->event_id }}
          </a>
        </div>
        <div class="flex justify-between">
          <span class="text-gray-500 dark:text-gray-400">ไฟล์</span>
          <span class="font-mono text-xs text-slate-700 dark:text-gray-200">
            {{ $photo->original_filename ?? $photo->filename }}
          </span>
        </div>
        @if($photo->uploader)
        <div class="flex justify-between">
          <span class="text-gray-500 dark:text-gray-400">ผู้อัปโหลด</span>
          <span class="text-slate-700 dark:text-gray-200">
            {{ trim(($photo->uploader->first_name ?? '') . ' ' . ($photo->uploader->last_name ?? '')) ?: $photo->uploader->email }}
          </span>
        </div>
        @endif
        <div class="flex justify-between">
          <span class="text-gray-500 dark:text-gray-400">อัปโหลดเมื่อ</span>
          <span class="text-slate-700 dark:text-gray-200">{{ $photo->created_at?->format('d/m/Y H:i') }}</span>
        </div>
      </div>
    </div>

    {{-- AI Decision + Actions (right column) --}}
    <div class="space-y-4">

      {{-- AI Decision --}}
      <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-5">
        <h3 class="text-sm font-bold text-slate-700 dark:text-gray-200 mb-3 flex items-center gap-2">
          <i class="bi bi-robot text-indigo-500"></i> ผลการสแกน AI
        </h3>

        @if($photo->moderation_score !== null)
        <div class="mb-3">
          <div class="flex items-center justify-between mb-1">
            <span class="text-xs text-gray-500 dark:text-gray-400">คะแนนความเสี่ยงสูงสุด</span>
            <span class="text-lg font-bold text-{{ $statusColor }}-600 dark:text-{{ $statusColor }}-400">
              {{ number_format($photo->moderation_score, 1) }}%
            </span>
          </div>
          <div class="h-2 bg-gray-100 dark:bg-slate-700 rounded-full overflow-hidden">
            <div class="h-full bg-gradient-to-r from-emerald-400 via-amber-400 to-red-500 rounded-full"
                 style="width: {{ min(100, $photo->moderation_score) }}%"></div>
          </div>
        </div>
        @endif

        @if(!empty($photo->moderation_labels))
        <div class="mt-4">
          <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">Label ที่ตรวจพบ</div>
          <div class="space-y-1.5">
            @foreach($photo->moderation_labels as $label)
              @php
                $name = $label['Name'] ?? '';
                $parent = $label['ParentName'] ?? '';
                $conf = $label['Confidence'] ?? 0;
                if (empty($name)) continue;
              @endphp
              <div class="flex items-center justify-between p-2 rounded-lg bg-gray-50 dark:bg-white/5 text-xs">
                <div>
                  <div class="font-medium text-slate-700 dark:text-gray-200">{{ $name }}</div>
                  @if($parent)
                  <div class="text-gray-500 dark:text-gray-400 text-[0.7rem]">{{ $parent }}</div>
                  @endif
                </div>
                <span class="font-mono font-semibold text-{{ $conf >= 90 ? 'red' : ($conf >= 50 ? 'amber' : 'emerald') }}-600">
                  {{ number_format((float) $conf, 1) }}%
                </span>
              </div>
            @endforeach
          </div>
        </div>
        @else
        <p class="text-xs text-gray-500 dark:text-gray-400 italic">ไม่มีข้อมูลการสแกน (อาจยังไม่ได้สแกนหรือถูกยกเว้น)</p>
        @endif

        @if($photo->moderation_reviewed_at)
        <div class="mt-4 pt-4 border-t border-gray-100 dark:border-white/5 text-xs text-gray-500 dark:text-gray-400">
          <i class="bi bi-person-check"></i>
          แอดมินตัดสินเมื่อ {{ $photo->moderation_reviewed_at->format('d/m/Y H:i') }}
          @if($photo->moderation_reject_reason)
          <div class="mt-1 p-2 rounded bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-300">
            เหตุผล: {{ $photo->moderation_reject_reason }}
          </div>
          @endif
        </div>
        @endif
      </div>

      {{-- Action Buttons --}}
      <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-5 space-y-2">
        <h3 class="text-sm font-bold text-slate-700 dark:text-gray-200 mb-2">ดำเนินการ</h3>

        @if(in_array($photo->moderation_status, ['flagged', 'pending', 'rejected']))
        <form method="POST" action="{{ route('admin.moderation.approve', $photo->id) }}">
          @csrf
          <button type="submit" class="w-full px-4 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg font-medium flex items-center justify-center gap-2">
            <i class="bi bi-check-circle"></i> อนุมัติภาพ
          </button>
        </form>
        @endif

        @if(in_array($photo->moderation_status, ['flagged', 'pending', 'approved', 'skipped']))
        <form method="POST" action="{{ route('admin.moderation.reject', $photo->id) }}" x-data="{ show: false }" @submit="if (!confirm('ปฏิเสธภาพนี้?')) $event.preventDefault()">
          @csrf
          <button @click.prevent="show = !show" type="button"
                  class="w-full px-4 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-lg font-medium flex items-center justify-center gap-2">
            <i class="bi bi-x-octagon"></i> ปฏิเสธภาพ
          </button>
          <div x-show="show" x-collapse class="mt-2 space-y-2">
            <textarea name="reason" rows="3" placeholder="เหตุผล (ตัวเลือก — ส่งให้ช่างภาพทางอีเมล)"
                      class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 rounded-lg text-sm"></textarea>
            <button type="submit" class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-semibold">
              ยืนยันการปฏิเสธ
            </button>
          </div>
        </form>
        @endif

        @if($photo->moderation_status !== 'skipped')
        <form method="POST" action="{{ route('admin.moderation.skip', $photo->id) }}">
          @csrf
          <button type="submit" class="w-full px-4 py-2.5 bg-gray-200 dark:bg-white/5 hover:bg-gray-300 dark:hover:bg-white/10 text-gray-700 dark:text-gray-200 rounded-lg font-medium flex items-center justify-center gap-2"
                  onclick="return confirm('ยกเว้นภาพนี้จากการตรวจสอบ?')">
            <i class="bi bi-slash-circle"></i> ยกเว้น
          </button>
        </form>
        @endif

        <form method="POST" action="{{ route('admin.moderation.rescan', $photo->id) }}">
          @csrf
          <button type="submit" class="w-full px-4 py-2.5 bg-indigo-100 dark:bg-indigo-500/10 hover:bg-indigo-200 dark:hover:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 rounded-lg font-medium flex items-center justify-center gap-2">
            <i class="bi bi-arrow-repeat"></i> สแกนใหม่
          </button>
        </form>
      </div>

    </div>
  </div>

</div>
@endsection
