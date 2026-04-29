<?php $__env->startSection('title', 'ตรวจสอบภาพ'); ?>

<?php $__env->startSection('content'); ?>
<div class="space-y-5">

  
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-800 dark:text-gray-100">
        <i class="bi bi-shield-check text-indigo-500 mr-2"></i>ตรวจสอบเนื้อหาภาพ
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
        ตรวจสอบภาพที่ระบบ AI ติดธงไว้ — อนุมัติ/ปฏิเสธได้ตามดุลยพินิจแอดมิน
      </p>
    </div>
    <?php if($stats['flagged'] > 0): ?>
    <span class="px-4 py-2 bg-amber-500 text-white rounded-xl text-sm font-medium flex items-center gap-2">
      <i class="bi bi-flag-fill"></i> มีภาพรอตรวจ <?php echo e($stats['flagged']); ?> รายการ
    </span>
    <?php endif; ?>
  </div>

  
  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
    <?php
      $statCards = [
        ['key' => 'total',    'label' => 'ทั้งหมด',    'color' => 'slate',   'icon' => 'bi-images'],
        ['key' => 'pending',  'label' => 'รอสแกน',     'color' => 'blue',    'icon' => 'bi-hourglass-split'],
        ['key' => 'flagged',  'label' => 'ติดธง',      'color' => 'amber',   'icon' => 'bi-flag-fill'],
        ['key' => 'rejected', 'label' => 'ถูกปฏิเสธ',   'color' => 'red',     'icon' => 'bi-x-octagon-fill'],
        ['key' => 'approved', 'label' => 'อนุมัติ',    'color' => 'emerald', 'icon' => 'bi-check-circle-fill'],
        ['key' => 'skipped',  'label' => 'ยกเว้น',     'color' => 'gray',    'icon' => 'bi-slash-circle'],
      ];
    ?>
    <?php $__currentLoopData = $statCards; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <a href="<?php echo e(route('admin.moderation.index', ['status' => $c['key']])); ?>"
       class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4 hover:shadow-md transition
              <?php echo e($status === $c['key'] ? 'ring-2 ring-indigo-400' : ''); ?>">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($c['label']); ?></div>
          <div class="text-2xl font-bold text-<?php echo e($c['color']); ?>-600 dark:text-<?php echo e($c['color']); ?>-400">
            <?php echo e(number_format($stats[$c['key']])); ?>

          </div>
        </div>
        <i class="bi <?php echo e($c['icon']); ?> text-2xl text-<?php echo e($c['color']); ?>-300 dark:text-<?php echo e($c['color']); ?>-500/50"></i>
      </div>
    </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>

  
  <form method="GET" action="<?php echo e(route('admin.moderation.index')); ?>"
        class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4 flex flex-wrap gap-3 items-end">
    <div class="flex-1 min-w-[180px]">
      <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">สถานะ</label>
      <select name="status" class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 rounded-lg text-sm">
        <option value="all"      <?php if($status === 'all'): echo 'selected'; endif; ?>>ทั้งหมด</option>
        <option value="flagged"  <?php if($status === 'flagged'): echo 'selected'; endif; ?>>ติดธง</option>
        <option value="pending"  <?php if($status === 'pending'): echo 'selected'; endif; ?>>รอสแกน</option>
        <option value="rejected" <?php if($status === 'rejected'): echo 'selected'; endif; ?>>ถูกปฏิเสธ</option>
        <option value="approved" <?php if($status === 'approved'): echo 'selected'; endif; ?>>อนุมัติ</option>
        <option value="skipped"  <?php if($status === 'skipped'): echo 'selected'; endif; ?>>ยกเว้น</option>
      </select>
    </div>
    <div class="flex-1 min-w-[180px]">
      <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">อีเวนต์</label>
      <select name="event_id" class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 rounded-lg text-sm">
        <option value="">-- ทุกอีเวนต์ --</option>
        <?php $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <option value="<?php echo e($event->id); ?>" <?php if(request('event_id') == $event->id): echo 'selected'; endif; ?>>
          <?php echo e($event->name); ?>

        </option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </select>
    </div>
    <div class="min-w-[140px]">
      <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">คะแนน ≥</label>
      <input type="number" step="1" min="0" max="100" name="min_score" value="<?php echo e(request('min_score')); ?>"
             class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 rounded-lg text-sm">
    </div>
    <button type="submit" class="px-4 py-2 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg text-sm font-medium">
      <i class="bi bi-search mr-1"></i>กรอง
    </button>
    <?php if(request()->hasAny(['event_id', 'min_score'])): ?>
    <a href="<?php echo e(route('admin.moderation.index', ['status' => $status])); ?>"
       class="px-4 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5">
      ล้าง
    </a>
    <?php endif; ?>
  </form>

  
  <form method="POST" action="<?php echo e(route('admin.moderation.bulk')); ?>" id="bulkForm"
        class="bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200 dark:border-indigo-500/20 rounded-xl p-3
               flex flex-wrap items-center gap-3 hidden" data-bulk-bar>
    <?php echo csrf_field(); ?>
    <span class="text-sm font-medium text-indigo-700 dark:text-indigo-300">
      เลือกแล้ว <span data-bulk-count>0</span> รายการ
    </span>
    <select name="action" class="px-3 py-1.5 border border-indigo-200 dark:border-indigo-500/30 rounded-lg text-sm bg-white dark:bg-slate-900">
      <option value="approve">อนุมัติทั้งหมด</option>
      <option value="reject">ปฏิเสธทั้งหมด</option>
      <option value="skip">ยกเว้นทั้งหมด</option>
      <option value="rescan">สแกนใหม่</option>
    </select>
    <input type="text" name="reason" placeholder="เหตุผล (ถ้าปฏิเสธ)"
           class="flex-1 min-w-[200px] px-3 py-1.5 border border-indigo-200 dark:border-indigo-500/30 rounded-lg text-sm bg-white dark:bg-slate-900">
    <button type="submit" class="px-4 py-1.5 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg text-sm font-medium">
      ดำเนินการ
    </button>
  </form>

  
  <?php if($photos->count() > 0): ?>
  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
    <?php $__currentLoopData = $photos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $photo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php
      $statusColor = match($photo->moderation_status) {
        'flagged'  => 'amber',
        'rejected' => 'red',
        'approved' => 'emerald',
        'skipped'  => 'gray',
        default    => 'blue',
      };
      $cats = collect($photo->moderation_labels ?? [])
        ->whereNotNull('Name')
        ->map(fn($l) => ($l['ParentName'] ?? '') ?: ($l['Name'] ?? ''))
        ->unique()
        ->filter()
        ->take(3)
        ->values();
    ?>
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl overflow-hidden
                hover:shadow-lg transition relative group">
      
      <label class="absolute top-2 left-2 z-10 w-6 h-6 rounded-md bg-white/80 dark:bg-slate-900/80 backdrop-blur
                    flex items-center justify-center cursor-pointer shadow-sm">
        <input type="checkbox" name="ids[]" value="<?php echo e($photo->id); ?>" form="bulkForm"
               class="w-4 h-4 accent-indigo-500" data-bulk-check>
      </label>

      
      <a href="<?php echo e(route('admin.moderation.show', $photo->id)); ?>" class="block relative aspect-square overflow-hidden bg-gray-100 dark:bg-slate-900">
        <?php if($photo->thumbnail_url): ?>
        <img src="<?php echo e($photo->thumbnail_url); ?>" alt="Photo #<?php echo e($photo->id); ?>"
             class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
             loading="lazy"
             
             <?php if(in_array($photo->moderation_status, ['flagged', 'rejected'])): ?> style="filter: blur(24px);" <?php endif; ?>>
        <?php if(in_array($photo->moderation_status, ['flagged', 'rejected'])): ?>
        <div class="absolute inset-0 flex items-center justify-center bg-black/20">
          <span class="px-3 py-1.5 rounded-lg bg-black/60 text-white text-xs font-medium">
            <i class="bi bi-eye-slash mr-1"></i>คลิกเพื่อดูภาพ
          </span>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="w-full h-full flex items-center justify-center text-gray-300 dark:text-gray-600">
          <i class="bi bi-image text-4xl"></i>
        </div>
        <?php endif; ?>
      </a>

      
      <div class="p-3 space-y-2">
        <div class="flex items-center gap-2">
          <span class="px-2 py-0.5 rounded-md text-[0.7rem] font-semibold
                       bg-<?php echo e($statusColor); ?>-100 dark:bg-<?php echo e($statusColor); ?>-500/20
                       text-<?php echo e($statusColor); ?>-700 dark:text-<?php echo e($statusColor); ?>-300">
            <?php echo e(match($photo->moderation_status) {
                'flagged' => 'ติดธง',
                'rejected' => 'ปฏิเสธ',
                'approved' => 'อนุมัติ',
                'skipped' => 'ยกเว้น',
                'pending' => 'รอสแกน',
                default => $photo->moderation_status,
            }); ?>

          </span>
          <?php if($photo->moderation_score): ?>
          <span class="text-xs font-mono font-semibold text-<?php echo e($statusColor); ?>-600 dark:text-<?php echo e($statusColor); ?>-400">
            <?php echo e(number_format($photo->moderation_score, 1)); ?>%
          </span>
          <?php endif; ?>
        </div>

        <?php if($cats->count() > 0): ?>
        <div class="flex flex-wrap gap-1">
          <?php $__currentLoopData = $cats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <span class="px-1.5 py-0.5 rounded text-[0.65rem] bg-gray-100 dark:bg-white/5 text-gray-600 dark:text-gray-400">
            <?php echo e($cat); ?>

          </span>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <?php endif; ?>

        <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
          <i class="bi bi-calendar-event text-[0.7rem]"></i>
          <?php echo e(optional($photo->event)->name ?? 'Event #' . $photo->event_id); ?>

        </div>

        <?php if($photo->uploader): ?>
        <div class="text-xs text-gray-400 dark:text-gray-500 truncate">
          <i class="bi bi-person text-[0.7rem]"></i>
          <?php echo e(trim(($photo->uploader->first_name ?? '') . ' ' . ($photo->uploader->last_name ?? '')) ?: $photo->uploader->email); ?>

        </div>
        <?php endif; ?>

        
        <?php if($photo->moderation_status === 'flagged'): ?>
        <div class="flex gap-1.5 pt-1">
          <form method="POST" action="<?php echo e(route('admin.moderation.approve', $photo->id)); ?>" class="flex-1">
            <?php echo csrf_field(); ?>
            <button type="submit" class="w-full px-2 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white rounded-md text-xs font-medium">
              <i class="bi bi-check-lg"></i> อนุมัติ
            </button>
          </form>
          <form method="POST" action="<?php echo e(route('admin.moderation.reject', $photo->id)); ?>" class="flex-1">
            <?php echo csrf_field(); ?>
            <button type="submit" class="w-full px-2 py-1.5 bg-red-500 hover:bg-red-600 text-white rounded-md text-xs font-medium"
                    onclick="return confirm('ปฏิเสธภาพนี้?')">
              <i class="bi bi-x-lg"></i> ปฏิเสธ
            </button>
          </form>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>

  
  <div class="mt-4">
    <?php echo e($photos->links()); ?>

  </div>

  <?php else: ?>
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-12 text-center">
    <i class="bi bi-shield-check text-6xl text-emerald-400"></i>
    <p class="mt-4 text-lg font-semibold text-slate-700 dark:text-gray-200">ไม่มีภาพในสถานะนี้</p>
    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
      <?php if($status === 'flagged'): ?>
        ยินดีด้วย! ไม่มีภาพที่ต้องตรวจสอบในขณะนี้
      <?php else: ?>
        ลองเปลี่ยนตัวกรองดู
      <?php endif; ?>
    </p>
  </div>
  <?php endif; ?>

</div>

<script>
// Bulk-selection helper — shows the bar when anything is checked.
(function () {
  const checks = document.querySelectorAll('[data-bulk-check]');
  const bar    = document.querySelector('[data-bulk-bar]');
  const count  = document.querySelector('[data-bulk-count]');
  if (!bar || !count) return;

  function update() {
    const selected = Array.from(checks).filter(c => c.checked).length;
    count.textContent = selected;
    bar.classList.toggle('hidden', selected === 0);
  }

  checks.forEach(c => c.addEventListener('change', update));

  // Also intercept submit to ensure FormData carries the checked IDs (they
  // are already attached via form="bulkForm", but Safari sometimes drops them).
  const form = document.getElementById('bulkForm');
  if (form) {
    form.addEventListener('submit', (e) => {
      const ids = Array.from(checks).filter(c => c.checked).map(c => c.value);
      if (ids.length === 0) {
        e.preventDefault();
        alert('กรุณาเลือกภาพอย่างน้อย 1 รายการ');
      }
    });
  }
})();
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/admin/moderation/index.blade.php ENDPATH**/ ?>