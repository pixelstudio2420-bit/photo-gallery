@extends('layouts.admin')

@section('title', 'API Keys')

@section('content')
<div class="space-y-5">

  {{-- Header --}}
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-slate-800 dark:text-gray-100">
        <i class="bi bi-key-fill text-amber-500 mr-2"></i>API Keys
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">จัดการ API keys สำหรับ integration และ mobile apps</p>
    </div>
    <div class="flex gap-2">
      <a href="{{ route('api.docs') }}" target="_blank" class="px-4 py-2 border border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/5 transition">
        <i class="bi bi-book"></i> API Docs
      </a>
      <a href="{{ route('admin.api-keys.create') }}" class="px-4 py-2 bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-xl text-sm font-medium hover:shadow-lg transition">
        <i class="bi bi-plus-lg mr-1"></i>สร้าง API Key
      </a>
    </div>
  </div>

  {{-- Flash: new key --}}
  @if(session('new_api_key'))
  <div class="bg-gradient-to-br from-emerald-50 to-green-50 border-2 border-emerald-200 rounded-2xl p-5">
    <div class="flex items-start gap-3">
      <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
        <i class="bi bi-check-circle-fill text-xl"></i>
      </div>
      <div class="flex-1">
        <h3 class="font-bold text-emerald-800 mb-1">API Key ใหม่สร้างแล้ว: {{ session('new_api_key_name') }}</h3>
        <p class="text-sm text-emerald-700 mb-3">⚠️ <strong>คัดลอก key นี้เก็บไว้ตอนนี้เลย</strong> — จะไม่แสดงอีกเพราะเราเก็บเฉพาะ hash</p>
        <div class="flex items-center gap-2 bg-white rounded-xl p-3 border border-emerald-200">
          <code class="flex-1 text-emerald-700 font-mono text-sm break-all" id="newApiKey">{{ session('new_api_key') }}</code>
          <button onclick="copyApiKey()" class="px-3 py-1.5 bg-emerald-500 text-white rounded-lg text-sm font-medium hover:bg-emerald-600 shrink-0">
            <i class="bi bi-clipboard mr-1"></i>คัดลอก
          </button>
        </div>
      </div>
    </div>
  </div>
  @endif

  {{-- Stats --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4">
      <div class="text-xs text-gray-500">ทั้งหมด</div>
      <div class="text-2xl font-bold">{{ number_format($stats['total']) }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4">
      <div class="text-xs text-gray-500">เปิดใช้งาน</div>
      <div class="text-2xl font-bold text-emerald-600">{{ number_format($stats['active']) }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4">
      <div class="text-xs text-gray-500">ใกล้หมดอายุ (7 วัน)</div>
      <div class="text-2xl font-bold {{ $stats['expiring_soon'] > 0 ? 'text-amber-600' : 'text-gray-800' }}">{{ number_format($stats['expiring_soon']) }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4">
      <div class="text-xs text-gray-500">Requests รวม</div>
      <div class="text-2xl font-bold text-indigo-600">{{ number_format($stats['total_usage']) }}</div>
    </div>
  </div>

  {{-- Filter --}}
  <form method="GET" class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4 flex gap-3 flex-wrap">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="ค้นหาชื่อหรือ prefix..."
           class="flex-1 min-w-[200px] px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
    <select name="status" class="px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-900 dark:text-gray-200">
      <option value="">ทุกสถานะ</option>
      <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>เปิดใช้งาน</option>
      <option value="revoked" {{ request('status') === 'revoked' ? 'selected' : '' }}>ยกเลิกแล้ว</option>
      <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>หมดอายุ</option>
    </select>
    <button type="submit" class="px-4 py-2 bg-indigo-500 text-white rounded-lg text-sm font-medium hover:bg-indigo-600">ค้นหา</button>
  </form>

  {{-- Table --}}
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-white/[0.02]">
          <tr>
            <th class="text-left p-3 font-semibold text-xs text-gray-600 uppercase">ชื่อ / Prefix</th>
            <th class="text-left p-3 font-semibold text-xs text-gray-600 uppercase">Scopes</th>
            <th class="text-center p-3 font-semibold text-xs text-gray-600 uppercase">Requests</th>
            <th class="text-left p-3 font-semibold text-xs text-gray-600 uppercase">Last Used</th>
            <th class="text-left p-3 font-semibold text-xs text-gray-600 uppercase">Expires</th>
            <th class="text-left p-3 font-semibold text-xs text-gray-600 uppercase">Status</th>
            <th class="text-center p-3 font-semibold text-xs text-gray-600 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($keys as $k)
          <tr class="border-t border-gray-50 dark:border-white/5 hover:bg-gray-50 dark:hover:bg-white/[0.02]">
            <td class="p-3">
              <div class="font-semibold text-slate-800 dark:text-gray-100">{{ $k->name }}</div>
              <div class="text-xs text-gray-500 font-mono">{{ $k->key_prefix }}...</div>
            </td>
            <td class="p-3">
              @if(empty($k->scopes))
                <span class="text-xs text-gray-400">ทั้งหมด</span>
              @else
                <div class="flex flex-wrap gap-1">
                  @foreach($k->scopes as $s)
                  <span class="text-[10px] px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded">{{ $s }}</span>
                  @endforeach
                </div>
              @endif
            </td>
            <td class="p-3 text-center">
              <span class="font-semibold text-indigo-600">{{ number_format($k->usage_count) }}</span>
            </td>
            <td class="p-3 text-xs text-gray-500">
              @if($k->last_used_at)
                {{ $k->last_used_at->diffForHumans() }}<br>
                <span class="text-[10px] text-gray-400">{{ $k->last_used_ip }}</span>
              @else
                <span class="text-gray-400">ยังไม่ใช้งาน</span>
              @endif
            </td>
            <td class="p-3 text-xs">
              @if($k->expires_at)
                @if($k->expires_at < now())
                  <span class="text-red-600">หมดอายุแล้ว<br>{{ $k->expires_at->format('d/m/Y') }}</span>
                @else
                  {{ $k->expires_at->format('d/m/Y') }}<br>
                  <span class="text-[10px] text-gray-400">({{ $k->expires_at->diffForHumans() }})</span>
                @endif
              @else
                <span class="text-gray-400">ไม่มีหมดอายุ</span>
              @endif
            </td>
            <td class="p-3">
              @if(!$k->is_active)
                <span class="px-2 py-0.5 bg-red-100 text-red-700 rounded-full text-xs font-medium">ยกเลิกแล้ว</span>
              @elseif($k->expires_at && $k->expires_at < now())
                <span class="px-2 py-0.5 bg-gray-100 text-gray-700 rounded-full text-xs font-medium">หมดอายุ</span>
              @else
                <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded-full text-xs font-medium">เปิดใช้งาน</span>
              @endif
            </td>
            <td class="p-3">
              <div class="flex gap-1 justify-center">
                @if($k->is_active)
                <form method="POST" action="{{ route('admin.api-keys.revoke', $k) }}">
                  @csrf
                  <button type="submit" onclick="return confirm('ยกเลิก API Key นี้?')"
                          class="w-8 h-8 rounded-lg text-amber-600 hover:bg-amber-50 flex items-center justify-center" title="ยกเลิก">
                    <i class="bi bi-slash-circle text-sm"></i>
                  </button>
                </form>
                @else
                <form method="POST" action="{{ route('admin.api-keys.reactivate', $k) }}">
                  @csrf
                  <button type="submit" class="w-8 h-8 rounded-lg text-emerald-600 hover:bg-emerald-50 flex items-center justify-center" title="เปิดใช้งาน">
                    <i class="bi bi-check-circle text-sm"></i>
                  </button>
                </form>
                @endif
                <form method="POST" action="{{ route('admin.api-keys.destroy', $k) }}">
                  @csrf
                  @method('DELETE')
                  <button type="submit" onclick="return confirm('ลบ API Key นี้ถาวร?')"
                          class="w-8 h-8 rounded-lg text-red-600 hover:bg-red-50 flex items-center justify-center" title="ลบ">
                    <i class="bi bi-trash text-sm"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="7" class="p-12 text-center text-gray-500">
              <i class="bi bi-key text-3xl text-gray-300"></i>
              <p class="mt-2">ยังไม่มี API Keys</p>
              <a href="{{ route('admin.api-keys.create') }}" class="text-indigo-600 hover:underline text-sm mt-2 inline-block">สร้าง API Key แรก →</a>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @if($keys->hasPages())
  <div class="flex justify-center">{{ $keys->links() }}</div>
  @endif
</div>

@endsection

@push('scripts')
<script>
function copyApiKey() {
  const key = document.getElementById('newApiKey').textContent;
  navigator.clipboard.writeText(key).then(() => {
    alert('คัดลอก API Key แล้ว!');
  });
}
</script>
@endpush
