@extends('layouts.app')

@section('title', 'ไฟล์ที่แชร์ — ' . $file->original_name)

@section('content')
<div class="max-w-lg mx-auto py-10">
  <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    {{-- Header --}}
    <div class="p-6 text-center bg-gradient-to-br from-indigo-500 to-purple-600 text-white">
      <div class="w-20 h-20 mx-auto rounded-2xl bg-white/10 backdrop-blur flex items-center justify-center mb-3">
        <i class="bi {{ $file->icon }} text-5xl"></i>
      </div>
      <div class="text-xs opacity-80">มีคนแชร์ไฟล์ให้คุณ</div>
      <div class="font-bold text-xl mt-1 break-all">{{ $file->original_name }}</div>
      <div class="text-xs opacity-90 mt-1">
        ขนาด {{ $file->human_size }}
      </div>
    </div>

    {{-- Flash --}}
    <div class="px-6 pt-4">
      @if(session('error'))
        <div class="mb-3 rounded-lg border border-rose-200 bg-rose-50 text-rose-900 text-sm px-3 py-2">
          <i class="bi bi-exclamation-triangle-fill mr-1"></i>{{ session('error') }}
        </div>
      @endif
      @if(session('success'))
        <div class="mb-3 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm px-3 py-2">
          <i class="bi bi-check-circle-fill mr-1"></i>{{ session('success') }}
        </div>
      @endif
    </div>

    <div class="px-6 pb-6 pt-2">
      @if($needsPassword && !$verified)
        {{-- Password form --}}
        <div class="text-center mb-4">
          <div class="w-12 h-12 mx-auto rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mb-2">
            <i class="bi bi-shield-lock-fill text-2xl"></i>
          </div>
          <div class="font-semibold text-gray-900">ไฟล์นี้มีรหัสผ่าน</div>
          <div class="text-xs text-gray-500 mt-1">โปรดกรอกรหัสผ่านที่เจ้าของไฟล์ให้ไว้</div>
        </div>

        <form method="POST" action="{{ route('storage.share.verify', ['token' => $token]) }}" class="space-y-3">
          @csrf
          <input type="password"
                 name="password"
                 autofocus
                 autocomplete="off"
                 placeholder="รหัสผ่าน"
                 class="w-full px-4 py-2.5 text-sm rounded-lg border border-gray-300 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 focus:outline-none">
          <button type="submit"
                  class="w-full py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 transition">
            <i class="bi bi-unlock-fill mr-1"></i> ปลดล็อค
          </button>
        </form>
      @else
        {{-- Download CTA --}}
        <div class="space-y-3">
          <a href="{{ route('storage.share.download', ['token' => $token]) }}"
             class="flex items-center justify-center gap-2 w-full py-3 rounded-lg bg-gray-900 text-white text-sm font-semibold hover:bg-gray-800 transition">
            <i class="bi bi-download text-lg"></i> ดาวน์โหลดไฟล์
          </a>

          @if($file->share_expires_at)
            <div class="text-center text-xs text-gray-500">
              <i class="bi bi-hourglass-split mr-1"></i>
              ลิงก์นี้หมดอายุ {{ $file->share_expires_at->format('d/m/Y H:i') }}
            </div>
          @endif

          <div class="text-center text-[11px] text-gray-400 mt-4 pt-4 border-t border-gray-100">
            <i class="bi bi-shield-check mr-1"></i>
            แชร์ผ่านระบบ Cloud Storage — ปลอดภัยด้วย Cloudflare R2
          </div>
        </div>
      @endif
    </div>
  </div>

  <div class="text-center mt-6">
    <a href="{{ route('home') }}" class="text-xs text-gray-500 hover:text-gray-700">
      <i class="bi bi-arrow-left mr-1"></i> กลับไปหน้าหลัก
    </a>
  </div>
</div>
@endsection
