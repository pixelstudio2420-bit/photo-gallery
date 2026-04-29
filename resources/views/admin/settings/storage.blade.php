@extends('layouts.admin')

@section('title', 'Multi-Driver Storage')

@section('content')
<div class="flex justify-between items-center mb-4">
  <h4 class="font-bold mb-0" style="letter-spacing:-0.02em;">
    <i class="bi bi-hdd-stack mr-2" style="color:#0ea5e9;"></i>Storage — R2 / S3 / Google Drive
  </h4>
  <div class="flex gap-2">
    <a href="{{ route('admin.settings.storage.test') }}" class="text-sm px-3 py-1.5 rounded-lg" style="background:rgba(14,165,233,0.1);color:#0369a1;border-radius:8px;font-weight:500;border:none;padding:0.4rem 1rem;">
      <i class="bi bi-shield-check mr-1"></i> Test Credentials
    </a>
    <a href="{{ route('admin.settings.index') }}" class="text-sm px-3 py-1.5 rounded-lg" style="background:rgba(99,102,241,0.08);color:#6366f1;border-radius:8px;font-weight:500;border:none;padding:0.4rem 1rem;">
      <i class="bi bi-arrow-left mr-1"></i> กลับ
    </a>
  </div>
</div>

@if(session('success'))
<div class="mb-4 p-3 rounded-lg" style="background:rgba(16,185,129,0.1);color:#059669;border:1px solid rgba(16,185,129,0.2);">
  <i class="bi bi-check-circle mr-1"></i> {{ session('success') }}
</div>
@endif

@if ($errors->any())
<div class="mb-4 p-3 rounded-lg" style="background:rgba(239,68,68,0.1);color:#dc2626;border:1px solid rgba(239,68,68,0.2);">
  <ul class="text-sm mb-0">
    @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
  </ul>
</div>
@endif

{{-- ── Explainer ───────────────────────────────────────────────────── --}}
<div class="card border-0 mb-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);background:linear-gradient(135deg,rgba(14,165,233,0.06),rgba(99,102,241,0.05));">
  <div class="p-5">
    <h6 class="font-semibold mb-2"><i class="bi bi-info-circle mr-1" style="color:#0ea5e9;"></i> ระบบนี้ทำอะไร</h6>
    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
      จัดการ storage ของรูปแบบ <strong>หลาย driver พร้อมกัน</strong> — เลือกว่าจะอ่าน/เขียน/มิเรอร์ไปที่ R2, S3, Drive, หรือ Local
      เพื่อรองรับการโหลดของลูกค้า <strong>5,000+ คน/วัน</strong> ได้แบบไม่จำกัด
    </p>
    <ul class="text-xs text-gray-500 dark:text-gray-400 list-disc ml-5 space-y-1">
      <li><strong>R2 แนะนำเป็น primary</strong> — Cloudflare ไม่คิด egress, รองรับ concurrent ไม่จำกัด</li>
      <li><strong>Drive ไม่เหมาะเป็น primary download</strong> — มี per-file quota ทำให้ไฟล์ popular โดนบล็อก</li>
      <li><strong>Signed URL</strong> — ส่ง browser ไป R2/S3 ตรงๆ (ไม่ผ่าน server นี้) = bandwidth 0 บาทฝั่งเรา</li>
      <li><strong>Mirror</strong> — copy รูปไปหลายที่อัตโนมัติเพื่อ backup</li>
    </ul>
  </div>
</div>

