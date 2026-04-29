@extends('layouts.photographer')

@section('title', 'API Keys')

@section('content')
@include('photographer.partials.page-hero', [
  'icon'     => 'bi-key',
  'eyebrow'  => 'บริการเสริม',
  'title'    => 'API Keys',
  'subtitle' => 'Bearer tokens สำหรับเรียก API ของแพลตฟอร์ม (Studio plan เท่านั้น)',
])

@if(session('success'))
  <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm px-4 py-3">
    <i class="bi bi-check-circle-fill mr-1.5"></i>{{ session('success') }}
  </div>
@endif
@if(session('error'))
  <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 text-rose-900 text-sm px-4 py-3">
    <i class="bi bi-exclamation-triangle-fill mr-1.5"></i>{{ session('error') }}
  </div>
@endif

@if(!$allowed)
<div class="rounded-xl border border-amber-200 bg-amber-50 p-5 mb-6">
  <p class="font-semibold text-amber-900">
    <i class="bi bi-lock-fill mr-1.5"></i> API access ยังไม่เปิดใช้
  </p>
  <p class="text-sm text-amber-800 mt-2">
    API access เปิดสำหรับแผน Studio เท่านั้น —
    <a href="{{ route('photographer.subscription.plans') }}" class="font-medium underline">อัปเกรดเพื่อปลดล็อก</a>
  </p>
</div>
@endif

@if($plainToken)
<div class="rounded-xl border-2 border-emerald-300 bg-emerald-50 p-5 mb-6">
  <p class="font-semibold text-emerald-900 mb-2">
    <i class="bi bi-shield-check mr-1.5"></i> Token ของคุณ — บันทึกตอนนี้
  </p>
  <p class="text-xs text-emerald-800 mb-3">
    Token นี้จะแสดงครั้งเดียวเท่านั้น หลังออกจากหน้านี้จะไม่สามารถดูได้อีก
  </p>
  <div class="flex items-center gap-2 bg-white border border-emerald-200 rounded-lg p-3 font-mono text-sm break-all">
    <span class="flex-1">{{ $plainToken }}</span>
    <button type="button"
            onclick="navigator.clipboard.writeText('{{ $plainToken }}'); this.textContent='✓ คัดลอกแล้ว';"
            class="px-3 py-1 rounded bg-emerald-600 text-white text-xs font-medium hover:bg-emerald-700">
      คัดลอก
    </button>
  </div>
</div>
@endif

@if($allowed)
<div class="pg-card p-5 mb-6">
  <h5 class="font-semibold text-gray-900 mb-4">
    <i class="bi bi-plus-circle mr-1.5 text-indigo-500"></i>สร้าง API key ใหม่
  </h5>
  <form method="POST" action="{{ route('photographer.api-keys.create') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3">
    @csrf
    <div class="md:col-span-2">
      <label class="text-xs text-gray-600 mb-1 block">ชื่อ key (สำหรับจดจำว่าใช้กับอะไร)</label>
      <input type="text" name="label" required maxlength="80"
             placeholder="เช่น 'Mobile App'"
             class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
    </div>
    <div>
      <label class="text-xs text-gray-600 mb-1 block">Scopes</label>
      <select name="scopes" class="w-full rounded-lg border-gray-300 text-sm">
        <option value="events:read,photos:read">read-only</option>
        <option value="events:read,photos:read,events:write,photos:write">read + write</option>
        <option value="events:read,photos:read,orders:read">read + orders</option>
      </select>
    </div>
    <div class="md:col-span-3 flex justify-end">
      <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
        <i class="bi bi-key-fill"></i> สร้าง key
      </button>
    </div>
  </form>
</div>
@endif

<div class="pg-card overflow-hidden pg-anim d2">
  <div class="pg-card-header">
    <h5 class="pg-section-title m-0"><i class="bi bi-key"></i> รายการ keys</h5>
  </div>
  @if($keys->isEmpty())
    <div class="pg-empty">
      <div class="pg-empty-icon"><i class="bi bi-key"></i></div>
      <p class="font-medium">ยังไม่มี API keys</p>
      <p class="text-xs mt-1">สร้าง key แรกเพื่อใช้งาน API</p>
    </div>
  @else
    <div class="pg-table-wrap" style="border:none;border-radius:0;box-shadow:none;">
      <table class="pg-table">
        <thead>
          <tr>
            <th>Label</th>
            <th>Prefix</th>
            <th>Scopes</th>
            <th>ใช้ล่าสุด</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($keys as $k)
            <tr class="@if($k->isRevoked()) opacity-50 @endif">
              <td class="font-medium">{{ $k->label }}</td>
              <td class="is-mono text-gray-700">{{ $k->token_prefix }}…</td>
              <td class="text-xs text-gray-600">{{ $k->scopes }}</td>
              <td class="text-xs text-gray-500">
                @if($k->last_used_at)
                  {{ $k->last_used_at->diffForHumans() }}
                  <br><span class="text-gray-400">{{ $k->last_used_ip }}</span>
                @else
                  <span class="text-gray-400">ยังไม่ได้ใช้</span>
                @endif
              </td>
              <td class="text-end">
                @if($k->isRevoked())
                  <span class="pg-pill pg-pill--gray">ยกเลิกแล้ว</span>
                @else
                  <form method="POST" action="{{ route('photographer.api-keys.revoke', $k->id) }}"
                        onsubmit="return confirm('ยืนยันยกเลิก key นี้? — ระบบจะหยุดยอมรับ token ทันที');" class="inline">
                    @csrf @method('DELETE')
                    <button class="text-rose-600 hover:text-rose-700 text-xs font-medium">
                      <i class="bi bi-x-circle"></i> ยกเลิก
                    </button>
                  </form>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
