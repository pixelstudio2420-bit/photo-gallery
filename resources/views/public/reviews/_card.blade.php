{{-- Reusable review card component --}}
{{-- Usage: @include('public.reviews._card', ['review' => $review]) --}}
@php
  $currentUserId = auth()->id();
  $isHelpful = $review->isHelpfulBy($currentUserId);
  $isReported = $review->isReportedBy($currentUserId);
  $isOwner = $currentUserId === $review->user_id;
@endphp

<div class="bg-white border border-gray-100 rounded-2xl p-5 hover:border-gray-200 transition"
     data-review-id="{{ $review->id }}">

  {{-- Header --}}
  <div class="flex items-start justify-between mb-3">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500 text-white flex items-center justify-center font-semibold text-sm shrink-0">
        {{ mb_strtoupper(mb_substr($review->user->first_name ?? 'U', 0, 1, 'UTF-8'), 'UTF-8') }}
      </div>
      <div>
        <div class="flex items-center gap-2">
          <span class="font-semibold text-slate-800 text-sm">
            {{ trim(($review->user->first_name ?? '') . ' ' . mb_substr($review->user->last_name ?? '', 0, 1)) ?: 'Unknown' }}
          </span>
          @if($review->is_verified_purchase)
            <span class="inline-flex items-center text-xs text-emerald-600" title="ซื้อจริงจากเว็บไซต์">
              <i class="bi bi-patch-check-fill"></i>
              <span class="ml-0.5">ซื้อจริง</span>
            </span>
          @endif
        </div>
        <div class="flex items-center gap-2 mt-0.5">
          <span class="flex items-center text-amber-500">
            @for($i=1;$i<=5;$i++)
              <i class="bi bi-star{{ $i <= $review->rating ? '-fill' : '' }} text-xs"></i>
            @endfor
          </span>
          <span class="text-xs text-gray-400">{{ $review->created_at?->diffForHumans() }}</span>
        </div>
      </div>
    </div>

    {{-- Report dropdown --}}
    @auth
      @if(!$isOwner)
      <div x-data="{ reportOpen: false }" class="relative">
        <button type="button" @click="reportOpen = !reportOpen" @click.outside="reportOpen = false"
                class="w-8 h-8 rounded-lg text-gray-400 hover:bg-gray-50 hover:text-gray-600 flex items-center justify-center transition">
          <i class="bi bi-three-dots text-sm"></i>
        </button>
        <div x-show="reportOpen" x-cloak
             class="absolute right-0 top-full mt-1 w-40 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-10">
          <button type="button" onclick="openReportModal({{ $review->id }})"
                  class="w-full px-3 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2">
            <i class="bi bi-flag"></i> รายงานรีวิว
          </button>
        </div>
      </div>
      @endif
    @endauth
  </div>

  {{-- Event link --}}
  @if(!empty($showEvent) && $review->event)
  <div class="text-xs text-gray-500 mb-2">
    <i class="bi bi-calendar3 mr-1"></i>
    <a href="{{ route('events.show', $review->event->slug ?: $review->event->id) }}" class="hover:text-indigo-600">
      {{ $review->event->name }}
    </a>
  </div>
  @endif

  {{-- Comment --}}
  @if($review->comment)
  <p class="text-sm text-gray-700 leading-relaxed mb-3">{{ $review->comment }}</p>
  @endif

  {{-- Images (if any) --}}
  @if(!empty($review->images))
  <div class="flex gap-2 mb-3 flex-wrap">
    @foreach($review->images as $img)
      <img src="{{ asset('storage/' . $img) }}" alt="Review image"
           class="w-20 h-20 rounded-lg object-cover cursor-pointer hover:scale-105 transition">
    @endforeach
  </div>
  @endif

  {{-- Photographer reply --}}
  @if($review->photographer_reply)
  <div class="mt-3 p-3 bg-indigo-50 border-l-4 border-indigo-500 rounded-r-lg">
    <div class="flex items-center gap-2 mb-1">
      <i class="bi bi-camera text-indigo-600 text-xs"></i>
      <span class="text-xs font-semibold text-indigo-700">คำตอบจากช่างภาพ</span>
      <span class="text-xs text-gray-400">· {{ $review->photographer_reply_at?->diffForHumans() }}</span>
    </div>
    <p class="text-sm text-gray-700">{{ $review->photographer_reply }}</p>
  </div>
  @endif

  {{-- Admin reply --}}
  @if($review->admin_reply)
  <div class="mt-2 p-3 bg-gradient-to-r from-blue-50 to-indigo-50 border-l-4 border-blue-500 rounded-r-lg">
    <div class="flex items-center gap-2 mb-1">
      <i class="bi bi-shield-check text-blue-600 text-xs"></i>
      <span class="text-xs font-semibold text-blue-700">คำตอบจากทีมงาน</span>
      <span class="text-xs text-gray-400">· {{ $review->admin_reply_at?->diffForHumans() }}</span>
    </div>
    <p class="text-sm text-gray-700">{{ $review->admin_reply }}</p>
  </div>
  @endif

  {{-- Action bar --}}
  <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-50">
    <div class="flex items-center gap-3">
      @auth
        @if(!$isOwner)
        <button type="button" onclick="toggleHelpful({{ $review->id }}, this)"
                data-active="{{ $isHelpful ? 'true' : 'false' }}"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition
                       {{ $isHelpful ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-50 text-gray-600 hover:bg-indigo-50 hover:text-indigo-600' }}">
          <i class="bi bi-hand-thumbs-up{{ $isHelpful ? '-fill' : '' }}"></i>
          <span>เป็นประโยชน์</span>
          <span class="count font-semibold">({{ $review->helpful_count }})</span>
        </button>
        @else
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-500">
          <i class="bi bi-hand-thumbs-up"></i>
          <span>{{ $review->helpful_count }} คนพบว่ามีประโยชน์</span>
        </span>
        @endif
      @else
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-500">
          <i class="bi bi-hand-thumbs-up"></i>
          <span>{{ $review->helpful_count }}</span>
        </span>
      @endauth
    </div>
  </div>
</div>
