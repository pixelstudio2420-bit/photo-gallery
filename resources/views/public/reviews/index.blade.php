@extends('layouts.app')

@section('title', 'รีวิวทั้งหมด')

@section('content')
<div class="max-w-6xl mx-auto px-4 md:px-6 py-6">

  {{-- Header --}}
  <div class="rounded-3xl bg-gradient-to-br from-amber-400 via-orange-500 to-rose-500 dark:from-amber-600 dark:via-orange-700 dark:to-rose-700 shadow-xl p-6 md:p-8 mb-6 text-white relative overflow-hidden">
    <div class="absolute inset-0 opacity-30 pointer-events-none">
      <div class="absolute -top-16 -right-16 w-64 h-64 bg-amber-300/40 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-10 -left-10 w-56 h-56 bg-rose-400/30 rounded-full blur-3xl"></div>
    </div>
    <div class="relative flex items-center gap-4 flex-wrap">
      <div class="w-16 h-16 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center shadow-lg">
        <i class="bi bi-star-fill text-3xl text-yellow-300 drop-shadow"></i>
      </div>
      <div class="flex-1 min-w-0">
        <h1 class="text-2xl md:text-3xl font-bold tracking-tight">รีวิวจากลูกค้า</h1>
        <p class="text-white/90 text-sm md:text-base mt-1">
          {{ number_format($totalReviews) }} รีวิว
          @if($avgRating)
            <span class="mx-1">·</span>
            คะแนนเฉลี่ย <strong class="font-bold">{{ number_format($avgRating, 1) }}</strong>/5
          @endif
        </p>
      </div>
      @if($avgRating)
      <div class="flex items-center gap-1">
        @for($i = 1; $i <= 5; $i++)
          <i class="bi {{ $i <= round($avgRating) ? 'bi-star-fill' : 'bi-star' }} text-yellow-300 text-xl drop-shadow"></i>
        @endfor
      </div>
      @endif
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Left: Filters --}}
    <div class="lg:col-span-1 space-y-5">
      {{-- Rating Distribution --}}
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm p-5">
        <h3 class="font-semibold text-slate-900 dark:text-white mb-4 flex items-center gap-1.5">
          <i class="bi bi-bar-chart-line-fill text-amber-500"></i> การกระจายคะแนน
        </h3>
        @php $maxCount = max(array_values($ratingDistribution) ?: [1]); @endphp
        <div class="space-y-2">
          @foreach($ratingDistribution as $star => $count)
          <a href="{{ request()->fullUrlWithQuery(['rating' => $star, 'page' => null]) }}"
             class="flex items-center gap-3 group {{ request('rating') == $star ? 'ring-2 ring-indigo-500 ring-offset-1 dark:ring-offset-slate-800 rounded-lg p-1 -m-1' : '' }}">
            <span class="flex items-center gap-1 flex-shrink-0 text-sm font-medium {{ request('rating') == $star ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-700 dark:text-slate-300' }}">
              <i class="bi bi-star-fill text-amber-400 text-xs"></i> {{ $star }}
            </span>
            <div class="flex-1 h-2 bg-slate-100 dark:bg-white/5 rounded-full overflow-hidden">
              <div class="h-full bg-gradient-to-r from-amber-400 to-orange-500 rounded-full transition-all duration-500" style="width:{{ $maxCount > 0 ? round(($count / $maxCount) * 100) : 0 }}%;"></div>
            </div>
            <span class="flex-shrink-0 text-xs text-slate-500 dark:text-slate-400 font-medium min-w-[32px] text-right">{{ number_format($count) }}</span>
          </a>
          @endforeach
        </div>
        @if(request('rating'))
          <a href="{{ route('reviews.index') }}{{ request('photographer_id') ? '?photographer_id='.request('photographer_id') : '' }}"
             class="block text-center mt-4 px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-white/10 text-xs font-medium transition">
            <i class="bi bi-x"></i> ล้างตัวกรองคะแนน
          </a>
        @endif
      </div>

      {{-- Photographer Filter --}}
      @if($photographers->count() > 0)
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm p-5">
        <h3 class="font-semibold text-slate-900 dark:text-white mb-4 flex items-center gap-1.5">
          <i class="bi bi-camera-fill text-indigo-500"></i> ช่างภาพ
        </h3>
        <form method="GET" action="{{ route('reviews.index') }}">
          @if(request('rating'))<input type="hidden" name="rating" value="{{ request('rating') }}">@endif
          <select name="photographer_id" onchange="this.form.submit()"
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
            <option value="">ช่างภาพทั้งหมด</option>
            @foreach($photographers as $pg)
              <option value="{{ $pg->user_id }}" {{ request('photographer_id') == $pg->user_id ? 'selected' : '' }}>
                {{ $pg->display_name }}
              </option>
            @endforeach
          </select>
        </form>
        @if(request('photographer_id'))
          <a href="{{ route('reviews.index') }}{{ request('rating') ? '?rating='.request('rating') : '' }}"
             class="block text-center mt-3 px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-white/10 text-xs font-medium transition">
            <i class="bi bi-x"></i> ล้างตัวกรอง
          </a>
        @endif
      </div>
      @endif
    </div>

    {{-- Right: Reviews --}}
    <div class="lg:col-span-2">
      {{-- Active filters --}}
      @if(request('rating') || request('photographer_id'))
      <div class="mb-4 flex items-center gap-2 flex-wrap">
        <span class="text-xs text-slate-500 dark:text-slate-400">กรอง:</span>
        @if(request('rating'))
          <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 text-xs font-semibold">
            <i class="bi bi-star-fill"></i> {{ request('rating') }} ดาว
            <a href="{{ request()->fullUrlWithQuery(['rating' => null, 'page' => null]) }}" class="hover:opacity-70">×</a>
          </span>
        @endif
        @if(request('photographer_id'))
          @php $selectedPg = $photographers->firstWhere('user_id', request('photographer_id')); @endphp
          <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 text-xs font-semibold">
            <i class="bi bi-camera"></i> {{ $selectedPg?->display_name ?? 'ช่างภาพ' }}
            <a href="{{ request()->fullUrlWithQuery(['photographer_id' => null, 'page' => null]) }}" class="hover:opacity-70">×</a>
          </span>
        @endif
      </div>
      @endif

      {{-- Reviews --}}
      @forelse($reviews as $review)
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm p-5 mb-4 hover:shadow-md transition">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
          <div class="flex items-center gap-3">
            @php $reviewerName = $review->user ? trim($review->user->first_name . ' ' . $review->user->last_name) : 'ไม่ระบุชื่อ'; @endphp
            <x-avatar :src="$review->user->avatar ?? null"
                 :name="$reviewerName"
                 :user-id="$review->user_id"
                 size="md" />
            <div>
              <div class="font-semibold text-slate-900 dark:text-white">{{ $reviewerName }}</div>
              <div class="text-xs text-slate-500 dark:text-slate-400">
                <i class="bi bi-clock mr-1"></i>{{ $review->created_at->format('d M Y') }}
              </div>
            </div>
          </div>

          {{-- Stars --}}
          <div class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20">
            @for($i = 1; $i <= 5; $i++)
              <i class="bi {{ $i <= $review->rating ? 'bi-star-fill' : 'bi-star' }} text-amber-500 text-xs"></i>
            @endfor
            <span class="ml-1 text-xs font-bold text-amber-700 dark:text-amber-300">{{ $review->rating }}/5</span>
          </div>
        </div>

        {{-- Meta --}}
        <div class="flex flex-wrap items-center gap-3 text-xs mb-3">
          @if($review->photographerProfile)
            <a href="{{ route('photographers.show', $review->photographer_id) }}"
               class="inline-flex items-center gap-1 text-indigo-600 dark:text-indigo-400 hover:underline">
              <i class="bi bi-camera"></i> {{ $review->photographerProfile->display_name }}
            </a>
          @endif
          @if($review->event)
            <span class="inline-flex items-center gap-1 text-slate-500 dark:text-slate-400">
              <i class="bi bi-calendar-event"></i> {{ $review->event->name }}
            </span>
          @endif
        </div>

        {{-- Comment --}}
        @if($review->comment)
          <p class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed">{{ $review->comment }}</p>
        @else
          <p class="text-sm italic text-slate-400 dark:text-slate-500">ไม่มีความคิดเห็น</p>
        @endif

        {{-- Admin Reply --}}
        @if($review->admin_reply)
          <div class="mt-3 p-3 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 border-l-4 border-indigo-500 dark:border-indigo-400">
            <div class="text-xs font-semibold text-indigo-700 dark:text-indigo-300 mb-1 flex items-center gap-1">
              <i class="bi bi-reply-fill"></i> การตอบกลับจากทีมงาน
              @if($review->admin_reply_at)
                <span class="text-slate-500 dark:text-slate-400 font-normal ml-1">· {{ $review->admin_reply_at->format('d M Y') }}</span>
              @endif
            </div>
            <p class="text-xs text-slate-600 dark:text-slate-300">{{ $review->admin_reply }}</p>
          </div>
        @endif
      </div>
      @empty
      <div class="text-center py-16 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-gradient-to-br from-amber-100 to-orange-100 dark:from-amber-500/20 dark:to-orange-500/20 text-amber-500 dark:text-amber-400 mb-4">
          <i class="bi bi-chat-square-text text-3xl"></i>
        </div>
        <p class="text-sm text-slate-500 dark:text-slate-400">ยังไม่มีรีวิว</p>
      </div>
      @endforelse

      @if($reviews->hasPages())
        <div class="mt-6 flex justify-center">
          {{ $reviews->withQueryString()->links() }}
        </div>
      @endif
    </div>
  </div>
</div>
@endsection
