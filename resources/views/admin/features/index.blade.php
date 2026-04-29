@extends('layouts.admin')
@section('title', 'Feature Flags')

@section('content')
<div class="flex items-center justify-between mb-4">
  <h4 class="font-bold tracking-tight flex items-center gap-2">
    <i class="bi bi-toggles text-indigo-500"></i> Feature Flags
    <span class="text-xs font-normal text-gray-400 ml-2">/ เปิด/ปิดฟีเจอร์ทั่วทั้งระบบ</span>
  </h4>
  <a href="{{ route('admin.subscriptions.plans') }}" class="px-3 py-1.5 bg-slate-600 hover:bg-slate-700 text-white rounded-lg text-sm">
    <i class="bi bi-boxes mr-1"></i>แก้ไขแพ็กเกจ
  </a>
</div>

@if(session('success'))
  <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm px-4 py-3">
    <i class="bi bi-check-circle-fill mr-1.5"></i>{{ session('success') }}
  </div>
@endif

<div class="rounded-xl border border-amber-200 bg-amber-50 text-amber-900 text-sm p-4 mb-5">
  <p class="font-semibold mb-1"><i class="bi bi-info-circle-fill mr-1.5"></i>วิธีใช้งาน</p>
  <p class="text-amber-800">
    Feature flag ที่ปิดอยู่ที่นี่จะ<strong>บล็อกฟีเจอร์ทั่วทั้งระบบ</strong> แม้ว่าแผนของช่างภาพจะมีสิทธิ์เข้าถึง —
    ใช้สำหรับ kill switch เวลา service ขัดข้อง (เช่น AWS down → ปิด face_search ชั่วคราว)
    หรือเปิด/ปิดฟีเจอร์ระดับโลกโดยไม่ต้องไปแก้ทุกแพ็กเกจ
  </p>
</div>

<form method="POST" action="{{ route('admin.features.update') }}">
  @csrf

  @foreach($grouped as $group => $features)
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden mb-5">
      <div class="px-5 py-3 border-b border-gray-100 dark:border-white/5 bg-gray-50 dark:bg-slate-900/40">
        <h6 class="font-semibold text-gray-900 dark:text-gray-100 text-sm">
          {{ $groupLabels[$group] ?? $group }}
        </h6>
      </div>
      <div class="divide-y divide-gray-100 dark:divide-white/5">
        @foreach($features as $f)
          <label class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 dark:hover:bg-slate-900/30 cursor-pointer">
            <div>
              <p class="font-medium text-gray-900 dark:text-gray-100 text-sm">{{ $f['label'] }}</p>
              <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $f['key'] }}</p>
            </div>
            <div class="flex items-center gap-3">
              <span class="text-xs font-medium {{ $f['on'] ? 'text-emerald-600' : 'text-gray-400' }}">
                {{ $f['on'] ? 'เปิด' : 'ปิด' }}
              </span>
              {{-- Tailwind toggle switch --}}
              <input type="checkbox"
                     name="flags[]"
                     value="{{ $f['key'] }}"
                     @checked($f['on'])
                     class="sr-only peer"
                     id="flag-{{ $f['key'] }}">
              <span onclick="document.getElementById('flag-{{ $f['key'] }}').click(); event.preventDefault();"
                    class="inline-flex h-6 w-11 items-center rounded-full {{ $f['on'] ? 'bg-emerald-500' : 'bg-gray-300' }} transition cursor-pointer relative">
                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition {{ $f['on'] ? 'translate-x-6' : 'translate-x-1' }}"></span>
              </span>
            </div>
          </label>
        @endforeach
      </div>
    </div>
  @endforeach

  <div class="flex justify-end gap-2">
    <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium">
      <i class="bi bi-save mr-1"></i> บันทึก
    </button>
  </div>
</form>

<script>
// Live-update toggle UI when checkbox changes
document.querySelectorAll('input[name="flags[]"]').forEach(cb => {
  cb.addEventListener('change', function() {
    const wrapper = this.closest('label');
    const switchEl = wrapper.querySelector('span[onclick]');
    const knob = switchEl.querySelector('span');
    const text = wrapper.querySelector('span.text-xs');
    if (this.checked) {
      switchEl.classList.remove('bg-gray-300');
      switchEl.classList.add('bg-emerald-500');
      knob.classList.remove('translate-x-1');
      knob.classList.add('translate-x-6');
      text.classList.remove('text-gray-400');
      text.classList.add('text-emerald-600');
      text.textContent = 'เปิด';
    } else {
      switchEl.classList.remove('bg-emerald-500');
      switchEl.classList.add('bg-gray-300');
      knob.classList.remove('translate-x-6');
      knob.classList.add('translate-x-1');
      text.classList.remove('text-emerald-600');
      text.classList.add('text-gray-400');
      text.textContent = 'ปิด';
    }
  });
});
</script>
@endsection
