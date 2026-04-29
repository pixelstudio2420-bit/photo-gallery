<?php $__env->startSection('title', 'แชท'); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('photographer.partials.page-hero', [
  'icon'     => 'bi-chat-dots',
  'eyebrow'  => 'การทำงาน',
  'title'    => 'แชท',
  'subtitle' => 'สนทนากับลูกค้าและตอบคำถามเกี่ยวกับอีเวนต์',
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php $__empty_1 = true; $__currentLoopData = $conversations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $conversation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<a href="<?php echo e(route('photographer.chat.show', $conversation)); ?>" class="no-underline block">
  <div class="pg-card mb-3 transition hover:-translate-y-0.5 hover:shadow-md">
    <div class="p-4">
      <div class="flex justify-between items-start">
        <div class="flex items-center">
          <div class="flex items-center justify-center mr-3 w-12 h-12 rounded-full" style="background:rgba(37,99,235,0.08);">
            <i class="bi bi-person text-xl text-indigo-600"></i>
          </div>
          <div>
            <h6 class="font-semibold mb-1 text-gray-800"><?php echo e($conversation->user->first_name ?? 'ผู้ใช้'); ?> <?php echo e($conversation->user->last_name ?? ''); ?></h6>
            <p class="text-gray-500 text-sm mb-0"><?php echo e(Str::limit($conversation->latestMessage->message ?? 'ยังไม่มีข้อความ', 60)); ?></p>
          </div>
        </div>
        <span class="text-gray-500 text-xs">
          <?php echo e($conversation->latestMessage ? $conversation->latestMessage->created_at->diffForHumans() : ''); ?>

        </span>
      </div>
    </div>
  </div>
</a>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<div class="pg-card">
  <div class="p-12 text-center">
    <i class="bi bi-chat-dots text-4xl text-indigo-600 opacity-30"></i>
    <p class="text-gray-500 mt-3">ยังไม่มีการสนทนา</p>
  </div>
</div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.photographer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/photographer/chat/index.blade.php ENDPATH**/ ?>