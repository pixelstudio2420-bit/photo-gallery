<?php $__env->startSection('title', 'เครดิตอัปโหลดของฉัน'); ?>

<?php
  use App\Models\CreditTransaction;
?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('photographer.partials.page-hero', [
  'icon'     => 'bi-coin',
  'eyebrow'  => 'บริการเสริม',
  'title'    => 'เครดิตอัปโหลด',
  'subtitle' => 'ดูเครดิตคงเหลือ · ประวัติการใช้ · ซื้อเครดิตเพิ่ม',
  'actions'  => '<a href="'.route('photographer.credits.store').'" class="pg-btn-primary"><i class="bi bi-bag-plus"></i> ซื้อเครดิตเพิ่ม</a>',
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


<?php if($profile->isCommissionMode()): ?>
  <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    <div>
      <p class="text-amber-900 font-semibold mb-1">
        <i class="bi bi-info-circle-fill mr-1"></i>บัญชีของคุณใช้โหมด "หักค่าคอมมิชชัน"
      </p>
      <p class="text-sm text-amber-800">
        เปลี่ยนมาใช้ "เครดิตอัปโหลด" ได้แต้มเครดิตฟรีรายเดือน + หักค่าขาย 0%
      </p>
    </div>
    <a href="<?php echo e(route('photographer.profile')); ?>"
       class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-amber-600 text-white text-sm font-medium hover:bg-amber-700">
      เปลี่ยนโหมดในโปรไฟล์ <i class="bi bi-arrow-right"></i>
    </a>
  </div>
<?php endif; ?>


<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
  
  <div class="bg-gradient-to-br from-indigo-500 to-purple-600 text-white rounded-xl shadow-sm p-5">
    <div class="flex justify-between items-start">
      <div>
        <p class="text-indigo-100 text-xs uppercase tracking-wider font-medium mb-1">เครดิตคงเหลือ</p>
        <h2 class="font-bold text-3xl tracking-tight"><?php echo e(number_format($summary['balance'] ?? 0)); ?></h2>
        <p class="text-[11px] text-indigo-100 mt-1">1 เครดิต = อัปโหลด 1 ภาพ</p>
      </div>
      <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-white/20">
        <i class="bi bi-coin"></i>
      </div>
    </div>
  </div>

  
  <div class="pg-card p-5">
    <div class="flex justify-between items-start">
      <div>
        <p class="text-gray-500 text-xs uppercase tracking-wider font-medium mb-1">เครดิตใกล้หมดอายุ</p>
        <?php if(!empty($summary['next_expiring'])): ?>
          <h2 class="font-bold text-2xl tracking-tight <?php echo e($summary['next_expiring']['warn_soon'] ? 'text-rose-600' : 'text-gray-900'); ?>">
            <?php echo e(number_format($summary['next_expiring']['credits'])); ?>

          </h2>
          <p class="text-[11px] mt-1 <?php echo e($summary['next_expiring']['warn_soon'] ? 'text-rose-500' : 'text-gray-400'); ?>">
            หมดอายุ <?php echo e($summary['next_expiring']['expires_at']); ?>

            (<?php echo e((int) $summary['next_expiring']['days_left']); ?> วัน)
          </p>
        <?php else: ?>
          <h2 class="font-bold text-2xl tracking-tight text-gray-900">—</h2>
          <p class="text-[11px] text-gray-400 mt-1">ยังไม่มีเครดิตที่มีวันหมดอายุ</p>
        <?php endif; ?>
      </div>
      <div class="flex items-center justify-center w-10 h-10 rounded-lg <?php echo e(($summary['next_expiring']['warn_soon'] ?? false) ? 'bg-rose-500/10 text-rose-600' : 'bg-amber-500/10 text-amber-600'); ?>">
        <i class="bi bi-hourglass-split"></i>
      </div>
    </div>
  </div>

  
  <div class="pg-card p-5">
    <div class="flex justify-between items-start">
      <div>
        <p class="text-gray-500 text-xs uppercase tracking-wider font-medium mb-1">ซื้อครั้งล่าสุด</p>
        <h2 class="font-bold text-lg tracking-tight text-gray-900">
          <?php echo e($summary['last_purchase'] ? \Illuminate\Support\Carbon::parse($summary['last_purchase'])->format('d M Y') : '—'); ?>

        </h2>
        <p class="text-[11px] text-gray-400 mt-1">
          <?php echo e($summary['last_purchase'] ? 'กดซื้อเพิ่มได้ทุกเมื่อ' : 'ยังไม่เคยซื้อแพ็คเก็จ'); ?>

        </p>
      </div>
      <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-500/10">
        <i class="bi bi-bag-check-fill text-emerald-600"></i>
      </div>
    </div>
  </div>
</div>


<?php if($pendingOrders->isNotEmpty()): ?>
  <div class="mb-6 rounded-xl border border-sky-200 bg-sky-50 p-4">
    <p class="text-sky-900 font-semibold text-sm mb-2">
      <i class="bi bi-clock-history mr-1"></i>คำสั่งซื้อที่ยังไม่ได้ชำระเงิน
    </p>
    <ul class="space-y-2">
      <?php $__currentLoopData = $pendingOrders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $po): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <li class="flex items-center justify-between text-sm">
          <span class="text-sky-800">
            <?php echo e($po->order_number); ?> — ฿<?php echo e(number_format($po->total, 2)); ?>

            <span class="text-xs text-sky-600">(<?php echo e(optional($po->created_at)->diffForHumans()); ?>)</span>
          </span>
          <a href="<?php echo e(route('payment.checkout', ['order' => $po->id])); ?>"
             class="inline-flex items-center gap-1 px-3 py-1 rounded-md bg-sky-600 text-white text-xs font-medium hover:bg-sky-700">
            ชำระเงินต่อ <i class="bi bi-arrow-right"></i>
          </a>
        </li>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ul>
  </div>
<?php endif; ?>


<div class="pg-card overflow-hidden mb-6 pg-anim d2">
  <div class="pg-card-header">
    <h5 class="pg-section-title m-0"><i class="bi bi-box-seam"></i> แพ็คเก็จเครดิตของคุณ</h5>
    <span class="text-xs text-gray-500">เรียงตามวันหมดอายุใกล้ที่สุดก่อน (FIFO)</span>
  </div>
  <div class="pg-table-wrap" style="border:none;border-radius:0;box-shadow:none;">
    <table class="pg-table">
      <thead>
        <tr>
          <th>แพ็คเก็จ</th>
          <th>ที่มา</th>
          <th class="text-end">เริ่มต้น</th>
          <th class="text-end">คงเหลือ</th>
          <th class="text-end">ใช้ไป</th>
          <th class="text-end">หมดอายุ</th>
        </tr>
      </thead>
      <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $bundles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <?php
            $used = max(0, (int)$b->credits_initial - (int)$b->credits_remaining);
            $pct  = $b->credits_initial > 0 ? round(($used / $b->credits_initial) * 100) : 0;
            $expired = $b->expires_at && $b->expires_at->isPast();

            $sourcePill = match($b->source) {
              'purchase'     => 'pg-pill--violet',
              'monthly_free' => 'pg-pill--green',
              'grant'        => 'pg-pill--amber',
              'bonus'        => 'pg-pill--violet',
              'refund'       => 'pg-pill--blue',
              default        => 'pg-pill--gray',
            };
          ?>
          <tr class="<?php echo e($expired ? 'opacity-60' : ''); ?>">
            <td>
              <div class="font-semibold"><?php echo e($b->package?->name ?? '— (ฟรี / ปรับยอด)'); ?></div>
              <?php if($b->note): ?>
                <div class="text-[11px] text-gray-500 mt-0.5"><?php echo e($b->note); ?></div>
              <?php endif; ?>
            </td>
            <td>
              <span class="pg-pill <?php echo e($sourcePill); ?>"><?php echo e($b->source); ?></span>
            </td>
            <td class="text-end is-mono"><?php echo e(number_format($b->credits_initial)); ?></td>
            <td class="text-end is-mono font-bold <?php echo e($expired ? 'text-gray-400' : 'text-indigo-700'); ?>">
              <?php echo e(number_format($b->credits_remaining)); ?>

            </td>
            <td class="text-end">
              <div class="flex items-center gap-2 justify-end">
                <div class="w-20 bg-gray-100 rounded-full h-1.5 overflow-hidden">
                  <div class="h-1.5 rounded-full" style="width: <?php echo e($pct); ?>%;background:linear-gradient(90deg,#6366f1,#7c3aed);"></div>
                </div>
                <span class="text-xs text-gray-500 is-mono"><?php echo e($pct); ?>%</span>
              </div>
            </td>
            <td class="text-end text-xs">
              <?php if($b->expires_at): ?>
                <span class="<?php echo e($expired ? 'text-rose-600 font-bold' : ($b->expires_at->diffInDays(now()) < 7 ? 'text-amber-600 font-semibold' : 'text-gray-500')); ?>">
                  <?php echo e($b->expires_at->format('d M Y')); ?>

                </span>
              <?php else: ?>
                <span class="text-gray-400">ไม่มีวันหมดอายุ</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr>
            <td colspan="6">
              <div class="pg-empty">
                <div class="pg-empty-icon"><i class="bi bi-coin"></i></div>
                <p class="font-medium">ยังไม่มีเครดิตในบัญชี</p>
                <a href="<?php echo e(route('photographer.credits.store')); ?>" class="pg-btn-primary mt-3">
                  <i class="bi bi-bag-plus"></i> เลือกซื้อแพ็คเก็จแรก
                </a>
              </div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>


<div class="pg-card overflow-hidden pg-anim d3">
  <div class="pg-card-header">
    <h5 class="pg-section-title m-0"><i class="bi bi-clock-history"></i> ประวัติล่าสุด</h5>
    <a href="<?php echo e(route('photographer.credits.history')); ?>" class="text-xs font-bold text-indigo-600 hover:text-indigo-700 inline-flex items-center gap-1 no-underline">
      ดูทั้งหมด <i class="bi bi-arrow-right"></i>
    </a>
  </div>
  <ul class="divide-y divide-indigo-100/40">
    <?php $__empty_1 = true; $__currentLoopData = $recentTxns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <li class="px-5 py-3 flex items-center justify-between hover:bg-indigo-50/30 transition">
        <div>
          <div class="text-sm font-semibold text-gray-900"><?php echo e($tx->kind_label); ?></div>
          <div class="text-xs text-gray-500 mt-0.5">
            <?php echo e($tx->created_at?->format('d M Y H:i')); ?>

            <?php if($tx->reference_type): ?>
              · <?php echo e($tx->reference_type); ?><?php echo e($tx->reference_id ? ' #'.$tx->reference_id : ''); ?>

            <?php endif; ?>
          </div>
        </div>
        <div class="text-right">
          <div class="font-bold is-mono <?php echo e($tx->delta >= 0 ? 'text-emerald-600' : 'text-rose-600'); ?>">
            <?php echo e($tx->delta >= 0 ? '+' : ''); ?><?php echo e(number_format($tx->delta)); ?>

          </div>
          <div class="text-[11px] text-gray-400">ยอดหลังรายการ: <?php echo e(number_format($tx->balance_after)); ?></div>
        </div>
      </li>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <li>
        <div class="pg-empty">
          <div class="pg-empty-icon"><i class="bi bi-receipt"></i></div>
          <p class="font-medium">ยังไม่มีประวัติการเคลื่อนไหว</p>
        </div>
      </li>
    <?php endif; ?>
  </ul>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.photographer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/photographer/credits/index.blade.php ENDPATH**/ ?>