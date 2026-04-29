<?php $__env->startSection('title', 'ตั้งค่าการรับเงิน'); ?>

<?php $__env->startSection('content'); ?>
<?php
  // Three UI states, in descending trust order:
  //   VERIFIED   — ITMX confirmed the name via a successful Omise transfer.
  //                Green card. `promptpay_verified_at` is set.
  //   SAVED      — Number + user-typed name are on file but no transfer has
  //                happened yet, so we haven't heard back from ITMX. Amber
  //                card. We explicitly say the bank name shown is what the
  //                photographer typed, NOT what the bank confirmed.
  //   EMPTY      — Nothing saved yet. No card shown.
  $isVerified  = $photographer && $photographer->isPromptPayVerified();
  $isSaved     = $photographer && !empty($photographer->promptpay_number) && !empty($photographer->bank_account_name);
  $verifiedName = $photographer?->promptpay_verified_name;
  $typedName    = $photographer?->bank_account_name;
  $promptPayVal = old('promptpay_number', $photographer->promptpay_number ?? '');
  $bankNameVal  = old('bank_account_name', $photographer->bank_account_name ?? '');
?>

<?php echo $__env->make('photographer.partials.page-hero', [
  'icon'     => 'bi-wallet2',
  'eyebrow'  => 'การเงิน',
  'title'    => 'ตั้งค่าการรับเงิน',
  'subtitle' => 'PromptPay · บัญชีธนาคาร · ตรวจสอบชื่อกับ ITMX',
  'actions'  => '<a href="'.route('photographer.dashboard').'" class="pg-btn-ghost"><i class="bi bi-arrow-left"></i> กลับแดชบอร์ด</a>',
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>


<div class="rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white p-5 md:p-6 mb-6 relative overflow-hidden">
  <div class="max-w-2xl">
    <div class="text-[11px] uppercase tracking-[0.2em] text-white/70 font-bold mb-2">
      <i class="bi bi-lightning-charge-fill mr-1"></i>รับเงินอัตโนมัติผ่าน PromptPay
    </div>
    <h2 class="text-xl md:text-2xl font-bold mb-1.5">กรอกเลข PromptPay + ชื่อบัญชี ระบบโอนให้อัตโนมัติ</h2>
    <p class="text-white/80 text-sm leading-relaxed">
      ระบบจะหักค่าธรรมเนียมเว็บแล้วโอนส่วนของคุณเข้า PromptPay อัตโนมัติตามเงื่อนไขที่แอดมินตั้งไว้
      (เช่น ทุกวันพฤหัสฯ หรือเมื่อยอดถึง ฿500) — ชื่อบัญชีจะถูก<strong>ยืนยันกับธนาคารจริง</strong>ตอนโอนเงินครั้งแรก
    </p>
  </div>
</div>

<?php if($errors->any()): ?>
  <div class="rounded-xl bg-rose-50 border border-rose-200 p-4 mb-5 dark:bg-rose-500/10 dark:border-rose-500/30">
    <ul class="list-disc list-inside text-sm text-rose-800 dark:text-rose-200 space-y-1">
      <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ul>
  </div>
<?php endif; ?>

<form action="<?php echo e(route('photographer.setup-bank.update')); ?>" method="POST" id="payout-form">
  <?php echo csrf_field(); ?>

  
  <div class="rounded-2xl border bg-white shadow-sm mb-5 overflow-hidden
              border-gray-100 dark:bg-slate-800 dark:border-white/5">
    <div class="p-5 md:p-6">
      <div class="flex items-center gap-2 mb-3">
        <div class="w-7 h-7 rounded-lg bg-indigo-600 text-white text-xs font-bold flex items-center justify-center">1</div>
        <h3 class="font-bold text-base text-gray-900 dark:text-white">หมายเลข PromptPay <span class="text-rose-500">*</span></h3>
        <?php if($isVerified): ?>
          <span class="ml-auto text-[11px] font-semibold px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200 inline-flex items-center gap-1">
            <i class="bi bi-patch-check-fill"></i>ยืนยันกับธนาคารแล้ว
          </span>
        <?php elseif($isSaved): ?>
          <span class="ml-auto text-[11px] font-semibold px-2 py-0.5 rounded-md bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200 inline-flex items-center gap-1">
            <i class="bi bi-hourglass-split"></i>บันทึกแล้ว รอยืนยัน
          </span>
        <?php endif; ?>
      </div>
      <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
        ใช้เบอร์มือถือ 10 หลัก หรือเลขบัตรประชาชน 13 หลักที่ผูกกับบัญชีธนาคารในระบบ PromptPay
      </p>

      <div class="flex gap-2 items-stretch">
        <input type="text" name="promptpay_number" id="promptpay_number"
               class="flex-1 px-4 py-2.5 border rounded-lg text-sm font-mono
                      border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                      dark:bg-slate-900 dark:border-white/10 dark:text-white
                      <?php $__errorArgs = ['promptpay_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
               value="<?php echo e($promptPayVal); ?>"
               placeholder="เช่น 0812345678 หรือ 1234567890123"
               autocomplete="off">
        <button type="button" id="verify-btn"
                class="px-4 py-2.5 rounded-lg text-sm font-semibold whitespace-nowrap inline-flex items-center gap-1.5
                       bg-indigo-600 text-white hover:bg-indigo-700 transition
                       dark:bg-indigo-500 dark:hover:bg-indigo-600
                       disabled:opacity-50 disabled:cursor-not-allowed">
          <i class="bi bi-check2-circle"></i><span id="verify-btn-label">ตรวจรูปแบบ</span>
        </button>
      </div>
      <?php $__errorArgs = ['promptpay_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
        <p class="text-rose-500 text-xs mt-1"><?php echo e($message); ?></p>
      <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

      
      <div id="verify-result" class="mt-3 hidden"></div>
    </div>
  </div>

  
  <div class="rounded-2xl border bg-white shadow-sm mb-5 overflow-hidden
              border-gray-100 dark:bg-slate-800 dark:border-white/5">
    <div class="p-5 md:p-6">
      <div class="flex items-center gap-2 mb-3">
        <div class="w-7 h-7 rounded-lg bg-indigo-600 text-white text-xs font-bold flex items-center justify-center">2</div>
        <h3 class="font-bold text-base text-gray-900 dark:text-white">ชื่อเจ้าของบัญชี <span class="text-rose-500">*</span></h3>
      </div>
      <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
        พิมพ์ให้ตรงกับที่ปรากฏในแอพธนาคารของคุณ (ไม่ต้องใส่คำนำหน้า เช่น นาย / นาง / นางสาว) —
        ธนาคารจะใช้ชื่อนี้ตรวจสอบว่าเลข PromptPay เป็นของคุณจริงก่อนโอน
      </p>
      <input type="text" name="bank_account_name" id="bank_account_name"
             class="w-full px-4 py-2.5 border rounded-lg text-sm
                    border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                    dark:bg-slate-900 dark:border-white/10 dark:text-white
                    <?php $__errorArgs = ['bank_account_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
             value="<?php echo e($bankNameVal); ?>"
             placeholder="เช่น เอกชัย ใจดี"
             autocomplete="off">
      <?php $__errorArgs = ['bank_account_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
        <p class="text-rose-500 text-xs mt-1"><?php echo e($message); ?></p>
      <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

      
      <?php if($isVerified): ?>
        <div class="mt-4 p-3 rounded-xl bg-emerald-50 border border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/30">
          <div class="flex items-start gap-3">
            <i class="bi bi-patch-check-fill text-emerald-600 dark:text-emerald-400 text-lg shrink-0 mt-0.5"></i>
            <div class="flex-1 min-w-0">
              <div class="text-xs font-semibold text-emerald-700 dark:text-emerald-300 mb-0.5">
                ชื่อที่ธนาคารยืนยัน (ITMX)
              </div>
              <div class="font-bold text-emerald-900 dark:text-emerald-100 text-base"><?php echo e($verifiedName); ?></div>
              <?php if($photographer->promptpay_verified_at): ?>
                <div class="text-[11px] text-emerald-700/80 dark:text-emerald-300/80 mt-0.5">
                  ยืนยันเมื่อ <?php echo e($photographer->promptpay_verified_at->translatedFormat('j M Y · H:i')); ?> ·
                  จากการโอนเงินสำเร็จกับ PromptPay
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php elseif($isSaved): ?>
        <div class="mt-4 p-3 rounded-xl bg-amber-50 border border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/30">
          <div class="flex items-start gap-3">
            <i class="bi bi-hourglass-split text-amber-600 dark:text-amber-400 text-lg shrink-0 mt-0.5"></i>
            <div class="flex-1 min-w-0">
              <div class="text-xs font-semibold text-amber-700 dark:text-amber-300 mb-0.5">
                บันทึกไว้แล้ว — รอการยืนยัน
              </div>
              <div class="text-sm text-amber-900 dark:text-amber-100">
                ชื่อนี้เป็นชื่อที่คุณกรอกเอง ยังไม่ได้ยืนยันกับธนาคาร ระบบจะเทียบกับชื่อจริงตอนโอนเงินครั้งแรก —
                ถ้าไม่ตรงจะแจ้งเตือนให้แก้ไข
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  
  <details class="rounded-2xl border bg-white shadow-sm mb-5
                  border-gray-100 dark:bg-slate-800 dark:border-white/5">
    <summary class="p-5 md:p-6 cursor-pointer flex items-center gap-2 select-none">
      <div class="w-7 h-7 rounded-lg bg-gray-200 text-gray-700 dark:bg-white/10 dark:text-gray-300 text-xs font-bold flex items-center justify-center">3</div>
      <h3 class="font-bold text-base text-gray-900 dark:text-white">
        ข้อมูลบัญชีธนาคาร
        <span class="text-xs font-normal text-gray-500 dark:text-gray-400">(ตัวเลือก — ไว้ใช้กรณีโอนผ่านธนาคารปกติ)</span>
      </h3>
      <i class="bi bi-chevron-down ml-auto text-gray-400"></i>
    </summary>
    <div class="px-5 md:px-6 pb-5 md:pb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">ชื่อธนาคาร</label>
        <select name="bank_name" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-900 dark:border-white/10 dark:text-white">
          <option value="">— เลือกธนาคาร —</option>
          <?php
            $banks = ['กสิกรไทย (KBANK)', 'กรุงเทพ (BBL)', 'กรุงไทย (KTB)', 'ไทยพาณิชย์ (SCB)', 'กรุงศรี (BAY)', 'ทหารไทยธนชาต (TTB)', 'ออมสิน (GSB)', 'อื่นๆ'];
          ?>
          <?php $__currentLoopData = $banks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bank): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($bank); ?>" <?php echo e(old('bank_name', $photographer->bank_name ?? '') === $bank ? 'selected' : ''); ?>><?php echo e($bank); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">เลขบัญชี</label>
        <input type="text" name="bank_account_number" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-900 dark:border-white/10 dark:text-white"
               value="<?php echo e(old('bank_account_number', $photographer->bank_account_number ?? '')); ?>">
      </div>
    </div>
  </details>

  
  <div class="flex justify-end">
    <button type="submit"
            class="bg-gradient-to-br from-indigo-600 to-indigo-700 text-white font-semibold px-6 py-2.5 rounded-lg inline-flex items-center gap-1.5 transition hover:shadow-lg hover:-translate-y-0.5">
      <i class="bi bi-check-lg"></i> บันทึกข้อมูลการรับเงิน
    </button>
  </div>
</form>

<?php $__env->startPush('scripts'); ?>
<script>
(function() {
  // Live format check. We deliberately DO NOT claim to verify the name
  // here — only ITMX (via Omise transfer) can do that. This button just
  // pattern-checks that the number is 10-digit phone or 13-digit citizen ID
  // so the photographer catches typos before submitting.
  const input   = document.getElementById('promptpay_number');
  const btn     = document.getElementById('verify-btn');
  const btnLbl  = document.getElementById('verify-btn-label');
  const result  = document.getElementById('verify-result');
  const csrfTok = document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('input[name="_token"]')?.value;

  if (!input || !btn || !result) return;

  btn.addEventListener('click', async () => {
    const value = (input.value || '').trim();
    if (!value) {
      input.focus();
      return;
    }

    btn.disabled = true;
    btnLbl.textContent = 'กำลังตรวจ…';
    result.classList.remove('hidden');
    result.innerHTML = `
      <div class="p-3 rounded-xl bg-gray-50 border border-gray-200 dark:bg-white/5 dark:border-white/10 text-sm text-gray-600 dark:text-gray-300 flex items-center gap-2">
        <i class="bi bi-arrow-repeat animate-spin"></i> กำลังตรวจรูปแบบ…
      </div>`;

    try {
      const resp = await fetch(<?php echo json_encode(route('photographer.setup-bank.verify-promptpay'), 15, 512) ?>, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfTok,
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
        body: JSON.stringify({ promptpay_number: value }),
      });
      const data = await resp.json();

      if (resp.ok && data.ok) {
        const typeLbl = data.type === 'phone' ? 'เบอร์โทร' : 'เลขบัตรประชาชน';
        result.innerHTML = `
          <div class="p-3 rounded-xl bg-sky-50 border border-sky-200 dark:bg-sky-500/10 dark:border-sky-500/30">
            <div class="flex items-start gap-3">
              <i class="bi bi-check2-circle text-sky-600 dark:text-sky-400 text-lg shrink-0 mt-0.5"></i>
              <div class="flex-1 min-w-0">
                <div class="text-xs font-semibold text-sky-700 dark:text-sky-300 mb-0.5">
                  รูปแบบถูกต้อง (${escapeHtml(typeLbl)})
                </div>
                <div class="text-sm text-sky-900 dark:text-sky-100">
                  ${data.masked ? `หมายเลข <code class="font-mono">${escapeHtml(data.masked)}</code> — ` : ''}กรอกชื่อเจ้าของบัญชีด้านล่างแล้วกดบันทึก
                </div>
                <div class="text-[11px] text-sky-700/80 dark:text-sky-300/80 mt-1">
                  <i class="bi bi-info-circle mr-1"></i>
                  การยืนยันชื่อจริงจะเกิดขึ้นตอนธนาคาร (ITMX) ประมวลผลการโอนเงินครั้งแรก
                </div>
              </div>
            </div>
          </div>`;
      } else {
        const errMsg = data.message || 'รูปแบบไม่ถูกต้อง กรุณาตรวจสอบหมายเลขอีกครั้ง';
        result.innerHTML = `
          <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 dark:bg-rose-500/10 dark:border-rose-500/30 text-sm text-rose-800 dark:text-rose-200 flex items-start gap-2">
            <i class="bi bi-x-circle-fill mt-0.5"></i><span>${escapeHtml(errMsg)}</span>
          </div>`;
      }
    } catch (e) {
      result.innerHTML = `
        <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 dark:bg-rose-500/10 dark:border-rose-500/30 text-sm text-rose-800 dark:text-rose-200">
          <i class="bi bi-wifi-off mr-1"></i>เชื่อมต่อเครือข่ายไม่ได้ กรุณาลองใหม่
        </div>`;
    } finally {
      btn.disabled = false;
      btnLbl.textContent = 'ตรวจรูปแบบ';
    }
  });

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }
})();
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.photographer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/photographer/profile/setup-bank.blade.php ENDPATH**/ ?>