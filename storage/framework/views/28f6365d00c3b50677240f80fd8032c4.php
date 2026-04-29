<?php $__env->startSection('title', 'สลิปการโอนเงิน'); ?>

<?php $__env->startSection('content'); ?>
<div class="flex justify-between items-center mb-6">
  <h4 class="font-bold text-xl tracking-tight">
    <i class="bi bi-credit-card-2-front mr-2 text-indigo-500"></i>สลิปการโอนเงิน
  </h4>
</div>


<div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-3">
  <div class="py-2 px-3">
    <div class="flex gap-1 flex-wrap">
      <a href="<?php echo e(route('admin.payments.index')); ?>" class="text-sm px-4 py-1.5 rounded-lg bg-indigo-500/[0.08] text-indigo-500 font-medium transition hover:bg-indigo-500/[0.15]">
        <i class="bi bi-receipt mr-1"></i> ธุรกรรม
      </a>
      <a href="<?php echo e(route('admin.payments.methods')); ?>" class="text-sm px-4 py-1.5 rounded-lg bg-indigo-500/[0.08] text-indigo-500 font-medium transition hover:bg-indigo-500/[0.15]">
        <i class="bi bi-wallet2 mr-1"></i> วิธีการชำระ
      </a>
      <a href="<?php echo e(route('admin.payments.slips')); ?>" class="text-sm px-4 py-1.5 rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-600 text-white font-medium">
        <i class="bi bi-image mr-1"></i> สลิปโอน
      </a>
      <a href="<?php echo e(route('admin.payments.banks')); ?>" class="text-sm px-4 py-1.5 rounded-lg bg-indigo-500/[0.08] text-indigo-500 font-medium transition hover:bg-indigo-500/[0.15]">
        <i class="bi bi-bank mr-1"></i> บัญชีธนาคาร
      </a>
      <a href="<?php echo e(route('admin.payments.payouts')); ?>" class="text-sm px-4 py-1.5 rounded-lg bg-indigo-500/[0.08] text-indigo-500 font-medium transition hover:bg-indigo-500/[0.15]">
        <i class="bi bi-cash-stack mr-1"></i> การจ่ายช่างภาพ
      </a>
    </div>
  </div>
</div>

<?php if(session('success')): ?>
<div class="flex items-center gap-2 mb-3 px-4 py-3 rounded-xl bg-emerald-500/10 text-emerald-800">
  <i class="bi bi-check-circle-fill"></i> <?php echo e(session('success')); ?>

</div>
<?php endif; ?>
<?php if(session('error')): ?>
<div class="flex items-center gap-2 mb-3 px-4 py-3 rounded-xl bg-red-500/10 text-red-800">
  <i class="bi bi-exclamation-circle-fill"></i> <?php echo e(session('error')); ?>

</div>
<?php endif; ?>


