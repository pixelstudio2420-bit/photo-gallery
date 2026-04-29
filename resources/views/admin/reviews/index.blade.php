@extends('layouts.admin')

@section('title', 'จัดการรีวิว')

@section('content')
<div class="space-y-5">

  {{-- Header --}}
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-800 dark:text-gray-100">
        <i class="bi bi-star-fill text-amber-400 mr-2"></i>จัดการรีวิว
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">ตรวจสอบและบริหารจัดการรีวิวในระบบ</p>
    </div>
    @if($pendingReports > 0)
    <a href="{{ route('admin.reviews.reports') }}" class="px-4 py-2 bg-red-500 text-white rounded-xl text-sm font-medium hover:bg-red-600 transition flex items-center gap-2">
      <i class="bi bi-flag-fill"></i> มีรายงาน {{ $pendingReports }} รายการ
    </a>
    @endif
  </div>

  {{-- Stats Cards --}}
  <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4">
      <div class="text-xs text-gray-500 dark:text-gray-400">ทั้งหมด</div>
      <div class="text-2xl font-bold text-slate-800 dark:text-gray-100">{{ number_format($stats['total']) }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4">
      <div class="text-xs text-gray-500 dark:text-gray-400">อนุมัติแล้ว</div>
      <div class="text-2xl font-bold text-emerald-600">{{ number_format($stats['approved']) }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4">
      <div class="text-xs text-gray-500 dark:text-gray-400">รอตรวจ</div>
      <div class="text-2xl font-bold text-amber-600">{{ number_format($stats['pending']) }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4">
      <div class="text-xs text-gray-500 dark:text-gray-400">ซ่อน</div>
      <div class="text-2xl font-bold text-gray-600">{{ number_format($stats['hidden']) }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4">
      <div class="text-xs text-gray-500 dark:text-gray-400">ติดธง</div>
      <div class="text-2xl font-bold text-orange-600">{{ number_format($stats['flagged']) }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4">
      <div class="text-xs text-gray-500 dark:text-gray-400">ถูกรายงาน</div>
      <div class="text-2xl font-bold text-red-600">{{ number_format($stats['reported']) }}</div>
    </div>
    <div class="bg-gradient-to-br from-amber-50 to-yellow-50 dark:from-amber-500/10 dark:to-yellow-500/10 border border-amber-100 dark:border-amber-500/20 rounded-2xl p-4">
      <div class="text-xs text-amber-700 dark:text-amber-400">คะแนนเฉลี่ย</div>
      <div class="text-2xl font-bold text-amber-700 dark:text-amber-400">
        {{ number_format($stats['avg_rating'], 1) }}<span class="text-sm font-normal"> / 5.0</span>
      </div>
    </div>
  </div>

  {{-- Rating Distribution --}}
  @if($ratingStats['total'] > 0)
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-5">
    <div class="flex items-center justify-between mb-3">
      <h3 class="font-semibold text-slate-800 dark:text-gray-100 text-sm">การกระจายคะแนน</h3>
      <span class="text-xs text-gray-500 dark:text-gray-400">จากทั้งหมด {{ $ratingStats['total'] }} รีวิว</span>
    </div>
    <div class="space-y-2">
      @for($i = 5; $i >= 1; $i--)
      <div class="flex items-center gap-3">
        <div class="w-16 text-sm flex items-center gap-1 text-amber-500">
          {{ $i }} <i class="bi bi-star-fill text-xs"></i>
        </div>
        <div class="flex-1 h-2.5 bg-gray-100 dark:bg-slate-700 rounded-full overflow-hidden">
          <div class="h-full bg-gradient-to-r from-amber-400 to-amber-500 rounded-full"
               style="width: {{ $ratingStats['percentages'][$i] }}%"></div>
        </div>
        <div class="w-24 text-xs text-gray-500 dark:text-gray-400 text-right">
          {{ number_format($ratingStats['distribution'][$i]) }} ({{ $ratingStats['percentages'][$i] }}%)
        </div>
      </div>
      @endfor
    </div>
  </div>
  @endif

  {{-- Filter Bar --}}
  <form class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4 grid grid-cols-1 md:grid-cols-6 gap-3" method="GET">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="ค้นหาความคิดเห็นหรือชื่อ..."
           class="col-span-2 px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
    <select name="rating" class="px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
      <option value="">ทุกคะแนน</option>
      @for($r = 5; $r >= 1; $r--)
      <option value="{{ $r }}" {{ request('rating') == $r ? 'selected' : '' }}>{{ $r }} ดาว</option>
      @endfor
    </select>
    <select name="status" class="px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
      <option value="">ทุกสถานะ</option>
      <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>อนุมัติแล้ว</option>
      <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>รอตรวจ</option>
      <option value="hidden" {{ request('status') === 'hidden' ? 'selected' : '' }}>ซ่อน</option>
      <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>ปฏิเสธ</option>
    </select>
    <select name="visibility" class="px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
      <option value="">การแสดงผล</option>
      <option value="visible" {{ request('visibility') === 'visible' ? 'selected' : '' }}>แสดง</option>
      <option value="hidden" {{ request('visibility') === 'hidden' ? 'selected' : '' }}>ซ่อน</option>
    </select>
    <button type="submit" class="px-4 py-2 bg-indigo-500 text-white rounded-lg text-sm font-medium hover:bg-indigo-600 transition">
      <i class="bi bi-search mr-1"></i>ค้นหา
    </button>
  </form>

  {{-- Review Table --}}
  <form method="POST" action="{{ route('admin.reviews.bulk-action') }}" id="bulkForm">
    @csrf
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl overflow-hidden">

      {{-- Bulk Actions Bar --}}
      <div class="p-3 border-b border-gray-100 dark:border-white/5 flex flex-wrap items-center gap-3 text-sm">
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" id="selectAll" class="rounded border-gray-300">
          <span class="text-gray-600 dark:text-gray-400">เลือกทั้งหมด</span>
        </label>
        <div class="flex gap-2 flex-wrap">
          <button type="button" onclick="bulkAction('approve')" class="px-3 py-1.5 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 rounded-lg text-xs font-medium hover:bg-emerald-100 transition">
            <i class="bi bi-check2"></i> อนุมัติ
          </button>
          <button type="button" onclick="bulkAction('hide')" class="px-3 py-1.5 bg-gray-100 dark:bg-gray-500/10 text-gray-600 rounded-lg text-xs font-medium hover:bg-gray-200 transition">
            <i class="bi bi-eye-slash"></i> ซ่อน
          </button>
          <button type="button" onclick="bulkAction('reject')" class="px-3 py-1.5 bg-orange-50 dark:bg-orange-500/10 text-orange-600 rounded-lg text-xs font-medium hover:bg-orange-100 transition">
            <i class="bi bi-x-circle"></i> ปฏิเสธ
          </button>
          <button type="button" onclick="bulkAction('toggle_flag')" class="px-3 py-1.5 bg-amber-50 dark:bg-amber-500/10 text-amber-600 rounded-lg text-xs font-medium hover:bg-amber-100 transition">
            <i class="bi bi-flag"></i> สลับธง
          </button>
          <button type="button" onclick="bulkAction('delete')" class="px-3 py-1.5 bg-red-50 dark:bg-red-500/10 text-red-600 rounded-lg text-xs font-medium hover:bg-red-100 transition">
            <i class="bi bi-trash"></i> ลบ
          </button>
        </div>
      </div>

      {{-- Reviews Grid --}}
      <div class="divide-y divide-gray-100 dark:divide-white/5">
        @forelse($reviews as $r)
        <div class="p-4 hover:bg-gray-50 dark:hover:bg-white/[0.02] {{ $r->is_flagged ? 'bg-amber-50/50 dark:bg-amber-500/[0.05]' : '' }} {{ $r->report_count > 0 ? 'bg-red-50/50 dark:bg-red-500/[0.05]' : '' }}">
          <div class="flex items-start gap-3">
            <input type="checkbox" name="ids[]" value="{{ $r->id }}" class="mt-2 rounded border-gray-300 review-checkbox">

            {{-- User Avatar --}}
            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500 text-white flex items-center justify-center font-semibold text-sm shrink-0">
              {{ mb_strtoupper(mb_substr($r->user->first_name ?? 'U', 0, 1, 'UTF-8'), 'UTF-8') }}
            </div>

            {{-- Content --}}
            <div class="flex-1 min-w-0">
              <div class="flex flex-wrap items-center gap-2 mb-1">
                <span class="font-semibold text-slate-800 dark:text-gray-100 text-sm">
                  {{ trim(($r->user->first_name ?? '') . ' ' . ($r->user->last_name ?? '')) ?: 'Unknown' }}
                </span>
                <span class="text-xs text-gray-400">→</span>
                <span class="text-sm text-gray-600 dark:text-gray-400">
                  <i class="bi bi-camera text-xs mr-0.5"></i>{{ $r->photographer->first_name ?? 'N/A' }}
                </span>

                {{-- Rating --}}
                <span class="inline-flex items-center gap-0.5 text-amber-500 ml-2">
                  @for($i = 1; $i <= 5; $i++)
                    <i class="bi bi-star{{ $i <= $r->rating ? '-fill' : '' }} text-xs"></i>
                  @endfor
                </span>

                {{-- Status badges --}}
                @switch($r->status)
                  @case('approved')<span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-medium">อนุมัติ</span>@break
                  @case('pending')<span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 font-medium">รอตรวจ</span>@break
                  @case('hidden')<span class="text-[10px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 font-medium">ซ่อน</span>@break
                  @case('rejected')<span class="text-[10px] px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-medium">ปฏิเสธ</span>@break
                @endswitch

                @if($r->is_flagged)
                  <span class="text-[10px] px-2 py-0.5 rounded-full bg-orange-100 text-orange-700 font-medium">
                    <i class="bi bi-flag-fill text-[9px]"></i> ติดธง
                  </span>
                @endif
                @if($r->report_count > 0)
                  <span class="text-[10px] px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-medium">
                    <i class="bi bi-exclamation-triangle-fill text-[9px]"></i> {{ $r->report_count }}
                  </span>
                @endif
                @if($r->helpful_count > 0)
                  <span class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 font-medium">
                    👍 {{ $r->helpful_count }}
                  </span>
                @endif
              </div>

              <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $r->comment ?: '(ไม่มีข้อความ)' }}</p>

              @if($r->event)
              <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                <i class="bi bi-calendar3 mr-1"></i>{{ $r->event->name }}
              </div>
              @endif

              {{-- Photographer reply --}}
              @if($r->photographer_reply)
              <div class="mt-2 p-3 bg-blue-50 dark:bg-blue-500/10 border-l-4 border-blue-500 rounded-r-lg">
                <div class="text-xs font-semibold text-blue-700 dark:text-blue-400 mb-1">
                  <i class="bi bi-camera mr-1"></i>ช่างภาพตอบกลับ · {{ $r->photographer_reply_at?->diffForHumans() }}
                </div>
                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $r->photographer_reply }}</p>
              </div>
              @endif

              {{-- Admin reply --}}
              @if($r->admin_reply)
              <div class="mt-2 p-3 bg-indigo-50 dark:bg-indigo-500/10 border-l-4 border-indigo-500 rounded-r-lg">
                <div class="text-xs font-semibold text-indigo-700 dark:text-indigo-400 mb-1">
                  <i class="bi bi-shield-check mr-1"></i>Admin ตอบกลับ · {{ $r->admin_reply_at?->diffForHumans() }}
                </div>
                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $r->admin_reply }}</p>
              </div>
              @endif

              {{-- Meta --}}
              <div class="flex items-center gap-3 text-xs text-gray-400 dark:text-gray-500 mt-2">
                <span><i class="bi bi-clock mr-1"></i>{{ $r->created_at?->diffForHumans() }}</span>
                <span>{{ $r->created_at?->format('d/m/Y H:i') }}</span>
                @if($r->is_verified_purchase)
                  <span class="text-emerald-600"><i class="bi bi-patch-check-fill mr-0.5"></i>ซื้อจริง</span>
                @endif
              </div>
            </div>

            {{-- Actions --}}
            <div class="flex flex-col gap-1 shrink-0">
              @if($r->status !== 'approved')
              <form method="POST" action="{{ route('admin.reviews.approve', $r) }}" class="inline">
                @csrf
                <button type="submit" class="w-8 h-8 rounded-lg text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 flex items-center justify-center" title="อนุมัติ">
                  <i class="bi bi-check2 text-sm"></i>
                </button>
              </form>
              @endif
              @if($r->status !== 'hidden')
              <form method="POST" action="{{ route('admin.reviews.hide', $r) }}" class="inline">
                @csrf
                <button type="submit" class="w-8 h-8 rounded-lg text-gray-600 hover:bg-gray-100 dark:hover:bg-white/10 flex items-center justify-center" title="ซ่อน">
                  <i class="bi bi-eye-slash text-sm"></i>
                </button>
              </form>
              @endif
              <form method="POST" action="{{ route('admin.reviews.flag', $r) }}" class="inline">
                @csrf
                <button type="submit" class="w-8 h-8 rounded-lg {{ $r->is_flagged ? 'text-amber-600 bg-amber-50 dark:bg-amber-500/10' : 'text-gray-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 hover:text-amber-600' }} flex items-center justify-center" title="ติดธง">
                  <i class="bi bi-flag{{ $r->is_flagged ? '-fill' : '' }} text-sm"></i>
                </button>
              </form>
              <button type="button" onclick="openReply({{ $r->id }})" class="w-8 h-8 rounded-lg text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 flex items-center justify-center" title="ตอบกลับ">
                <i class="bi bi-reply text-sm"></i>
              </button>
              <form method="POST" action="{{ route('admin.reviews.destroy', $r) }}" class="inline" onsubmit="return confirm('ลบรีวิวนี้?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="w-8 h-8 rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10 flex items-center justify-center" title="ลบ">
                  <i class="bi bi-trash text-sm"></i>
                </button>
              </form>
            </div>
          </div>

          {{-- Reply form (hidden) --}}
          <div id="reply-{{ $r->id }}" class="hidden mt-3 pl-13">
            <form method="POST" action="{{ route('admin.reviews.reply', $r) }}">
              @csrf
              <textarea name="reply" rows="3" maxlength="2000" required placeholder="พิมพ์คำตอบ..."
                        class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200"></textarea>
              <div class="flex gap-2 mt-2">
                <button type="submit" class="px-4 py-1.5 bg-indigo-500 text-white rounded-lg text-sm font-medium hover:bg-indigo-600">ส่งคำตอบ</button>
                <button type="button" onclick="closeReply({{ $r->id }})" class="px-4 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-sm">ยกเลิก</button>
              </div>
            </form>
          </div>
        </div>
        @empty
        <div class="p-12 text-center">
          <i class="bi bi-star text-3xl text-gray-300"></i>
          <p class="text-gray-500 mt-2">ยังไม่มีรีวิว</p>
        </div>
        @endforelse
      </div>
    </div>
  </form>

  {{-- Pagination --}}
  @if($reviews->hasPages())
  <div class="flex justify-center">{{ $reviews->links() }}</div>
  @endif

</div>

@endsection

@push('scripts')
<script>
document.getElementById('selectAll').addEventListener('change', function() {
  document.querySelectorAll('.review-checkbox').forEach(cb => cb.checked = this.checked);
});

function bulkAction(action) {
  const checked = document.querySelectorAll('.review-checkbox:checked');
  if (checked.length === 0) return alert('กรุณาเลือกรายการ');
  const texts = { approve: 'อนุมัติ', hide: 'ซ่อน', reject: 'ปฏิเสธ', delete: 'ลบ', toggle_flag: 'สลับธง' };
  if (!confirm(`${texts[action]} ${checked.length} รีวิว?`)) return;
  const form = document.getElementById('bulkForm');
  let input = form.querySelector('input[name="action"]');
  if (!input) { input = document.createElement('input'); input.type = 'hidden'; input.name = 'action'; form.appendChild(input); }
  input.value = action;
  form.submit();
}

function openReply(id) { document.getElementById(`reply-${id}`).classList.remove('hidden'); }
function closeReply(id) { document.getElementById(`reply-${id}`).classList.add('hidden'); }
</script>
@endpush
