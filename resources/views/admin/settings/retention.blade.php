@extends('layouts.admin')

@section('title', 'Retention Policy')

@section('content')
<div class="flex justify-between items-center mb-4">
  <h4 class="font-bold mb-0" style="letter-spacing:-0.02em;">
    <i class="bi bi-hourglass-split mr-2" style="color:#ef4444;"></i>Retention Policy — ลบอีเวนต์อัตโนมัติ
  </h4>
  <a href="{{ route('admin.settings.index') }}" class="text-sm px-3 py-1.5 rounded-lg" style="background:rgba(99,102,241,0.08);color:#6366f1;border-radius:8px;font-weight:500;border:none;padding:0.4rem 1rem;">
    <i class="bi bi-arrow-left mr-1"></i> กลับ
  </a>
</div>

@if(session('success'))
<div class="mb-4 p-3 rounded-lg" style="background:rgba(16,185,129,0.1);color:#059669;border:1px solid rgba(16,185,129,0.2);">
  <i class="bi bi-check-circle mr-1"></i> {{ session('success') }}
</div>
@endif

{{-- ── Explainer ───────────────────────────────────────────────────── --}}
<div class="card border-0 mb-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);background:linear-gradient(135deg,rgba(239,68,68,0.04),rgba(249,115,22,0.04));">
  <div class="p-5">
    <h6 class="font-semibold mb-2"><i class="bi bi-info-circle mr-1" style="color:#ef4444;"></i> ระบบนี้ทำอะไร</h6>
    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
      เมื่อเปิดใช้งาน ระบบจะลบอีเวนต์ที่เก่ากว่า <strong>จำนวนวันที่กำหนด</strong> อัตโนมัติทุกคืน (02:30 น.)
      เพื่อคืนพื้นที่เก็บข้อมูล — รวมถึงรูปภาพ, cover, reviews, wishlists, และ pricing packages
    </p>
    <ul class="text-xs text-gray-500 dark:text-gray-400 list-disc ml-5 space-y-1">
      <li><strong>ปลอดภัยโดยดีฟอลต์</strong> — ถ้าปิดอยู่จะไม่มีอะไรถูกลบ</li>
      <li><strong>ออเดอร์ที่จ่ายแล้วได้รับการปกป้อง</strong> — ระบบจะข้ามอีเวนต์ที่มีออเดอร์สถานะ paid/completed/processing</li>
      <li><strong>ปักหมุดรายตัวได้</strong> — ในหน้าแก้ไขอีเวนต์มีช่อง "ห้ามลบอัตโนมัติ"</li>
      <li><strong>Dry-run ก่อนเปิดใช้งาน</strong> — ใช้ปุ่ม <em>Preview</em> ด้านล่างเพื่อดูว่าจะมีอีเวนต์กี่รายการถูกลบ</li>
    </ul>
  </div>
</div>

