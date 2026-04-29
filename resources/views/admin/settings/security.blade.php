@extends('layouts.admin')

@section('title', 'ความปลอดภัย')

@section('content')
<div class="flex items-center justify-between mb-4">
  <h4 class="font-bold tracking-tight">
    <i class="bi bi-shield-lock mr-2 text-indigo-500"></i>ความปลอดภัย
  </h4>
  <a href="{{ route('admin.settings.index') }}" class="inline-flex items-center px-4 py-1.5 bg-indigo-50 text-indigo-600 text-sm font-medium rounded-lg hover:bg-indigo-100 transition">
    <i class="bi bi-arrow-left mr-1"></i> กลับ
  </a>
</div>

@if(session('success'))
<div class="bg-emerald-50 text-emerald-700 rounded-lg p-4 text-sm mb-4">
  <i class="bi bi-check-circle mr-1"></i> {{ session('success') }}
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
  {{-- 2FA Status --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-4">
      <h6 class="font-semibold text-sm mb-3"><i class="bi bi-key mr-1 text-indigo-500"></i> การยืนยันตัวตน 2 ชั้น (2FA)</h6>
      @if($twoFa)
        <div class="flex items-center gap-2 mb-2">
          <span class="inline-flex items-center px-3 py-1 bg-emerald-50 text-emerald-700 text-xs font-medium rounded-lg">
            <i class="bi bi-check-circle mr-1"></i>เปิดใช้งาน
          </span>
          <small class="text-gray-500">วิธี: {{ $twoFa->method ?? 'TOTP' }}</small>
        </div>
      @else
        <span class="inline-flex items-center px-3 py-1 bg-red-50 text-red-500 text-xs font-medium rounded-lg">
          <i class="bi bi-x-circle mr-1"></i>ยังไม่เปิดใช้งาน
        </span>
      @endif
    </div>
  </div>

  {{-- Security Settings --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-4">
      <h6 class="font-semibold text-sm mb-3"><i class="bi bi-gear mr-1 text-indigo-500"></i> การตั้งค่า</h6>
      <table class="w-full text-sm">
        <tr><td class="text-gray-500 py-1">Session Lifetime</td><td class="py-1 font-medium">{{ $settings['session_lifetime'] ?? config('session.lifetime') }} นาที</td></tr>
        <tr><td class="text-gray-500 py-1">Max Login Attempts</td><td class="py-1 font-medium">{{ $settings['max_login_attempts'] ?? '5' }}</td></tr>
        <tr><td class="text-gray-500 py-1">Lockout Duration</td><td class="py-1 font-medium">{{ $settings['lockout_duration'] ?? '15' }} นาที</td></tr>
      </table>
    </div>
  </div>
</div>

{{-- Idle Auto-Logout Settings --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 mt-4">
  <div class="p-4">
    <div class="flex items-center gap-2 mb-1">
      <i class="bi bi-clock-history text-amber-500 text-lg"></i>
      <h6 class="font-semibold text-sm">ออกจากระบบอัตโนมัติเมื่อไม่มีการใช้งาน</h6>
    </div>
    <p class="text-gray-500 text-xs mb-3">ระบบจะออกจากระบบโดยอัตโนมัติเมื่อไม่มีการขยับเม้าส์ กดคีย์บอร์ด หรือคลิก ตามเวลาที่กำหนด <strong>ไม่รวมลูกค้าทั่วไป</strong></p>

    <form method="POST" action="{{ route('admin.settings.idle-timeout.update') }}">
      @csrf
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            <i class="bi bi-shield-lock mr-1 text-red-500"></i>แอดมิน (นาที)
          </label>
          <div class="flex">
            <input type="number" name="idle_timeout_admin" class="w-full px-3 py-2 border border-gray-300 rounded-l-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" value="{{ $settings['idle_timeout_admin'] ?? 15 }}" min="0" max="480" step="1">
            <span class="inline-flex items-center px-3 bg-gray-50 border border-l-0 border-gray-300 rounded-r-lg text-sm text-gray-500">นาที</span>
          </div>
          <small class="text-gray-500 text-xs">0 = ปิดการใช้งาน | แนะนำ 15-30 นาที</small>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            <i class="bi bi-camera mr-1 text-blue-500"></i>ช่างภาพ (นาที)
          </label>
          <div class="flex">
            <input type="number" name="idle_timeout_photographer" class="w-full px-3 py-2 border border-gray-300 rounded-l-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" value="{{ $settings['idle_timeout_photographer'] ?? 30 }}" min="0" max="480" step="1">
            <span class="inline-flex items-center px-3 bg-gray-50 border border-l-0 border-gray-300 rounded-r-lg text-sm text-gray-500">นาที</span>
          </div>
          <small class="text-gray-500 text-xs">0 = ปิดการใช้งาน | แนะนำ 30-60 นาที</small>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            <i class="bi bi-exclamation-triangle mr-1 text-amber-500"></i>แจ้งเตือนก่อน (วินาที)
          </label>
          <div class="flex">
            <input type="number" name="idle_warning_seconds" class="w-full px-3 py-2 border border-gray-300 rounded-l-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" value="{{ $settings['idle_warning_seconds'] ?? 60 }}" min="10" max="300" step="5">
            <span class="inline-flex items-center px-3 bg-gray-50 border border-l-0 border-gray-300 rounded-r-lg text-sm text-gray-500">วินาที</span>
          </div>
          <small class="text-gray-500 text-xs">แสดง popup นับถอยหลังก่อนออกจากระบบ</small>
        </div>
      </div>
      <div class="mt-4 flex items-center gap-3">
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2 rounded-lg transition text-sm">
          <i class="bi bi-check-lg mr-1"></i> บันทึก
        </button>
        <div class="flex items-center gap-2 text-gray-500 text-xs">
          <i class="bi bi-info-circle"></i>
          <span>ลูกค้าทั่วไปไม่ได้รับผลกระทบจากการตั้งค่านี้</span>
        </div>
      </div>
    </form>
  </div>
</div>

{{-- Login Attempts --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 mt-4">
  <div class="p-4">
    <h6 class="font-semibold text-sm mb-3"><i class="bi bi-person-exclamation mr-1 text-rose-500"></i> การพยายามเข้าสู่ระบบล่าสุด</h6>
    @if($loginAttempts->count() > 0)
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50">
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">เวลา</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">Email</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">IP</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">สถานะ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
        @foreach($loginAttempts->take(20) as $attempt)
          <tr class="hover:bg-gray-50 transition">
            <td class="px-3 py-2">{{ $attempt->created_at ?? '-' }}</td>
            <td class="px-3 py-2">{{ $attempt->email ?? '-' }}</td>
            <td class="px-3 py-2"><code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded">{{ $attempt->ip_address ?? '-' }}</code></td>
            <td class="px-3 py-2">
              @if(($attempt->status ?? $attempt->success ?? null) == 1 || ($attempt->status ?? '') === 'success')
                <span class="inline-flex items-center px-2 py-0.5 bg-emerald-50 text-emerald-700 text-xs rounded-md">สำเร็จ</span>
              @else
                <span class="inline-flex items-center px-2 py-0.5 bg-red-50 text-red-500 text-xs rounded-md">ล้มเหลว</span>
              @endif
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
    @else
    <p class="text-gray-500 text-sm">ไม่มีข้อมูลการเข้าสู่ระบบ</p>
    @endif
  </div>
</div>

{{-- Security Logs --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 mt-4">
  <div class="p-4">
    <h6 class="font-semibold text-sm mb-3"><i class="bi bi-journal-text mr-1 text-indigo-500"></i> Security Logs</h6>
    @if($securityLogs->count() > 0)
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50">
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">เวลา</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">ประเภท</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">รายละเอียด</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">IP</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
        @foreach($securityLogs->take(20) as $log)
          <tr class="hover:bg-gray-50 transition">
            <td class="px-3 py-2">{{ $log->created_at ?? '-' }}</td>
            <td class="px-3 py-2"><span class="inline-flex items-center px-2 py-0.5 bg-gray-100 text-gray-700 text-xs rounded-md">{{ $log->event_type ?? $log->type ?? '-' }}</span></td>
            <td class="px-3 py-2">{{ \Illuminate\Support\Str::limit($log->description ?? $log->message ?? '-', 80) }}</td>
            <td class="px-3 py-2"><code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded">{{ $log->ip_address ?? '-' }}</code></td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
    @else
    <p class="text-gray-500 text-sm">ไม่มีข้อมูล Security Logs</p>
    @endif
  </div>
</div>
@endsection
