@extends('layouts.admin')

@section('title', 'Storage Credential Tester')

@section('content')
<div class="flex justify-between items-center mb-4">
  <h4 class="font-bold mb-0" style="letter-spacing:-0.02em;">
    <i class="bi bi-shield-check mr-2" style="color:#0ea5e9;"></i>Storage Credential Tester
  </h4>
  <div class="flex gap-2">
    <button type="button" onclick="runAllTests()" class="text-sm px-4 py-2 rounded-lg text-white" style="background:#10b981;border:none;font-weight:500;">
      <i class="bi bi-play-fill mr-1"></i> Test All Drivers
    </button>
    <a href="{{ route('admin.settings.storage') }}" class="text-sm px-3 py-1.5 rounded-lg" style="background:rgba(99,102,241,0.08);color:#6366f1;border-radius:8px;font-weight:500;border:none;padding:0.4rem 1rem;">
      <i class="bi bi-arrow-left mr-1"></i> กลับไปหน้า Storage
    </a>
  </div>
</div>

{{-- ── Explainer ───────────────────────────────────────────────────── --}}
<div class="card border-0 mb-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);background:linear-gradient(135deg,rgba(14,165,233,0.06),rgba(99,102,241,0.05));">
  <div class="p-5">
    <h6 class="font-semibold mb-2"><i class="bi bi-info-circle mr-1" style="color:#0ea5e9;"></i> หน้านี้ใช้ทำอะไร</h6>
    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
      รัน <strong>PUT → GET → DELETE</strong> บน storage driver จริงๆ แล้วโชว์ error message เต็มๆ
      (พร้อม <code>AwsErrorCode</code>, HTTP status, ข้อความจาก SDK) เพื่อแก้ปัญหา credential ได้ตรงจุด
      แทนที่จะเห็นแค่ "Upload failed" ทั่วไปในหน้า upload ปกติ
    </p>
    <ul class="text-xs text-gray-500 dark:text-gray-400 list-disc ml-5 space-y-1">
      <li>ไฟล์ทดสอบอยู่ใน prefix <code>_storage-test/</code> — จะถูกลบทิ้งตอน step สุดท้ายของ test</li>
      <li>ถ้า <strong>PUT fail → AccessDenied</strong> → API token ขาดสิทธิ์ <em>Object Write</em> (ต้องไปแก้ที่ Cloudflare / AWS IAM)</li>
      <li>ถ้า <strong>LIST fail → NoSuchBucket</strong> → bucket name พิมพ์ผิด หรือ token scope คนละ bucket</li>
      <li>ถ้า <strong>config fail</strong> → AppSetting ยังไม่ถูกบันทึก หรือ queue worker booting ก่อน setting ลง DB</li>
    </ul>
  </div>
</div>