<div x-data="{
  showSettings: false,
  mode: '<?php echo e($settings['slip_verify_mode']); ?>',
  threshold: <?php echo e($settings['slip_auto_approve_threshold']); ?>,
  tolerance: <?php echo e($settings['slip_amount_tolerance_percent']); ?>,
  requireSlipok: <?php echo e($settings['slip_require_slipok_for_auto'] ? 'true' : 'false'); ?>,
  requireReceiver: <?php echo e($settings['slip_require_receiver_match'] ? 'true' : 'false'); ?>,
  slipokEnabled: <?php echo e($settings['slipok_enabled'] ? 'true' : 'false'); ?>

}" class="bg-white rounded-xl shadow-sm border border-gray-100 mb-3">
  <button type="button" @click="showSettings = !showSettings"
    class="w-full flex items-center justify-between py-3 px-4 text-left hover:bg-gray-50/50 transition rounded-xl">
    <div class="flex items-center gap-2">
      <i class="bi bi-gear text-indigo-500"></i>
      <span class="font-semibold text-sm">ตั้งค่าการตรวจสลิป</span>
      <span class="text-xs px-2 py-0.5 rounded-full font-medium"
        :class="mode === 'auto' ? 'bg-emerald-500/10 text-emerald-600' : 'bg-gray-500/10 text-gray-500'"
        x-text="mode === 'auto' ? 'อัตโนมัติ' : 'ตรวจเอง'"></span>
    </div>
    <i class="bi text-gray-400 transition-transform duration-200"
      :class="showSettings ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
  </button>

  <div x-show="showSettings" x-collapse x-cloak>
    <form method="POST" action="<?php echo e(route('admin.payments.slips.settings')); ?>" class="px-4 pb-4 border-t border-gray-100 pt-4">
      <?php echo csrf_field(); ?>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">โหมดตรวจสลิป</label>
            <div class="flex gap-3">
              <label class="flex-1 cursor-pointer">
                <input type="radio" name="slip_verify_mode" value="manual" x-model="mode" class="peer sr-only">
                <div class="border-2 rounded-xl p-3 text-center transition peer-checked:border-indigo-500 peer-checked:bg-indigo-500/[0.04]"
                  :class="mode === 'manual' ? 'border-indigo-500 bg-indigo-500/[0.04]' : 'border-gray-200 hover:border-gray-300'">
                  <i class="bi bi-person-check text-xl block mb-1"
                    :class="mode === 'manual' ? 'text-indigo-500' : 'text-gray-400'"></i>
                  <div class="font-semibold text-sm" :class="mode === 'manual' ? 'text-indigo-600' : 'text-gray-600'">ตรวจเอง</div>
                  <div class="text-xs text-gray-500 mt-0.5">แอดมินตรวจทุกสลิป</div>
                </div>
              </label>
              <label class="flex-1 cursor-pointer">
                <input type="radio" name="slip_verify_mode" value="auto" x-model="mode" class="peer sr-only">
                <div class="border-2 rounded-xl p-3 text-center transition peer-checked:border-emerald-500 peer-checked:bg-emerald-500/[0.04]"
                  :class="mode === 'auto' ? 'border-emerald-500 bg-emerald-500/[0.04]' : 'border-gray-200 hover:border-gray-300'">
                  <i class="bi bi-robot text-xl block mb-1"
                    :class="mode === 'auto' ? 'text-emerald-500' : 'text-gray-400'"></i>
                  <div class="font-semibold text-sm" :class="mode === 'auto' ? 'text-emerald-600' : 'text-gray-600'">อัตโนมัติ</div>
                  <div class="text-xs text-gray-500 mt-0.5">อนุมัติอัตโนมัติตามคะแนน</div>
                </div>
              </label>
            </div>
          </div>

          
          <div x-show="mode === 'auto'" x-transition class="bg-gray-50 rounded-xl p-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              คะแนนขั้นต่ำสำหรับอนุมัติอัตโนมัติ: <span class="text-indigo-600 font-bold" x-text="threshold + '%'"></span>
            </label>
            <input type="range" name="slip_auto_approve_threshold" min="50" max="100" step="5"
              x-model="threshold"
              class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-indigo-500">
            <div class="flex justify-between text-xs text-gray-400 mt-1">
              <span>50%</span>
              <span>75%</span>
              <span>100%</span>
            </div>
            <p class="text-xs text-gray-500 mt-2">
              <i class="bi bi-info-circle mr-1"></i>สลิปที่คะแนนตรวจสอบ >= <span x-text="threshold"></span>% จะถูกอนุมัติอัตโนมัติ
            </p>
          </div>
          
          <input x-show="mode !== 'auto'" type="hidden" name="slip_auto_approve_threshold" :value="threshold">

          
          <div class="bg-gray-50 rounded-xl p-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              ช่วงคลาดเคลื่อนของยอดเงิน: <span class="text-indigo-600 font-bold" x-text="tolerance + '%'"></span>
            </label>
            <input type="range" name="slip_amount_tolerance_percent" min="0.1" max="5" step="0.1"
              x-model.number="tolerance"
              class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-indigo-500">
            <div class="flex justify-between text-xs text-gray-400 mt-1">
              <span>0.1%</span>
              <span>1%</span>
              <span>5%</span>
            </div>
            <p class="text-xs text-gray-500 mt-2">
              <i class="bi bi-info-circle mr-1"></i>ยอดเงินในสลิปต้องไม่ต่างจากออเดอร์เกิน <span x-text="tolerance"></span>% (แนะนำ 1% สำหรับร้านค้าทั่วไป)
            </p>
          </div>

          
          <div x-show="mode === 'auto' && slipokEnabled" x-transition class="bg-amber-50 border border-amber-200 rounded-xl p-4 space-y-3">
            <div class="flex items-center gap-2 text-sm font-semibold text-amber-700">
              <i class="bi bi-shield-check"></i> โหมดเข้มงวด (แนะนำ)
            </div>
            <label class="flex items-start gap-2 cursor-pointer">
              <input type="hidden" name="slip_require_slipok_for_auto" value="0">
              <input type="checkbox" name="slip_require_slipok_for_auto" value="1" x-model="requireSlipok"
                class="mt-0.5 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
              <div class="text-xs">
                <div class="font-medium text-gray-700">ต้องผ่าน SlipOK API ก่อนอนุมัติอัตโนมัติ</div>
                <div class="text-gray-500">ลดความเสี่ยงสลิปปลอมที่ผ่านเกณฑ์คะแนนด้านอื่น</div>
              </div>
            </label>
            <label class="flex items-start gap-2 cursor-pointer">
              <input type="hidden" name="slip_require_receiver_match" value="0">
              <input type="checkbox" name="slip_require_receiver_match" value="1" x-model="requireReceiver"
                class="mt-0.5 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
              <div class="text-xs">
                <div class="font-medium text-gray-700">ต้องโอนเข้าบัญชีที่ตั้งไว้</div>
                <div class="text-gray-500">ป้องกันผู้ใช้อัปสลิปของร้านอื่น (ต้องตั้งบัญชีธนาคารไว้)</div>
              </div>
            </label>
          </div>
        </div>

        
        <div class="space-y-4">
          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="text-sm font-medium text-gray-700">SlipOK API</label>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="hidden" name="slipok_enabled" value="0">
                <input type="checkbox" name="slipok_enabled" value="1" x-model="slipokEnabled"
                  class="sr-only peer">
                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-500"></div>
                <span class="ml-2 text-xs font-medium" :class="slipokEnabled ? 'text-indigo-600' : 'text-gray-400'" x-text="slipokEnabled ? 'เปิด' : 'ปิด'"></span>
              </label>
            </div>
            <p class="text-xs text-gray-500 mb-3">
              <i class="bi bi-info-circle mr-1"></i>เชื่อมต่อ SlipOK สำหรับตรวจสอบสลิปอัตโนมัติผ่าน API
            </p>
          </div>

          <div x-show="slipokEnabled" x-transition class="space-y-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
              <input type="password" name="slipok_api_key"
                value="<?php echo e($settings['slipok_api_key']); ?>"
                placeholder="ใส่ API Key ใหม่เพื่อเปลี่ยน..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                autocomplete="off">
              <?php if($settings['slipok_api_key']): ?>
              <p class="text-xs text-emerald-600 mt-1"><i class="bi bi-check-circle mr-1"></i>ตั้งค่า API Key แล้ว</p>
              <?php endif; ?>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Branch ID</label>
              <input type="text" name="slipok_branch_id"
                value="<?php echo e($settings['slipok_branch_id']); ?>"
                placeholder="เช่น 12345"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
          </div>
        </div>
      </div>

      
      <div class="flex justify-end mt-5 pt-4 border-t border-gray-100">
        <button type="submit"
          class="bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-lg font-medium text-sm px-6 py-2.5 transition hover:from-indigo-600 hover:to-indigo-700 flex items-center gap-2">
          <i class="bi bi-check-lg"></i> บันทึกการตั้งค่า
        </button>
      </div>
    </form>
  </div>
