<?php $__env->startSection('title', $isNew ? 'สร้างแคมเปญใหม่' : 'แก้ไขแคมเปญ'); ?>
<?php $__env->startSection('content'); ?>
<div class="max-w-3xl mx-auto px-4 py-6">
  <a href="<?php echo e(route('admin.monetization.dashboard')); ?>" class="text-xs text-slate-500 hover:underline">← Monetization</a>
  <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mb-5"><?php echo e($isNew ? 'สร้างแคมเปญ Brand Ad' : 'แก้ไขแคมเปญ'); ?></h1>

  <?php if($errors->any()): ?>
    <div class="mb-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm">
      <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div>· <?php echo e($e); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="<?php echo e(route('admin.monetization.campaigns.store')); ?>" class="space-y-5">
    <?php echo csrf_field(); ?>
    <div class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 p-5 space-y-4">
      <div>
        <label class="block text-xs font-bold text-slate-600 mb-1">ชื่อแคมเปญ *</label>
        <input type="text" name="name" required value="<?php echo e(old('name', $campaign->name)); ?>"
               class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">Brand / Advertiser *</label>
          <input type="text" name="advertiser" required value="<?php echo e(old('advertiser', $campaign->advertiser)); ?>"
                 class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">อีเมลผู้ลงโฆษณา</label>
          <input type="email" name="contact_email" value="<?php echo e(old('contact_email', $campaign->contact_email)); ?>"
                 class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">Pricing Model *</label>
          <select name="pricing_model" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
            <option value="cpm"          <?php echo e(old('pricing_model', $campaign->pricing_model) === 'cpm' ? 'selected' : ''); ?>>CPM (ต่อ 1,000 impressions)</option>
            <option value="cpc"          <?php echo e(old('pricing_model', $campaign->pricing_model) === 'cpc' ? 'selected' : ''); ?>>CPC (ต่อคลิก)</option>
            <option value="flat_daily"   <?php echo e(old('pricing_model', $campaign->pricing_model) === 'flat_daily' ? 'selected' : ''); ?>>Flat (รายวัน)</option>
            <option value="flat_monthly" <?php echo e(old('pricing_model', $campaign->pricing_model) === 'flat_monthly' ? 'selected' : ''); ?>>Flat (รายเดือน)</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">Rate (฿) *</label>
          <input type="number" name="rate_thb" step="0.01" min="0" required value="<?php echo e(old('rate_thb', $campaign->rate_thb)); ?>"
                 class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">Budget Cap (฿) <span class="text-slate-400 text-[10px]">เว้นว่าง = ไม่จำกัด</span></label>
          <input type="number" name="budget_cap_thb" step="0.01" min="0" value="<?php echo e(old('budget_cap_thb', $campaign->budget_cap_thb)); ?>"
                 class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">เริ่มต้น *</label>
          <input type="datetime-local" name="starts_at" required
                 value="<?php echo e(old('starts_at', $campaign->starts_at?->format('Y-m-d\\TH:i'))); ?>"
                 class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">สิ้นสุด *</label>
          <input type="datetime-local" name="ends_at" required
                 value="<?php echo e(old('ends_at', $campaign->ends_at?->format('Y-m-d\\TH:i'))); ?>"
                 class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">สถานะ *</label>
          <select name="status" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
            <option value="pending"   <?php echo e(old('status', $campaign->status) === 'pending' ? 'selected' : ''); ?>>pending</option>
            <option value="active"    <?php echo e(old('status', $campaign->status) === 'active' ? 'selected' : ''); ?>>active</option>
            <option value="paused"    <?php echo e(old('status', $campaign->status) === 'paused' ? 'selected' : ''); ?>>paused</option>
            <option value="exhausted" <?php echo e(old('status', $campaign->status) === 'exhausted' ? 'selected' : ''); ?>>exhausted</option>
            <option value="ended"     <?php echo e(old('status', $campaign->status) === 'ended' ? 'selected' : ''); ?>>ended</option>
          </select>
        </div>
      </div>
    </div>

    <div class="flex items-center gap-2">
      <button class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">
        <i class="bi bi-save"></i> สร้างแคมเปญ
      </button>
      <a href="<?php echo e(route('admin.monetization.dashboard')); ?>" class="text-sm text-slate-500 hover:underline ml-2">ยกเลิก</a>
    </div>
  </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/admin/monetization/campaign-form.blade.php ENDPATH**/ ?>