@extends('layouts.app')

@section('title', 'เขียนรีวิว')

@section('content')
<div class="max-w-2xl mx-auto px-4 md:px-6 py-6">

  {{-- Header --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <h1 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
      <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 text-white shadow-md">
        <i class="bi bi-star-fill"></i>
      </span>
      เขียนรีวิว
    </h1>
    <a href="{{ route('orders.index') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5 text-sm transition">
      <i class="bi bi-arrow-left"></i> กลับ
    </a>
  </div>

  <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-lg overflow-hidden">
    <div class="h-1.5 bg-gradient-to-r from-amber-400 via-orange-500 to-rose-500"></div>
    <div class="p-5 md:p-6">
      <p class="text-sm text-slate-500 dark:text-slate-400 mb-5">
        แบ่งปันประสบการณ์ของคุณกับเรา รีวิวจะช่วยให้ลูกค้าท่านอื่นตัดสินใจได้ง่ายขึ้น
      </p>

      <form action="{{ route('reviews.store') }}" method="POST" x-data="{ rating: {{ (int) old('rating', 0) }}, hovering: 0 }" class="space-y-5">
        @csrf

        {{-- Rating stars --}}
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
            คะแนนของคุณ <span class="text-rose-500">*</span>
          </label>
          <div class="flex items-center gap-1">
            @for($i = 1; $i <= 5; $i++)
              <button type="button"
                      @click="rating = {{ $i }}"
                      @mouseover="hovering = {{ $i }}"
                      @mouseleave="hovering = 0"
                      class="w-11 h-11 rounded-xl transition-all">
                <i class="bi text-2xl transition-colors"
                   :class="(hovering > 0 ? hovering : rating) >= {{ $i }} ? 'bi-star-fill text-amber-400' : 'bi-star text-slate-300 dark:text-slate-600'"></i>
              </button>
            @endfor
            <span class="ml-3 text-sm font-semibold" x-text="rating > 0 ? rating + '/5' : 'เลือกคะแนน'"
                  :class="rating > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400 dark:text-slate-500'"></span>
          </div>
          <input type="hidden" name="rating" x-model="rating" required>
          @error('rating')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror

          {{-- Rating labels --}}
          <div class="mt-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-900/30 text-sm" x-show="rating > 0" x-cloak>
            <template x-if="rating == 5"><span class="font-medium text-emerald-600 dark:text-emerald-400"><i class="bi bi-emoji-heart-eyes mr-1"></i>ดีมาก — ประทับใจสุดๆ</span></template>
            <template x-if="rating == 4"><span class="font-medium text-blue-600 dark:text-blue-400"><i class="bi bi-emoji-smile mr-1"></i>ดี — พอใจมาก</span></template>
            <template x-if="rating == 3"><span class="font-medium text-amber-600 dark:text-amber-400"><i class="bi bi-emoji-neutral mr-1"></i>ปานกลาง — โอเค</span></template>
            <template x-if="rating == 2"><span class="font-medium text-orange-600 dark:text-orange-400"><i class="bi bi-emoji-expressionless mr-1"></i>พอใช้ — มีข้อปรับปรุง</span></template>
            <template x-if="rating == 1"><span class="font-medium text-rose-600 dark:text-rose-400"><i class="bi bi-emoji-frown mr-1"></i>ควรปรับปรุง</span></template>
          </div>
        </div>

        {{-- Comment --}}
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">ความคิดเห็นของคุณ</label>
          <textarea name="comment" rows="5"
                    placeholder="แชร์ประสบการณ์ที่คุณได้รับ... อะไรดี อะไรต้องปรับปรุง"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition resize-none">{{ old('comment') }}</textarea>
          @error('comment')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- Submit --}}
        <div class="flex items-center gap-3 flex-wrap">
          <button type="submit"
                  :disabled="rating === 0"
                  class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold shadow-md hover:shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed">
            <i class="bi bi-send-fill"></i> ส่งรีวิว
          </button>
          <a href="{{ route('orders.index') }}"
             class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition">
            ยกเลิก
          </a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
