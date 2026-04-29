<?php $__env->startSection('title', 'อีเวนต์ของฉัน'); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('photographer.partials.page-hero', [
  'icon'     => 'bi-calendar-event',
  'eyebrow'  => 'การทำงาน',
  'title'    => 'อีเวนต์ของฉัน',
  'subtitle' => 'จัดการอีเวนต์ทั้งหมด · สร้างใหม่ · เผยแพร่ · ปิดงาน',
  'actions'  => '<a href="'.route('photographer.events.create').'" class="pg-btn-primary"><i class="bi bi-plus-lg"></i> สร้างอีเวนต์</a>',
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<div class="pg-table-wrap pg-anim d1">
  <table class="pg-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>ชื่อ</th>
        <th>วันที่ถ่าย</th>
        <th>สถานะ</th>
        <th class="text-end">จัดการ</th>
      </tr>
    </thead>
    <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <tr>
        <td class="is-mono font-semibold"><?php echo e($event->id); ?></td>
        <td class="font-medium"><?php echo e($event->name); ?></td>
        <td class="text-gray-500"><?php echo e($event->shoot_date ? \Carbon\Carbon::parse($event->shoot_date)->format('d/m/Y') : '-'); ?></td>
        <td>
          <?php
            $statusPill = match($event->status) {
              'active'    => 'pg-pill--green',
              'published' => 'pg-pill--blue',
              'archived'  => 'pg-pill--amber',
              default     => 'pg-pill--gray',
            };
          ?>
          <span class="pg-pill <?php echo e($statusPill); ?>"><?php echo e(ucfirst($event->status)); ?></span>
        </td>
        <td class="text-end">
          <div class="flex gap-1 justify-end">
            <a href="<?php echo e(route('photographer.events.show', $event)); ?>" class="inline-flex items-center justify-center text-sm w-8 h-8 rounded-lg transition" style="background:rgba(37,99,235,0.08);color:#2563eb;" title="ดู">
              <i class="bi bi-eye"></i>
            </a>
            <a href="<?php echo e(route('photographer.events.edit', $event)); ?>" class="inline-flex items-center justify-center text-sm w-8 h-8 rounded-lg transition" style="background:rgba(245,158,11,0.08);color:#f59e0b;" title="แก้ไข">
              <i class="bi bi-pencil"></i>
            </a>
            <a href="<?php echo e(route('photographer.events.photos.upload', $event)); ?>" class="inline-flex items-center justify-center text-sm w-8 h-8 rounded-lg transition" style="background:rgba(99,102,241,0.08);color:#6366f1;" title="อัปโหลดรูป">
              <i class="bi bi-cloud-upload"></i>
            </a>
            <a href="<?php echo e(route('photographer.events.photos.index', $event)); ?>" class="inline-flex items-center justify-center text-sm w-8 h-8 rounded-lg transition" style="background:rgba(16,185,129,0.08);color:#10b981;" title="จัดการรูปภาพ">
              <i class="bi bi-images"></i>
            </a>
            <a href="<?php echo e(route('photographer.events.qrcode', $event)); ?>" class="inline-flex items-center justify-center text-sm w-8 h-8 rounded-lg transition" style="background:rgba(124,58,237,0.08);color:#7c3aed;" title="QR Code">
              <i class="bi bi-qr-code"></i>
            </a>
            <form action="<?php echo e(route('photographer.events.destroy', $event)); ?>" method="POST" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบอีเวนต์นี้?');" class="inline">
              <?php echo csrf_field(); ?>
              <?php echo method_field('DELETE'); ?>
              <button type="submit" class="inline-flex items-center justify-center text-sm w-8 h-8 rounded-lg transition" style="background:rgba(239,68,68,0.08);color:#ef4444;" title="ลบ">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <tr>
        <td colspan="5">
          <div class="pg-empty">
            <div class="pg-empty-icon"><i class="bi bi-calendar-x"></i></div>
            <p class="font-medium">ยังไม่มีอีเวนต์</p>
            <p class="text-xs mt-1">เริ่มสร้างอีเวนต์แรกของคุณ</p>
          </div>
        </td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if($events->hasPages()): ?>
<div class="flex justify-center mt-6">
  <?php echo e($events->links()); ?>

</div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.photographer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/photographer/events/index.blade.php ENDPATH**/ ?>