</div>


<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 h-full">
    <div class="flex items-center gap-3 py-3 px-4">
      <div class="w-11 h-11 rounded-xl bg-indigo-500/10 flex items-center justify-center flex-shrink-0">
        <i class="bi bi-image text-lg text-indigo-500"></i>
      </div>
      <div>
        <div class="font-bold text-xl leading-none"><?php echo e(number_format($stats->total ?? 0)); ?></div>
        <div class="text-gray-500 text-sm mt-1">สลิปทั้งหมด</div>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 h-full">
    <div class="flex items-center gap-3 py-3 px-4">
      <div class="w-11 h-11 rounded-xl bg-amber-500/10 flex items-center justify-center flex-shrink-0">
        <i class="bi bi-hourglass-split text-lg text-amber-500"></i>
      </div>
      <div>
        <div class="font-bold text-xl leading-none text-amber-500"><?php echo e(number_format($stats->pending_count ?? 0)); ?></div>
        <div class="text-gray-500 text-sm mt-1">รอตรวจสอบ</div>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 h-full">
    <div class="flex items-center gap-3 py-3 px-4">
      <div class="w-11 h-11 rounded-xl bg-emerald-500/10 flex items-center justify-center flex-shrink-0">
        <i class="bi bi-check-circle text-lg text-emerald-500"></i>
      </div>
      <div>
        <div class="font-bold text-xl leading-none text-emerald-500"><?php echo e(number_format($stats->approved_count ?? 0)); ?></div>
        <div class="text-gray-500 text-sm mt-1">อนุมัติแล้ว</div>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 h-full">
    <div class="flex items-center gap-3 py-3 px-4">
      <div class="w-11 h-11 rounded-xl bg-red-500/10 flex items-center justify-center flex-shrink-0">
        <i class="bi bi-x-circle text-lg text-red-500"></i>
      </div>
      <div>
        <div class="font-bold text-xl leading-none text-red-500"><?php echo e(number_format($stats->rejected_count ?? 0)); ?></div>
        <div class="text-gray-500 text-sm mt-1">ปฏิเสธ</div>
      </div>
    </div>
  </div>
