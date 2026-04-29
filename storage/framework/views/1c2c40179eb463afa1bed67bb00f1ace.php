<?php $__env->startSection('title', 'รายได้ของฉัน'); ?>

<?php
  use App\Models\PhotographerDisbursement;
?>

<?php $__env->startSection('content'); ?>
<?php
  $earningsActions = !empty($photographer->promptpay_number)
    ? '<div class="pg-btn-ghost"><i class="bi bi-lightning-charge-fill text-emerald-500"></i> จ่ายอัตโนมัติ → PromptPay ***'.substr($photographer->promptpay_number, -4).'</div>'
    : null;
?>
<?php echo $__env->make('photographer.partials.page-hero', [
  'icon'     => 'bi-wallet2',
  'eyebrow'  => 'การเงิน',
  'title'    => 'รายได้ของฉัน',
  'subtitle' => 'ภาพรวมรายได้ · ประวัติการโอน · ค่าคอมมิชชั่นที่ค้าง',
  'actions'  => $earningsActions,
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>


<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
  
  <div class="pg-card p-5">
    <div class="flex justify-between items-start">
      <div>
        <p class="text-gray-500 text-xs uppercase tracking-wider font-medium mb-1">รายได้สะสม</p>
        <h2 class="font-bold text-2xl tracking-tight text-gray-900">฿<?php echo e(number_format($totalEarnings ?? 0, 2)); ?></h2>
        <p class="text-[11px] text-gray-400 mt-1">ยอดรวมจากทุกคำสั่งซื้อ</p>
      </div>
      <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-500/10">
        <i class="bi bi-bar-chart-line-fill text-indigo-600"></i>
      </div>
    </div>
  </div>

  
  <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 text-white rounded-xl shadow-sm p-5">
    <div class="flex justify-between items-start">
      <div>
        <p class="text-emerald-100 text-xs uppercase tracking-wider font-medium mb-1">โอนเข้าบัญชีแล้ว</p>
        <h2 class="font-bold text-2xl tracking-tight">฿<?php echo e(number_format($totalPaid ?? 0, 2)); ?></h2>
        <p class="text-[11px] text-emerald-100 mt-1">การจ่ายที่สำเร็จทั้งหมด</p>
      </div>
      <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-white/20">
        <i class="bi bi-check-circle-fill"></i>
      </div>
    </div>
  </div>

  
  <div class="pg-card p-5">
    <div class="flex justify-between items-start">
      <div>
        <p class="text-gray-500 text-xs uppercase tracking-wider font-medium mb-1">รอจ่ายในรอบถัดไป</p>
        <h2 class="font-bold text-2xl tracking-tight text-amber-600">฿<?php echo e(number_format($pendingAmount ?? 0, 2)); ?></h2>
        <p class="text-[11px] text-gray-400 mt-1">
          <?php if(empty($photographer->promptpay_number)): ?>
            ⚠️ ยังไม่ได้กรอก PromptPay
          <?php else: ?>
            ระบบจะโอนอัตโนมัติเมื่อถึงเกณฑ์
          <?php endif; ?>
        </p>
      </div>
      <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-500/10">
        <i class="bi bi-hourglass-split text-amber-600"></i>
      </div>
    </div>
  </div>
</div>


<div class="pg-card mb-3">
  <div class="py-2 px-3 flex gap-1 flex-wrap" role="tablist">
    <button type="button" data-tab-target="disbursements"
            class="tab-btn text-sm px-4 py-1.5 rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-600 text-white font-medium transition">
      <i class="bi bi-bank mr-1"></i> ประวัติการโอน
      <?php if($disbursements->total() > 0): ?>
        <span class="ml-1 text-xs bg-white/25 px-1.5 py-0.5 rounded"><?php echo e($disbursements->total()); ?></span>
      <?php endif; ?>
    </button>
    <button type="button" data-tab-target="payouts"
            class="tab-btn text-sm px-4 py-1.5 rounded-lg bg-indigo-500/[0.08] text-indigo-500 font-medium transition hover:bg-indigo-500/[0.15]">
      <i class="bi bi-receipt mr-1"></i> รายการต่อคำสั่งซื้อ
      <span class="ml-1 text-xs bg-white/60 text-indigo-700 px-1.5 py-0.5 rounded"><?php echo e($payouts->total()); ?></span>
    </button>
  </div>
</div>




<div data-tab-panel="disbursements" class="tab-panel pg-card overflow-hidden pg-anim d2">
  <div class="pg-card-header">
    <div>
      <h5 class="pg-section-title m-0"><i class="bi bi-bank"></i> ประวัติการโอนเงิน (Disbursements)</h5>
      <p class="text-xs text-gray-500 mt-1">แต่ละแถวคือการโอนเข้าบัญชี 1 ครั้ง รวมจากหลายคำสั่งซื้อ</p>
    </div>
  </div>
  <div class="pg-table-wrap" style="border:none;border-radius:0;box-shadow:none;">
    <table class="pg-table">
      <thead>
        <tr>
          <th>วันที่</th>
          <th class="text-end">จำนวน</th>
          <th class="text-center">คำสั่งซื้อ</th>
          <th>วิธีโอน</th>
          <th>สถานะ</th>
          <th>เลขอ้างอิง</th>
        </tr>
      </thead>
      <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $disbursements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <?php
            $statusPillMap = [
              PhotographerDisbursement::STATUS_PENDING    => ['pg-pill--gray',  'รอดำเนินการ',  'bi-clock'],
              PhotographerDisbursement::STATUS_PROCESSING => ['pg-pill--blue',  'กำลังโอน',     'bi-arrow-repeat'],
              PhotographerDisbursement::STATUS_SUCCEEDED  => ['pg-pill--green', 'โอนสำเร็จ',    'bi-check-circle-fill'],
              PhotographerDisbursement::STATUS_FAILED     => ['pg-pill--rose',  'โอนไม่สำเร็จ', 'bi-exclamation-triangle-fill'],
            ];
            $sp = $statusPillMap[$d->status] ?? $statusPillMap[PhotographerDisbursement::STATUS_PENDING];
          ?>
          <tr>
            <td class="whitespace-nowrap">
              <div class="text-sm font-semibold text-gray-900"><?php echo e($d->created_at?->format('d/m/Y')); ?></div>
              <div class="text-xs text-gray-400"><?php echo e($d->created_at?->format('H:i')); ?></div>
            </td>
            <td class="text-end is-mono font-bold text-indigo-700 whitespace-nowrap">
              ฿<?php echo e(number_format((float) $d->amount_thb, 2)); ?>

            </td>
            <td class="text-center">
              <span class="pg-pill pg-pill--gray"><?php echo e($d->payout_count); ?> รายการ</span>
            </td>
            <td class="text-xs uppercase is-mono text-gray-600">
              <?php echo e($d->provider ?? '—'); ?>

            </td>
            <td>
              <span class="pg-pill <?php echo e($sp[0]); ?>"><i class="bi <?php echo e($sp[2]); ?>"></i> <?php echo e($sp[1]); ?></span>
              <?php if($d->status === PhotographerDisbursement::STATUS_FAILED && $d->error_message): ?>
                <div class="text-[11px] text-rose-500 mt-1"><?php echo e($d->error_message); ?></div>
              <?php endif; ?>
            </td>
            <td>
              <?php if($d->provider_txn_id): ?>
                <code class="text-[11px] bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded font-mono"><?php echo e($d->provider_txn_id); ?></code>
              <?php else: ?>
                <span class="text-xs text-gray-400">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr>
            <td colspan="6">
              <div class="pg-empty">
                <div class="pg-empty-icon"><i class="bi bi-bank"></i></div>
                <p class="font-medium">ยังไม่มีการโอนเงิน</p>
                <p class="text-xs mt-2">
                  <?php if(empty($photographer->promptpay_number)): ?>
                    กรอก PromptPay ที่หน้า <a href="<?php echo e(route('photographer.setup-bank')); ?>" class="text-indigo-600 font-bold underline">"ตั้งค่าบัญชีธนาคาร"</a> เพื่อเริ่มรับโอนอัตโนมัติ
                  <?php else: ?>
                    ระบบจะโอนเงินให้อัตโนมัติเมื่อถึงเกณฑ์ที่แอดมินตั้งไว้
                  <?php endif; ?>
                </p>
              </div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if($disbursements->hasPages()): ?>
    <div class="pg-card-footer">
      <?php echo e($disbursements->links()); ?>

    </div>
  <?php endif; ?>
</div>




<div data-tab-panel="payouts" class="tab-panel hidden pg-card overflow-hidden pg-anim d2">
  <div class="pg-card-header">
    <div>
      <h5 class="pg-section-title m-0"><i class="bi bi-receipt"></i> รายได้ต่อคำสั่งซื้อ (Earnings)</h5>
      <p class="text-xs text-gray-500 mt-1">แต่ละแถวคือยอดที่จะได้รับจาก 1 คำสั่งซื้อ</p>
    </div>
  </div>
  <div class="pg-table-wrap" style="border:none;border-radius:0;box-shadow:none;">
    <table class="pg-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>คำสั่งซื้อ#</th>
          <th class="text-end">ยอดรวม</th>
          <th class="text-end">ค่าคอมฯ</th>
          <th class="text-end">รับจริง</th>
          <th>สถานะ</th>
          <th>วันที่</th>
        </tr>
      </thead>
      <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $payouts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payout): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr>
            <td class="is-mono font-semibold"><?php echo e($payout->id); ?></td>
            <td><span class="pg-pill pg-pill--blue">#<?php echo e($payout->order_id); ?></span></td>
            <td class="text-end is-mono"><?php echo e(number_format($payout->gross_amount, 2)); ?></td>
            <td class="text-end is-mono text-gray-500"><?php echo e(number_format($payout->platform_fee, 2)); ?></td>
            <td class="text-end is-mono font-bold text-indigo-700"><?php echo e(number_format($payout->payout_amount, 2)); ?></td>
            <td>
              <?php
                $payoutPill = match($payout->status) {
                  'pending'   => ['pg-pill--amber', 'รอดำเนินการ'],
                  'requested' => ['pg-pill--blue',  'ขอถอนแล้ว'],
                  'paid'      => ['pg-pill--green', 'จ่ายแล้ว'],
                  'failed'    => ['pg-pill--rose',  'ล้มเหลว'],
                  default     => ['pg-pill--gray',  $payout->status],
                };
              ?>
              <span class="pg-pill <?php echo e($payoutPill[0]); ?>"><?php echo e($payoutPill[1]); ?></span>
            </td>
            <td class="text-gray-500 text-sm whitespace-nowrap"><?php echo e($payout->created_at->format('d/m/Y H:i')); ?></td>
          </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr>
            <td colspan="7">
              <div class="pg-empty">
                <div class="pg-empty-icon"><i class="bi bi-wallet2"></i></div>
                <p class="font-medium">ยังไม่มีรายได้</p>
              </div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if($payouts->hasPages()): ?>
    <div class="pg-card-footer">
      <?php echo e($payouts->links()); ?>

    </div>
  <?php endif; ?>
</div>


<script>
(function () {
  const btns   = document.querySelectorAll('[data-tab-target]');
  const panels = document.querySelectorAll('[data-tab-panel]');

  function activate(target) {
    btns.forEach(b => {
      const active = b.dataset.tabTarget === target;
      b.classList.toggle('bg-gradient-to-br', active);
      b.classList.toggle('from-indigo-500',   active);
      b.classList.toggle('to-indigo-600',     active);
      b.classList.toggle('text-white',        active);
      b.classList.toggle('bg-indigo-500/[0.08]', !active);
      b.classList.toggle('text-indigo-500',      !active);
    });
    panels.forEach(p => {
      p.classList.toggle('hidden', p.dataset.tabPanel !== target);
    });
  }

  btns.forEach(b => b.addEventListener('click', () => {
    activate(b.dataset.tabTarget);
    history.replaceState(null, '', '#' + b.dataset.tabTarget);
  }));

  // Honour hash on load (e.g. notification deep-link to #payouts).
  const initial = (location.hash || '#disbursements').replace('#', '');
  if (document.querySelector('[data-tab-target="' + initial + '"]')) {
    activate(initial);
  }
})();
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.photographer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/photographer/earnings/index.blade.php ENDPATH**/ ?>