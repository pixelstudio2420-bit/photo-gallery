{{--
  Reviews section — can be embedded in event pages, photographer profile, etc.
  Required: $reviewStats, $reviewsList (paginated or collection)
  Optional: $showEvent (bool), $showEventFilter (bool)
--}}

<div x-data="{ reportModalOpen: false, reportReviewId: null, reportReason: '', reportDesc: '' }" class="space-y-4">

  {{-- Stats Summary --}}
  @if(($reviewStats['total'] ?? 0) > 0)
  <div class="bg-white border border-gray-100 rounded-2xl p-5 mb-5">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 items-center">
      {{-- Average --}}
      <div class="text-center md:text-left md:border-r md:pr-5">
        <div class="text-5xl font-bold text-slate-800">{{ number_format($reviewStats['average'], 1) }}</div>
        <div class="flex justify-center md:justify-start items-center gap-0.5 text-amber-500 mt-1">
          @for($i=1;$i<=5;$i++)
            <i class="bi bi-star{{ $reviewStats['average'] >= $i ? '-fill' : ($reviewStats['average'] >= $i-0.5 ? '-half' : '') }}"></i>
          @endfor
        </div>
        <div class="text-sm text-gray-500 mt-1">จาก {{ number_format($reviewStats['total']) }} รีวิว</div>
      </div>

      {{-- Distribution --}}
      <div class="md:col-span-2 space-y-1.5">
        @for($i = 5; $i >= 1; $i--)
        <div class="flex items-center gap-3">
          <div class="w-12 text-sm text-gray-600 flex items-center gap-1">
            {{ $i }} <i class="bi bi-star-fill text-amber-400 text-xs"></i>
          </div>
          <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full bg-gradient-to-r from-amber-400 to-amber-500 rounded-full transition-all"
                 style="width: {{ $reviewStats['percentages'][$i] ?? 0 }}%"></div>
          </div>
          <div class="w-20 text-xs text-gray-500 text-right">
            {{ number_format($reviewStats['distribution'][$i] ?? 0) }} ({{ $reviewStats['percentages'][$i] ?? 0 }}%)
          </div>
        </div>
        @endfor
      </div>
    </div>
  </div>
  @endif

  {{-- Reviews List --}}
  <div class="space-y-3">
    @forelse($reviewsList as $review)
      @include('public.reviews._card', ['review' => $review, 'showEvent' => $showEvent ?? false])
    @empty
    <div class="bg-white border border-gray-100 rounded-2xl p-12 text-center">
      <i class="bi bi-chat-square-text text-4xl text-gray-300"></i>
      <p class="text-gray-500 mt-2">ยังไม่มีรีวิว</p>
      <p class="text-sm text-gray-400 mt-1">เป็นคนแรกที่เขียนรีวิว!</p>
    </div>
    @endforelse
  </div>

  {{-- Pagination --}}
  @if(method_exists($reviewsList, 'hasPages') && $reviewsList->hasPages())
  <div class="flex justify-center mt-4">{{ $reviewsList->links() }}</div>
  @endif

  {{-- Report Modal --}}
  <div x-show="reportModalOpen" x-cloak @click.self="reportModalOpen = false"
       class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6" @click.stop>
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-bold text-slate-800">
          <i class="bi bi-flag-fill text-red-500 mr-2"></i>รายงานรีวิว
        </h3>
        <button @click="reportModalOpen = false" class="text-gray-400 hover:text-gray-600">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <p class="text-sm text-gray-600 mb-4">ช่วยเราตรวจสอบโดยแจ้งเหตุผลที่คุณคิดว่ารีวิวนี้ไม่เหมาะสม</p>

      <div class="space-y-2 mb-4">
        @foreach(\App\Models\ReviewReport::REASONS as $key => $label)
        <label class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 cursor-pointer">
          <input type="radio" x-model="reportReason" value="{{ $key }}" class="text-indigo-600">
          <span class="text-sm text-gray-700">{{ $label }}</span>
        </label>
        @endforeach
      </div>

      <textarea x-model="reportDesc" placeholder="รายละเอียดเพิ่มเติม (ไม่บังคับ)" rows="3" maxlength="500"
                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-200"></textarea>

      <div class="flex gap-2 mt-4">
        <button @click="reportModalOpen = false"
                class="flex-1 px-4 py-2 border border-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-50">
          ยกเลิก
        </button>
        <button @click="submitReport()"
                :disabled="!reportReason"
                :class="!reportReason ? 'opacity-50 cursor-not-allowed' : ''"
                class="flex-1 px-4 py-2 bg-red-500 text-white rounded-lg font-medium hover:bg-red-600">
          ส่งรายงาน
        </button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
async function toggleHelpful(reviewId, btn) {
  @auth
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  try {
    const res = await fetch(`/reviews/${reviewId}/helpful`, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
    });
    const data = await res.json();
    if (data.success) {
      const isActive = data.is_helpful;
      btn.dataset.active = isActive;
      btn.querySelector('.count').textContent = `(${data.helpful_count})`;
      btn.querySelector('i').className = `bi bi-hand-thumbs-up${isActive ? '-fill' : ''}`;
      btn.className = `inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition ${isActive ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-50 text-gray-600 hover:bg-indigo-50 hover:text-indigo-600'}`;
    } else {
      alert(data.error || 'เกิดข้อผิดพลาด');
    }
  } catch (e) {
    alert('เกิดข้อผิดพลาด');
  }
  @else
  window.location.href = '{{ route("auth.login") }}';
  @endauth
}

function openReportModal(reviewId) {
  @auth
  const component = document.querySelector('[x-data*="reportModalOpen"]')?._x_dataStack?.[0];
  if (component) {
    component.reportModalOpen = true;
    component.reportReviewId = reviewId;
    component.reportReason = '';
    component.reportDesc = '';
  }
  @else
  window.location.href = '{{ route("auth.login") }}';
  @endauth
}

// Submit report (Alpine.js-accessible function)
window.submitReport = async function() {
  const component = document.querySelector('[x-data*="reportModalOpen"]')?._x_dataStack?.[0];
  if (!component || !component.reportReason) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  try {
    const res = await fetch(`/reviews/${component.reportReviewId}/report`, {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ reason: component.reportReason, description: component.reportDesc }),
    });
    const data = await res.json();
    if (data.success) {
      alert('ส่งรายงานเรียบร้อย ทีมงานจะตรวจสอบโดยเร็ว');
      component.reportModalOpen = false;
    } else {
      alert(data.error || 'เกิดข้อผิดพลาด');
    }
  } catch (e) {
    alert('เกิดข้อผิดพลาด');
  }
};
</script>
@endpush
