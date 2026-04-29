{{--
    Plan-required modal for the Lightroom Presets feature.

    Drop this anywhere on a page that interacts with the preset endpoints
    (the index card list, the editor's live-preview pane, etc.). The
    helpers `openPresetUpgradeModal()` / `closePresetUpgradeModal()` are
    exposed on `window` so any other script on the page can trigger the
    modal — typically inside a `fetch()` 402 handler that detects
    `code: 'plan_required'` in the JSON body.

    Auto-opens when:
      • URL has `?upgrade=presets` (set by controller-side bounce on
        non-AJAX form POSTs).
      • Session flash `preset_upgrade_required` is present (set on the
        same bounce, lets us show the modal even if the URL was
        normalised).
--}}
<div id="presetUpgradeModal"
     class="fixed inset-0 z-[1080] hidden items-center justify-center bg-black/50 backdrop-blur-sm p-4"
     role="dialog" aria-modal="true" aria-labelledby="presetUpgradeTitle">
  <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden"
       onclick="event.stopPropagation()">
    {{-- Gradient header (matches auth-flow theme) --}}
    <div class="px-6 py-5 text-white relative overflow-hidden"
         style="background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 50%,#ec4899 100%);">
      <div class="absolute -top-8 -right-8 w-32 h-32 rounded-full bg-white/10"></div>
      <div class="absolute -bottom-10 -left-6 w-28 h-28 rounded-full bg-white/10"></div>
      <div class="relative flex items-start gap-3">
        <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center backdrop-blur-sm">
          <i class="bi bi-stars text-2xl"></i>
        </div>
        <div class="flex-1">
          <p class="text-xs font-semibold uppercase tracking-wider opacity-90">ต้องอัปเกรดแผน</p>
          <h3 id="presetUpgradeTitle" class="text-xl font-bold mt-0.5">Lightroom Presets</h3>
          <p class="text-sm opacity-95 mt-1">ปรับโทนสีของรูปทั้งงานด้วยคลิกเดียว</p>
        </div>
        <button type="button" onclick="closePresetUpgradeModal()"
                class="absolute top-0 right-0 w-9 h-9 rounded-lg flex items-center justify-center hover:bg-white/20 transition"
                aria-label="ปิด">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    </div>

    {{-- Body --}}
    <div class="px-6 py-5">
      <p class="text-sm text-gray-700 leading-relaxed">
        ฟีเจอร์ <strong>Lightroom Presets</strong> เปิดให้ใช้สำหรับสมาชิกแผน
        <span class="font-semibold text-indigo-700">Starter</span>,
        <span class="font-semibold text-indigo-700">Pro</span>,
        <span class="font-semibold text-indigo-700">Business</span> และ
        <span class="font-semibold text-indigo-700">Studio</span> เท่านั้น —
        แผน Free ไม่รองรับฟีเจอร์นี้
      </p>

      <ul class="mt-4 space-y-2 text-sm text-gray-700">
        <li class="flex items-start gap-2">
          <i class="bi bi-check-circle-fill text-emerald-500 mt-0.5"></i>
          <span>ใช้ <strong>preset สำเร็จรูป</strong> ของระบบ 8 ตัว (Cinematic, Wedding Bright, ฯลฯ)</span>
        </li>
        <li class="flex items-start gap-2">
          <i class="bi bi-check-circle-fill text-emerald-500 mt-0.5"></i>
          <span><strong>นำเข้า .xmp</strong> จาก Lightroom Classic / Mobile ได้ทุกตัว</span>
        </li>
        <li class="flex items-start gap-2">
          <i class="bi bi-check-circle-fill text-emerald-500 mt-0.5"></i>
          <span>ตั้ง <strong>preset อัตโนมัติ</strong> รูปอัปโหลดใหม่ใช้ preset นี้ทันที</span>
        </li>
        <li class="flex items-start gap-2">
          <i class="bi bi-check-circle-fill text-emerald-500 mt-0.5"></i>
          <span>ใช้กับ <strong>ทุกรูปในงาน</strong> ด้วยคลิกเดียว (Bulk apply)</span>
        </li>
      </ul>

      <div class="mt-5 rounded-xl bg-indigo-50 border border-indigo-100 px-4 py-3 text-sm text-indigo-800">
        <i class="bi bi-lightbulb-fill mr-1.5"></i>
        เริ่มต้นเพียง <strong>฿299/เดือน</strong> (Starter) — รวม Face Search, AI คัดรูปเบลอ และ Presets ครบ
      </div>
    </div>

    {{-- Footer actions --}}
    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-2">
      <button type="button" onclick="closePresetUpgradeModal()"
              class="px-4 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100 transition">
        ปิดหน้าต่าง
      </button>
      <a href="{{ route('photographer.subscription.plans') }}"
         class="inline-flex items-center gap-2 px-5 py-2 rounded-lg text-sm font-medium text-white shadow-sm hover:shadow-md transition"
         style="background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 50%,#ec4899 100%);">
        <i class="bi bi-stars"></i> ดูแผน / อัปเกรด
      </a>
    </div>
  </div>
</div>

@if(session('preset_upgrade_required') || request('upgrade') === 'presets')
<script>window.__presetUpgradeAutoOpen = true;</script>
@endif

<script>
(function () {
  // Idempotent — partial may be included on multiple pages and we don't
  // want duplicate listeners.
  if (window.__presetUpgradeModalReady) return;
  window.__presetUpgradeModalReady = true;

  window.openPresetUpgradeModal = function () {
    const m = document.getElementById('presetUpgradeModal');
    if (!m) return;
    m.classList.remove('hidden');
    m.classList.add('flex');
    document.body.style.overflow = 'hidden';
  };
  window.closePresetUpgradeModal = function () {
    const m = document.getElementById('presetUpgradeModal');
    if (!m) return;
    m.classList.add('hidden');
    m.classList.remove('flex');
    document.body.style.overflow = '';
  };

  document.addEventListener('DOMContentLoaded', () => {
    const m = document.getElementById('presetUpgradeModal');
    if (m) m.addEventListener('click', () => window.closePresetUpgradeModal());
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') window.closePresetUpgradeModal();
    });
    if (window.__presetUpgradeAutoOpen) {
      setTimeout(window.openPresetUpgradeModal, 120);
    }
  });
})();
</script>
