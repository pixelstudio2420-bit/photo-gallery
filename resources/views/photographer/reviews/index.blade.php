@extends('layouts.photographer')

@section('title', 'รีวิวของฉัน')

@section('content')
<div class="space-y-5">
  @include('photographer.partials.page-hero', [
    'icon'     => 'bi-star-fill',
    'eyebrow'  => 'การทำงาน',
    'title'    => 'รีวิวของฉัน',
    'subtitle' => 'ดูและตอบกลับรีวิวจากลูกค้าที่ซื้อรูปของคุณ',
  ])

  {{-- Stats Cards --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <div class="pg-card pg-card-padded pg-anim d1">
      <div class="text-xs text-gray-500 font-bold uppercase tracking-wider">รีวิวทั้งหมด</div>
      <div class="text-2xl font-bold text-slate-800 mt-1">{{ number_format($stats['total']) }}</div>
    </div>
    <div class="pg-card pg-card-padded pg-anim d2 relative overflow-hidden"
         style="background:linear-gradient(135deg, rgba(251,191,36,.10), rgba(245,158,11,.06)); border-color:rgba(245,158,11,.25);">
      <div class="text-xs text-amber-700 dark:text-amber-300 font-bold uppercase tracking-wider">คะแนนเฉลี่ย</div>
      <div class="text-2xl font-bold text-amber-700 dark:text-amber-300 mt-1">
        {{ number_format($stats['average'], 1) }}
        <span class="text-sm font-normal text-amber-600/80 dark:text-amber-300/70">/ 5.0</span>
      </div>
      <div class="mt-1 flex items-center gap-0.5 text-amber-500">
        @for($i=1;$i<=5;$i++)
          <i class="bi bi-star{{ $stats['average'] >= $i ? '-fill' : ($stats['average'] >= $i-0.5 ? '-half' : '') }} text-xs"></i>
        @endfor
      </div>
    </div>
    <div class="pg-card pg-card-padded pg-anim d3">
      <div class="text-xs text-gray-500 font-bold uppercase tracking-wider">รีวิว 5 ดาว</div>
      <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ number_format($stats['distribution'][5] ?? 0) }}</div>
      <div class="text-xs text-gray-400 mt-0.5">{{ $stats['percentages'][5] ?? 0 }}% ของทั้งหมด</div>
    </div>
    <div class="pg-card pg-card-padded pg-anim d4">
      <div class="text-xs text-gray-500 font-bold uppercase tracking-wider">ยังไม่ได้ตอบ</div>
      <div class="text-2xl font-bold mt-1 {{ ($stats['no_reply_count'] ?? 0) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
        {{ number_format($stats['no_reply_count'] ?? 0) }}
      </div>
    </div>
  </div>

  {{-- Filters --}}
  <form method="GET" class="pg-card pg-card-padded pg-anim d5 flex gap-3 flex-wrap items-center">
    <span class="text-xs text-gray-500 font-bold uppercase tracking-wider">กรอง:</span>
    <select name="rating" class="px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-800 text-gray-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-300">
      <option value="">ทุกคะแนน</option>
      @for($r = 5; $r >= 1; $r--)
      <option value="{{ $r }}" {{ request('rating') == $r ? 'selected' : '' }}>{{ $r }} ดาว</option>
      @endfor
    </select>
    <select name="reply" class="px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-800 text-gray-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-300">
      <option value="">ทั้งหมด</option>
      <option value="no_reply" {{ request('reply') === 'no_reply' ? 'selected' : '' }}>ยังไม่ตอบ</option>
      <option value="with_reply" {{ request('reply') === 'with_reply' ? 'selected' : '' }}>ตอบแล้ว</option>
    </select>
    <button type="submit" class="pg-btn-primary"><i class="bi bi-search"></i> ค้นหา</button>
  </form>

  {{-- Reviews --}}
  <div class="space-y-3">
    @forelse($reviews as $r)
    <div class="pg-card pg-card-padded pg-anim d1">
      <div class="flex items-start gap-3">
        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 text-white flex items-center justify-center font-semibold text-sm shrink-0 shadow-md">
          {{ mb_strtoupper(mb_substr($r->user->first_name ?? 'U', 0, 1, 'UTF-8'), 'UTF-8') }}
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 mb-1 flex-wrap">
            <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $r->user->first_name ?? 'Unknown' }}</span>
            <span class="text-amber-500">
              @for($i=1;$i<=5;$i++)<i class="bi bi-star{{ $i <= $r->rating ? '-fill' : '' }} text-sm"></i>@endfor
            </span>
            <span class="text-xs text-gray-400">· {{ $r->created_at?->diffForHumans() }}</span>
            @if($r->is_verified_purchase)
              <span class="pg-pill pg-pill--green"><i class="bi bi-patch-check-fill"></i> ซื้อจริง</span>
            @endif
          </div>
          @if($r->event)
          <div class="text-xs text-gray-500 mb-2">
            <i class="bi bi-calendar3 mr-1"></i>{{ $r->event->name }}
          </div>
          @endif
          <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $r->comment ?: '(ไม่มีข้อความ)' }}</p>

          @if($r->helpful_count > 0)
          <div class="text-xs text-gray-400 mt-2">👍 {{ $r->helpful_count }} คนพบว่ามีประโยชน์</div>
          @endif

          {{-- Your Reply --}}
          @if($r->photographer_reply)
          <div class="mt-3 p-3 rounded-r-lg border-l-4 border-indigo-500"
               style="background:rgba(99,102,241,.08);">
            <div class="flex items-center justify-between mb-1">
              <div class="text-xs font-semibold text-indigo-700 dark:text-indigo-300">
                <i class="bi bi-camera mr-1"></i>คำตอบของคุณ · {{ $r->photographer_reply_at?->diffForHumans() }}
              </div>
              <form method="POST" action="{{ route('photographer.reviews.reply.delete', $r) }}" onsubmit="return confirm('ลบคำตอบ?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-xs text-red-500 hover:underline">ลบ</button>
              </form>
            </div>
            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $r->photographer_reply }}</p>
          </div>
          @else
          {{-- Reply Form --}}
          <div x-data="{ showReply: false }" class="mt-3">
            <button x-show="!showReply" @click="showReply = true" class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 font-medium">
              <i class="bi bi-reply mr-1"></i>ตอบกลับรีวิวนี้
            </button>
            <form x-show="showReply" x-cloak method="POST" action="{{ route('photographer.reviews.reply', $r) }}">
              @csrf
              <textarea name="reply" rows="3" maxlength="2000" required placeholder="พิมพ์คำตอบอย่างสุภาพ..."
                        class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-800 text-gray-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-200"></textarea>
              <div class="flex gap-2 mt-2">
                <button type="submit" class="pg-btn-primary">
                  <i class="bi bi-send"></i> ส่งคำตอบ
                </button>
                <button type="button" @click="showReply = false" class="pg-btn-ghost">ยกเลิก</button>
              </div>
            </form>
          </div>
          @endif

          @if($r->admin_reply)
          <div class="mt-2 p-3 rounded-r-lg border-l-4 border-rose-500"
               style="background:rgba(239,68,68,.08);">
            <div class="text-xs font-semibold text-rose-700 dark:text-rose-300 mb-1"><i class="bi bi-shield-check mr-1"></i>Admin · {{ $r->admin_reply_at?->diffForHumans() }}</div>
            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $r->admin_reply }}</p>
          </div>
          @endif
        </div>
      </div>
    </div>
    @empty
    <div class="pg-card pg-anim d1">
      <div class="pg-empty">
        <div class="pg-empty-icon"><i class="bi bi-star"></i></div>
        <p class="font-medium">ยังไม่มีรีวิว</p>
        <p class="text-xs mt-1">รอลูกค้าซื้อรูปและรีวิวคุณ</p>
      </div>
    </div>
    @endforelse
  </div>

  {{-- Pagination --}}
  @if($reviews->hasPages())
  <div class="flex justify-center">{{ $reviews->links() }}</div>
  @endif
</div>
@endsection
