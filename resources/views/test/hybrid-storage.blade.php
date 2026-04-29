<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ทดสอบ Hybrid Storage Architecture</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Sarabun', sans-serif; box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #0f172a; color: #e2e8f0; min-height: 100vh; padding: 2rem; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { font-size: 1.8rem; font-weight: 700; margin-bottom: 0.5rem; }
        h1 span { background: linear-gradient(135deg, #818cf8, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .subtitle { color: #94a3b8; margin-bottom: 2rem; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
        @media (max-width: 768px) { .grid { grid-template-columns: 1fr; } }

        .card { background: linear-gradient(145deg, #1e293b, #1a2332); border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 1.5rem; }
        .card h2 { font-size: 1.2rem; font-weight: 700; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.5rem; }
        .card .desc { color: #94a3b8; font-size: 0.85rem; margin-bottom: 1rem; line-height: 1.5; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; }
        .badge-a { background: rgba(99,102,241,0.2); color: #a5b4fc; }
        .badge-b { background: rgba(6,182,212,0.2); color: #67e8f9; }

        .dropzone { border: 2px dashed rgba(255,255,255,0.15); border-radius: 12px; padding: 2rem; text-align: center; cursor: pointer; transition: all 0.2s; position: relative; }
        .dropzone:hover, .dropzone.dragover { border-color: #6366f1; background: rgba(99,102,241,0.06); }
        .dropzone input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .dropzone .icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .dropzone p { color: #94a3b8; font-size: 0.85rem; }

        .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 10px; font-weight: 600; font-size: 0.85rem; cursor: pointer; border: none; transition: all 0.2s; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-primary { background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; }
        .btn-primary:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(99,102,241,0.4); }
        .btn-success { background: linear-gradient(135deg, #10b981, #059669); color: #fff; }
        .btn-warning { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; }

        .progress-bar { background: rgba(255,255,255,0.08); border-radius: 8px; height: 8px; margin: 0.75rem 0; overflow: hidden; }
        .progress-fill { background: linear-gradient(90deg, #6366f1, #06b6d4); height: 100%; border-radius: 8px; transition: width 0.5s; }

        .log { background: #0f1419; border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; padding: 1rem; max-height: 300px; overflow-y: auto; font-family: 'Cascadia Code', 'Fira Code', monospace; font-size: 0.78rem; line-height: 1.6; }
        .log .time { color: #475569; }
        .log .info { color: #38bdf8; }
        .log .success { color: #34d399; }
        .log .error { color: #f87171; }
        .log .warn { color: #fbbf24; }

        .photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 0.5rem; margin-top: 1rem; }
        .photo-grid img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 8px; border: 1px solid rgba(255,255,255,0.08); }
        .photo-grid .processing { background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.3); border-radius: 8px; aspect-ratio: 1; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; color: #a5b4fc; }

        .status-section { margin-top: 2rem; }
        .status-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-top: 1rem; }
        .stat-box { background: rgba(255,255,255,0.04); border-radius: 10px; padding: 1rem; text-align: center; }
        .stat-box .num { font-size: 1.5rem; font-weight: 700; }
        .stat-box .label { font-size: 0.75rem; color: #94a3b8; }

        .config-table { width: 100%; border-collapse: collapse; margin-top: 0.75rem; }
        .config-table td { padding: 0.4rem 0.75rem; font-size: 0.8rem; border-bottom: 1px solid rgba(255,255,255,0.04); }
        .config-table td:first-child { color: #94a3b8; white-space: nowrap; }
        .config-table .val { font-family: monospace; }
        .ok { color: #34d399; }
        .no { color: #f87171; }
        .input-group { display: flex; gap: 0.5rem; margin-bottom: 0.75rem; }
        .input-group input { flex: 1; padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.05); color: #e2e8f0; font-size: 0.85rem; }
        .input-group input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }
    </style>
</head>
<body>
<div class="container">
    <h1>🧪 <span>Hybrid Storage Architecture</span> — Test Panel</h1>
    <p class="subtitle">ทดสอบการอัพโหลดรูปภาพทั้ง 2 รูปแบบ: Direct Upload + Google Drive Import</p>

    {{-- System Status --}}
    <div class="card" style="margin-bottom: 1.5rem;">
        <h2>⚙️ System Configuration</h2>
        <table class="config-table">
            <tr>
                <td>Queue Driver</td>
                <td class="val">{{ config('queue.default') }} @if(config('queue.default') === 'sync') <span class="warn">(sync = ทำงานทันที ไม่ใช้ queue)</span> @else <span class="ok">✓ async</span> @endif</td>
            </tr>
            <tr>
                <td>Storage Disk</td>
                <td class="val">
                    @php
                        $sm = app(\App\Services\StorageManager::class);
                        $disk = $sm->preferredDisk();
                    @endphp
                    {{ $disk }}
                    @if($disk === 'r2') <span class="ok">✓ Cloudflare R2</span>
                    @elseif($disk === 's3') <span class="ok">✓ AWS S3</span>
                    @else <span class="warn">⚠ Local (ไม่มี cloud storage)</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td>Google Drive SA</td>
                <td class="val">
                    @php $hasSA = app(\App\Services\GoogleDriveService::class)->hasServiceAccount(); @endphp
                    @if($hasSA) <span class="ok">✓ Service Account configured</span>
                    @else <span class="no">✗ ยังไม่ตั้งค่า (Case A จะใช้ไม่ได้)</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td>Test Event</td>
                <td class="val">
                    @php $event = \App\Models\Event::first(); @endphp
                    @if($event) #{{ $event->id }} — {{ $event->name }} <span class="ok">✓</span>
                    @else <span class="no">ไม่มี event</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td>Watermark</td>
                <td class="val">
                    @php $wm = new \App\Services\WatermarkService(); @endphp
                    @if($wm->isEnabled()) <span class="ok">✓ เปิดใช้งาน</span>
                    @else <span class="warn">⚠ ปิดอยู่ (จะ resize เป็น preview แทน)</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="grid">
        {{-- Case B: Direct Upload --}}
        <div class="card">
            <h2>📤 Case B: Direct Upload <span class="badge badge-b">UPLOAD</span></h2>
            <div class="desc">
                ลาก/เลือกรูปภาพ → อัพโหลด original → สร้าง EventPhoto(processing) → Queue job สร้าง thumbnail + watermark
            </div>

            <div class="dropzone" id="dropzone">
                <input type="file" id="fileInput" multiple accept="image/*">
                <div class="icon">📸</div>
                <p>ลากไฟล์มาวางที่นี่ หรือคลิกเพื่อเลือก</p>
                <p style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">รองรับ JPEG, PNG, WebP, GIF (สูงสุด 20MB)</p>
            </div>

            <div class="progress-bar" id="uploadProgress" style="display:none;">
                <div class="progress-fill" id="uploadProgressFill" style="width:0%"></div>
            </div>

            <div id="uploadedPhotos" class="photo-grid"></div>

            <div class="log" id="uploadLog" style="margin-top: 1rem;">
                <div><span class="time">[ready]</span> <span class="info">พร้อมรับไฟล์...</span></div>
            </div>
        </div>

        {{-- Case A: Google Drive Import --}}
        <div class="card">
            <h2>☁️ Case A: Google Drive Import <span class="badge badge-a">DRIVE</span></h2>
            <div class="desc">
                วาง Google Drive folder link → Coordinator job lists files → Dispatch per-photo download jobs → S3/R2 → thumbnail + watermark
            </div>

            <div class="input-group">
                <input type="text" id="driveUrl" placeholder="https://drive.google.com/drive/folders/xxxxx">
                <button class="btn btn-primary" id="importDriveBtn" onclick="startDriveImport()">
                    🚀 นำเข้า
                </button>
            </div>

            <div class="progress-bar" id="driveProgress" style="display:none;">
                <div class="progress-fill" id="driveProgressFill" style="width:0%"></div>
            </div>
            <div id="driveStatus" style="font-size: 0.8rem; color: #94a3b8; margin-bottom: 0.5rem;"></div>

            <div id="drivePhotos" class="photo-grid"></div>

            <div class="log" id="driveLog" style="margin-top: 1rem;">
                <div><span class="time">[ready]</span> <span class="info">รอ Google Drive folder link...</span></div>
            </div>
        </div>
    </div>

    {{-- Queue Status --}}
    <div class="card status-section">
        <h2>📊 Queue & Photo Status</h2>
        <div class="status-grid" id="statusGrid">
            <div class="stat-box"><div class="num" id="statPending">-</div><div class="label">Pending</div></div>
            <div class="stat-box"><div class="num" id="statProcessing">-</div><div class="label">Processing</div></div>
            <div class="stat-box"><div class="num" id="statCompleted">-</div><div class="label">Completed</div></div>
            <div class="stat-box"><div class="num" id="statFailed">-</div><div class="label">Failed</div></div>
        </div>

        <div style="margin-top: 1rem; display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <button class="btn btn-success" onclick="processQueue()">⚡ Process Queue Now</button>
            <button class="btn btn-warning" onclick="processAllQueue()">🔄 Process All</button>
            <button class="btn btn-primary" onclick="refreshStatus()">📊 Refresh Status</button>
            <button class="btn" style="background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;" onclick="resetQueue()">🗑️ Reset Queue</button>
        </div>

        <div class="log" id="queueLog" style="margin-top: 1rem;">
            <div><span class="time">[ready]</span> <span class="info">Queue monitor ready</span></div>
        </div>
    </div>

    {{-- Instructions --}}
    <div class="card" style="margin-top: 1.5rem;">
        <h2>📖 วิธีทดสอบ</h2>
        <div class="desc" style="line-height: 2;">
            <strong>Case B (Direct Upload) — ทดสอบได้ทันที:</strong><br>
            1. ลากรูปภาพมาวางที่ Drop Zone หรือคลิกเลือกไฟล์<br>
            2. ระบบจะอัพโหลด original file ทันที → สร้าง EventPhoto(status=processing)<br>
            3. ถ้า QUEUE_CONNECTION=sync → thumbnail/watermark จะสร้างทันที<br>
            4. ถ้า QUEUE_CONNECTION=database → กด "Process Queue Now" เพื่อรัน job<br>
            5. หรือเปิด terminal: <code style="background:rgba(255,255,255,0.1);padding:2px 6px;border-radius:4px;">php artisan queue:work --queue=photos</code><br>
            <br>
            <strong>Case A (Google Drive) — ต้องตั้งค่า Service Account ก่อน:</strong><br>
            1. สร้าง Google Cloud Project → เปิด Drive API<br>
            2. สร้าง Service Account → Download JSON key<br>
            3. อัพโหลด JSON ที่ Admin > Settings > Google Drive<br>
            4. แชร์ Drive folder กับ Service Account email<br>
            5. วาง folder link แล้วกด "นำเข้า"
        </div>
    </div>
</div>

<script>
const EVENT_ID = {{ $event->id ?? 0 }};
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

// ── Logging Helper ──
function log(target, msg, type = 'info') {
    const el = document.getElementById(target);
    const time = new Date().toLocaleTimeString('th-TH');
    const div = document.createElement('div');
    div.innerHTML = `<span class="time">[${time}]</span> <span class="${type}">${msg}</span>`;
    el.appendChild(div);
    el.scrollTop = el.scrollHeight;
}

// ══════════════════════════════════════════════
//  Case B: Direct Upload
// ══════════════════════════════════════════════
const dropzone = document.getElementById('dropzone');
const fileInput = document.getElementById('fileInput');

dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('dragover'); });
dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
dropzone.addEventListener('drop', e => {
    e.preventDefault();
    dropzone.classList.remove('dragover');
    handleFiles(e.dataTransfer.files);
});
fileInput.addEventListener('change', e => handleFiles(e.target.files));

async function handleFiles(files) {
    const total = files.length;
    log('uploadLog', `เริ่มอัพโหลด ${total} ไฟล์...`, 'info');

    document.getElementById('uploadProgress').style.display = 'block';
    let done = 0;

    for (const file of files) {
        try {
            log('uploadLog', `📤 กำลังอัพโหลด: ${file.name} (${(file.size/1024/1024).toFixed(1)} MB)`, 'info');

            const form = new FormData();
            form.append('photo', file);

            const res = await fetch(`/test/upload-photo/${EVENT_ID}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: form
            });

            const data = await res.json();
            done++;

            const percent = Math.round((done / total) * 100);
            document.getElementById('uploadProgressFill').style.width = percent + '%';

            if (data.success) {
                const photo = data.photo;
                const statusText = photo.status === 'active' ? '✅ active' : '⏳ processing';
                log('uploadLog', `${statusText} — ${photo.filename} (${photo.file_size_human})`, photo.status === 'active' ? 'success' : 'warn');

                // Add to photo grid
                const grid = document.getElementById('uploadedPhotos');
                if (photo.thumbnail_url) {
                    grid.innerHTML += `<img src="${photo.thumbnail_url}" title="${photo.filename}" loading="lazy">`;
                } else {
                    grid.innerHTML += `<div class="processing" data-id="${photo.id}">⏳ #${photo.id}<br>processing</div>`;
                    // Poll for status
                    pollPhotoStatus(photo.id);
                }
            } else {
                log('uploadLog', `❌ Failed: ${data.message}`, 'error');
            }
        } catch (err) {
            done++;
            log('uploadLog', `❌ Error: ${err.message}`, 'error');
        }
    }

    log('uploadLog', `✅ อัพโหลดเสร็จ ${done}/${total} ไฟล์`, 'success');
    refreshStatus();
}

// Poll photo status for processing photos
async function pollPhotoStatus(photoId) {
    const maxAttempts = 30;
    for (let i = 0; i < maxAttempts; i++) {
        await new Promise(r => setTimeout(r, 2000)); // Poll every 2s

        try {
            const res = await fetch(`/test/photo-status/${EVENT_ID}?ids[]=${photoId}`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }
            });
            const data = await res.json();

            if (data.photos?.[0]?.status === 'active') {
                const photo = data.photos[0];
                const el = document.querySelector(`[data-id="${photoId}"]`);
                if (el && photo.thumbnail_url) {
                    const img = document.createElement('img');
                    img.src = photo.thumbnail_url;
                    img.title = `Photo #${photoId}`;
                    img.loading = 'lazy';
                    el.replaceWith(img);
                }
                log('uploadLog', `✅ Photo #${photoId} พร้อมใช้งาน (thumbnail สร้างเสร็จ)`, 'success');
                return;
            }

            if (data.photos?.[0]?.status === 'failed') {
                log('uploadLog', `❌ Photo #${photoId} processing failed`, 'error');
                return;
            }
        } catch (err) {
            // continue polling
        }
    }
    log('uploadLog', `⚠️ Photo #${photoId} ยังไม่เสร็จ (timeout)`, 'warn');
}

// ══════════════════════════════════════════════
//  Case A: Google Drive Import
// ══════════════════════════════════════════════
async function startDriveImport() {
    const url = document.getElementById('driveUrl').value.trim();
    if (!url) {
        log('driveLog', '⚠️ กรุณาวาง Google Drive folder link', 'warn');
        return;
    }

    // First, update the event's drive_folder_url
    log('driveLog', `🔗 Link: ${url}`, 'info');
    log('driveLog', '🚀 กำลังเริ่มนำเข้า...', 'info');

    document.getElementById('importDriveBtn').disabled = true;

    try {
        const res = await fetch(`/test/import-drive/${EVENT_ID}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ drive_folder_url: url })
        });

        const data = await res.json();

        if (data.success) {
            log('driveLog', `✅ ${data.message} (queue_id: ${data.queue_id})`, 'success');
            document.getElementById('driveProgress').style.display = 'block';
            pollDriveProgress();
        } else {
            log('driveLog', `❌ ${data.message}`, 'error');
            document.getElementById('importDriveBtn').disabled = false;
        }
    } catch (err) {
        log('driveLog', `❌ Error: ${err.message}`, 'error');
        document.getElementById('importDriveBtn').disabled = false;
    }
}

async function pollDriveProgress() {
    const maxAttempts = 120; // 4 minutes
    for (let i = 0; i < maxAttempts; i++) {
        await new Promise(r => setTimeout(r, 2000));

        try {
            const res = await fetch(`/test/import-progress/${EVENT_ID}`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }
            });
            const data = await res.json();

            if (data.active) {
                const pct = data.percent || 0;
                document.getElementById('driveProgressFill').style.width = pct + '%';
                document.getElementById('driveStatus').textContent =
                    `${data.status} — ${data.processed_files}/${data.total_files} files (${pct}%)`;

                if (data.status === 'completed') {
                    log('driveLog', `✅ นำเข้าเสร็จสิ้น: ${data.processed_files}/${data.total_files} ไฟล์`, 'success');
                    document.getElementById('importDriveBtn').disabled = false;
                    refreshStatus();
                    return;
                }
            } else {
                if (data.last_import?.status === 'completed') {
                    log('driveLog', `✅ นำเข้าเสร็จแล้ว: ${data.last_import.processed_files}/${data.last_import.total_files}`, 'success');
                    document.getElementById('importDriveBtn').disabled = false;
                    refreshStatus();
                    return;
                }
            }
        } catch (err) {
            // continue polling
        }
    }
    log('driveLog', '⚠️ Timeout — ตรวจสอบ queue status', 'warn');
    document.getElementById('importDriveBtn').disabled = false;
}

// ══════════════════════════════════════════════
//  Queue Management
// ══════════════════════════════════════════════
async function processQueue() {
    log('queueLog', '⚡ Processing next job...', 'info');
    try {
        const res = await fetch('/test/process-queue', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
        });
        const data = await res.json();
        log('queueLog', data.message, data.processed ? 'success' : 'warn');
        refreshStatus();
    } catch (err) {
        log('queueLog', `❌ ${err.message}`, 'error');
    }
}

async function processAllQueue() {
    log('queueLog', '🔄 Processing all pending jobs...', 'info');
    try {
        const res = await fetch('/test/process-queue-all', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
        });
        const data = await res.json();
        log('queueLog', data.message, 'success');
        refreshStatus();
    } catch (err) {
        log('queueLog', `❌ ${err.message}`, 'error');
    }
}

async function resetQueue() {
    try {
        const res = await fetch('/test/reset-queue', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
        });
        const data = await res.json();
        log('queueLog', `🗑️ ${data.message}`, 'success');
        refreshStatus();
    } catch (err) {
        log('queueLog', `❌ ${err.message}`, 'error');
    }
}

async function refreshStatus() {
    try {
        const res = await fetch('/test/queue-status', {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        document.getElementById('statPending').textContent = data.queue.pending;
        document.getElementById('statProcessing').textContent = data.queue.processing;
        document.getElementById('statCompleted').textContent = data.queue.completed;
        document.getElementById('statFailed').textContent = data.queue.failed;
    } catch (err) {
        // ignore
    }
}

// Auto-refresh every 5s
setInterval(refreshStatus, 5000);
refreshStatus();
</script>
</body>
</html>
