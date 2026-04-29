
<div id="slipUploadSection" class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden" style="display:none;">
  <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center gap-3">
    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 text-white flex items-center justify-center shadow-md">
      <i class="bi bi-image"></i>
    </div>
    <div>
      <h3 class="font-semibold text-slate-900 dark:text-white">แนบหลักฐานการโอนเงิน</h3>
      <p class="text-xs text-slate-500 dark:text-slate-400">อัปโหลดสลิปเพื่อให้เจ้าหน้าที่ตรวจสอบ</p>
    </div>
  </div>

  <div class="p-5 space-y-4">
    
    <div id="slipDropZone"
         class="flex flex-col items-center justify-center gap-2 p-8 rounded-2xl border-2 border-dashed border-indigo-200 dark:border-indigo-500/30 bg-indigo-50/50 dark:bg-indigo-500/5 hover:border-indigo-400 dark:hover:border-indigo-500 cursor-pointer transition">
      <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center shadow-md">
        <i class="bi bi-cloud-arrow-up text-2xl"></i>
      </div>
      <p class="font-medium text-sm text-slate-700 dark:text-slate-200">ลากไฟล์มาวางที่นี่ หรือ <span class="text-indigo-600 dark:text-indigo-400 underline">คลิกเพื่อเลือกไฟล์</span></p>
      <p class="text-xs text-slate-500 dark:text-slate-400">รองรับ JPG, PNG, WEBP — สูงสุด 5MB</p>
      <input type="file" id="slipFileInput" accept="image/*" class="hidden">
    </div>

    
    <div id="slipPreviewWrapper" class="text-center" style="display:none !important;">
      <img id="slipPreviewImg" src="" alt="slip preview"
           class="max-h-64 mx-auto rounded-xl shadow-md object-contain bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-white/10">
      <div class="mt-3">
        <button type="button" id="slipRemoveBtn"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 hover:bg-rose-500 hover:text-white text-xs font-medium transition">
          <i class="bi bi-trash"></i> ลบรูป
        </button>
      </div>
    </div>

    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
      <div>
        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">ยอดที่โอน (฿) <span class="text-rose-500">*</span></label>
        <input type="number" id="slipTransferAmount" step="0.01" min="0"
               value="<?php echo e(number_format((float)($order->total ?? 0), 2, '.', '')); ?>"
               placeholder="0.00"
               class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">วันที่โอน <span class="text-rose-500">*</span></label>
        <input type="date" id="slipTransferDate" value="<?php echo e(date('Y-m-d')); ?>"
               class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">รหัสอ้างอิง</label>
        <input type="text" id="slipRefCode" maxlength="100" placeholder="เช่น 67032401234"
               class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
      </div>
    </div>

    
    <div id="slipProgressWrapper" style="display:none;">
      <div class="flex justify-between mb-1">
        <span class="text-xs text-slate-500 dark:text-slate-400">กำลังอัปโหลด...</span>
        <span id="slipProgressText" class="text-xs text-slate-500 dark:text-slate-400">0%</span>
      </div>
      <div class="w-full h-2 bg-slate-200 dark:bg-white/10 rounded-full overflow-hidden">
        <div id="slipProgressBar" class="h-full bg-gradient-to-r from-indigo-500 to-purple-600 rounded-full transition-all" style="width:0%;"></div>
      </div>
    </div>

    
    <div id="slipResultAlert" style="display:none;"></div>

    
    <button type="button" id="slipUploadBtn"
            class="w-full py-3 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
      <i class="bi bi-upload"></i> ส่งหลักฐานการโอนเงิน
    </button>
  </div>
</div>