</div>


<div class="af-bar mb-3" x-data="adminFilter()">
  <form method="GET" action="<?php echo e(route('admin.payments.slips')); ?>">
    <div class="af-grid">

      
      <div class="af-search">
        <label class="af-label">ค้นหา</label>
        <div class="af-search-wrap">
          <i class="bi bi-search af-search-icon"></i>
          <input type="text" name="search" class="af-input" placeholder="เลขออเดอร์, อีเมล, รหัสอ้างอิง..." value="<?php echo e(request('search')); ?>">
          <div class="af-spinner" x-show="loading" x-cloak></div>
        </div>
      </div>

      
      <div>
        <label class="af-label">สถานะ</label>
        <select name="status" class="af-input">
          <option value="">ทุกสถานะ</option>
          <option value="pending"  <?php echo e(request('status') === 'pending'  ? 'selected' : ''); ?>>รอตรวจสอบ</option>
          <option value="approved" <?php echo e(request('status') === 'approved' ? 'selected' : ''); ?>>อนุมัติแล้ว</option>
          <option value="rejected" <?php echo e(request('status') === 'rejected' ? 'selected' : ''); ?>>ปฏิเสธ</option>
        </select>
      </div>

      
      <div class="af-actions">
        <button type="button" class="af-btn-clear" @click="clearFilters()">
          <i class="bi bi-x-lg mr-1"></i>ล้าง
        </button>
      </div>

    </div>
  </form>
</div>

<div id="admin-table-area">

<form id="bulkForm" method="POST">
  <?php echo csrf_field(); ?>
  <input type="hidden" name="reason" id="bulkReasonInput" value="">


<div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-3 hidden" id="bulkBar">
  <div class="py-2 px-4 flex items-center gap-3 flex-wrap">
    <span class="text-gray-500 text-sm font-medium" id="selectedCount">0 รายการที่เลือก</span>
    <button type="button" class="text-sm px-4 py-1.5 rounded-lg bg-emerald-500/10 text-emerald-500 font-medium transition hover:bg-emerald-500/[0.15]" onclick="submitBulkApprove()">
      <i class="bi bi-check-all mr-1"></i> อนุมัติที่เลือก
    </button>
    <button type="button" class="text-sm px-4 py-1.5 rounded-lg bg-red-500/10 text-red-500 font-medium transition hover:bg-red-500/[0.15]" onclick="openBulkRejectModal()">
      <i class="bi bi-x-lg mr-1"></i> ปฏิเสธที่เลือก
    </button>
  </div>
