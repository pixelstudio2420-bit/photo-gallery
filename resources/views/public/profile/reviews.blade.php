@extends('layouts.app')

@section('title', 'รีวิวของฉัน')

{{-- =======================================================================
     PROFILE · REVIEWS
     -------------------------------------------------------------------
     Review cards with star ratings, visibility badge, and admin replies.
     Design matches the profile dashboard.
     ====================================================================== --}}
@section('content')
@php
  // Aggregate review stats (across all pages — cheap: only counts)
  $userId = auth()->id();
  $totalReviews    = \App\Models\Review::where('user_id', $userId)->count();
  $avgRating       = round(\App\Models\Review::where('user_id', $userId)->avg('rating') ?? 0, 1);
  $publishedCount  = \App\Models\Review::where('user_id', $userId)->where('is_visible', true)->count();
  $pendingCount    = $totalReviews - $publishedCount;
@endphp

<div class="max-w-6xl mx-auto px-4 md:px-6 py-6">

  {{-- ───────────── Header ───────────── --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h1 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 text-white shadow-md">
          <i class="bi bi-star-fill"></i>
        </span>
        รีวิวของฉัน
      </h1>
      @if($totalReviews > 0)
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 ml-1">
          ทั้งหมด <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $totalReviews }}</span> รีวิว
          · คะแนนเฉลี่ย <span class="font-semibold text-amber-600 dark:text-amber-400">{{ number_format($avgRating, 1) }}</span>
          <i class="bi bi-star-fill text-amber-500 text-[10px]"></i>
        </p>
      @endif
    </div>
  </div>

  {{-- ───────────── Tab Navigation ───────────── --}}
  <div class="mb-6 flex items-center gap-1 overflow-x-auto pb-1 border-b border-slate-200 dark:border-white/10">
    @foreach([
      ['route' => route('profile'),           'label' => 'ภาพรวม',      'icon' => 'bi-grid',     'active' => false],
      ['route' => route('profile.orders'),    'label' => 'คำสั่งซื้อ',   'icon' => 'bi-receipt',  'active' => false],
      ['route' => route('profile.downloads'), 'label' => 'ดาวน์โหลด', 'icon' => 'bi-download', 'active' => false],
      ['route' => route('profile.reviews'),   'label' => 'รีวิว',        'icon' => 'bi-star',     'active' => true],
      ['route' => route('wishlist.index'),    'label' => 'รายการโปรด','icon' => 'bi-heart',    'active' => false],
      ['route' => route('profile.referrals'), 'label' => 'แนะนำเพื่อน', 'icon' => 'bi-people-fill','active' => false],
    ] as $tab)
      <a href="{{ $tab['route'] }}"
         class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 whitespace-nowrap transition
            {{ $tab['active']
                ? 'border-amber-500 text-amber-600 dark:text-amber-400'
                : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300' }}">
        <i class="bi {{ $tab['icon'] }}"></i> {{ $tab['label'] }}
      </a>
    @endforeach
  </div>

  @if($totalReviews > 0)
    {{-- Quick stats strip --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 text-white flex items-center justify-center shadow-md flex-shrink-0">
            <i class="bi bi-star-fill"></i>
          </div>
          <div class="min-w-0">
            <div class="text-xl font-bold text-slate-900 dark:text-white leading-none">{{ number_format($avgRating, 1) }}</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">คะแนนเฉลี่ย</div>
          </div>
        </div>
      </div>
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center shadow-md flex-shrink-0">
            <i class="bi bi-chat-square-text-fill"></i>
          </div>
          <div class="min-w-0">
            <div class="text-xl font-bold text-slate-900 dark:text-white leading-none">{{ $totalReviews }}</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">รีวิวทั้งหมด</div>
          </div>
        </div>
      </div>
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-500 text-white flex items-center justify-center shadow-md flex-shrink-0">
            <i class="bi bi-check2-circle"></i>
          </div>
          <div class="min-w-0">
            <div class="text-xl font-bold text-slate-900 dark:text-white leading-none">{{ $publishedCount }}</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">เผยแพร่แล้ว</div>
          </div>
        </div>
      </div>
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm p-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-slate-500 to-slate-700 text-white flex items-center justify-center shadow-md flex-shrink-0">
            <i class="bi bi-hourglass-split"></i>
          </div>
          <div class="min-w-0">
            <div class="text-xl font-bold text-slate-900 dark:text-white leading-none">{{ $pendingCount }}</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">รออนุมัติ</div>
          </div>
        </div>
      </div>
    </div>
  @endif

  {{-- ───────────── Reviews List ───────────── --}}
  @if($reviews->isEmpty())
    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm text-center py-16 px-6">
      <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-gradient-to-br from-amber-100 to-orange-100 dark:from-amber-500/20 dark:to-orange-500/20 text-amber-500 dark:text-amber-400 mb-4">
        <i class="bi bi-star text-3xl"></i>
      </div>
      <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-1">ยังไม่มีรีวิว</h3>
      <p class="text-sm text-slate-500 dark:text-slate-400 mb-5">
        คุณสามารถเขียนรีวิวได้หลังจากชำระเงินและดาวน์โหลดรูปภาพสำเร็จ
      </p>
      <a href="{{ route('profile.orders', ['status' => 'paid']) }}"
         class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-gradient-to-br from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white text-sm font-semibold shadow-md transition">
        <i class="bi bi-receipt"></i> ดูคำสั่งซื้อของฉัน
      </a>
    </div>
  @else
    <div class="space-y-3">
      @foreach($reviews as $review)
        @php
          $stars = max(1, min(5, (int) $review->rating));
        @endphp
        <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm hover:shadow-md transition overflow-hidden">
          <div class="p-5">
            <div class="flex items-start gap-3 mb-3">
              <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 text-white flex items-center justify-center shadow-md flex-shrink-0">
                <i class="bi bi-star-fill"></i>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2 flex-wrap">
                  <h6 class="font-semibold text-slate-900 dark:text-white truncate">
                    @if($review->event)
                      <a href="{{ route('events.show', $review->event->id) }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                        {{ $review->event->name }}
                      </a>
                    @else
                      อีเวนต์
                    @endif
                  </h6>
                  <span class="text-xs text-slate-500 dark:text-slate-400 flex-shrink-0">
                    <i class="bi bi-clock mr-0.5"></i>{{ $review->created_at->diffForHumans() }}
                  </span>
                </div>

                {{-- Stars + rating + visibility --}}
                <div class="flex items-center gap-2 flex-wrap mt-1.5">
                  <div class="inline-flex items-center gap-0.5">
                    @for($i = 1; $i <= 5; $i++)
                      @if($i <= $stars)
                        <i class="bi bi-star-fill text-amber-500 text-sm"></i>
                      @else
                        <i class="bi bi-star text-slate-300 dark:text-slate-600 text-sm"></i>
                      @endif
                    @endfor
                    <span class="ml-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300">{{ $stars }}.0</span>
                  </div>

                  @if($review->is_visible)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-[10px] font-semibold">
                      <i class="bi bi-eye-fill"></i> เผยแพร่แล้ว
                    </span>
                  @else
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 text-[10px] font-semibold">
                      <i class="bi bi-hourglass-split"></i> รออนุมัติ
                    </span>
                  @endif
                </div>
              </div>
            </div>

            {{-- Comment --}}
            @if($review->comment)
              <div class="pl-14">
                <p class="text-sm text-slate-700 dark:text-slate-200 leading-relaxed whitespace-pre-line">{{ $review->comment }}</p>
              </div>
            @else
              <div class="pl-14">
                <p class="text-xs text-slate-400 dark:text-slate-500 italic">— ไม่มีข้อความรีวิว —</p>
              </div>
            @endif

            {{-- Admin reply --}}
            @if($review->admin_reply)
              <div class="mt-4 ml-14 p-4 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 border-l-4 border-indigo-500 dark:border-indigo-400">
                <div class="flex items-center gap-2 mb-1.5">
                  <span class="inline-flex items-center justify-center w-6 h-6 rounded-lg bg-indigo-500 text-white">
                    <i class="bi bi-reply-fill text-[11px]"></i>
                  </span>
                  <span class="text-xs font-semibold text-indigo-700 dark:text-indigo-300">ตอบกลับจากทีมงาน</span>
                  @if($review->admin_reply_at)
                    <span class="text-[10px] text-slate-500 dark:text-slate-400">· {{ $review->admin_reply_at->diffForHumans() }}</span>
                  @endif
                </div>
                <p class="text-sm text-slate-700 dark:text-slate-200 leading-relaxed whitespace-pre-line">{{ $review->admin_reply }}</p>
              </div>
            @endif

            {{-- Footer: precise date --}}
            <div class="mt-3 pt-3 border-t border-slate-100 dark:border-white/5 flex items-center gap-3 text-[11px] text-slate-400 dark:text-slate-500 pl-14">
              <span><i class="bi bi-calendar3 mr-1"></i>{{ $review->created_at->format('d/m/Y H:i') }}</span>
              @if($review->updated_at && $review->updated_at->ne($review->created_at))
                <span><i class="bi bi-pencil mr-1"></i>แก้ไข {{ $review->updated_at->diffForHumans() }}</span>
              @endif
            </div>
          </div>
        </div>
      @endforeach
    </div>

    @if($reviews->hasPages())
      <div class="mt-6 flex justify-center">
        {{ $reviews->withQueryString()->links() }}
      </div>
    @endif
  @endif
</div>
@endsection
