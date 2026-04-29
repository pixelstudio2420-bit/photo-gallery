<?php if($products->hasPages()): ?>
<div class="flex justify-center pagination-tw">
  <?php echo e($products->withQueryString()->links()); ?>

</div>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/public/products/_pagination.blade.php ENDPATH**/ ?>