</div>


<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-indigo-500/[0.03]">
        <tr>
          <th class="px-3 py-3 text-left w-10">
            <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
          </th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ออเดอร์ #</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ผู้ซื้อ</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ยอดเงิน</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">วันที่โอน</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">รหัสอ้างอิง</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">สลิป</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">คะแนน</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">สถานะ</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">จัดการ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php $__empty_1 = true; $__currentLoopData = $slips; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slip): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <?php
          $isPending = ($slip->verify_status ?? 'pending') === 'pending';
          $statusMap = [
            'pending' => ['bg' => 'rgba(245,158,11,0.1)', 'color' => '#f59e0b', 'label' => 'รอตรวจสอบ'],
            'approved' => ['bg' => 'rgba(16,185,129,0.1)', 'color' => '#10b981', 'label' => 'อนุมัติแล้ว'],
            'rejected' => ['bg' => 'rgba(239,68,68,0.1)', 'color' => '#ef4444', 'label' => 'ปฏิเสธ'],
          ];
          $sc = $statusMap[$slip->verify_status ?? 'pending'] ?? ['bg' => 'rgba(107,114,128,0.1)', 'color' => '#6b7280', 'label' => $slip->verify_status ?? 'pending'];
          $score = $slip->verify_score ?? null;
          $scoreColor = $score === null ? '#94a3b8' : ($score >= 80 ? '#10b981' : ($score >= 50 ? '#f59e0b' : '#ef4444'));
          $scoreBg    = $score === null ? 'bg-gray-200' : ($score >= 80 ? 'bg-emerald-500' : ($score >= 50 ? 'bg-amber-500' : 'bg-red-500'));
          $scoreBadgeBg = $score === null ? 'bg-gray-100 text-gray-400' : ($score >= 80 ? 'bg-emerald-500/10 text-emerald-600' : ($score >= 50 ? 'bg-amber-500/10 text-amber-600' : 'bg-red-500/10 text-red-600'));
          $fraudFlags = [];
          if (!empty($slip->fraud_flags)) {
              $decoded = is_string($slip->fraud_flags) ? json_decode($slip->fraud_flags, true) : (array) $slip->fraud_flags;
              $fraudFlags = is_array($decoded) ? $decoded : [];
          }
          $slipImage = $slip->slip_image ?? $slip->slip_path ?? null;
          // `$slip` is a stdClass from a raw DB join, not a PaymentSlip model —
          // so the slip_url accessor isn't available. Resolve via StorageManager
          // directly; it figures out whether the file lives on R2, S3, or the
          // legacy `public` disk without us having to know up-front.
          $slipUrl = $slipImage
              ? app(\App\Services\StorageManager::class)->resolveUrl($slipImage)
              : '';
        ?>
        <tr class="hover:bg-gray-50/50 transition align-middle">
          <td class="px-3 py-3">
            <?php if($isPending): ?>
            <input type="checkbox" name="slip_ids[]" value="<?php echo e($slip->id); ?>" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer slip-check">
            <?php endif; ?>
          </td>
          <td class="px-4 py-3">
            <span class="font-semibold text-indigo-500">#<?php echo e($slip->order_number ?? $slip->order_id); ?></span>
          </td>
          <td class="px-4 py-3">
            <div class="font-medium text-sm"><?php echo e(trim(($slip->first_name ?? '') . ' ' . ($slip->last_name ?? '')) ?: '-'); ?></div>
            <div class="text-gray-500 text-xs"><?php echo e($slip->user_email ?? '-'); ?></div>
          </td>
          <td class="px-4 py-3 font-semibold">
            ฿<?php echo e(number_format($slip->transfer_amount ?? $slip->amount ?? 0, 2)); ?>

          </td>
          <td class="px-4 py-3 text-gray-500 text-sm">
            <?php echo e($slip->transfer_date ? \Carbon\Carbon::parse($slip->transfer_date)->format('d/m/Y H:i') : '-'); ?>

          </td>
          <td class="px-4 py-3">
            <?php if($slip->ref_code ?? $slip->reference_code): ?>
            <code class="bg-indigo-500/[0.08] text-indigo-500 px-2 py-0.5 rounded text-xs"><?php echo e($slip->ref_code ?? $slip->reference_code); ?></code>
            <?php else: ?>
            <span class="text-gray-500">-</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3">
            <?php if($slipImage): ?>
            <img src="<?php echo e($slipUrl); ?>"
               alt="สลิป"
               class="rounded-lg cursor-pointer slip-thumb w-12 h-12 object-cover border-2 border-gray-200 hover:border-indigo-300 transition"
               onclick="previewSlip(this.src)"
               loading="lazy">
            <?php else: ?>
            <div class="w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center">
              <i class="bi bi-image text-gray-500"></i>
            </div>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3">
            <?php if($score !== null): ?>
            <div class="flex items-center gap-2">
              <div class="w-16">
                <div class="flex items-center justify-between mb-0.5">
                  <span class="font-bold text-sm <?php echo e($scoreBadgeBg); ?> px-1.5 py-0 rounded"><?php echo e($score); ?>%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-1.5">
                  <div class="<?php echo e($scoreBg); ?> h-1.5 rounded-full transition-all" style="width: <?php echo e(min($score, 100)); ?>%"></div>
                </div>
              </div>
              <?php if($score >= 80): ?>
              <i class="bi bi-shield-check text-emerald-500 text-sm" title="คะแนนสูง"></i>
              <?php elseif($score < 50): ?>
              <i class="bi bi-exclamation-triangle text-red-500 text-sm" title="คะแนนต่ำ"></i>
              <?php endif; ?>
            </div>
            <?php if(count($fraudFlags) > 0): ?>
            <div class="mt-1 flex flex-wrap gap-1">
              <?php $__currentLoopData = $fraudFlags; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $flag): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-500/10 text-red-600" title="<?php echo e($flag); ?>">
                <i class="bi bi-flag-fill text-[8px]"></i><?php echo e(Str::limit($flag, 20)); ?>

              </span>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <span class="text-gray-400 text-xs">ยังไม่ตรวจ</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold" style="background:<?php echo e($sc['bg']); ?>;color:<?php echo e($sc['color']); ?>;">
              <?php echo e($sc['label']); ?>

            </span>
            <?php if(($slip->verify_status ?? '') === 'rejected' && $slip->reject_reason): ?>
            <div class="text-gray-500 mt-1 text-xs" title="<?php echo e($slip->reject_reason); ?>">
              <i class="bi bi-info-circle mr-1"></i><?php echo e(Str::limit($slip->reject_reason, 30)); ?>

            </div>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3">
            <?php if($isPending): ?>
            <div class="flex gap-1">
              <button type="button"
                class="w-8 h-8 rounded-lg bg-emerald-500/[0.08] text-emerald-500 flex items-center justify-center transition hover:bg-emerald-500/[0.15]"
                title="อนุมัติ"
                onclick="confirmApprove(<?php echo e($slip->id); ?>, '<?php echo e($slip->order_number ?? $slip->order_id); ?>')">
                <i class="bi bi-check-lg text-sm"></i>
              </button>
              <button type="button"
                class="w-8 h-8 rounded-lg bg-red-500/[0.08] text-red-500 flex items-center justify-center transition hover:bg-red-500/[0.15]"
                title="ปฏิเสธ"
                onclick="openRejectModal(<?php echo e($slip->id); ?>, '<?php echo e($slip->order_number ?? $slip->order_id); ?>')">
                <i class="bi bi-x-lg text-sm"></i>
              </button>
            </div>
            <?php else: ?>
            <span class="text-gray-500 text-sm">-</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr>
          <td colspan="10" class="text-center py-12">
            <i class="bi bi-image text-4xl text-gray-300"></i>
            <p class="text-gray-500 mt-2 text-sm">ยังไม่มีสลิปการโอนเงิน</p>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</form>

