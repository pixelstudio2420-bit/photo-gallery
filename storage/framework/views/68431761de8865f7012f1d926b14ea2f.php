<?php $__env->startSection('title', 'ชำระเงิน — ' . ($order->order_number ?? '#' . $order->id)); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-6xl mx-auto px-4 md:px-6 py-6">

  
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <h1 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
      <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-md">
        <i class="bi bi-credit-card-fill"></i>
      </span>
      ชำระเงิน
    </h1>
    <a href="<?php echo e(route('orders.show', $order->id)); ?>"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5 text-sm transition">
      <i class="bi bi-arrow-left"></i> กลับ
    </a>
  </div>

  <?php if(session('warning')): ?>
    <div class="mb-4 p-4 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-amber-800 dark:text-amber-300 text-sm flex items-start gap-2">
      <i class="bi bi-exclamation-triangle-fill mt-0.5"></i> <?php echo e(session('warning')); ?>

    </div>
  <?php endif; ?>
  <?php if(session('error')): ?>
    <div class="mb-4 p-4 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 text-rose-800 dark:text-rose-300 text-sm flex items-start gap-2">
      <i class="bi bi-x-circle-fill mt-0.5"></i> <?php echo e(session('error')); ?>

    </div>
  <?php endif; ?>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    
    <div class="lg:col-span-2 space-y-5">

      <form method="POST" action="<?php echo e(route('payment.process')); ?>" id="paymentForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="order_id" value="<?php echo e($order->id); ?>">

        
        <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center shadow-md">
              <i class="bi bi-wallet2"></i>
            </div>
            <div>
              <h3 class="font-semibold text-slate-900 dark:text-white">เลือกช่องทางชำระเงิน</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">เลือกวิธีที่สะดวกที่สุด</p>
            </div>
          </div>

          <div class="p-5">
            <?php if($paymentMethods->isEmpty()): ?>
              <div class="text-center py-10 px-4">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-rose-100 dark:bg-rose-500/20 text-rose-500 dark:text-rose-400 mb-3">
                  <i class="bi bi-exclamation-circle text-2xl"></i>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">ยังไม่มีช่องทางชำระเงินที่เปิดใช้งาน<br>กรุณาติดต่อผู้ดูแลระบบ</p>
              </div>
            <?php else: ?>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <?php $__currentLoopData = $paymentMethods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $method): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                  $type  = $method->method_type;
                  $icon  = $methodIcons[$type] ?? 'bi-cash-coin';
                  $first = $index === 0;
                ?>
                <label for="method_<?php echo e($type); ?>"
                       class="payment-method-card group cursor-pointer flex items-center gap-3 p-4 rounded-2xl border-2 transition
                          <?php echo e($first ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-500/10' : 'border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900/30 hover:border-indigo-300 dark:hover:border-indigo-500/30'); ?>"
                       data-type="<?php echo e($type); ?>">
                  <input type="radio"
                         name="payment_method"
                         value="<?php echo e($type); ?>"
                         id="method_<?php echo e($type); ?>"
                         <?php echo e($first ? 'checked' : ''); ?>

                         onchange="selectPaymentMethod(this)"
                         class="sr-only">
                  <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center shadow-sm flex-shrink-0">
                    <i class="bi <?php echo e($icon); ?> text-xl"></i>
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="font-semibold text-slate-900 dark:text-white text-sm truncate"><?php echo e($method->method_name); ?></div>
                    <?php if($method->description): ?>
                      <div class="text-xs text-slate-500 dark:text-slate-400 truncate"><?php echo e($method->description); ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="payment-check flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center border-2
                          <?php echo e($first ? 'border-indigo-500 bg-indigo-500' : 'border-slate-300 dark:border-white/20 bg-transparent'); ?>">
                    <?php if($first): ?><i class="bi bi-check text-white text-xs"></i><?php endif; ?>
                  </div>
                </label>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              </div>

              
              <button type="submit" id="proceedPaymentBtn"
                      class="w-full py-3.5 mt-5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-2">
                <i class="bi bi-lock-fill"></i> ชำระเงิน
                <?php echo e(number_format((float)($order->net_amount ?? $order->total_amount ?? $order->total ?? 0), 0)); ?> ฿
              </button>
            <?php endif; ?>
          </div>
        </div>
      </form>

      
      <?php if(!empty($promptPayNumber)): ?>
      <?php
        $ppGateway = new \App\Services\Payment\PromptPayGateway();
        $orderAmt  = (float)($order->net_amount ?? $order->total_amount ?? $order->total ?? 0);
        $ppPayload = $ppGateway->generateEMVPayload($promptPayNumber, $orderAmt);
        $ppQrUrl   = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
          'data' => $ppPayload, 'size' => '220x220', 'ecc' => 'M', 'margin' => '10', 'format' => 'png'
        ]);
        // Is PromptPay the default-selected method (first in the list)?
        $firstMethodType = $paymentMethods->first()->method_type ?? null;
        $ppIsDefault     = $firstMethodType === 'promptpay';
      ?>
      <div id="section-promptpay" class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden" <?php if(!$ppIsDefault): ?> style="display:none;" <?php endif; ?>>
        <div class="px-5 py-4 bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-500/5 dark:to-teal-500/5 border-b border-slate-200 dark:border-white/10 flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-500 text-white flex items-center justify-center shadow-md">
            <i class="bi bi-qr-code"></i>
          </div>
          <div>
            <h3 class="font-semibold text-slate-900 dark:text-white">PromptPay QR Code</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">QR นี้ใช้ดูตัวอย่าง · ระบบจะสร้างใหม่ให้ออเดอร์นี้</p>
          </div>
        </div>
        <div class="p-6 text-center">
          <div class="inline-block p-3 rounded-2xl bg-white border-2 border-dashed border-emerald-300 dark:border-emerald-500/40 shadow-sm">
            <img src="<?php echo e($ppQrUrl); ?>" alt="PromptPay QR" class="w-52 h-52">
          </div>
          <div class="mt-4 text-sm text-slate-500 dark:text-slate-400">PromptPay: <strong class="text-slate-900 dark:text-white font-mono"><?php echo e($promptPayNumber); ?></strong></div>
          <div class="mt-1 text-2xl font-bold bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text text-transparent">
            <?php echo e(number_format($orderAmt, 2)); ?> ฿
          </div>
        </div>
      </div>
      <?php endif; ?>

      
      <?php if(isset($bankAccounts) && $bankAccounts->isNotEmpty()): ?>
      <div id="section-bank_transfer" class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden" style="display:none;">
        <div class="px-5 py-4 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-500/5 dark:to-indigo-500/5 border-b border-slate-200 dark:border-white/10 flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center shadow-md">
            <i class="bi bi-bank2"></i>
          </div>
          <div>
            <h3 class="font-semibold text-slate-900 dark:text-white">บัญชีธนาคาร</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">คัดลอกเลขบัญชีและโอนผ่านแอปธนาคาร</p>
          </div>
        </div>
        <div class="p-5">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <?php $__currentLoopData = $bankAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bank): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="relative overflow-hidden rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-slate-900/30 group hover:border-indigo-300 dark:hover:border-indigo-500/30 transition">
              <div class="h-1 bg-gradient-to-r from-indigo-500 to-purple-600"></div>
              <div class="p-4 flex gap-3">
                <div class="w-11 h-11 rounded-xl bg-indigo-100 dark:bg-indigo-500/20 flex items-center justify-center flex-shrink-0">
                  <i class="bi bi-bank text-indigo-600 dark:text-indigo-400 text-lg"></i>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="font-semibold text-sm text-indigo-600 dark:text-indigo-400 truncate"><?php echo e($bank->bank_name); ?></div>
                  <?php if(!empty($bank->branch)): ?>
                    <div class="text-xs text-slate-500 dark:text-slate-400">สาขา <?php echo e($bank->branch); ?></div>
                  <?php endif; ?>
                  <div class="mt-1 font-mono font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <?php echo e($bank->account_number); ?>

                    <button type="button" onclick="navigator.clipboard.writeText('<?php echo e($bank->account_number); ?>'); this.innerHTML='<i class=\'bi bi-check2 text-emerald-500\'></i>'"
                            class="text-slate-400 hover:text-indigo-500 transition" title="คัดลอก">
                      <i class="bi bi-clipboard text-xs"></i>
                    </button>
                  </div>
                  <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5"><?php echo e($bank->account_holder_name ?? ''); ?></div>
                </div>
              </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </div>
          <div class="mt-4 p-3 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200 dark:border-indigo-500/20 text-xs text-indigo-900 dark:text-indigo-300">
            <i class="bi bi-info-circle mr-1"></i> กรุณากด "ชำระเงิน" แล้วอัปโหลดสลิปในหน้าถัดไป เพื่อให้เจ้าหน้าที่ตรวจสอบ
          </div>
        </div>
      </div>
      <?php endif; ?>

      
      <?php if(View::exists('public.payment.partials.slip-upload')): ?>
        <?php echo $__env->make('public.payment.partials.slip-upload', ['order' => $order], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php endif; ?>
    </div>

    
    <div class="lg:col-span-1">
      <div class="lg:sticky lg:top-24 space-y-4">

        <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-lg overflow-hidden">
          <div class="h-1.5 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500"></div>
          <div class="p-5">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-1.5 mb-4">
              <i class="bi bi-receipt text-indigo-500"></i> สรุปคำสั่งซื้อ
            </h3>

            <dl class="text-sm space-y-2.5 mb-4 pb-4 border-b border-slate-100 dark:border-white/5">
              <div class="flex justify-between">
                <dt class="text-slate-500 dark:text-slate-400">เลขคำสั่งซื้อ</dt>
                <dd class="font-mono font-medium text-slate-900 dark:text-white"><?php echo e($order->order_number ?? '#' . $order->id); ?></dd>
              </div>
              <?php if(!empty($order->event)): ?>
              <div class="flex justify-between gap-2">
                <dt class="text-slate-500 dark:text-slate-400 flex-shrink-0">อีเวนต์</dt>
                <dd class="font-medium text-slate-900 dark:text-white text-right truncate"><?php echo e($order->event->name ?? '-'); ?></dd>
              </div>
              <?php endif; ?>
              <?php if(!empty($order->package)): ?>
              <div class="flex justify-between gap-2">
                <dt class="text-slate-500 dark:text-slate-400 flex-shrink-0">แพ็กเกจ</dt>
                <dd class="font-medium text-indigo-600 dark:text-indigo-400 text-right">
                  <i class="bi bi-box-seam mr-0.5"></i><?php echo e($order->package->name); ?> (<?php echo e($order->package->photo_count); ?>)
                </dd>
              </div>
              <?php endif; ?>
              <div class="flex justify-between">
                <dt class="text-slate-500 dark:text-slate-400">จำนวนรูป</dt>
                <dd class="font-medium text-slate-900 dark:text-white"><?php echo e($order->items->count()); ?> รูป</dd>
              </div>

              <?php if(isset($order->total_amount) && isset($order->net_amount) && $order->total_amount != $order->net_amount): ?>
                <div class="flex justify-between">
                  <dt class="text-slate-500 dark:text-slate-400">ราคาเต็ม</dt>
                  <dd class="text-slate-500 dark:text-slate-400 line-through"><?php echo e(number_format((float)$order->total_amount, 0)); ?> ฿</dd>
                </div>
              <?php endif; ?>
              <?php if(!empty($order->discount_amount) && (float)$order->discount_amount > 0): ?>
                <div class="flex justify-between text-rose-600 dark:text-rose-400">
                  <dt class="inline-flex items-center gap-1"><i class="bi bi-tag-fill"></i> ส่วนลด</dt>
                  <dd class="font-semibold">-<?php echo e(number_format((float)$order->discount_amount, 0)); ?> ฿</dd>
                </div>
              <?php endif; ?>
            </dl>

            <div class="flex items-baseline justify-between">
              <span class="font-bold text-slate-900 dark:text-white">ยอดชำระ</span>
              <span class="text-3xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
                <?php echo e(number_format((float)($order->net_amount ?? $order->total_amount ?? $order->total ?? 0), 0)); ?> <span class="text-base">฿</span>
              </span>
            </div>
          </div>
        </div>

        
        <?php if($order->items->isNotEmpty()): ?>
        <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm p-4">
          <div class="text-xs uppercase tracking-wide font-semibold text-slate-500 dark:text-slate-400 mb-2">
            <i class="bi bi-images mr-1"></i> รูปภาพที่เลือก
          </div>
          <div class="flex flex-wrap gap-1.5">
            <?php $__currentLoopData = $order->items->take(8); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php if(!empty($item->thumbnail_url)): ?>
                <img src="<?php echo e($item->thumbnail_url); ?>" alt="photo"
                     class="w-12 h-12 object-cover rounded-lg border border-slate-200 dark:border-white/10">
              <?php else: ?>
                <div class="w-12 h-12 rounded-lg bg-slate-100 dark:bg-white/5 flex items-center justify-center">
                  <i class="bi bi-image text-slate-400"></i>
                </div>
              <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php if($order->items->count() > 8): ?>
              <div class="w-12 h-12 rounded-lg bg-slate-100 dark:bg-white/5 flex items-center justify-center">
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">+<?php echo e($order->items->count() - 8); ?></span>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 flex gap-3">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-500 text-white flex items-center justify-center flex-shrink-0 shadow-sm">
            <i class="bi bi-shield-fill-check"></i>
          </div>
          <div class="text-xs">
            <div class="font-semibold text-emerald-900 dark:text-emerald-200">การชำระเงินปลอดภัย</div>
            <p class="text-emerald-800 dark:text-emerald-300/90 mt-0.5">ข้อมูลของคุณถูกเข้ารหัสและได้รับการคุ้มครอง</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Payment methods requiring slip upload
const slipMethods = ['promptpay', 'bank_transfer', 'banktransfer', 'prompt_pay'];
const inlineSections = ['promptpay', 'bank_transfer'];

function selectPaymentMethod(radio) {
  document.querySelectorAll('.payment-method-card').forEach(function(card) {
    card.classList.remove('border-indigo-500', 'bg-indigo-50', 'dark:bg-indigo-500/10');
    card.classList.add('border-slate-200', 'dark:border-white/10', 'bg-white', 'dark:bg-slate-900/30');
    const chk = card.querySelector('.payment-check');
    if (chk) {
      chk.classList.remove('border-indigo-500', 'bg-indigo-500');
      chk.classList.add('border-slate-300', 'dark:border-white/20', 'bg-transparent');
      chk.innerHTML = '';
    }
  });
  const card = radio.closest('.payment-method-card');
  card.classList.remove('border-slate-200', 'dark:border-white/10', 'bg-white', 'dark:bg-slate-900/30');
  card.classList.add('border-indigo-500', 'bg-indigo-50', 'dark:bg-indigo-500/10');
  const chk = card.querySelector('.payment-check');
  if (chk) {
    chk.classList.remove('border-slate-300', 'dark:border-white/20', 'bg-transparent');
    chk.classList.add('border-indigo-500', 'bg-indigo-500');
    chk.innerHTML = '<i class="bi bi-check text-white text-xs"></i>';
  }
  toggleSections(radio.value);
}

function toggleSections(type) {
  inlineSections.forEach(function(t) {
    const el = document.getElementById('section-' + t);
    if (el) el.style.display = (type === t) ? '' : 'none';
  });
  const slipSection = document.getElementById('slipUploadSection');
  const proceedBtn  = document.getElementById('proceedPaymentBtn');
  const needsSlip   = slipMethods.indexOf(type.toLowerCase()) !== -1;
  if (slipSection) slipSection.style.display = needsSlip ? '' : 'none';
  if (proceedBtn) proceedBtn.style.display = needsSlip ? 'none' : '';
}

document.addEventListener('DOMContentLoaded', function() {
  const checked = document.querySelector('input[name="payment_method"]:checked');
  if (checked) toggleSections(checked.value);
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/public/payment/checkout.blade.php ENDPATH**/ ?>