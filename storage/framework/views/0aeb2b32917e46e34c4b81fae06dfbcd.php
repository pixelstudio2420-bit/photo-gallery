<?php $__env->startSection('title', 'แผนสมัครสมาชิกของฉัน'); ?>

<?php
  use App\Models\PhotographerSubscription;
  use App\Models\SubscriptionInvoice;

  $plan     = $summary['plan'] ?? null;
  $sub      = $summary['subscription'] ?? null;
  $isFree   = (bool) ($summary['is_free'] ?? true);
  $inGrace  = (bool) ($summary['in_grace'] ?? false);
  $willCancel = (bool) ($summary['cancel_at_period_end'] ?? false);
  $featureLabels = [
    'face_search'         => 'ค้นหาด้วยใบหน้า',
    'quality_filter'      => 'คัดรูปเสียด้วย AI',
    'duplicate_detection' => 'ตรวจจับรูปซ้ำ',
    'auto_tagging'        => 'แท็กอัตโนมัติ',
    'best_shot'           => 'เลือกช็อตเด็ด',
    'priority_upload'     => 'อัพโหลดด่วน',
    'color_enhance'       => 'ปรับสีอัตโนมัติ',
    'customer_analytics'  => 'Analytics ลูกค้า',
    'smart_captions'      => 'Smart Captions',
    'custom_branding'     => 'Custom Branding',
    'video_thumbnails'    => 'Video Thumbnails',
    'api_access'          => 'API Access',
    'white_label'         => 'White-label',
  ];
