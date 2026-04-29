
<?php
  $icon     = $icon     ?? null;
  $eyebrow  = $eyebrow  ?? null;
  $title    = $title    ?? '';
  $subtitle = $subtitle ?? null;
  $actions  = $actions  ?? null;
?>

<div class="pg-hero pg-anim">
  <div class="flex items-start gap-3 min-w-0 flex-1">
    <?php if($icon): ?>
      <div class="pg-hero-icon"><i class="bi <?php echo e($icon); ?>"></i></div>
    <?php endif; ?>
    <div class="min-w-0 flex-1">
      <?php if($eyebrow): ?>
        <p class="pg-hero-eyebrow">
          <i class="bi bi-stars"></i><?php echo e($eyebrow); ?>

        </p>
      <?php endif; ?>
      <h1 class="pg-hero-title"><?php echo e($title); ?></h1>
      <?php if($subtitle): ?>
        <p class="pg-hero-subtitle"><?php echo e($subtitle); ?></p>
      <?php endif; ?>
    </div>
  </div>
  <?php if($actions): ?>
    <div class="pg-hero-actions">
      <?php echo $actions; ?>

    </div>
  <?php endif; ?>
</div>
<?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/photographer/partials/page-hero.blade.php ENDPATH**/ ?>