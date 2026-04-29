<?php if(isset($events) && $events instanceof \Illuminate\Pagination\LengthAwarePaginator && $events->hasPages()): ?>
<div class="flex justify-center mt-8">
  <?php echo e($events->withQueryString()->links()); ?>

</div>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/public/events/_pagination.blade.php ENDPATH**/ ?>