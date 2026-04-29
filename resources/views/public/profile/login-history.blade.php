@extends('layouts.app')

@section('title', 'ประวัติการเข้าสู่ระบบ')

@section('content')
<div class="max-w-4xl mx-auto">

  {{-- Header --}}
  <div class="mb-6">
    <div class="flex items-center gap-3 mb-2">
      <a href="{{ route('profile') }}" class="text-gray-400 hover:text-gray-600 transition">
        <i class="bi bi-chevron-left"></i>
      </a>
      <h1 class="text-2xl font-bold text-gray-800">
        <i class="bi bi-shield-lock-fill text-indigo-500 mr-2"></i>ประวัติการเข้าสู่ระบบ
      </h1>
    </div>
    <p class="text-sm text-gray-500">ตรวจสอบการเข้าใช้งานบัญชีของคุณใน 30 รายการล่าสุด</p>
  </div>

  {{-- Suspicious warning --}}
  @if($hasSuspicious)
    <div class="mb-4 bg-red-50 border border-red-200 rounded-xl p-4 flex items-start gap-3">
      <i class="bi bi-exclamation-triangle-fill text-red-500 mt-0.5"></i>
      <div class="flex-1">
        <p class="font-semibold text-red-700 mb-1">พบกิจกรรมที่น่าสงสัย</p>
        <p class="text-sm text-red-600 mb-2">
          เราตรวจพบการเข้าสู่ระบบจากหลายสถานที่ใน 7 วันที่ผ่านมา
          หากไม่ใช่คุณ แนะนำให้เปลี่ยนรหัสผ่านและเปิดใช้ 2FA ทันที
        </p>
        <div class="flex flex-wrap gap-2">
          <a href="{{ url('/profile/security') }}"
             class="inline-flex items-center bg-red-500 hover:bg-red-600 text-white text-sm font-medium px-3 py-1.5 rounded-lg transition">
            <i class="bi bi-shield-lock mr-1"></i>ตั้งค่าความปลอดภัย
          </a>
          <form method="POST" action="{{ url('/auth/logout-others') }}" class="inline">
            @csrf
            <button type="submit"
                    class="inline-flex items-center bg-white hover:bg-red-50 text-red-600 text-sm font-medium px-3 py-1.5 rounded-lg border border-red-300 transition">
              <i class="bi bi-box-arrow-right mr-1"></i>ออกจากระบบทุกอุปกรณ์
            </button>
          </form>
        </div>
      </div>
    </div>
  @else
    <div class="mb-4 bg-emerald-50 border border-emerald-100 rounded-xl p-4 flex items-center gap-3">
      <i class="bi bi-shield-check-fill text-emerald-500"></i>
      <p class="text-sm text-emerald-700 mb-0">ยังไม่พบกิจกรรมที่น่าสงสัยในบัญชีของคุณ</p>
    </div>
  @endif

  {{-- History list --}}
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 bg-gradient-to-r from-indigo-50/50 to-violet-50/50">
      <h3 class="font-semibold text-gray-700">กิจกรรมล่าสุด</h3>
    </div>

    @if($logs->isEmpty())
      <div class="p-12 text-center">
        <i class="bi bi-shield-lock" style="font-size:3rem;color:#6366f1;opacity:0.2;"></i>
        <p class="text-gray-500 mt-3 mb-0">ยังไม่มีประวัติการเข้าสู่ระบบ</p>
      </div>
    @else
      <div class="divide-y divide-gray-100">
        @foreach($logs as $log)
          @php
            $isCurrent = $log->ip_address && $log->ip_address === $currentIp && $log->event_type === 'login';
            $iconClass = match($log->event_type) {
              'login'        => 'text-emerald-500',
              '2fa_success'  => 'text-emerald-500',
              'logout'       => 'text-gray-400',
              'failed'       => 'text-red-500',
              '2fa_failed'   => 'text-red-500',
              '2fa_required' => 'text-amber-500',
              default        => 'text-gray-400',
            };
            $eventLabel = match($log->event_type) {
              'login'        => 'เข้าสู่ระบบ',
              '2fa_success'  => '2FA สำเร็จ',
              'logout'       => 'ออกจากระบบ',
              'failed'       => 'ล็อกอินล้มเหลว',
              '2fa_failed'   => '2FA ล้มเหลว',
              '2fa_required' => 'ต้อง 2FA',
              default        => $log->event_type,
            };
          @endphp
          <div class="px-5 py-4 flex items-start gap-4 hover:bg-gray-50 transition {{ $isCurrent ? 'bg-emerald-50/30' : '' }} {{ $log->is_suspicious ? 'bg-red-50/30' : '' }}">
            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 flex-shrink-0">
              <i class="bi {{ $log->icon }} {{ $iconClass }}"></i>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between flex-wrap gap-2 mb-1">
                <div class="font-medium text-gray-800">
                  {{ $eventLabel }}
                  @if($isCurrent)
                    <span class="ml-2 text-xs font-semibold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">
                      เซสชันปัจจุบัน
                    </span>
                  @endif
                  @if($log->is_suspicious)
                    <span class="ml-2 text-xs font-semibold text-red-700 bg-red-100 px-2 py-0.5 rounded-full">
                      <i class="bi bi-exclamation-triangle-fill mr-0.5"></i>น่าสงสัย
                    </span>
                  @endif
                </div>
                <span class="text-xs text-gray-500">
                  {{ $log->created_at ? \Carbon\Carbon::parse($log->created_at)->format('d/m/Y H:i') : '-' }}
                </span>
              </div>
              <div class="text-sm text-gray-600">
                {{ $log->browser_info }}
              </div>
              <div class="text-xs text-gray-400 mt-0.5">
                IP: <span class="font-mono">{{ $log->ip_address ?? '-' }}</span>
                @if($log->country)
                  · {{ $log->country }}{{ $log->city ? ' / ' . $log->city : '' }}
                @endif
              </div>
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </div>

  {{-- Footer action --}}
  @if(!$logs->isEmpty())
    <div class="mt-4 text-center">
      <form method="POST" action="{{ url('/auth/logout-others') }}" class="inline">
        @csrf
        <button type="submit"
                class="inline-flex items-center text-sm font-medium text-gray-600 hover:text-red-600 transition">
          <i class="bi bi-box-arrow-right mr-1"></i>ออกจากระบบในอุปกรณ์อื่นทั้งหมด
        </button>
      </form>
    </div>
  @endif
</div>
@endsection