</div>

<?php if($slips->hasPages()): ?>
<div id="admin-pagination-area" class="flex justify-center mt-6"><?php echo e($slips->withQueryString()->links()); ?></div>
<?php endif; ?>


<div x-data="{ open: false }" x-on:open-approve-modal.window="open = true" x-cloak>
  <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/40" @click="open = false"></div>
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
      <div class="text-center">
        <div class="w-16 h-16 rounded-full bg-emerald-500/10 flex items-center justify-center mx-auto mb-4">
          <i class="bi bi-check-circle-fill text-3xl text-emerald-500"></i>
        </div>
        <h5 class="font-bold text-lg mb-2">ยืนยันการอนุมัติ</h5>
        <p class="text-gray-500 mb-6" id="approveModalText">คุณต้องการอนุมัติสลิปนี้ใช่หรือไม่?</p>
        <div class="flex gap-2 justify-center">
          <button type="button" @click="open = false" class="rounded-lg bg-gray-500/10 text-gray-500 font-medium px-6 py-2.5 transition hover:bg-gray-500/[0.15]">ยกเลิก</button>
          <form id="approveForm" method="POST">
            <?php echo csrf_field(); ?>
            <button type="submit" class="rounded-lg bg-gradient-to-br from-emerald-500 to-emerald-600 text-white font-medium px-6 py-2.5 transition hover:from-emerald-600 hover:to-emerald-700">
              <i class="bi bi-check-lg mr-1"></i> อนุมัติ
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>