{{-- ── Driver Health ──────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
  @foreach($health as $driver => $h)
    @php
      $color = $h['enabled'] && $h['ok'] ? '#10b981' : ($h['enabled'] ? '#ef4444' : '#9ca3af');
      $icon = match($driver) { 'r2' => 'bi-cloud', 's3' => 'bi-amazon', 'drive' => 'bi-google', default => 'bi-hdd' };
      $labels = ['r2' => 'Cloudflare R2', 's3' => 'AWS S3', 'drive' => 'Google Drive', 'public' => 'Local Disk'];
    @endphp
    <div class="card border-0 p-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-xs text-gray-500 mb-1">{{ $labels[$driver] }}</div>
          <div class="text-lg font-semibold" style="color:{{ $color }}">
            <i class="bi {{ $icon }} mr-1"></i>
            {{ $h['enabled'] ? ($h['ok'] ? 'Online' : 'Error') : 'Disabled' }}
          </div>
          <div class="text-[11px] text-gray-400 mt-1 truncate" title="{{ $h['detail'] }}">{{ $h['detail'] ?: '—' }}</div>
        </div>
      </div>
    </div>
  @endforeach
</div>

{{-- ── Current Stats ──────────────────────────────────────────────── --}}
<div class="card border-0 mb-4 p-5" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
  <h6 class="font-semibold mb-3"><i class="bi bi-bar-chart mr-1 text-indigo-500"></i>สถานะปัจจุบัน</h6>
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div>
      <div class="text-xs text-gray-500">Total photos</div>
      <div class="text-2xl font-bold">{{ number_format($stats['total']) }}</div>
    </div>
    <div>
      <div class="text-xs text-gray-500">On cloud (R2/S3)</div>
      <div class="text-2xl font-bold text-sky-600">{{ number_format($stats['on_cloud']) }}</div>
      @if($stats['total'] > 0)
        <div class="text-[11px] text-gray-400">{{ round($stats['on_cloud'] / $stats['total'] * 100, 1) }}%</div>
      @endif
    </div>
    <div>
      <div class="text-xs text-gray-500">Mirrored</div>
      <div class="text-2xl font-bold text-purple-600">{{ number_format($stats['mirrored']) }}</div>
    </div>
    <div>
      <div class="text-xs text-gray-500">Resolved primary</div>
      <div class="text-xl font-bold text-emerald-600">{{ strtoupper($stats['primary_resolved']) }}</div>
      <div class="text-[11px] text-gray-400">upload→{{ strtoupper($stats['upload_resolved']) }} · zip→{{ strtoupper($stats['zip_resolved']) }}</div>
    </div>
  </div>

  @if(!empty($stats['disk_distribution']))
  <div class="mt-4 text-xs text-gray-500">
    <strong>Distribution:</strong>
    @foreach($stats['disk_distribution'] as $d => $c)
      <span class="inline-block mr-3"><code class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800">{{ $d ?: 'null' }}</code> {{ number_format($c) }}</span>
    @endforeach
  </div>
  @endif

  @if(!empty($stats['mirror_targets_now']))
  <div class="mt-2 text-xs text-gray-500">
    <strong>Active mirror targets:</strong>
    @foreach($stats['mirror_targets_now'] as $t)
      <span class="inline-block px-2 py-0.5 rounded text-[11px] mr-1" style="background:rgba(139,92,246,0.12);color:#7c3aed;">{{ strtoupper($t) }}</span>
    @endforeach
  </div>
  @endif
</div>

{{-- ── Main form ──────────────────────────────────────────────────── --}}
<form method="POST" action="{{ route('admin.settings.storage.update') }}" class="space-y-4">
  @csrf

  {{-- Driver toggles --}}
  <div class="card border-0 p-5" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <h6 class="font-semibold mb-3"><i class="bi bi-toggles mr-1 text-amber-500"></i>เปิด-ปิด Driver</h6>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
      @foreach([
        'r2_enabled'            => ['Cloudflare R2', 'แนะนำเป็น primary — zero egress'],
        'storage_s3_enabled'    => ['AWS S3', 'backup หรือ primary สำรอง'],
        'storage_drive_enabled' => ['Google Drive', 'archival + upload จาก photographer'],
      ] as $key => $info)
        <label class="flex items-start gap-2 p-3 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800">
          <input type="checkbox" name="{{ $key }}" value="1" {{ ($settings[$key] ?? '0') === '1' ? 'checked' : '' }} class="mt-1">
          <div>
            <div class="font-medium text-sm">{{ $info[0] }}</div>
            <div class="text-[11px] text-gray-500">{{ $info[1] }}</div>
          </div>
        </label>
      @endforeach
      <label class="flex items-start gap-2 p-3 rounded-lg border-2 border-dashed border-indigo-300 cursor-pointer hover:bg-indigo-50">
        <input type="checkbox" name="storage_multi_driver_enabled" value="1" {{ ($settings['storage_multi_driver_enabled'] ?? '0') === '1' ? 'checked' : '' }} class="mt-1">
        <div>
          <div class="font-medium text-sm text-indigo-700">Multi-driver mode</div>
          <div class="text-[11px] text-indigo-500">master switch — เปิดเพื่อใช้ features ด้านล่าง</div>
        </div>
      </label>
    </div>
  </div>

  {{-- Driver selection --}}
  <div class="card border-0 p-5" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <h6 class="font-semibold mb-3"><i class="bi bi-arrow-down-up mr-1 text-sky-500"></i>Driver ที่ใช้งาน</h6>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">Primary read (ดาวน์โหลด)</label>
        <select name="storage_primary_driver" class="w-full rounded-lg border-gray-300 text-sm">
          @foreach(['auto' => 'Auto (R2>S3>Local)', 'r2' => 'Cloudflare R2', 's3' => 'AWS S3', 'drive' => 'Google Drive', 'public' => 'Local'] as $v => $label)
            <option value="{{ $v }}" {{ $settings['storage_primary_driver'] === $v ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
        <div class="text-[11px] text-gray-500 mt-1">ลูกค้าอ่านจากที่ไหน (resolved: <code>{{ strtoupper($stats['primary_resolved']) }}</code>)</div>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Upload destination</label>
        <select name="storage_upload_driver" class="w-full rounded-lg border-gray-300 text-sm">
          @foreach(['auto' => 'Auto (same as primary)', 'r2' => 'R2', 's3' => 'S3', 'drive' => 'Drive', 'public' => 'Local'] as $v => $label)
            <option value="{{ $v }}" {{ $settings['storage_upload_driver'] === $v ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
        <div class="text-[11px] text-gray-500 mt-1">รูปใหม่เก็บที่ไหน (resolved: <code>{{ strtoupper($stats['upload_resolved']) }}</code>)</div>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">ZIP staging disk</label>
        <select name="storage_zip_disk" class="w-full rounded-lg border-gray-300 text-sm">
          @foreach(['auto' => 'Auto', 'r2' => 'R2', 's3' => 'S3', 'public' => 'Local'] as $v => $label)
            <option value="{{ $v }}" {{ $settings['storage_zip_disk'] === $v ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
        <div class="text-[11px] text-gray-500 mt-1">ZIP ใหญ่ๆ ไว้ที่ไหน (resolved: <code>{{ strtoupper($stats['zip_resolved']) }}</code>)</div>
      </div>
    </div>
  </div>

  {{-- Mirror config --}}
  <div class="card border-0 p-5" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <h6 class="font-semibold mb-3"><i class="bi bi-collection mr-1 text-purple-500"></i>Hybrid Mirror</h6>
    <label class="flex items-start gap-2 mb-3">
      <input type="checkbox" name="storage_mirror_enabled" value="1" {{ ($settings['storage_mirror_enabled'] ?? '0') === '1' ? 'checked' : '' }} class="mt-1">
      <div>
        <div class="font-medium text-sm">เปิดใช้ mirror อัตโนมัติ</div>
        <div class="text-[11px] text-gray-500">ทุกรูปที่อัปโหลดจะถูก copy ไป driver ที่เลือกข้างล่างนี้</div>
      </div>
    </label>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
      @foreach(['r2' => 'Cloudflare R2', 's3' => 'AWS S3', 'drive' => 'Google Drive', 'public' => 'Local'] as $d => $label)
        <label class="flex items-center gap-2 p-2 rounded border border-gray-200 dark:border-gray-700">
          <input type="checkbox" name="storage_mirror_targets[]" value="{{ $d }}" {{ in_array($d, $mirrorTargets) ? 'checked' : '' }}>
          <span class="text-sm">{{ $label }}</span>
        </label>
      @endforeach
    </div>
  </div>

  {{-- Download flow --}}
  <div class="card border-0 p-5" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <h6 class="font-semibold mb-3"><i class="bi bi-download mr-1 text-emerald-500"></i>Download flow (สำหรับลูกค้า)</h6>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <label class="flex items-start gap-2">
        <input type="checkbox" name="storage_use_signed_urls" value="1" {{ ($settings['storage_use_signed_urls'] ?? '1') === '1' ? 'checked' : '' }} class="mt-1">
        <div>
          <div class="font-medium text-sm">ใช้ Signed URL (แนะนำ)</div>
          <div class="text-[11px] text-gray-500">browser โหลดตรงจาก R2/S3 = bandwidth 0 บาทฝั่งเรา</div>
        </div>
      </label>
      <div>
        <label class="block text-sm font-medium mb-1">Signed URL TTL (วินาที)</label>
        <input type="number" name="storage_signed_url_ttl" min="60" max="604800" value="{{ $settings['storage_signed_url_ttl'] }}" class="w-full rounded-lg border-gray-300 text-sm">
        <div class="text-[11px] text-gray-500 mt-1">ลิงก์มีอายุนานแค่ไหน (default 3600 = 1 ชม.)</div>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Download mode</label>
        <select name="storage_download_mode" class="w-full rounded-lg border-gray-300 text-sm">
          <option value="redirect" {{ $settings['storage_download_mode'] === 'redirect' ? 'selected' : '' }}>Redirect (เร็วสุด)</option>
          <option value="proxy"    {{ $settings['storage_download_mode'] === 'proxy' ? 'selected' : '' }}>Proxy (ผ่าน server เรา)</option>
          <option value="auto"     {{ $settings['storage_download_mode'] === 'auto' ? 'selected' : '' }}>Auto</option>
        </select>
      </div>
      <label class="flex items-start gap-2">
        <input type="checkbox" name="storage_drive_read_fallback" value="1" {{ ($settings['storage_drive_read_fallback'] ?? '1') === '1' ? 'checked' : '' }} class="mt-1">
        <div>
          <div class="font-medium text-sm">Drive fallback</div>
          <div class="text-[11px] text-gray-500">ถ้ารูปยังไม่อยู่บน R2/S3 ให้ดึงจาก Drive</div>
        </div>
      </label>
      <div>
        <label class="block text-sm font-medium mb-1">ZIP retention (ชั่วโมง)</label>
        <input type="number" name="storage_zip_retention_hours" min="1" max="8760" value="{{ $settings['storage_zip_retention_hours'] }}" class="w-full rounded-lg border-gray-300 text-sm">
        <div class="text-[11px] text-gray-500 mt-1">ลบ ZIP ที่เก่ากว่า N ชั่วโมง (default 168 = 7 วัน)</div>
      </div>
    </div>
  </div>

  <div class="flex justify-end gap-2">
    <button type="button" onclick="probeStorage()" class="px-4 py-2 text-sm font-semibold rounded-lg" style="background:rgba(14,165,233,0.08);color:#0284c7;border:none;">
      <i class="bi bi-activity mr-1"></i> Test Connections
    </button>
    <button type="submit" class="px-5 py-2 text-sm font-semibold rounded-lg text-white" style="background:#0ea5e9;border:none;">
      <i class="bi bi-save mr-1"></i> บันทึกการตั้งค่า
    </button>
  </div>
</form>

{{-- ── Migration helper ───────────────────────────────────────────── --}}
<div class="card border-0 mt-4 p-5" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);background:#0f172a;color:#e2e8f0;">
  <h6 class="font-semibold mb-2"><i class="bi bi-terminal mr-1"></i>CLI migration</h6>
  <p class="text-xs text-gray-400 mb-3">ย้ายรูปเก่าระหว่าง driver ด้วยคำสั่ง:</p>
  <pre class="text-[11px] leading-relaxed" style="background:transparent;color:#94a3b8;"><code># preview ก่อน
php artisan photos:migrate-storage --from=drive --to=r2 --dry-run

# ทำจริง 500 รูปแรก
php artisan photos:migrate-storage --from=drive --to=r2 --limit=500

# เจาะจงอีเวนต์
php artisan photos:migrate-storage --from=public --to=r2 --event=42</code></pre>
</div>

<div id="probe-result" class="mt-4"></div>

<script>
async function probeStorage() {
  const box = document.getElementById('probe-result');
  box.innerHTML = '<div class="text-sm text-gray-500"><i class="bi bi-arrow-repeat mr-1"></i>กำลังทดสอบ...</div>';
  try {
    const res = await fetch('{{ route('admin.settings.storage.probe') }}', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json',
      },
    });
    const data = await res.json();
    let html = '<div class="card border-0 p-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);"><h6 class="font-semibold mb-2"><i class="bi bi-check-circle mr-1 text-emerald-500"></i>Health probe</h6><table class="text-sm w-full"><thead><tr><th class="text-left pb-1">Driver</th><th>Enabled</th><th>OK</th><th class="text-left">Detail</th></tr></thead><tbody>';
    for (const [d, info] of Object.entries(data.drivers)) {
      const okColor = info.ok ? 'text-emerald-600' : (info.enabled ? 'text-red-600' : 'text-gray-400');
      html += `<tr class="border-t border-gray-100"><td class="py-1 font-mono">${d.toUpperCase()}</td><td class="text-center">${info.enabled ? '✔' : '—'}</td><td class="text-center ${okColor}">${info.ok ? '✔' : (info.enabled ? '✗' : '—')}</td><td class="text-xs text-gray-500">${info.detail || '—'}</td></tr>`;
    }
    html += '</tbody></table><div class="mt-3 text-xs text-gray-500">Resolved: primary=<code>' + data.resolved.primary.toUpperCase() + '</code> · upload=<code>' + data.resolved.upload.toUpperCase() + '</code> · zip=<code>' + data.resolved.zip.toUpperCase() + '</code></div></div>';
    box.innerHTML = html;
  } catch (e) {
    box.innerHTML = '<div class="p-3 text-red-600 text-sm">Probe ล้มเหลว: ' + e.message + '</div>';
  }
}
</script>
@endsection