<script>
(function () {
  'use strict';

  const orderId   = <?php echo e($order->id ?? 0); ?>;
  const uploadUrl = '<?php echo e(route('payment.slip.upload')); ?>';
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

  const dropZone        = document.getElementById('slipDropZone');
  const fileInput       = document.getElementById('slipFileInput');
  const previewWrapper  = document.getElementById('slipPreviewWrapper');
  const previewImg      = document.getElementById('slipPreviewImg');
  const removeBtn       = document.getElementById('slipRemoveBtn');
  const uploadBtn       = document.getElementById('slipUploadBtn');
  const progressWrapper = document.getElementById('slipProgressWrapper');
  const progressBar     = document.getElementById('slipProgressBar');
  const progressText    = document.getElementById('slipProgressText');
  const resultAlert     = document.getElementById('slipResultAlert');

  let selectedFile = null;

  dropZone.addEventListener('click', () => fileInput.click());
  dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.classList.add('border-indigo-500', 'bg-indigo-100/50');
  });
  dropZone.addEventListener('dragleave', () => {
    dropZone.classList.remove('border-indigo-500', 'bg-indigo-100/50');
  });
  dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('border-indigo-500', 'bg-indigo-100/50');
    const file = e.dataTransfer.files[0];
    if (file) handleFile(file);
  });
  fileInput.addEventListener('change', () => { if (fileInput.files[0]) handleFile(fileInput.files[0]); });
  removeBtn.addEventListener('click', () => {
    selectedFile = null;
    fileInput.value = '';
    previewWrapper.style.display = 'none';
    dropZone.style.display = '';
  });

  function handleFile(file) {
    if (!file.type.startsWith('image/')) { showAlert('danger', '<i class="bi bi-x-circle mr-1"></i>กรุณาเลือกไฟล์รูปภาพเท่านั้น'); return; }
    if (file.size > 5 * 1024 * 1024) { showAlert('danger', '<i class="bi bi-x-circle mr-1"></i>ขนาดไฟล์ต้องไม่เกิน 5MB'); return; }
    selectedFile = file;
    const reader = new FileReader();
    reader.onload = (e) => {
      previewImg.src = e.target.result;
      previewWrapper.style.display = '';
      dropZone.style.display = 'none';
    };
    reader.readAsDataURL(file);
  }

  uploadBtn.addEventListener('click', () => {
    if (!selectedFile) { showAlert('warning', '<i class="bi bi-exclamation-triangle mr-1"></i>กรุณาเลือกไฟล์สลิปก่อน'); return; }
    const amount = document.getElementById('slipTransferAmount').value;
    const date   = document.getElementById('slipTransferDate').value;
    if (!amount || parseFloat(amount) <= 0) { showAlert('warning', '<i class="bi bi-exclamation-triangle mr-1"></i>กรุณาระบุยอดที่โอน'); return; }
    if (!date) { showAlert('warning', '<i class="bi bi-exclamation-triangle mr-1"></i>กรุณาระบุวันที่โอน'); return; }

    const formData = new FormData();
    formData.append('_token', csrfToken);
    formData.append('order_id', orderId);
    formData.append('slip_image', selectedFile);
    formData.append('transfer_amount', amount);
    formData.append('transfer_date', date);
    formData.append('ref_code', document.getElementById('slipRefCode').value);
    formData.append('payment_method', document.querySelector('input[name="payment_method"]:checked')?.value ?? 'bank_transfer');

    uploadBtn.disabled = true;
    progressWrapper.style.display = '';
    resultAlert.style.display = 'none';

    const xhr = new XMLHttpRequest();
    xhr.open('POST', uploadUrl);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.upload.addEventListener('progress', (e) => {
      if (e.lengthComputable) {
        const pct = Math.round((e.loaded / e.total) * 100);
        progressBar.style.width = pct + '%';
        progressText.textContent = pct + '%';
      }
    });
    xhr.addEventListener('load', () => {
      progressWrapper.style.display = 'none';
      uploadBtn.disabled = false;
      try {
        const res = JSON.parse(xhr.responseText);
        if (res.success) {
          const icon = res.status === 'approved'
            ? '<i class="bi bi-check-circle-fill mr-1"></i>'
            : '<i class="bi bi-hourglass-split mr-1"></i>';
          showAlert('success', icon + res.message);
          setTimeout(() => { if (res.redirect) window.location.href = res.redirect; }, 2000);
        } else {
          showAlert('danger', '<i class="bi bi-x-circle mr-1"></i>' + (res.message ?? 'เกิดข้อผิดพลาด'));
        }
      } catch (e) {
        showAlert('danger', '<i class="bi bi-x-circle mr-1"></i>เกิดข้อผิดพลาดในการอัปโหลด');
      }
    });
    xhr.addEventListener('error', () => {
      progressWrapper.style.display = 'none';
      uploadBtn.disabled = false;
      showAlert('danger', '<i class="bi bi-x-circle mr-1"></i>ไม่สามารถเชื่อมต่อได้ กรุณาลองใหม่');
    });
    xhr.send(formData);
  });

  function showAlert(type, html) {
    const classMap = {
      success: 'bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-800 dark:text-emerald-300',
      danger:  'bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 text-rose-800 dark:text-rose-300',
      warning: 'bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-amber-800 dark:text-amber-300',
    };
    resultAlert.className = 'p-3 rounded-xl text-sm ' + (classMap[type] || classMap.warning);
    resultAlert.innerHTML = html;
    resultAlert.style.display = '';
  }
})();
</script>
<?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/public/payment/partials/slip-upload.blade.php ENDPATH**/ ?>