<div x-data="{ open: false }" x-on:open-reject-modal.window="open = true" x-cloak>
  <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/40" @click="open = false"></div>
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
      <div class="text-center mb-4">
        <div class="w-16 h-16 rounded-full bg-red-500/10 flex items-center justify-center mx-auto mb-4">
          <i class="bi bi-x-circle-fill text-3xl text-red-500"></i>
        </div>
        <h5 class="font-bold text-lg mb-1">ปฏิเสธสลิป</h5>
        <p class="text-gray-500 text-sm" id="rejectModalText">กรุณาระบุเหตุผล</p>
      </div>
      <form id="rejectForm" method="POST">
        <?php echo csrf_field(); ?>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1.5">เหตุผลการปฏิเสธ <span class="text-red-500">*</span></label>
          <textarea name="reason" rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none" placeholder="ระบุเหตุผลที่ปฏิเสธสลิป..." required maxlength="500"></textarea>
        </div>
        <div class="flex gap-2 justify-end">
          <button type="button" @click="open = false" class="rounded-lg bg-gray-500/10 text-gray-500 font-medium px-5 py-2 transition hover:bg-gray-500/[0.15]">ยกเลิก</button>
          <button type="submit" class="rounded-lg bg-gradient-to-br from-red-500 to-red-600 text-white font-medium px-5 py-2 transition hover:from-red-600 hover:to-red-700">
            <i class="bi bi-x-lg mr-1"></i> ปฏิเสธ
          </button>
        </div>
      </form>
    </div>
  </div>
</div>


<div x-data="{ open: false }" x-on:open-bulk-reject-modal.window="open = true" x-cloak>
  <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/40" @click="open = false"></div>
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
      <div class="text-center mb-4">
        <div class="w-16 h-16 rounded-full bg-red-500/10 flex items-center justify-center mx-auto mb-4">
          <i class="bi bi-x-circle-fill text-3xl text-red-500"></i>
        </div>
        <h5 class="font-bold text-lg mb-1">ปฏิเสธสลิปที่เลือก</h5>
        <p class="text-gray-500 text-sm" id="bulkRejectText">กรุณาระบุเหตุผล</p>
      </div>
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1.5">เหตุผลการปฏิเสธ <span class="text-red-500">*</span></label>
        <textarea id="bulkRejectReason" rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none" placeholder="ระบุเหตุผล..." maxlength="500"></textarea>
      </div>
      <div class="flex gap-2 justify-end">
        <button type="button" @click="open = false" class="rounded-lg bg-gray-500/10 text-gray-500 font-medium px-5 py-2 transition hover:bg-gray-500/[0.15]">ยกเลิก</button>
        <button type="button" onclick="submitBulkReject()" class="rounded-lg bg-gradient-to-br from-red-500 to-red-600 text-white font-medium px-5 py-2 transition hover:from-red-600 hover:to-red-700">
          <i class="bi bi-x-lg mr-1"></i> ปฏิเสธทั้งหมด
        </button>
      </div>
    </div>
  </div>
