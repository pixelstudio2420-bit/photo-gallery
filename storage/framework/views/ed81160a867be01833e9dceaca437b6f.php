<?php $__env->startSection('title', 'Lightroom Presets'); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('photographer.partials.page-hero', [
  'icon'     => 'bi-sliders',
  'eyebrow'  => 'บริการเสริม',
  'title'    => 'Lightroom Presets',
  'subtitle' => 'ปรับสีรูปทั้งงานด้วยคลิกเดียว · ใช้ preset สำเร็จรูป สร้างเอง หรือ import จาก Lightroom',
  'actions'  => $allowed
    ? '<a href="'.route('photographer.presets.create').'" class="pg-btn-primary"><i class="bi bi-plus-lg"></i> สร้างใหม่</a>'
    : '<button type="button" onclick="openPresetUpgradeModal()" class="pg-btn-primary opacity-90"><i class="bi bi-lock-fill"></i> สร้างใหม่ (อัปเกรด)</button>',
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php if(session('success')): ?>
  <div class="pg-alert pg-alert--success mb-4">
    <i class="bi bi-check-circle-fill"></i>
    <div><?php echo e(session('success')); ?></div>
  </div>
<?php endif; ?>
<?php if(session('error')): ?>
  <div class="pg-alert pg-alert--danger mb-4">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div><?php echo e(session('error')); ?></div>
  </div>
<?php endif; ?>

<?php if(!$allowed): ?>
<div class="pg-card pg-card-padded pg-anim d1 mb-6 flex flex-col md:flex-row md:items-center gap-4 relative overflow-hidden"
     style="background:linear-gradient(135deg, rgba(251,191,36,.10), rgba(245,158,11,.06)); border-color:rgba(245,158,11,.3);">
  <span class="absolute top-0 right-0 w-40 h-40 pointer-events-none"
        style="background:radial-gradient(circle at top right, rgba(245,158,11,.18) 0%, transparent 70%);"></span>
  <div class="relative flex items-start gap-3 flex-1">
    <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-white flex items-center justify-center shadow-sm shrink-0">
      <i class="bi bi-lock-fill text-lg"></i>
    </div>
    <div>
      <p class="font-semibold text-amber-900 dark:text-amber-200">ฟีเจอร์ Lightroom Presets ยังไม่เปิดสำหรับแผนปัจจุบัน</p>
      <p class="text-sm text-amber-800 dark:text-amber-300/90 mt-1">
        เปิดใช้งานได้เมื่อสมัครแผน <strong>Starter / Pro / Business / Studio</strong> —
        ใช้ preset สำเร็จรูป สร้างเอง หรือ import จาก Lightroom ได้ไม่จำกัด
      </p>
    </div>
  </div>
  <div class="relative flex items-center gap-2 md:shrink-0">
    <button type="button" onclick="openPresetUpgradeModal()" class="pg-btn-ghost">
      <i class="bi bi-info-circle"></i> ดูรายละเอียด
    </button>
    <a href="<?php echo e(route('photographer.subscription.plans')); ?>"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gradient-to-br from-amber-500 to-orange-500 text-white text-sm font-medium hover:shadow-md no-underline">
      <i class="bi bi-stars"></i> ดูแผน / อัปเกรด
    </a>
  </div>
</div>
<?php endif; ?>


<?php if($defaultPresetId): ?>
  <?php $defaultPreset = $presets->firstWhere('id', $defaultPresetId); ?>
  <?php if($defaultPreset): ?>
    <div class="pg-card pg-card-padded pg-anim d2 mb-5 flex items-center justify-between gap-3 flex-wrap relative overflow-hidden"
         style="border-color:rgba(225,29,72,.4); border-width:2px; background:linear-gradient(135deg, rgba(244,63,94,.06), rgba(236,72,153,.04));">
      <div class="text-sm flex items-center gap-2 text-rose-900 dark:text-rose-200">
        <i class="bi bi-magic text-rose-600 dark:text-rose-400 text-lg"></i>
        <span><strong><?php echo e($defaultPreset->name); ?></strong> ตั้งเป็น preset เริ่มต้น — รูปที่อัปโหลดใหม่จะใช้ preset นี้อัตโนมัติ</span>
      </div>
      <form method="POST" action="<?php echo e(route('photographer.presets.clear-default')); ?>">
        <?php echo csrf_field(); ?>
        <button class="text-xs text-rose-700 dark:text-rose-300 hover:underline font-bold">
          <i class="bi bi-x-circle"></i> ยกเลิก auto-apply
        </button>
      </form>
    </div>
  <?php endif; ?>
<?php endif; ?>


<?php if($allowed): ?>
<details class="pg-card mb-6 group pg-anim d2">
  <summary class="cursor-pointer px-5 py-4 font-semibold text-sm text-gray-900 dark:text-gray-100 flex items-center gap-2 hover:bg-indigo-50/30 dark:hover:bg-white/[0.02] transition rounded-t-2xl">
    <i class="bi bi-cloud-upload text-rose-500"></i>
    Import .xmp จาก Lightroom
    <span class="ml-auto text-xs text-gray-400 group-open:rotate-90 transition-transform"><i class="bi bi-chevron-right"></i></span>
  </summary>
  <div class="px-5 pb-5 border-t border-gray-100 dark:border-white/[0.06] pt-4">
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
      ลาก preset ไฟล์ .xmp ที่ Export จาก Lightroom Classic / Mobile มาวางที่นี่
      (ระบบรองรับ Exposure, Contrast, Highlights, Shadows, Whites, Blacks, Vibrance, Saturation, Temperature, Tint, Clarity, Sharpness, B&W, Vignette — HSL ต่อสีและ tone curve ยังไม่รองรับเนื่องจากข้อจำกัดของ GD)
    </p>
    <form method="POST" action="<?php echo e(route('photographer.presets.import')); ?>" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-3 gap-3">
      <?php echo csrf_field(); ?>
      <input type="text" name="name" required maxlength="100" placeholder="ชื่อ preset"
             class="md:col-span-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-800 text-gray-700 dark:text-gray-100 text-sm focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20 px-3 py-2">
      <input type="file" name="xmp" required accept=".xmp,.xml,.txt"
             class="text-sm text-gray-700 dark:text-gray-300 file:mr-3 file:px-3 file:py-1.5 file:rounded file:border-0 file:bg-rose-50 dark:file:bg-rose-500/15 file:text-rose-700 dark:file:text-rose-300 file:text-xs file:font-medium">
      <div class="md:col-span-3 flex justify-end">
        <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700 transition">
          <i class="bi bi-cloud-upload"></i> Import
        </button>
      </div>
    </form>
  </div>
</details>
<?php endif; ?>


<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
  <?php $__empty_1 = true; $__currentLoopData = $presets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <?php $isDefault = $defaultPresetId == $p->id; ?>
    <div class="pg-card pg-card-hover overflow-hidden pg-anim <?php echo e($isDefault ? 'd1' : 'd'.(($loop->index % 4) + 1)); ?>"
         <?php if($isDefault): ?> style="border-color:rgba(225,29,72,.5); border-width:2px; box-shadow:0 12px 28px -8px rgba(244,63,94,.25);" <?php endif; ?>>
      <div class="aspect-[4/3] bg-gray-100 dark:bg-slate-800 relative">
        
        <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"
             data-pid="<?php echo e($p->id); ?>"
             class="w-full h-full object-cover preset-thumb bg-gray-200 dark:bg-slate-800"
             alt="<?php echo e($p->name); ?>">
        
        <div class="absolute inset-0 flex items-center justify-center text-gray-300 dark:text-gray-500 text-3xl pointer-events-none preset-thumb-loader">
          <i class="bi bi-arrow-clockwise animate-spin"></i>
        </div>
        <?php if($p->is_system): ?>
          <span class="absolute top-2 left-2 px-2 py-0.5 rounded bg-black/70 text-white text-[10px] font-bold tracking-wider">SYSTEM</span>
        <?php endif; ?>
        <?php if($isDefault): ?>
          <span class="absolute top-2 right-2 px-2 py-0.5 rounded bg-rose-600 text-white text-[10px] font-bold inline-flex items-center gap-1 shadow-md">
            <i class="bi bi-magic"></i> AUTO
          </span>
        <?php endif; ?>
      </div>
      <div class="p-4">
        <h6 class="font-bold text-gray-900 dark:text-gray-100 text-sm"><?php echo e($p->name); ?></h6>
        <?php if($p->description): ?>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2"><?php echo e($p->description); ?></p>
        <?php endif; ?>
        <div class="mt-3 flex flex-wrap gap-1.5">
          <?php $__currentLoopData = array_slice(array_filter(($p->settings ?? []), fn($v) => $v !== 0 && $v !== false), 0, 4, true); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <span class="text-[10px] px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 font-mono">
              <?php echo e($k); ?> <?php echo e(is_bool($v) ? 'ON' : ($v > 0 ? '+'.$v : $v)); ?>

            </span>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>

        <div class="mt-4 flex items-center gap-2 pt-3 border-t border-gray-100 dark:border-white/[0.06]">
          <?php if($p->is_system): ?>
            <?php if($allowed): ?>
              <form method="POST" action="<?php echo e(route('photographer.presets.duplicate', $p->id)); ?>">
                <?php echo csrf_field(); ?>
                <button class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-bold">
                  <i class="bi bi-files"></i> ทำเวอร์ชันของฉัน
                </button>
              </form>
            <?php else: ?>
              <button type="button" onclick="openPresetUpgradeModal()"
                      class="text-xs text-amber-700 dark:text-amber-300 hover:underline font-bold inline-flex items-center gap-1">
                <i class="bi bi-lock-fill"></i> ทำเวอร์ชันของฉัน
              </button>
            <?php endif; ?>
          <?php else: ?>
            <a href="<?php echo e(route('photographer.presets.edit', $p->id)); ?>" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-bold">
              <i class="bi bi-pencil"></i> แก้ไข
            </a>
            <form method="POST" action="<?php echo e(route('photographer.presets.destroy', $p->id)); ?>"
                  onsubmit="return confirm('ลบ preset <?php echo e($p->name); ?> ?');">
              <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
              <button class="text-xs text-rose-600 dark:text-rose-400 hover:underline font-bold">
                <i class="bi bi-trash"></i> ลบ
              </button>
            </form>
          <?php endif; ?>
          <?php if(!$isDefault): ?>
            <?php if($allowed): ?>
              <form method="POST" action="<?php echo e(route('photographer.presets.set-default', $p->id)); ?>" class="ml-auto">
                <?php echo csrf_field(); ?>
                <button class="text-xs text-rose-600 dark:text-rose-400 hover:underline font-bold">
                  <i class="bi bi-magic"></i> Auto-apply
                </button>
              </form>
            <?php else: ?>
              <button type="button" onclick="openPresetUpgradeModal()"
                      class="text-xs text-amber-700 dark:text-amber-300 hover:underline font-bold inline-flex items-center gap-1 ml-auto">
                <i class="bi bi-lock-fill"></i> Auto-apply
              </button>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <div class="md:col-span-2 lg:col-span-3 pg-card pg-anim d1">
      <div class="pg-empty">
        <div class="pg-empty-icon"><i class="bi bi-sliders"></i></div>
        <p class="font-medium">ยังไม่มี preset</p>
        <?php if($allowed): ?>
          <a href="<?php echo e(route('photographer.presets.create')); ?>" class="pg-btn-primary mt-3">
            <i class="bi bi-plus-lg"></i> สร้าง preset แรก
          </a>
        <?php else: ?>
          <p class="text-xs mt-1">ต้องอัปเกรดแผนเพื่อใช้งาน</p>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>


<?php echo $__env->make('photographer.presets.partials.upgrade-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<script>
// ─── Preset thumbnail rendering ─────────────────────────────────────
// Generate live thumbnails for each preset by POSTing the preset's
// settings to /preview and updating the <img> src. Done in serial (4 at
// a time) to avoid overloading the GD pipeline.
//
// Free-plan users can't hit /preview (it's gated), so we don't even try
// — we just render the static placeholder + a "ต้องอัปเกรด" overlay so
// the cards still render meaningfully.
document.addEventListener('DOMContentLoaded', () => {
  const allowed = <?php echo json_encode($allowed, 15, 512) ?>;
  const thumbs = Array.from(document.querySelectorAll('.preset-thumb[data-pid]'));

  // Free-plan path: skip the network entirely; show a friendly lock state
  if (!allowed) {
    thumbs.forEach((img) => {
      const card = img.closest('.aspect-\\[4\\/3\\]');
      const loader = card ? card.querySelector('.preset-thumb-loader') : null;
      if (loader) {
        loader.innerHTML =
          '<button type="button" onclick="openPresetUpgradeModal()" class="flex flex-col items-center gap-1 text-amber-600 hover:text-amber-700 pointer-events-auto">' +
          '  <i class="bi bi-lock-fill text-2xl"></i>' +
          '  <span class="text-[11px] font-medium">ต้องอัปเกรดเพื่อดู preview</span>' +
          '</button>';
        loader.classList.remove('pointer-events-none');
      }
    });
    return;
  }

  const presetSettings = <?php echo json_encode($presets->mapWithKeys(fn($p) => [$p->id => $p->settings])->all(), 15, 512) ?>;
  const csrf = '<?php echo e(csrf_token()); ?>';

  async function renderThumb(img) {
    const pid = img.getAttribute('data-pid');
    const settings = presetSettings[pid] || {};
    const card = img.closest('.aspect-\\[4\\/3\\]');
    const loader = card ? card.querySelector('.preset-thumb-loader') : null;
    try {
      const fd = new FormData();
      fd.append('_token', csrf);
      Object.entries(settings).forEach(([k, v]) => {
        fd.append(`settings[${k}]`, typeof v === 'boolean' ? (v ? '1' : '0') : v);
      });
      const resp = await fetch('<?php echo e(route('photographer.presets.preview')); ?>', {
        method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
      });

      // Plan-required (the Free plan should never reach here because we
      // bail out above, but if the admin flips the global feature flag
      // off mid-session this catches the resulting 402 cleanly).
      if (resp.status === 402) {
        const data = await resp.json().catch(() => null);
        if (data && data.code === 'plan_required') {
          openPresetUpgradeModal();
        }
        if (loader) loader.innerHTML = '<i class="bi bi-lock-fill text-amber-500 text-2xl"></i>';
        return;
      }

      if (resp.ok) {
        const blob = await resp.blob();
        img.src = URL.createObjectURL(blob);
        img.onload = () => { if (loader) loader.style.display = 'none'; };
      } else if (loader) {
        loader.innerHTML = '<i class="bi bi-image-alt text-2xl"></i>';
      }
    } catch (e) {
      if (loader) loader.innerHTML = '<i class="bi bi-exclamation-triangle text-rose-400 text-xl"></i>';
    }
  }

  // Process 4 at a time
  (async () => {
    const batchSize = 4;
    for (let i = 0; i < thumbs.length; i += batchSize) {
      await Promise.all(thumbs.slice(i, i + batchSize).map(renderThumb));
    }
  })();
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.photographer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/photographer/presets/index.blade.php ENDPATH**/ ?>