{{-- ── Per-driver test cards ──────────────────────────────────────── --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">

  {{-- ── R2 ─────────────────────────────────────────────── --}}
  @php $r2 = $summary['r2']; @endphp
  <div class="card border-0 driver-card" data-driver="r2" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div class="p-5">
      <div class="flex items-center justify-between mb-3">
        <div>
          <h6 class="font-semibold mb-0 text-lg">
            <i class="bi bi-cloud mr-1" style="color:#f97316;"></i>Cloudflare R2
          </h6>
          <div class="text-[11px] text-gray-400">S3-compatible · zero egress fees</div>
        </div>
        @if($enabled['r2'])
          <span class="text-[10px] px-2 py-0.5 rounded-full" style="background:rgba(16,185,129,0.12);color:#059669;font-weight:600;">ENABLED</span>
        @else
          <span class="text-[10px] px-2 py-0.5 rounded-full" style="background:rgba(156,163,175,0.15);color:#6b7280;font-weight:600;">DISABLED</span>
        @endif
      </div>

      <div class="text-xs text-gray-500 dark:text-gray-400 space-y-1 mb-3 font-mono" style="background:#f9fafb;padding:10px 12px;border-radius:8px;">
        <div><span class="text-gray-400">bucket</span> = <span class="text-gray-800">{{ $r2['bucket'] ?: '(empty)' }}</span></div>
        <div class="truncate"><span class="text-gray-400">endpoint</span> = <span class="text-gray-800">{{ $r2['endpoint'] ?: '(empty)' }}</span></div>
        <div class="truncate"><span class="text-gray-400">public_url</span> = <span class="text-gray-800">{{ $r2['public_url'] ?: '(empty)' }}</span></div>
        <div><span class="text-gray-400">custom_domain</span> = <span class="text-gray-800">{{ $r2['custom_domain'] ?: '(none)' }}</span></div>
        <div><span class="text-gray-400">key</span> = <span class="text-gray-800">{{ $r2['key_preview'] }}</span></div>
        <div><span class="text-gray-400">secret</span> = <span class="text-gray-800">{{ $r2['secret_preview'] }}</span></div>
      </div>

      <button type="button" onclick="runDriverTest('r2')"
              @disabled(!$enabled['r2'])
              class="w-full py-2 text-sm font-semibold rounded-lg text-white disabled:opacity-40 disabled:cursor-not-allowed"
              style="background:#f97316;border:none;">
        <i class="bi bi-play-fill mr-1"></i>Run R2 Test (PUT → GET → DELETE)
      </button>
      <div class="result-box mt-3"></div>
    </div>
  </div>

  {{-- ── S3 ─────────────────────────────────────────────── --}}
  @php $s3 = $summary['s3']; @endphp
  <div class="card border-0 driver-card" data-driver="s3" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div class="p-5">
      <div class="flex items-center justify-between mb-3">
        <div>
          <h6 class="font-semibold mb-0 text-lg">
            <i class="bi bi-amazon mr-1" style="color:#f59e0b;"></i>AWS S3
          </h6>
          <div class="text-[11px] text-gray-400">us-east-1 default · IAM-scoped</div>
        </div>
        @if($enabled['s3'])
          <span class="text-[10px] px-2 py-0.5 rounded-full" style="background:rgba(16,185,129,0.12);color:#059669;font-weight:600;">ENABLED</span>
        @else
          <span class="text-[10px] px-2 py-0.5 rounded-full" style="background:rgba(156,163,175,0.15);color:#6b7280;font-weight:600;">DISABLED</span>
        @endif
      </div>

      <div class="text-xs text-gray-500 dark:text-gray-400 space-y-1 mb-3 font-mono" style="background:#f9fafb;padding:10px 12px;border-radius:8px;">
        <div><span class="text-gray-400">bucket</span> = <span class="text-gray-800">{{ $s3['bucket'] ?: '(empty)' }}</span></div>
        <div><span class="text-gray-400">region</span> = <span class="text-gray-800">{{ $s3['region'] ?: '(empty)' }}</span></div>
        <div class="truncate"><span class="text-gray-400">url</span> = <span class="text-gray-800">{{ $s3['url'] ?: '(default)' }}</span></div>
        <div><span class="text-gray-400">key</span> = <span class="text-gray-800">{{ $s3['key_preview'] }}</span></div>
        <div><span class="text-gray-400">secret</span> = <span class="text-gray-800">{{ $s3['secret_preview'] }}</span></div>
      </div>

      <button type="button" onclick="runDriverTest('s3')"
              @disabled(!$enabled['s3'])
              class="w-full py-2 text-sm font-semibold rounded-lg text-white disabled:opacity-40 disabled:cursor-not-allowed"
              style="background:#f59e0b;border:none;">
        <i class="bi bi-play-fill mr-1"></i>Run S3 Test (PUT → GET → DELETE)
      </button>
      <div class="result-box mt-3"></div>
    </div>
  </div>

  {{-- ── Google Drive ───────────────────────────────────── --}}
  @php $dr = $summary['drive']; @endphp
  <div class="card border-0 driver-card" data-driver="drive" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div class="p-5">
      <div class="flex items-center justify-between mb-3">
        <div>
          <h6 class="font-semibold mb-0 text-lg">
            <i class="bi bi-google mr-1" style="color:#4285f4;"></i>Google Drive
          </h6>
          <div class="text-[11px] text-gray-400">OAuth2 refresh-token flow</div>
        </div>
        @if($enabled['drive'])
          <span class="text-[10px] px-2 py-0.5 rounded-full" style="background:rgba(16,185,129,0.12);color:#059669;font-weight:600;">ENABLED</span>
        @else
          <span class="text-[10px] px-2 py-0.5 rounded-full" style="background:rgba(156,163,175,0.15);color:#6b7280;font-weight:600;">DISABLED</span>
        @endif
      </div>

      <div class="text-xs text-gray-500 dark:text-gray-400 space-y-1 mb-3 font-mono" style="background:#f9fafb;padding:10px 12px;border-radius:8px;">
        <div><span class="text-gray-400">folder_id</span> = <span class="text-gray-800">{{ $dr['folder_id'] ?: '(empty)' }}</span></div>
        <div><span class="text-gray-400">client_id</span> = <span class="text-gray-800">{{ $dr['client_id_preview'] }}</span></div>
        <div><span class="text-gray-400">refresh_token</span> = <span class="text-gray-800">{{ $dr['refresh_token_present'] ? 'present' : '(empty)' }}</span></div>
      </div>

      <button type="button" onclick="runDriverTest('drive')"
              @disabled(!$enabled['drive'])
              class="w-full py-2 text-sm font-semibold rounded-lg text-white disabled:opacity-40 disabled:cursor-not-allowed"
              style="background:#4285f4;border:none;">
        <i class="bi bi-play-fill mr-1"></i>Run Drive Test (OAuth + List)
      </button>
      <div class="result-box mt-3"></div>
    </div>
  </div>

  {{-- ── Local ──────────────────────────────────────────── --}}
  @php $pu = $summary['public']; @endphp
  <div class="card border-0 driver-card" data-driver="public" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div class="p-5">
      <div class="flex items-center justify-between mb-3">
        <div>
          <h6 class="font-semibold mb-0 text-lg">
            <i class="bi bi-hdd mr-1" style="color:#6b7280;"></i>Local Disk
          </h6>
          <div class="text-[11px] text-gray-400">storage/app/public · fastest fallback</div>
        </div>
        <span class="text-[10px] px-2 py-0.5 rounded-full" style="background:rgba(16,185,129,0.12);color:#059669;font-weight:600;">ALWAYS ON</span>
      </div>

      <div class="text-xs text-gray-500 dark:text-gray-400 space-y-1 mb-3 font-mono" style="background:#f9fafb;padding:10px 12px;border-radius:8px;">
        <div class="truncate"><span class="text-gray-400">root</span> = <span class="text-gray-800">{{ $pu['root'] ?: '(empty)' }}</span></div>
        <div class="truncate"><span class="text-gray-400">url</span> = <span class="text-gray-800">{{ $pu['url'] ?: '(empty)' }}</span></div>
      </div>

      <button type="button" onclick="runDriverTest('public')"
              class="w-full py-2 text-sm font-semibold rounded-lg text-white"
              style="background:#6b7280;border:none;">
        <i class="bi bi-play-fill mr-1"></i>Run Local Test (PUT → GET → DELETE)
      </button>
      <div class="result-box mt-3"></div>
    </div>
  </div>

</div>

{{-- ── CSRF meta for AJAX ──────────────────────────────────────────── --}}
<meta name="csrf-token" content="{{ csrf_token() }}">

<script>
const TEST_ENDPOINT = @json(route('admin.settings.storage.test.run'));
const CSRF_TOKEN    = @json(csrf_token());

/**
 * Render a single operation row inside the per-driver result card.
 * Status icon + timing + detail/error payload.
 */
function renderOp(op) {
  const okIcon = op.ok
    ? '<i class="bi bi-check-circle-fill" style="color:#10b981;"></i>'
    : '<i class="bi bi-x-circle-fill" style="color:#ef4444;"></i>';
  const rowBg = op.ok ? 'rgba(16,185,129,0.04)' : 'rgba(239,68,68,0.04)';

  let errHtml = '';
  if (!op.ok && op.error) {
    const chainHtml = (op.error.chain || []).map((item, i) => {
      const awsBadge = item.aws_code
        ? `<span class="inline-block ml-2 px-2 py-0.5 rounded text-[10px] font-bold" style="background:#ef4444;color:#fff;">${escapeHtml(item.aws_code)}${item.aws_status ? ' ' + item.aws_status : ''}</span>`
        : '';
      return `
        <div class="mt-2 pl-4 border-l-2" style="border-color:#ef4444;">
          <div class="text-xs font-semibold text-gray-700">${i === 0 ? '▸' : '↳ caused by:'} <code class="text-[11px]">${escapeHtml(item.class)}</code>${awsBadge}</div>
          <div class="text-[11px] text-gray-600 mt-0.5 font-mono whitespace-pre-wrap break-all">${escapeHtml(item.message)}</div>
          <div class="text-[10px] text-gray-400 mt-0.5">at ${escapeHtml(item.file)}${item.aws_request ? ' · req-id=' + escapeHtml(item.aws_request) : ''}</div>
        </div>`;
    }).join('');

    const hintHtml = op.error.hint
      ? `<div class="mt-2 p-2 rounded text-[12px]" style="background:#fef3c7;color:#92400e;border-left:3px solid #f59e0b;">
           <i class="bi bi-lightbulb mr-1"></i><strong>วิธีแก้:</strong> ${escapeHtml(op.error.hint)}
         </div>`
      : '';

    errHtml = `
      <div class="mt-1 p-3 rounded" style="background:#fee2e2;border:1px solid #fecaca;">
        <div class="text-xs font-semibold text-red-700 mb-1"><i class="bi bi-exclamation-triangle mr-1"></i>Error Chain</div>
        ${chainHtml}
        ${hintHtml}
      </div>`;
  }

  return `
    <div class="flex items-start gap-2 py-2 px-3 rounded" style="background:${rowBg};">
      <div class="pt-0.5">${okIcon}</div>
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2">
          <code class="text-[11px] font-bold uppercase tracking-wide text-gray-700">${escapeHtml(op.step)}</code>
          <span class="text-[10px] text-gray-400">${op.ms}ms</span>
        </div>
        <div class="text-xs text-gray-600 mt-0.5">${escapeHtml(op.detail || (op.error ? op.error.message : ''))}</div>
        ${errHtml}
      </div>
    </div>`;
}

function renderResult(card, data) {
  const box = card.querySelector('.result-box');

  if (data.enabled === false) {
    box.innerHTML = `
      <div class="p-3 rounded text-xs" style="background:rgba(156,163,175,0.1);color:#6b7280;">
        <i class="bi bi-info-circle mr-1"></i>${escapeHtml(data.error || 'Driver is not enabled')}
      </div>`;
    return;
  }

  const summary = data.ok
    ? `<div class="flex items-center gap-2 mb-2"><i class="bi bi-check-circle-fill" style="color:#10b981;"></i><span class="text-sm font-semibold text-emerald-700">All operations succeeded</span><span class="text-[11px] text-gray-400 ml-auto">${data.total_ms}ms total</span></div>`
    : `<div class="flex items-center gap-2 mb-2"><i class="bi bi-x-circle-fill" style="color:#ef4444;"></i><span class="text-sm font-semibold text-red-700">Test failed — expand below for full error</span><span class="text-[11px] text-gray-400 ml-auto">${data.total_ms}ms total</span></div>`;

  const ops = (data.operations || []).map(renderOp).join('');

  box.innerHTML = `
    <div class="p-3 rounded border" style="background:#fff;border-color:#e5e7eb;">
      ${summary}
      <div class="space-y-1">${ops}</div>
      <div class="text-[10px] text-gray-400 mt-2 text-right">ran at ${escapeHtml(data.timestamp || '')}</div>
    </div>`;
}

function renderRunning(card) {
  const box = card.querySelector('.result-box');
  box.innerHTML = `
    <div class="p-3 rounded text-xs text-center" style="background:rgba(14,165,233,0.08);color:#0369a1;">
      <i class="bi bi-arrow-repeat mr-1" style="animation:spin 1s linear infinite;display:inline-block;"></i>
      กำลังทดสอบ...
    </div>`;
}

function renderError(card, message) {
  const box = card.querySelector('.result-box');
  box.innerHTML = `
    <div class="p-3 rounded text-xs" style="background:#fee2e2;color:#dc2626;border:1px solid #fecaca;">
      <i class="bi bi-exclamation-triangle mr-1"></i>Request failed: ${escapeHtml(message)}
    </div>`;
}

async function runDriverTest(driver) {
  const card = document.querySelector(`.driver-card[data-driver="${driver}"]`);
  if (!card) return;
  renderRunning(card);

  try {
    const fd = new FormData();
    fd.append('driver', driver);

    const res = await fetch(TEST_ENDPOINT, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': CSRF_TOKEN,
        'Accept': 'application/json',
      },
      body: fd,
    });

    if (!res.ok) {
      let errText = `HTTP ${res.status}`;
      try {
        const body = await res.text();
        errText += ' — ' + body.slice(0, 400);
      } catch (_) {}
      throw new Error(errText);
    }

    const data = await res.json();
    renderResult(card, data);
  } catch (e) {
    renderError(card, e.message || String(e));
  }
}

async function runAllTests() {
  // Run sequentially so the server isn't slammed and the UI reveals results one by one
  for (const driver of ['r2', 's3', 'drive', 'public']) {
    const card = document.querySelector(`.driver-card[data-driver="${driver}"]`);
    // Skip disabled drivers whose button is disabled
    if (!card) continue;
    const btn = card.querySelector('button[onclick^="runDriverTest"]');
    if (btn && btn.disabled) continue;
    await runDriverTest(driver);
  }
}

function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}
</script>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>

@endsection
