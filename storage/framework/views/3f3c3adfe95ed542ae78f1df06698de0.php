

<?php if($creditsInfo && ($creditsInfo['enabled'] ?? false) && ($creditsInfo['billing_mode'] ?? null) === 'credits'): ?>
  <?php
    $balance = (int) ($creditsInfo['balance'] ?? 0);
    $next    = $creditsInfo['next_expiring'] ?? null;
    $lowBal  = $balance <= 10;
    $warnBar = $lowBal ? '#ef4444' : '#6366f1';
    $warnBg  = $lowBal ? 'rgba(239,68,68,0.08)' : 'rgba(99,102,241,0.08)';
  ?>

  <div class="card border-0 mb-4" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div class="p-5">
      <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
          <i class="bi bi-coin text-xl" style="color:<?php echo e($warnBar); ?>;"></i>
          <h6 class="font-semibold mb-0 text-sm">เครดิตอัปโหลด</h6>
          <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-500/10 text-indigo-700">CREDITS MODE</span>
        </div>
        <a href="<?php echo e(route('photographer.credits.index')); ?>" class="text-[11px] text-gray-400 hover:text-indigo-500">
          ดูทั้งหมด <i class="bi bi-arrow-right"></i>
        </a>
      </div>

      <div class="flex items-baseline justify-between mb-3">
        <div>
          <span class="text-3xl font-bold" style="color:<?php echo e($warnBar); ?>;"><?php echo e(number_format($balance)); ?></span>
          <span class="text-sm text-gray-400 ml-1">เครดิต</span>
        </div>
        <?php if($next): ?>
          <div class="text-right">
            <div class="text-[10px] text-gray-400 uppercase tracking-wider">ใกล้หมดอายุ</div>
            <div class="text-xs font-medium <?php echo e(($next['warn_soon'] ?? false) ? 'text-rose-600' : 'text-gray-600'); ?>">
              <?php echo e(number_format($next['credits'])); ?> หน่วย · <?php echo e((int) ($next['days_left'] ?? 0)); ?> วัน
            </div>
          </div>
        <?php endif; ?>
      </div>

      <?php if($lowBal): ?>
        <div class="p-3 rounded-lg mb-3 text-xs" style="background:<?php echo e($warnBg); ?>; color:#dc2626;">
          <i class="bi bi-exclamation-triangle-fill mr-1"></i>
          เครดิตเหลือน้อย — ซื้อแพ็คเก็จเพิ่มเพื่อไม่ให้อัปโหลดสะดุด
        </div>
      <?php endif; ?>

      <div class="grid grid-cols-2 gap-2 mt-2">
        <a href="<?php echo e(route('photographer.credits.store')); ?>"
           class="text-center py-2 rounded-lg text-xs font-semibold transition"
           style="background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;">
          <i class="bi bi-bag-plus mr-1"></i> ซื้อเครดิต
        </a>
        <a href="<?php echo e(route('photographer.credits.history')); ?>"
           class="text-center py-2 rounded-lg text-xs font-semibold border border-gray-200 hover:bg-gray-50">
          <i class="bi bi-clock-history mr-1"></i> ประวัติการใช้
        </a>
      </div>
    </div>
  </div>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/photographer/partials/credits-widget.blade.php ENDPATH**/ ?>