</div>


<div x-data="{ open: false, src: '' }" x-on:open-slip-preview.window="src = $event.detail; open = true" x-cloak>
  <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/60" @click="open = false"></div>
    <div x-show="open" x-transition class="relative max-w-3xl w-full text-center">
      <button type="button" @click="open = false" class="absolute -top-3 -right-3 w-9 h-9 bg-white rounded-full shadow-lg flex items-center justify-center z-10 hover:bg-gray-100 transition">
        <i class="bi bi-x-lg text-gray-600"></i>
      </button>
      <img :src="src" alt="สลิป" class="max-h-[85vh] rounded-xl object-contain mx-auto">
    </div>
  </div>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
// Checkbox select all
const selectAll = document.getElementById('selectAll');
const bulkBar = document.getElementById('bulkBar');
const selectedCount = document.getElementById('selectedCount');

function updateBulkBar() {
  const checked = document.querySelectorAll('.slip-check:checked').length;
  selectedCount.textContent = checked + ' รายการที่เลือก';
  bulkBar.classList.toggle('hidden', checked === 0);
}

selectAll?.addEventListener('change', function () {
  document.querySelectorAll('.slip-check').forEach(cb => cb.checked = this.checked);
  updateBulkBar();
});

document.querySelectorAll('.slip-check').forEach(cb => {
  cb.addEventListener('change', function () {
    const all = document.querySelectorAll('.slip-check');
    const checked = document.querySelectorAll('.slip-check:checked');
    selectAll.checked = all.length > 0 && checked.length === all.length;
    selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
    updateBulkBar();
  });
});

// Approve single
function confirmApprove(slipId, orderNum) {
  document.getElementById('approveModalText').textContent =
    'คุณต้องการอนุมัติสลิปสำหรับออเดอร์ #' + orderNum + ' ใช่หรือไม่?';
  document.getElementById('approveForm').action = '/admin/payments/slips/' + slipId + '/approve';
  window.dispatchEvent(new CustomEvent('open-approve-modal'));
}

// Reject single
function openRejectModal(slipId, orderNum) {
  document.getElementById('rejectModalText').textContent =
    'ออเดอร์ #' + orderNum + ' — กรุณาระบุเหตุผล';
  document.getElementById('rejectForm').action = '/admin/payments/slips/' + slipId + '/reject';
  document.querySelector('#rejectForm textarea[name="reason"]').value = '';
  window.dispatchEvent(new CustomEvent('open-reject-modal'));
}

// Bulk approve
function submitBulkApprove() {
  const form = document.getElementById('bulkForm');
  form.action = '<?php echo e(route('admin.payments.slips.bulk-approve')); ?>';
  document.getElementById('bulkReasonInput').name = '';
  form.submit();
}

// Bulk reject modal
function openBulkRejectModal() {
  document.getElementById('bulkRejectText').textContent =
    document.querySelectorAll('.slip-check:checked').length + ' รายการที่เลือก';
  document.getElementById('bulkRejectReason').value = '';
  window.dispatchEvent(new CustomEvent('open-bulk-reject-modal'));
}

function submitBulkReject() {
  const reason = document.getElementById('bulkRejectReason').value.trim();
  if (!reason) {
    document.getElementById('bulkRejectReason').classList.add('border-red-500');
    return;
  }
  const form = document.getElementById('bulkForm');
  form.action = '<?php echo e(route('admin.payments.slips.bulk-reject')); ?>';
  document.getElementById('bulkReasonInput').name = 'reason';
  document.getElementById('bulkReasonInput').value = reason;
  form.submit();
}

// Slip image preview
function previewSlip(src) {
  window.dispatchEvent(new CustomEvent('open-slip-preview', { detail: src }));
}
</script>
<?php $__env->stopPush(); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/admin/payments/slips.blade.php ENDPATH**/ ?>