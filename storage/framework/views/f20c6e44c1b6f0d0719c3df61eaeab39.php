<?php $__env->startSection('title', 'กำหนดแบรนด์'); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('photographer.partials.page-hero', [
  'icon'     => 'bi-palette',
  'eyebrow'  => 'บริการเสริม',
  'title'    => 'กำหนดแบรนด์',
  'subtitle' => 'โลโก้ · สีประจำ · ลายน้ำ · ซ่อนเครดิตแพลตฟอร์ม (Business+)',
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php if(session('success')): ?>
  <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm px-4 py-3">
    <i class="bi bi-check-circle-fill mr-1.5"></i><?php echo e(session('success')); ?>

  </div>
<?php endif; ?>
<?php if(session('error')): ?>
  <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 text-rose-900 text-sm px-4 py-3">
    <i class="bi bi-exclamation-triangle-fill mr-1.5"></i><?php echo e(session('error')); ?>

  </div>
<?php endif; ?>

<?php if(!$canCustom): ?>
<div class="rounded-xl border border-amber-200 bg-amber-50 p-5 mb-6">
  <p class="font-semibold text-amber-900">
    <i class="bi bi-lock-fill mr-1.5"></i> ฟีเจอร์ Custom Branding ยังไม่เปิดใช้
  </p>
  <p class="text-sm text-amber-800 mt-2">
    กำหนดแบรนด์ (โลโก้, สีประจำ, ลายน้ำ) เปิดสำหรับแผน Business ขึ้นไป —
    <a href="<?php echo e(route('photographer.subscription.plans')); ?>" class="font-medium underline">อัปเกรดเพื่อปลดล็อก</a>
  </p>
</div>
<?php endif; ?>

<form method="POST" action="<?php echo e(route('photographer.branding.update')); ?>" enctype="multipart/form-data" class="space-y-6">
  <?php echo csrf_field(); ?>

  
  <div class="pg-card p-5">
    <h5 class="font-semibold text-gray-900 mb-4">
      <i class="bi bi-image mr-1.5 text-indigo-500"></i>โลโก้แบรนด์
    </h5>
    <?php if($settings->logo_path): ?>
      <div class="flex items-center gap-4 mb-4">
        <img src="<?php echo e(app(\App\Services\StorageManager::class)->url($settings->logo_path)); ?>"
             alt="Logo" class="h-16 w-auto rounded border border-gray-200 bg-gray-50 p-2">
        <form method="POST" action="<?php echo e(route('photographer.branding.logo.remove')); ?>" class="inline">
          <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
          <button class="text-xs text-rose-600 hover:underline">ลบโลโก้</button>
        </form>
      </div>
    <?php endif; ?>
    <input type="file" name="logo" accept="image/*"
           <?php echo e($canCustom ? '' : 'disabled'); ?>

           class="block text-sm text-gray-700 file:mr-3 file:px-3 file:py-1.5 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700 file:text-xs file:font-medium hover:file:bg-indigo-100">
    <p class="text-xs text-gray-500 mt-2">PNG, JPG, SVG, WebP — ขนาดไม่เกิน 2 MB</p>
  </div>

  
  <div class="pg-card p-5">
    <h5 class="font-semibold text-gray-900 mb-4">
      <i class="bi bi-droplet mr-1.5 text-indigo-500"></i>สีประจำแบรนด์
    </h5>
    <div class="flex items-center gap-3">
      <input type="color"
             name="accent_hex"
             value="<?php echo e($settings->accent_hex ?? '#6366f1'); ?>"
             <?php echo e($canCustom ? '' : 'disabled'); ?>

             class="h-10 w-20 rounded border-gray-300 cursor-pointer">
      <input type="text" name="accent_hex"
             value="<?php echo e($settings->accent_hex); ?>"
             pattern="#[0-9A-Fa-f]{6}"
             placeholder="#6366f1"
             <?php echo e($canCustom ? '' : 'disabled'); ?>

             class="rounded-lg border-gray-300 text-sm font-mono w-32 focus:border-indigo-500 focus:ring-indigo-500"
             oninput="this.previousElementSibling.value=this.value;">
    </div>
    <p class="text-xs text-gray-500 mt-2">ใช้ในปุ่ม CTA และเส้นขอบของหน้า public event</p>
  </div>

  
  <div class="pg-card p-5">
    <h5 class="font-semibold text-gray-900 mb-4">
      <i class="bi bi-droplet-half mr-1.5 text-indigo-500"></i>ลายน้ำ
    </h5>
    <label class="inline-flex items-center gap-2 mb-3">
      <input type="checkbox" name="watermark_enabled" value="1"
             <?php if($settings->watermark_enabled): echo 'checked'; endif; ?>
             <?php echo e($canCustom ? '' : 'disabled'); ?>

             class="rounded text-indigo-600 focus:ring-indigo-500">
      <span class="text-sm text-gray-700">เปิดใช้ลายน้ำในภาพพรีวิว</span>
    </label>
    <input type="text" name="watermark_text"
           value="<?php echo e($settings->watermark_text); ?>"
           maxlength="80"
           placeholder="© ชื่อสตูดิโอ ของคุณ"
           <?php echo e($canCustom ? '' : 'disabled'); ?>

           class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
  </div>

  
  <div class="pg-card p-5">
    <div class="flex items-start justify-between mb-4">
      <h5 class="font-semibold text-gray-900">
        <i class="bi bi-incognito mr-1.5 text-indigo-500"></i>White-label (Studio)
      </h5>
      <?php if(!$canWhiteLabel): ?>
        <span class="text-[11px] px-2 py-0.5 rounded bg-gray-100 text-gray-600">เปิดสำหรับแผน Studio</span>
      <?php endif; ?>
    </div>
    <label class="inline-flex items-start gap-2 mb-4">
      <input type="checkbox" name="hide_platform_credits" value="1"
             <?php if($settings->hide_platform_credits): echo 'checked'; endif; ?>
             <?php echo e($canWhiteLabel ? '' : 'disabled'); ?>

             class="mt-1 rounded text-indigo-600 focus:ring-indigo-500">
      <span class="text-sm text-gray-700">
        ซ่อน "Powered by" ในหน้าสาธารณะ
        <span class="block text-xs text-gray-500">ลูกค้าของคุณจะไม่เห็นแบรนด์ของแพลตฟอร์มในหน้า event/portfolio</span>
      </span>
    </label>
    <div>
      <label class="text-xs text-gray-600 mb-1 block">Custom Domain (optional)</label>
      <input type="text" name="custom_domain"
             value="<?php echo e($settings->custom_domain); ?>"
             placeholder="gallery.yourstudio.com"
             <?php echo e($canWhiteLabel ? '' : 'disabled'); ?>

             class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
      <p class="text-xs text-gray-500 mt-1">ทำ CNAME มาที่ระบบเรา — แอดมินยืนยันก่อนใช้งาน</p>
    </div>
  </div>

  <div class="flex justify-end">
    <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
            <?php echo e($canCustom ? '' : 'disabled'); ?>>
      <i class="bi bi-save"></i> บันทึก
    </button>
  </div>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.photographer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/photographer/branding/edit.blade.php ENDPATH**/ ?>