{{-- ── Stats ───────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
  <div class="card border-0 p-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div class="text-xs text-gray-500 mb-1">อีเวนต์ทั้งหมด</div>
    <div class="text-2xl font-bold" style="color:#6366f1;">{{ number_format($stats['total_events']) }}</div>
  </div>
  <div class="card border-0 p-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div class="text-xs text-gray-500 mb-1">ปักหมุด (ห้ามลบ)</div>
    <div class="text-2xl font-bold" style="color:#10b981;">{{ number_format($stats['exempt']) }}</div>
  </div>
  <div class="card border-0 p-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div class="text-xs text-gray-500 mb-1">กำหนดวันลบไว้แล้ว</div>
    <div class="text-2xl font-bold" style="color:#f59e0b;">{{ number_format($stats['explicit_date']) }}</div>
  </div>
  <div class="card border-0 p-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div class="text-xs text-gray-500 mb-1">กำหนด TTL รายตัว</div>
    <div class="text-2xl font-bold" style="color:#8b5cf6;">{{ number_format($stats['per_event_ttl']) }}</div>
  </div>
</div>

{{-- ── Settings form ───────────────────────────────────────────────── --}}
<form method="POST" action="{{ route('admin.settings.retention.update') }}">
  @csrf
  <div class="card border-0 mb-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div class="p-5">
      <h6 class="font-semibold mb-4"><i class="bi bi-sliders mr-1" style="color:#6366f1;"></i> การตั้งค่า</h6>

      {{-- Master toggle --}}
      <div class="mb-5 p-4 rounded-lg" style="background:rgba(239,68,68,0.04);border:1px solid rgba(239,68,68,0.15);">
        <label class="flex items-start gap-3 cursor-pointer">
          <input type="checkbox" name="event_auto_delete_enabled" value="1"
                 {{ $settings['event_auto_delete_enabled'] === '1' ? 'checked' : '' }}
                 class="mt-1" style="width:18px;height:18px;accent-color:#ef4444;">
          <div>
            <div class="font-semibold text-sm">เปิดใช้งาน Auto-Delete</div>
            <div class="text-xs text-gray-500 mt-1">
              เมื่อเปิดแล้ว ระบบจะเริ่มลบอีเวนต์ตามกฎที่ตั้งไว้ในคืนถัดไป (02:30 น.) — แนะนำให้รัน Preview ก่อนเสมอ
            </div>
          </div>
        </label>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        {{-- Default retention days --}}
        <div>
          <label class="block text-sm font-semibold mb-1.5">จำนวนวันเก็บข้อมูลเริ่มต้น</label>
          <div class="flex items-center gap-2">
            <input type="number" name="event_default_retention_days" min="1" max="3650"
                   value="{{ $settings['event_default_retention_days'] }}"
                   class="w-32 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100">
            <span class="text-sm text-gray-500">วัน</span>
          </div>
          <p class="text-xs text-gray-500 mt-1">อีเวนต์ที่เก่ากว่านี้จะถูกลบ (สามารถ override รายตัวได้ในหน้าแก้ไขอีเวนต์)</p>
        </div>

        {{-- From field --}}
        <div>
          <label class="block text-sm font-semibold mb-1.5">นับอายุจากวันที่</label>
          <select name="event_auto_delete_from_field"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100">
            <option value="shoot_date" {{ $settings['event_auto_delete_from_field'] === 'shoot_date' ? 'selected' : '' }}>
              วันถ่าย (shoot_date) — แนะนำ
            </option>
            <option value="created_at" {{ $settings['event_auto_delete_from_field'] === 'created_at' ? 'selected' : '' }}>
              วันที่สร้างอีเวนต์ (created_at)
            </option>
          </select>
          <p class="text-xs text-gray-500 mt-1">ถ้าเลือก shoot_date และไม่มีข้อมูล ระบบจะ fallback ไปใช้ created_at</p>
        </div>

        {{-- Warn days --}}
        <div>
          <label class="block text-sm font-semibold mb-1.5">แจ้งเตือนช่างภาพล่วงหน้า</label>
          <div class="flex items-center gap-2">
            <input type="number" name="event_auto_delete_warn_days" min="0" max="90"
                   value="{{ $settings['event_auto_delete_warn_days'] }}"
                   class="w-32 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100">
            <span class="text-sm text-gray-500">วันก่อนลบ</span>
          </div>
          <p class="text-xs text-gray-500 mt-1">ส่งอีเมลแจ้งช่างภาพ N วันก่อนลบ (0 = ไม่แจ้งเตือน)</p>
        </div>

        {{-- Batch limit --}}
        <div>
          <label class="block text-sm font-semibold mb-1.5">จำกัดต่อครั้ง</label>
          <div class="flex items-center gap-2">
            <input type="number" name="event_auto_delete_batch_limit" min="1" max="10000"
                   value="{{ $settings['event_auto_delete_batch_limit'] }}"
                   class="w-32 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100">
            <span class="text-sm text-gray-500">อีเวนต์ต่อการรันหนึ่งครั้ง</span>
          </div>
          <p class="text-xs text-gray-500 mt-1">ป้องกันการลบจำนวนมากเกินไปในคืนเดียว (ส่วนที่เกินจะรอคืนถัดไป)</p>
        </div>
      </div>

      {{-- Safety toggles --}}
      <div class="mt-5 pt-5 border-t border-gray-200 dark:border-white/10 space-y-3">
        <label class="flex items-start gap-3 cursor-pointer">
          <input type="checkbox" name="event_auto_delete_skip_if_orders" value="1"
                 {{ $settings['event_auto_delete_skip_if_orders'] === '1' ? 'checked' : '' }}
                 class="mt-1" style="width:16px;height:16px;accent-color:#10b981;">
          <div>
            <div class="text-sm font-medium">ข้ามอีเวนต์ที่มีออเดอร์จ่ายแล้ว <span class="text-green-600 text-xs">(แนะนำ)</span></div>
            <div class="text-xs text-gray-500">ปกป้องประวัติรายได้ — ถ้ามีออเดอร์สถานะ paid / completed / processing จะไม่ลบ</div>
          </div>
        </label>

        <label class="flex items-start gap-3 cursor-pointer">
          <input type="checkbox" name="event_auto_delete_purge_drive" value="1"
                 {{ $settings['event_auto_delete_purge_drive'] === '1' ? 'checked' : '' }}
                 class="mt-1" style="width:16px;height:16px;accent-color:#ef4444;">
          <div>
            <div class="text-sm font-medium">ลบโฟลเดอร์ Google Drive ด้วย <span class="text-red-600 text-xs">(ลบไฟล์ต้นฉบับ ไม่สามารถกู้คืนได้)</span></div>
            <div class="text-xs text-gray-500">เมื่อปิดไว้ จะลบเฉพาะรายการใน DB + cover image บน local disk</div>
          </div>
        </label>
      </div>

      {{-- ── Tier-based retention ───────────────────────────────────── --}}
      <div class="mt-5 pt-5 border-t border-gray-200 dark:border-white/10">
        <h6 class="font-semibold mb-1 text-sm"><i class="bi bi-layers mr-1" style="color:#8b5cf6;"></i> Retention รายแพ็คเก็จ (Tier) <span class="text-xs text-gray-500 font-normal">— แทนที่ค่าเริ่มต้นด้านบน</span></h6>
        <p class="text-xs text-gray-500 mb-4">
          กำหนดจำนวนวันเก็บข้อมูลตามแพ็คเก็จของช่างภาพ — ช่างภาพ <em>Free</em> จะเก็บได้สั้นที่สุด, <em>Pro</em> เก็บได้นานที่สุด (สร้างแรงจูงใจให้อัปเกรด)
          ลำดับการใช้ค่า: <code class="text-xs">exempt → auto_delete_at → event.retention_days_override → tier → default</code>
        </p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
          {{-- Creator (Free) --}}
          <div class="p-4 rounded-lg" style="background:rgba(99,102,241,0.04);border:1px solid rgba(99,102,241,0.15);">
            <div class="flex items-center gap-2 mb-2">
              <span class="px-2 py-0.5 rounded text-[11px] font-semibold" style="background:rgba(99,102,241,0.15);color:#4f46e5;">CREATOR (Free)</span>
            </div>
            <label class="block text-xs font-medium mb-1.5 text-gray-600 dark:text-gray-300">เก็บข้อมูลนาน</label>
            <div class="flex items-center gap-2">
              <input type="number" name="retention_days_creator" min="0" max="3650"
                     value="{{ $settings['retention_days_creator'] ?? 7 }}"
                     class="w-24 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100">
              <span class="text-sm text-gray-500">วัน</span>
            </div>
            <p class="text-[11px] text-gray-500 mt-2">แนะนำ: 7 วัน — สั้นพอให้รู้สึกอยากอัปเกรด</p>
          </div>

          {{-- Seller --}}
          <div class="p-4 rounded-lg" style="background:rgba(16,185,129,0.04);border:1px solid rgba(16,185,129,0.15);">
            <div class="flex items-center gap-2 mb-2">
              <span class="px-2 py-0.5 rounded text-[11px] font-semibold" style="background:rgba(16,185,129,0.15);color:#059669;">SELLER</span>
            </div>
            <label class="block text-xs font-medium mb-1.5 text-gray-600 dark:text-gray-300">เก็บข้อมูลนาน</label>
            <div class="flex items-center gap-2">
              <input type="number" name="retention_days_seller" min="0" max="3650"
                     value="{{ $settings['retention_days_seller'] ?? 30 }}"
                     class="w-24 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100">
              <span class="text-sm text-gray-500">วัน</span>
            </div>
            <p class="text-[11px] text-gray-500 mt-2">แนะนำ: 30 วัน — ครอบคลุมรอบการขายเกือบทั้งหมด</p>
          </div>

          {{-- Pro --}}
          <div class="p-4 rounded-lg" style="background:rgba(245,158,11,0.04);border:1px solid rgba(245,158,11,0.15);">
            <div class="flex items-center gap-2 mb-2">
              <span class="px-2 py-0.5 rounded text-[11px] font-semibold" style="background:rgba(245,158,11,0.15);color:#d97706;">PRO</span>
            </div>
            <label class="block text-xs font-medium mb-1.5 text-gray-600 dark:text-gray-300">เก็บข้อมูลนาน</label>
            <div class="flex items-center gap-2">
              <input type="number" name="retention_days_pro" min="0" max="3650"
                     value="{{ $settings['retention_days_pro'] ?? 90 }}"
                     class="w-24 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100">
              <span class="text-sm text-gray-500">วัน</span>
            </div>
            <p class="text-[11px] text-gray-500 mt-2">แนะนำ: 90 วัน — สำหรับช่างภาพที่ขายยาวนาน</p>
          </div>
        </div>

        {{-- Warning email toggle --}}
        <div class="p-4 rounded-lg" style="background:rgba(245,158,11,0.04);border:1px solid rgba(245,158,11,0.15);">
          <label class="flex items-start gap-3 cursor-pointer mb-3">
            <input type="checkbox" name="retention_warning_enabled" value="1"
                   {{ ($settings['retention_warning_enabled'] ?? '1') === '1' ? 'checked' : '' }}
                   class="mt-1" style="width:16px;height:16px;accent-color:#f59e0b;">
            <div>
              <div class="text-sm font-medium"><i class="bi bi-envelope mr-1" style="color:#f59e0b;"></i> ส่งอีเมลเตือนช่างภาพก่อนลบ</div>
              <div class="text-xs text-gray-500">ระบบจะส่งอีเมลถึงช่างภาพที่กำลังจะถูกลบอีเวนต์อัตโนมัติ เพื่อให้มีโอกาสอัปเกรดแพ็คเก็จหรือปักหมุดรายตัว</div>
            </div>
          </label>
          <div class="ml-7">
            <label class="block text-xs font-medium mb-1.5 text-gray-600 dark:text-gray-300">แจ้งเตือนล่วงหน้า</label>
            <div class="flex items-center gap-2">
              <input type="number" name="retention_warning_days_ahead" min="0" max="30"
                     value="{{ $settings['retention_warning_days_ahead'] ?? 1 }}"
                     class="w-24 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100">
              <span class="text-sm text-gray-500">วันก่อนลบ</span>
            </div>
            <p class="text-[11px] text-gray-500 mt-1">ระบบส่งอีเมลกลุ่ม (หนึ่งอีเมลต่อช่างภาพต่อรอบ) — กำหนดไม่ให้ spam</p>
          </div>
        </div>
      </div>
    </div>

    <div class="p-4 border-t border-gray-200 dark:border-white/10 flex items-center gap-2">
      <button type="submit" class="text-sm px-4 py-2 rounded-lg font-medium" style="background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border:none;">
        <i class="bi bi-save mr-1"></i> บันทึกการตั้งค่า
      </button>

      <button type="button" id="previewBtn" class="text-sm px-4 py-2 rounded-lg font-medium" style="background:rgba(245,158,11,0.1);color:#d97706;border:1px solid rgba(245,158,11,0.2);">
        <i class="bi bi-eye mr-1"></i> Preview (Dry-Run)
      </button>

      <span class="text-xs text-gray-400 ml-auto">
        <i class="bi bi-clock mr-1"></i> Scheduled daily at 02:30
      </span>
    </div>
  </div>
</form>

{{-- ── Dry-run preview area ────────────────────────────────────────── --}}
<div id="previewResult" class="hidden card border-0 mb-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
  <div class="p-5">
    <div class="flex items-center justify-between mb-3">
      <h6 class="font-semibold mb-0"><i class="bi bi-list-check mr-1" style="color:#f59e0b;"></i> Preview — อีเวนต์ที่จะถูกลบ</h6>
      <span id="previewCount" class="text-xs font-semibold px-2 py-1 rounded" style="background:rgba(245,158,11,0.1);color:#d97706;"></span>
    </div>
    <div id="previewEmpty" class="hidden text-sm text-gray-500 py-8 text-center">
      <i class="bi bi-check-circle text-2xl mb-2" style="color:#10b981;"></i>
      <div>ไม่มีอีเวนต์เข้าเงื่อนไขลบอัตโนมัติตามกฎปัจจุบัน</div>
    </div>
    <div id="previewTable" class="overflow-x-auto"></div>
  </div>
</div>

{{-- Manual run hint --}}
<div class="card border-0" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);background:rgba(15,23,42,0.02);">
  <div class="p-5">
    <h6 class="font-semibold mb-2 text-sm"><i class="bi bi-terminal mr-1"></i> สั่งรันจาก CLI</h6>
    <div class="bg-gray-900 text-green-400 p-3 rounded-lg font-mono text-xs overflow-x-auto">
      <div><span class="text-gray-500"># Preview only</span></div>
      <div>php artisan events:purge-expired --dry-run</div>
      <div class="mt-2"><span class="text-gray-500"># Override TTL ตอนรัน</span></div>
      <div>php artisan events:purge-expired --days=180 --limit=10</div>
      <div class="mt-2"><span class="text-gray-500"># ลบอีเวนต์เฉพาะตัว</span></div>
      <div>php artisan events:purge-expired --event=123 --force</div>
    </div>
  </div>
</div>

<script>
document.getElementById('previewBtn').addEventListener('click', async () => {
  const btn    = document.getElementById('previewBtn');
  const result = document.getElementById('previewResult');
  const count  = document.getElementById('previewCount');
  const empty  = document.getElementById('previewEmpty');
  const table  = document.getElementById('previewTable');
  const origText = btn.innerHTML;

  btn.innerHTML = '<i class="bi bi-hourglass-split mr-1"></i> กำลังคำนวณ…';
  btn.disabled = true;

  try {
    const res = await fetch(@json(route('admin.settings.retention.preview')), {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await res.json();

    result.classList.remove('hidden');
    count.textContent = data.due_count + ' อีเวนต์';

    if (data.due_count === 0) {
      empty.classList.remove('hidden');
      table.innerHTML = '';
    } else {
      empty.classList.add('hidden');
      const rows = data.events.map(e => `
        <tr class="border-b border-gray-100 dark:border-white/10">
          <td class="py-2 px-3 text-xs">#${e.id}</td>
          <td class="py-2 px-3 text-sm font-medium">${escapeHtml(e.name || '(ไม่มีชื่อ)')}</td>
          <td class="py-2 px-3 text-xs text-gray-500">${e.shoot_date || '—'}</td>
          <td class="py-2 px-3 text-xs text-gray-500">${e.eta}</td>
          <td class="py-2 px-3 text-xs">
            <span style="color:${e.days_overdue > 30 ? '#ef4444' : '#f59e0b'};">
              ${e.days_overdue} วัน
            </span>
          </td>
          <td class="py-2 px-3 text-xs">
            ${e.would_skip
              ? '<span class="px-2 py-0.5 rounded" style="background:rgba(16,185,129,0.1);color:#059669;">SKIP (has orders)</span>'
              : '<span class="px-2 py-0.5 rounded" style="background:rgba(239,68,68,0.1);color:#dc2626;">DELETE</span>'
            }
          </td>
        </tr>
      `).join('');

      table.innerHTML = `
        <table class="w-full text-left">
          <thead><tr class="text-xs text-gray-500 border-b border-gray-200 dark:border-white/10">
            <th class="py-2 px-3 font-medium">ID</th>
            <th class="py-2 px-3 font-medium">ชื่ออีเวนต์</th>
            <th class="py-2 px-3 font-medium">วันถ่าย</th>
            <th class="py-2 px-3 font-medium">วันที่จะลบ</th>
            <th class="py-2 px-3 font-medium">เกินมา</th>
            <th class="py-2 px-3 font-medium">สถานะ</th>
          </tr></thead>
          <tbody>${rows}</tbody>
        </table>
      `;
    }
  } catch (err) {
    alert('ไม่สามารถโหลด preview ได้: ' + err.message);
  } finally {
    btn.innerHTML = origText;
    btn.disabled = false;
  }
});

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}
</script>
@endsection