?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('photographer.partials.page-hero', [
  'icon'     => 'bi-stars',
  'eyebrow'  => 'บริการเสริม',
  'title'    => 'แผนสมัครสมาชิกของฉัน',
  'subtitle' => 'พื้นที่จัดเก็บ · ค่าคอมมิชชั่น · ฟีเจอร์ AI ที่ปลดล็อก',
  'actions'  => '<a href="'.route('photographer.subscription.plans').'" class="pg-btn-primary"><i class="bi bi-arrow-up-circle"></i> '.($isFree ? 'ดูแผนทั้งหมด' : 'เปลี่ยน / อัปเกรดแผน').'</a>',
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
<?php if(session('info')): ?>
  <div class="mb-4 rounded-lg border border-sky-200 bg-sky-50 text-sky-900 text-sm px-4 py-3">
    <i class="bi bi-info-circle-fill mr-1.5"></i><?php echo e(session('info')); ?>

  </div>
<?php endif; ?>

<?php if($inGrace): ?>
  <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-5">
    <div class="flex items-start gap-3">
      <i class="bi bi-exclamation-triangle-fill text-rose-600 text-xl mt-0.5"></i>
      <div class="flex-1">
        <p class="font-semibold text-rose-900 mb-1">การชำระเงินล่าสุดไม่สำเร็จ</p>
        <p class="text-sm text-rose-800">
          บัญชีของคุณเข้าสู่ช่วงผ่อนผัน — กรุณาอัพเดทวิธีชำระเงินภายใน
          <?php echo e($summary['grace_ends_at']?->format('d M Y') ?? '—'); ?>

          เพื่อคงสิทธิ์การใช้งาน มิฉะนั้นจะถูกดาวน์เกรดเป็นแผนฟรีอัตโนมัติ
        </p>
      </div>
    </div>
  </div>
<?php endif; ?>


<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">

  
  <div class="lg:col-span-2 rounded-xl shadow-sm p-6 text-white"
       style="background:linear-gradient(135deg, <?php echo e($plan?->color_hex ?: '#6366f1'); ?> 0%, #4f46e5 100%);">
    <div class="flex items-start justify-between mb-4">
      <div>
        <p class="text-white/70 text-xs uppercase tracking-wider font-medium mb-1">แผนปัจจุบัน</p>
        <h2 class="font-bold text-3xl tracking-tight"><?php echo e($plan?->name ?? 'Free'); ?></h2>
        <?php if($plan?->badge): ?>
          <span class="inline-block mt-1 px-2 py-0.5 rounded bg-white/25 text-[11px] font-medium"><?php echo e($plan->badge); ?></span>
        <?php endif; ?>
      </div>
      
      <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm border border-white/25 shadow-sm">
        <i class="bi <?php echo e($plan?->iconClass() ?? 'bi-camera'); ?> text-xl"></i>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4 mt-4">
      <div>
        <p class="text-white/70 text-[11px] uppercase tracking-wider">พื้นที่จัดเก็บ</p>
        <p class="font-semibold text-lg"><?php echo e(number_format($summary['storage_quota_gb'], 0)); ?> GB</p>
      </div>
      <div>
        <p class="text-white/70 text-[11px] uppercase tracking-wider">ค่าคอมมิชชั่น</p>
        <p class="font-semibold text-lg">
          <?php echo e(rtrim(rtrim(number_format((float) ($summary['commission_pct'] ?? 0), 2), '0'), '.')); ?>%
          <span class="text-[11px] text-white/70">(คุณรับ <?php echo e(rtrim(rtrim(number_format((float) ($summary['photographer_share_pct'] ?? 100), 2), '0'), '.')); ?>%)</span>
        </p>
      </div>
      <div>
        <p class="text-white/70 text-[11px] uppercase tracking-wider">ราคา</p>
        <p class="font-semibold text-lg">
          <?php if($isFree): ?>
            ฟรี
          <?php else: ?>
            <?php echo e(number_format((float) ($plan->price_thb ?? 0), 0)); ?> บาท/เดือน
          <?php endif; ?>
        </p>
      </div>
      <div>
        <p class="text-white/70 text-[11px] uppercase tracking-wider">สถานะ</p>
        <p class="font-semibold text-lg">
          <?php if($willCancel): ?>
            <span class="text-amber-200"><i class="bi bi-clock-history"></i> จะหมดสิทธิ์สิ้นรอบ</span>
          <?php elseif($inGrace): ?>
            <span class="text-rose-200"><i class="bi bi-exclamation-triangle-fill"></i> ช่วงผ่อนผัน</span>
          <?php elseif($isFree): ?>
            <span>ฟรี</span>
          <?php else: ?>
            <span class="text-emerald-100"><i class="bi bi-check-circle-fill"></i> ใช้งานอยู่</span>
          <?php endif; ?>
        </p>
      </div>
    </div>

    <?php if(!$isFree && $summary['current_period_end']): ?>
      <p class="text-white/80 text-xs mt-4">
        <i class="bi bi-calendar-event mr-1"></i>
        <?php if($willCancel): ?>
          สิ้นสุดการใช้งาน: <?php echo e(\Carbon\Carbon::parse($summary['current_period_end'])->format('d M Y')); ?>

        <?php else: ?>
          ต่ออายุถัดไป: <?php echo e(\Carbon\Carbon::parse($summary['current_period_end'])->format('d M Y')); ?>

          <?php if($summary['days_until_renewal']): ?>
            <span class="ml-1 text-white/70">(อีก <?php echo e((int) $summary['days_until_renewal']); ?> วัน)</span>
          <?php endif; ?>
        <?php endif; ?>
      </p>
    <?php endif; ?>
  </div>

  
  <div class="pg-card p-5">
    <p class="text-gray-500 text-xs uppercase tracking-wider font-medium mb-3">การใช้พื้นที่</p>
    <p class="font-bold text-2xl tracking-tight text-gray-900">
      <?php echo e(number_format($summary['storage_used_gb'], 2)); ?>

      <span class="text-sm font-medium text-gray-500">/ <?php echo e(number_format($summary['storage_quota_gb'], 0)); ?> GB</span>
    </p>
    <div class="mt-3 w-full bg-gray-100 rounded-full h-2.5">
      <?php
        $pct = (float) $summary['storage_used_pct'];
        $barClass = $summary['storage_critical'] ? 'bg-rose-500'
                     : ($summary['storage_warn'] ? 'bg-amber-500' : 'bg-indigo-500');
      ?>
      <div class="<?php echo e($barClass); ?> h-2.5 rounded-full transition-all" style="width: <?php echo e($pct); ?>%"></div>
    </div>
    <p class="text-[11px] text-gray-500 mt-2">
      ใช้แล้ว <?php echo e(number_format($pct, 1)); ?>%
      <?php if($summary['storage_critical']): ?>
        — <span class="text-rose-600 font-medium">เต็มใกล้แล้ว! ลบอีเว้นเก่าเพื่อคืนพื้นที่</span>
      <?php elseif($summary['storage_warn']): ?>
        — <span class="text-amber-600 font-medium">ใกล้เต็มแล้ว</span>
      <?php endif; ?>
    </p>
  </div>
</div>


<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">

  
  <div class="pg-card p-5">
    <div class="flex items-center justify-between mb-2">
      <p class="text-gray-500 text-xs uppercase tracking-wider font-medium">
        <i class="bi bi-calendar-event mr-1"></i>อีเวนต์ที่เปิดอยู่
      </p>
      <?php if($summary['events_unlimited']): ?>
        <span class="text-[11px] px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 font-medium">ไม่จำกัด</span>
      <?php endif; ?>
    </div>
    <p class="font-bold text-2xl tracking-tight text-gray-900">
      <?php echo e((int) $summary['events_used']); ?>

      <span class="text-sm font-medium text-gray-500">
        <?php if($summary['events_unlimited']): ?>
          / ∞
        <?php else: ?>
          / <?php echo e((int) $summary['events_cap']); ?> งาน
        <?php endif; ?>
      </span>
    </p>
    <?php if(!$summary['events_unlimited']): ?>
      <div class="mt-3 w-full bg-gray-100 rounded-full h-2.5">
        <?php
          $epct = (float) $summary['events_used_pct'];
          $ebar = $epct >= 100 ? 'bg-rose-500' : ($epct >= 80 ? 'bg-amber-500' : 'bg-indigo-500');
        ?>
        <div class="<?php echo e($ebar); ?> h-2.5 rounded-full transition-all" style="width: <?php echo e(min(100, $epct)); ?>%"></div>
      </div>
      <?php if((int) $summary['events_cap'] === 0): ?>
        <p class="text-[11px] text-gray-500 mt-2">
          แผนปัจจุบันไม่อนุญาตให้เปิดขายอีเวนต์ —
          <a href="<?php echo e(route('photographer.subscription.plans')); ?>" class="text-indigo-600 font-medium hover:underline">อัปเกรดเพื่อเริ่มขาย</a>
        </p>
      <?php elseif($epct >= 100): ?>
        <p class="text-[11px] text-rose-600 font-medium mt-2">เต็มโควต้าแล้ว — ปิดอีเวนต์เก่าหรืออัปเกรดแผน</p>
      <?php elseif($epct >= 80): ?>
        <p class="text-[11px] text-amber-600 font-medium mt-2">ใกล้เต็มโควต้าแล้ว</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  
  <div class="pg-card p-5">
    <div class="flex items-center justify-between mb-2">
      <p class="text-gray-500 text-xs uppercase tracking-wider font-medium">
        <i class="bi bi-cpu mr-1"></i>เครดิต AI ในรอบนี้
      </p>
      <?php if($summary['ai_credits_period_end']): ?>
        <span class="text-[11px] text-gray-500">
          รีเซ็ต <?php echo e(\Carbon\Carbon::parse($summary['ai_credits_period_end'])->format('d M')); ?>

        </span>
      <?php endif; ?>
    </div>
    <p class="font-bold text-2xl tracking-tight text-gray-900">
      <?php echo e(number_format((int) $summary['ai_credits_used'])); ?>

      <span class="text-sm font-medium text-gray-500">
        / <?php echo e(number_format((int) $summary['ai_credits_cap'])); ?>

      </span>
    </p>
    <?php if((int) $summary['ai_credits_cap'] > 0): ?>
      <div class="mt-3 w-full bg-gray-100 rounded-full h-2.5">
        <?php
          $apct = (float) $summary['ai_credits_used_pct'];
          $abar = $apct >= 100 ? 'bg-rose-500' : ($apct >= 80 ? 'bg-amber-500' : 'bg-violet-500');
        ?>
        <div class="<?php echo e($abar); ?> h-2.5 rounded-full transition-all" style="width: <?php echo e(min(100, $apct)); ?>%"></div>
      </div>
      <p class="text-[11px] text-gray-500 mt-2">
        เหลือ <?php echo e(number_format((int) $summary['ai_credits_remaining'])); ?> เครดิต
        <?php if($apct >= 100): ?>
          — <span class="text-rose-600 font-medium">หมดแล้ว — รอรอบใหม่หรืออัปเกรดแผน</span>
        <?php elseif($apct >= 80): ?>
          — <span class="text-amber-600 font-medium">ใกล้หมด</span>
        <?php endif; ?>
      </p>
    <?php else: ?>
      <p class="text-[11px] text-gray-500 mt-3">
        แผนนี้ไม่มีเครดิต AI —
        <a href="<?php echo e(route('photographer.subscription.plans')); ?>" class="text-indigo-600 font-medium hover:underline">ดูแผนที่รองรับ AI</a>
      </p>
    <?php endif; ?>
  </div>

</div>


<div class="pg-card p-5 mb-6">
  <div class="flex items-center justify-between mb-4">
    <h5 class="font-semibold text-gray-900">
      <i class="bi bi-cpu mr-1.5 text-indigo-500"></i>ฟีเจอร์ AI ที่เปิดใช้งาน
    </h5>
    <?php if($isFree): ?>
      <a href="<?php echo e(route('photographer.subscription.plans')); ?>"
         class="text-xs font-medium text-indigo-600 hover:text-indigo-700">
        อัพเกรดเพื่อปลดล็อก AI <i class="bi bi-arrow-right"></i>
      </a>
    <?php endif; ?>
  </div>
  <?php $activeFeatures = $summary['ai_features'] ?? []; ?>
  <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
    <?php $__currentLoopData = $featureLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $code => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php $enabled = in_array($code, $activeFeatures, true); ?>
      <div class="flex items-center gap-2.5 rounded-lg border p-3
                  <?php echo e($enabled ? 'border-emerald-200 bg-emerald-50' : 'border-gray-200 bg-gray-50 opacity-60'); ?>">
        <i class="bi <?php echo e($enabled ? 'bi-check-circle-fill text-emerald-600' : 'bi-lock-fill text-gray-400'); ?>"></i>
        <span class="text-sm <?php echo e($enabled ? 'text-emerald-900 font-medium' : 'text-gray-500'); ?>"><?php echo e($label); ?></span>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
</div>


<?php if(!$isFree): ?>
<div class="flex flex-wrap gap-2 mb-6">
  <?php if($willCancel): ?>
    <form method="POST" action="<?php echo e(route('photographer.subscription.resume')); ?>">
      <?php echo csrf_field(); ?>
      <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">
        <i class="bi bi-arrow-clockwise"></i> กู้คืนการต่ออายุ
      </button>
    </form>
  <?php else: ?>
    <form method="POST" action="<?php echo e(route('photographer.subscription.cancel')); ?>"
          onsubmit="return confirm('ยืนยันการยกเลิกต่ออายุ? คุณจะยังใช้งานได้จนถึงสิ้นรอบบิลปัจจุบัน');">
      <?php echo csrf_field(); ?>
      <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50">
        <i class="bi bi-x-circle"></i> ยกเลิกการต่ออายุ
      </button>
    </form>
  <?php endif; ?>
  <a href="<?php echo e(route('photographer.subscription.invoices')); ?>"
     class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50">
    <i class="bi bi-receipt"></i> ใบเสร็จย้อนหลัง
  </a>
</div>
<?php endif; ?>


<div class="pg-card overflow-hidden pg-anim d3">
  <div class="pg-card-header">
    <h5 class="pg-section-title m-0"><i class="bi bi-receipt"></i> ใบเสร็จล่าสุด</h5>
    <a href="<?php echo e(route('photographer.subscription.invoices')); ?>" class="text-xs font-bold text-indigo-600 hover:text-indigo-700 inline-flex items-center gap-1 no-underline">
      ดูทั้งหมด <i class="bi bi-arrow-right"></i>
    </a>
  </div>
  <?php if($invoices->isEmpty()): ?>
    <div class="pg-empty">
      <div class="pg-empty-icon"><i class="bi bi-receipt"></i></div>
      <p class="font-medium">ยังไม่มีใบเสร็จ</p>
    </div>
  <?php else: ?>
    <div class="pg-table-wrap" style="border:none;border-radius:0;box-shadow:none;">
      <table class="pg-table">
        <thead>
          <tr>
            <th>เลขที่</th>
            <th>วันที่</th>
            <th class="text-end">ยอด</th>
            <th>สถานะ</th>
          </tr>
        </thead>
        <tbody>
          <?php $__currentLoopData = $invoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $inv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
              <td class="is-mono text-gray-700"><?php echo e($inv->invoice_number); ?></td>
              <td class="text-gray-600"><?php echo e($inv->created_at?->format('d M Y')); ?></td>
              <td class="text-end is-mono font-bold"><?php echo e(number_format((float) $inv->amount_thb, 2)); ?></td>
              <td>
                <?php
                  $iPill = match($inv->status) {
                    SubscriptionInvoice::STATUS_PAID     => ['pg-pill--green', 'ชำระแล้ว'],
                    SubscriptionInvoice::STATUS_PENDING  => ['pg-pill--amber', 'รอชำระ'],
                    SubscriptionInvoice::STATUS_FAILED   => ['pg-pill--rose',  'ล้มเหลว'],
                    SubscriptionInvoice::STATUS_REFUNDED => ['pg-pill--blue',  'คืนเงิน'],
                    default                              => ['pg-pill--gray',  $inv->status],
                  };
                ?>
                <span class="pg-pill <?php echo e($iPill[0]); ?>"><?php echo e($iPill[1]); ?></span>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.photographer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/photographer/subscription/index.blade.php ENDPATH**/ ?>