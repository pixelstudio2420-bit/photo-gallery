@extends('layouts.app')

@section('title', 'ตั้งค่าการแจ้งเตือน')

@section('content')
<div class="max-w-4xl mx-auto">

  {{-- Header --}}
  <div class="mb-6">
    <div class="flex items-center gap-3 mb-2">
      <a href="{{ route('profile') }}" class="text-gray-400 hover:text-gray-600 transition">
        <i class="bi bi-chevron-left"></i>
      </a>
      <h1 class="text-2xl font-bold text-gray-800">
        <i class="bi bi-bell-fill text-indigo-500 mr-2"></i>ตั้งค่าการแจ้งเตือน
      </h1>
    </div>
    <p class="text-sm text-gray-500">เลือกวิธีที่คุณต้องการรับการแจ้งเตือนจากเรา</p>
  </div>

  <form method="POST" action="{{ route('profile.notification-preferences.update') }}" class="space-y-4">
    @csrf
    @method('PUT')

    {{-- Channel headers card --}}
    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
      <div class="px-5 py-4 bg-gradient-to-r from-indigo-50 to-violet-50 border-b border-gray-100">
        <div class="grid grid-cols-[1fr_auto_auto_auto_auto] gap-3 items-center">
          <div class="text-sm font-semibold text-gray-700">ประเภทการแจ้งเตือน</div>
          <div class="w-16 text-center">
            <div class="text-xs text-gray-500">ในแอป</div>
            <i class="bi bi-bell text-sm text-indigo-500"></i>
          </div>
          <div class="w-16 text-center">
            <div class="text-xs text-gray-500">อีเมล</div>
            <i class="bi bi-envelope text-sm text-blue-500"></i>
          </div>
          <div class="w-16 text-center">
            <div class="text-xs text-gray-500">SMS</div>
            <i class="bi bi-phone text-sm text-emerald-500"></i>
          </div>
          <div class="w-16 text-center">
            <div class="text-xs text-gray-500">Push</div>
            <i class="bi bi-bell-fill text-sm text-purple-500"></i>
          </div>
        </div>
      </div>

      {{-- Preference rows --}}
      <div class="divide-y divide-gray-100">
        @foreach($preferences as $type => $pref)
        <div class="px-5 py-4 grid grid-cols-[1fr_auto_auto_auto_auto] gap-3 items-center hover:bg-gray-50 transition">
          <div>
            <div class="font-medium text-gray-800 text-sm">{{ $pref['label'] }}</div>
            <div class="text-xs text-gray-500 mt-0.5">{{ $pref['desc'] }}</div>
          </div>
          <div class="w-16 text-center">
            <label class="inline-flex items-center cursor-pointer">
              <input type="checkbox" name="prefs[{{ $type }}][in_app]" value="1" {{ $pref['in_app_enabled'] ? 'checked' : '' }}
                     class="sr-only peer">
              <div class="relative w-10 h-5 bg-gray-200 peer-checked:bg-indigo-500 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-200 transition-colors">
                <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow peer-checked:translate-x-5 transition-transform"></div>
              </div>
            </label>
          </div>
          <div class="w-16 text-center">
            <label class="inline-flex items-center cursor-pointer">
              <input type="checkbox" name="prefs[{{ $type }}][email]" value="1" {{ $pref['email_enabled'] ? 'checked' : '' }}
                     class="sr-only peer">
              <div class="relative w-10 h-5 bg-gray-200 peer-checked:bg-blue-500 rounded-full peer-focus:ring-2 peer-focus:ring-blue-200 transition-colors">
                <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow peer-checked:translate-x-5 transition-transform"></div>
              </div>
            </label>
          </div>
          <div class="w-16 text-center">
            <label class="inline-flex items-center cursor-pointer">
              <input type="checkbox" name="prefs[{{ $type }}][sms]" value="1" {{ $pref['sms_enabled'] ? 'checked' : '' }}
                     class="sr-only peer">
              <div class="relative w-10 h-5 bg-gray-200 peer-checked:bg-emerald-500 rounded-full peer-focus:ring-2 peer-focus:ring-emerald-200 transition-colors">
                <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow peer-checked:translate-x-5 transition-transform"></div>
              </div>
            </label>
          </div>
          <div class="w-16 text-center">
            <label class="inline-flex items-center cursor-pointer">
              <input type="checkbox" name="prefs[{{ $type }}][push]" value="1" {{ $pref['push_enabled'] ? 'checked' : '' }}
                     class="sr-only peer">
              <div class="relative w-10 h-5 bg-gray-200 peer-checked:bg-purple-500 rounded-full peer-focus:ring-2 peer-focus:ring-purple-200 transition-colors">
                <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow peer-checked:translate-x-5 transition-transform"></div>
              </div>
            </label>
          </div>
        </div>
        @endforeach
      </div>
    </div>

    {{-- Quick Actions --}}
    <div class="bg-white border border-gray-100 rounded-2xl p-4 flex flex-wrap gap-2">
      <button type="button" onclick="toggleAll('in_app', true)"
              class="px-3 py-2 bg-indigo-50 text-indigo-600 rounded-lg text-sm hover:bg-indigo-100 transition">
        <i class="bi bi-bell mr-1"></i> เปิดในแอปทั้งหมด
      </button>
      <button type="button" onclick="toggleAll('in_app', false)"
              class="px-3 py-2 bg-gray-100 text-gray-600 rounded-lg text-sm hover:bg-gray-200 transition">
        ปิดในแอปทั้งหมด
      </button>
      <button type="button" onclick="toggleAll('email', true)"
              class="px-3 py-2 bg-blue-50 text-blue-600 rounded-lg text-sm hover:bg-blue-100 transition">
        <i class="bi bi-envelope mr-1"></i> เปิดอีเมลทั้งหมด
      </button>
      <button type="button" onclick="toggleAll('email', false)"
              class="px-3 py-2 bg-gray-100 text-gray-600 rounded-lg text-sm hover:bg-gray-200 transition">
        ปิดอีเมลทั้งหมด
      </button>
    </div>

    {{-- Submit --}}
    <div class="flex gap-3">
      <a href="{{ route('profile') }}" class="px-5 py-2.5 border border-gray-200 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition">
        ยกเลิก
      </a>
      <button type="submit"
              class="flex-1 sm:flex-none px-8 py-2.5 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-xl font-medium hover:shadow-lg transition">
        <i class="bi bi-check2 mr-1"></i>บันทึกการตั้งค่า
      </button>
    </div>
  </form>
</div>

@push('scripts')
<script>
function toggleAll(channel, enable) {
  document.querySelectorAll(`input[name*="[${channel}]"]`).forEach(cb => cb.checked = enable);
}
</script>
@endpush
@endsection
