@extends('layouts.admin')
@section('title', 'CDN Cache Purge')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-cloud-slash text-indigo-500"></i> CDN Cache Purge
        <span class="text-xs font-normal text-gray-400 ml-2">/ ล้าง Cloudflare cache</span>
    </h4>
</div>

@if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 rounded-xl p-3 mb-4 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-500/30 text-rose-800 dark:text-rose-200 rounded-xl p-3 mb-4 text-sm">{{ session('error') }}</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    {{-- Configuration --}}
    <div class="lg:col-span-1 space-y-4">
        <form action="{{ route('admin.cache-purge.settings') }}" method="POST" class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-100 dark:border-white/5 space-y-3">
            @csrf
            <div class="flex items-center justify-between">
                <h5 class="font-bold text-sm">การเชื่อมต่อ Cloudflare</h5>
                <span class="text-[11px] px-2 py-0.5 rounded-full {{ $cfg['enabled'] ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-200' : 'bg-gray-200 text-gray-500' }}">
                    {{ $cfg['enabled'] ? 'Enabled' : 'Disabled' }}
                </span>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="cloudflare_enabled" value="0">
                <input type="checkbox" name="cloudflare_enabled" value="1" {{ $cfg['enabled'] ? 'checked' : '' }}
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                เปิดใช้งาน Cloudflare cache purging
            </label>

            <div>
                <label class="text-xs font-semibold text-gray-500">Zone ID</label>
                <input type="text" name="cloudflare_zone_id" value="{{ $cfg['zone_id'] }}" placeholder="32-char hex"
                       class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm font-mono">
            </div>

            <div>
                <label class="text-xs font-semibold text-gray-500">API Token (Zone: Cache Purge)</label>
                <input type="password" name="cloudflare_api_token" value="" placeholder="{{ $cfg['api_token'] ? '•••••••• (ตั้งไว้แล้ว)' : 'ใส่ token ใหม่' }}"
                       class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm font-mono">
                <div class="text-[11px] text-gray-400 mt-1">เว้นว่างจะไม่เปลี่ยนค่าเดิม</div>
            </div>

            <div>
                <label class="text-xs font-semibold text-gray-500">Base URL (advanced)</label>
                <input type="url" name="cloudflare_base_url" value="{{ $cfg['base_url'] }}"
                       class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm font-mono">
            </div>

            <button class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm">
                <i class="bi bi-save mr-1"></i>บันทึก
            </button>
        </form>

        @if($verify)
            <div class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-100 dark:border-white/5 text-sm">
                <h5 class="font-bold text-sm mb-2">การตรวจสอบ Zone</h5>
                @if($verify['ok'])
                    <div class="flex items-center gap-2 text-emerald-600">
                        <i class="bi bi-check-circle"></i>
                        <span>เชื่อมต่อสำเร็จ</span>
                    </div>
                    <div class="mt-2 text-xs text-gray-500 space-y-1">
                        <div>Zone: <span class="font-mono">{{ $verify['zone_name'] ?? '—' }}</span></div>
                        <div>Status: {{ $verify['zone_status'] ?? '—' }}</div>
                        <div>Plan: {{ $verify['plan'] ?? '—' }}</div>
                    </div>
                @else
                    <div class="flex items-center gap-2 text-rose-600">
                        <i class="bi bi-x-circle"></i>
                        <span>{{ $verify['error'] ?? 'ล้มเหลว' }}</span>
                    </div>
                @endif
                <form action="{{ route('admin.cache-purge.verify') }}" method="POST" class="mt-3">
                    @csrf
                    <button class="w-full px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs">
                        <i class="bi bi-arrow-repeat mr-1"></i>ตรวจสอบอีกครั้ง
                    </button>
                </form>
            </div>
        @endif
    </div>

    {{-- Purge actions --}}
    <div class="lg:col-span-2 space-y-4">
        @if(!$cfg['enabled'])
            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-500/30 rounded-xl p-4 text-sm text-amber-800 dark:text-amber-200">
                <i class="bi bi-exclamation-triangle mr-1"></i>
                ยังไม่ได้เปิดใช้งาน Cloudflare — กรอก Zone ID + API Token ในฟอร์มทางซ้าย
            </div>
        @endif

        {{-- Purge by URL --}}
        <form action="{{ route('admin.cache-purge.urls') }}" method="POST"
              class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-100 dark:border-white/5 space-y-3">
            @csrf
            <h5 class="font-bold text-sm"><i class="bi bi-link-45deg mr-1"></i>Purge by URLs</h5>
            <p class="text-xs text-gray-500">หนึ่ง URL ต่อบรรทัด · สูงสุด 30 ต่อครั้ง (ระบบจะแบ่ง batch ให้)</p>
            <textarea name="urls" rows="5" required placeholder="https://example.com/path&#10;https://example.com/image.jpg"
                      class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm font-mono"></textarea>
            <button {{ $cfg['enabled'] ? '' : 'disabled' }}
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-400 text-white rounded-lg text-sm">
                <i class="bi bi-trash mr-1"></i>Purge URLs
            </button>
        </form>

        {{-- Purge by Hosts --}}
        <form action="{{ route('admin.cache-purge.hosts') }}" method="POST"
              class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-100 dark:border-white/5 space-y-3">
            @csrf
            <h5 class="font-bold text-sm"><i class="bi bi-hdd-network mr-1"></i>Purge by Hosts</h5>
            <p class="text-xs text-gray-500">ชื่อโดเมนหรือ subdomain · คั่นด้วย space, comma หรือ newline</p>
            <input type="text" name="hosts" required placeholder="www.example.com, cdn.example.com"
                   class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm font-mono">
            <button {{ $cfg['enabled'] ? '' : 'disabled' }}
                    class="px-4 py-2 bg-amber-500 hover:bg-amber-600 disabled:bg-gray-400 text-white rounded-lg text-sm">
                <i class="bi bi-trash mr-1"></i>Purge Hosts
            </button>
        </form>

        {{-- Purge by Tags (Enterprise) --}}
        <form action="{{ route('admin.cache-purge.tags') }}" method="POST"
              class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-100 dark:border-white/5 space-y-3">
            @csrf
            <h5 class="font-bold text-sm"><i class="bi bi-tags mr-1"></i>Purge by Cache-Tags
                <span class="text-[10px] font-normal text-amber-500">Enterprise</span></h5>
            <input type="text" name="tags" required placeholder="news, events-123, api"
                   class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm font-mono">
            <button {{ $cfg['enabled'] ? '' : 'disabled' }}
                    class="px-4 py-2 bg-purple-600 hover:bg-purple-700 disabled:bg-gray-400 text-white rounded-lg text-sm">
                <i class="bi bi-trash mr-1"></i>Purge Tags
            </button>
        </form>

        {{-- Nuclear option --}}
        <form action="{{ route('admin.cache-purge.everything') }}" method="POST"
              onsubmit="return confirm('⚠️ ล้าง cache ทั้ง zone? การกระทำนี้จะเพิ่มภาระต่อ origin อย่างมาก — ยืนยันหรือไม่?')"
              class="bg-rose-50 dark:bg-rose-900/20 border-2 border-rose-500/30 rounded-xl p-5 space-y-2">
            @csrf
            <h5 class="font-bold text-sm text-rose-700 dark:text-rose-200">
                <i class="bi bi-exclamation-octagon mr-1"></i>Purge Everything
            </h5>
            <p class="text-xs text-rose-600 dark:text-rose-300">⚠️ ล้าง cache ทุก URL ทุก file ใน zone — ใช้เฉพาะเมื่อจำเป็น</p>
            <button {{ $cfg['enabled'] ? '' : 'disabled' }}
                    class="px-4 py-2 bg-rose-600 hover:bg-rose-700 disabled:bg-gray-400 text-white rounded-lg text-sm">
                <i class="bi bi-exclamation-triangle mr-1"></i>Purge Everything
            </button>
        </form>

        {{-- Recent history --}}
        @if(!empty($recent))
            <div class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-100 dark:border-white/5">
                <h5 class="font-bold text-sm mb-3"><i class="bi bi-clock-history mr-1"></i>ประวัติล่าสุด</h5>
                <table class="w-full text-xs">
                    <thead class="text-left text-gray-500">
                        <tr>
                            <th class="py-2">เวลา</th>
                            <th class="py-2">ประเภท</th>
                            <th class="py-2">สถานะ</th>
                            <th class="py-2">Payload</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach($recent as $r)
                            <tr>
                                <td class="py-2 text-gray-500">{{ \Carbon\Carbon::parse($r['at'])->format('d M H:i:s') }}</td>
                                <td class="py-2 font-mono">{{ $r['kind'] }}</td>
                                <td class="py-2">
                                    @if($r['ok'])
                                        <span class="text-emerald-600"><i class="bi bi-check-circle"></i> OK</span>
                                    @else
                                        <span class="text-rose-600"><i class="bi bi-x-circle"></i> {{ $r['error'] ?? 'failed' }}</span>
                                    @endif
                                </td>
                                <td class="py-2 text-gray-500 font-mono truncate max-w-[400px]">
                                    @if(is_array($r['payload']))
                                        {{ implode(', ', $r['payload']) }}
                                    @else
                                        {{ $r['payload'] ?? '—' }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
