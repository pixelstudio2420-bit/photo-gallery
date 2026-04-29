<?php $__env->startSection('slot'); ?>
<?php
    /**
     * Generic lifecycle email — receives a LifecycleMessage instance and
     * renders the canonical headline/body/bullets/CTA.
     *
     * One template for every event kind keeps the visual design
     * consistent (photographer learns the layout once, looks for the
     * same pieces in every email). The accent colour shifts by severity
     * so the reader can tell critical from informational at a glance.
     */
    $accent = match ($severity ?? 'info') {
        'critical' => '#dc2626',
        'warn'     => '#f59e0b',
        default    => '#4f46e5',
    };
?>

<div style="border-left:4px solid <?php echo e($accent); ?>;padding-left:16px;margin-bottom:18px;">
  <h2 style="margin:0 0 6px 0;color:#0f172a;"><?php echo e($headline); ?></h2>
</div>

<p>สวัสดีช่างภาพ <strong><?php echo e($name); ?></strong>,</p>

<p><?php echo nl2br(e($body)); ?></p>

<?php if(!empty($bullets)): ?>
<div class="info-box">
  <?php $__currentLoopData = $bullets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="info-row">
      <?php
        // Bullet format from formatter is "Label: Value" — split for
        // visual hierarchy. Falls back to single-cell if no colon.
        $parts = explode(':', $b, 2);
      ?>
      <?php if(count($parts) === 2): ?>
        <span class="label"><?php echo e(trim($parts[0])); ?></span>
        <span class="value"><?php echo e(trim($parts[1])); ?></span>
      <?php else: ?>
        <span class="value" style="font-weight:600;"><?php echo e($b); ?></span>
      <?php endif; ?>
    </div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php endif; ?>

<?php if(!empty($cta['url'] ?? '') && !empty($cta['label'] ?? '')): ?>
<div style="margin:24px 0;text-align:center;">
  <a href="<?php echo e($cta['url']); ?>"
     style="display:inline-block;padding:12px 32px;border-radius:8px;
            background:<?php echo e($accent); ?>;color:#ffffff;font-weight:700;
            text-decoration:none;font-size:15px;">
    <?php echo e($cta['label']); ?> →
  </a>
</div>
<?php endif; ?>

<p style="margin-top:24px;color:#64748b;font-size:13px;">
  หากมีคำถามเพิ่มเติมเกี่ยวกับแผน/บริการเสริมของคุณ
  <a href="<?php echo e(url('/photographer/store/status')); ?>" style="color:<?php echo e($accent); ?>;">ดูสถานะแผน &amp; การใช้งาน</a>
  ได้เสมอ หรือติดต่อทีมสนับสนุน
</p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layout', ['title' => $headline, 'preheader' => $message->shortBody], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/emails/photographer/lifecycle.blade.php ENDPATH**/ ?>