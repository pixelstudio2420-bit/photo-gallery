<?php $__env->startSection('title', 'คำสั่งซื้อ #' . $order->id); ?>

<?php
    /**
     * Status presentation table — every status code the admin app may see
     * mapped to a colour scheme + Thai label. Centralised here so a future
     * status (e.g. partial_refund) is added in one place.
     */
    $statusMeta = [
        'paid'              => ['label' => 'ชำระเงินแล้ว',     'tone' => 'emerald', 'icon' => 'bi-check-circle-fill'],
        'completed'         => ['label' => 'เสร็จสิ้น',          'tone' => 'emerald', 'icon' => 'bi-check2-all'],
        'pending'           => ['label' => 'รอดำเนินการ',       'tone' => 'amber',   'icon' => 'bi-clock'],
        'pending_payment'   => ['label' => 'รอชำระเงิน',         'tone' => 'amber',   'icon' => 'bi-hourglass-split'],
        'pending_review'    => ['label' => 'รอตรวจสอบสลิป',     'tone' => 'amber',   'icon' => 'bi-eye'],
        'cancelled'         => ['label' => 'ยกเลิก',             'tone' => 'rose',    'icon' => 'bi-x-circle'],
        'failed'            => ['label' => 'ล้มเหลว',            'tone' => 'rose',    'icon' => 'bi-exclamation-triangle'],
        'refunded'          => ['label' => 'คืนเงินแล้ว',         'tone' => 'slate',   'icon' => 'bi-arrow-counterclockwise'],
        'cart'              => ['label' => 'ตะกร้า',             'tone' => 'slate',   'icon' => 'bi-cart'],
    ];
    $st = $statusMeta[$order->status] ?? ['label' => ucfirst($order->status), 'tone' => 'slate', 'icon' => 'bi-circle'];

    /** Thai label for order_type. */
    $typeLabel = match ($order->order_type ?? 'photo_package') {
        'photo_package'              => 'ซื้อภาพ',
        'credit_package'             => 'ซื้อเครดิต',
        'subscription'               => 'สมัครแผน',
        'user_storage_subscription'  => 'พื้นที่ผู้ใช้',
        'gift_card'                  => 'บัตรของขวัญ',
        'addon'                      => 'บริการเสริม',
        default                      => $order->order_type ?? 'ภาพ',
    };

    /**
     * Map tone → Tailwind classes for badges. Inline `style` colours
     * dodge the legacy darkmode.css `[data-bs-theme="dark"] .bg-{color}-50`
     * overrides that wash the badge into a flat slate block on dark mode.
     * Using rgba()-based backgrounds with opacity keeps the badge visible
     * on BOTH light slate and dark slate page bodies, so the same class
     * list works across themes.
     */
    $toneClasses = [
        'emerald' => 'text-emerald-700 dark:text-emerald-300 ring-emerald-300/50',
        'amber'   => 'text-amber-700   dark:text-amber-300   ring-amber-300/50',
        'rose'    => 'text-rose-700    dark:text-rose-300    ring-rose-300/50',
        'slate'   => 'text-slate-700   dark:text-slate-300   ring-slate-300/50',
        'indigo'  => 'text-indigo-700  dark:text-indigo-300  ring-indigo-300/50',
    ];
    $toneBg = [
        'emerald' => 'rgba(16,185,129,0.15)',
        'amber'   => 'rgba(245,158,11,0.18)',
        'rose'    => 'rgba(244, 63, 94,0.18)',
        'slate'   => 'rgba(100,116,139,0.18)',
        'indigo'  => 'rgba(99,102,241,0.15)',
    ];
    $statusBadge = $toneClasses[$st['tone']] ?? $toneClasses['slate'];
    $statusBadgeBg = $toneBg[$st['tone']] ?? $toneBg['slate'];

    /** Money + bytes helpers used by multiple sections. */
    $money = fn ($v) => '฿' . number_format((float) $v, 2);
?>

<?php $__env->startSection('content'); ?>
<div class="max-w-7xl mx-auto p-4 md:p-6 space-y-6">

    
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <a href="<?php echo e(route('admin.orders.index')); ?>"
               class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-indigo-600 mb-2">
                <i class="bi bi-arrow-left"></i> รายการคำสั่งซื้อทั้งหมด
            </a>
            <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                คำสั่งซื้อ #<?php echo e($order->id); ?>

            </h1>
            <div class="flex flex-wrap items-center gap-2 mt-2 text-sm">
                <code class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-mono text-xs">
                    <?php echo e($order->order_number ?? 'O-' . $order->id); ?>

                </code>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset <?php echo e($statusBadge); ?>"
                      style="background:<?php echo e($statusBadgeBg); ?>;">
                    <i class="bi <?php echo e($st['icon']); ?>"></i> <?php echo e($st['label']); ?>

                </span>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset <?php echo e($toneClasses['indigo']); ?>"
                      style="background:<?php echo e($toneBg['indigo']); ?>;">
                    <i class="bi bi-tag-fill"></i> <?php echo e($typeLabel); ?>

                </span>
                <span class="text-slate-500 dark:text-slate-400 text-xs">
                    สั่งซื้อ <?php echo e($order->created_at?->format('d/m/Y H:i') ?? '-'); ?>

                </span>
            </div>
        </div>

        
        <div class="flex flex-wrap items-center gap-2">
            <?php if(!in_array($order->status, ['paid','completed','cancelled','refunded'])): ?>
                <form method="POST" action="<?php echo e(route('admin.orders.update', $order->id)); ?>" class="inline">
                    <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
                    <input type="hidden" name="status" value="paid">
                    <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm transition">
                        <i class="bi bi-check-circle"></i> ทำเครื่องหมายชำระแล้ว
                    </button>
                </form>
                <form method="POST" action="<?php echo e(route('admin.orders.update', $order->id)); ?>" class="inline">
                    <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
                    <input type="hidden" name="status" value="cancelled">
                    
                    <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg ring-1 ring-rose-300/60
                                   text-rose-700 dark:text-rose-300 font-semibold text-sm transition
                                   hover:ring-rose-400"
                            style="background:rgba(244,63,94,0.15);"
                            onclick="return confirm('ยืนยันยกเลิกคำสั่งซื้อนี้?');">
                        <i class="bi bi-x-circle"></i> ยกเลิก
                    </button>
                </form>
            <?php endif; ?>
            <?php if($order->status === 'paid'): ?>
                <form method="POST" action="<?php echo e(route('admin.orders.update', $order->id)); ?>" class="inline">
                    <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
                    <input type="hidden" name="status" value="completed">
                    <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm transition">
                        <i class="bi bi-check2-all"></i> ทำเครื่องหมายเสร็จสิ้น
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if(session('success')): ?>
        <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 text-emerald-700 px-4 py-3 text-sm">
            <i class="bi bi-check-circle-fill"></i> <?php echo e(session('success')); ?>

        </div>
    <?php endif; ?>
    <?php if(session('error')): ?>
        <div class="rounded-xl bg-rose-50 ring-1 ring-rose-200 text-rose-700 px-4 py-3 text-sm">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo e(session('error')); ?>

        </div>
    <?php endif; ?>

    
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 ring-1 ring-slate-100 dark:ring-slate-700">
            <div class="text-xs uppercase tracking-wide text-slate-500 mb-1">ยอดสุทธิ</div>
            <div class="text-2xl font-extrabold text-indigo-600"><?php echo e($money($order->total)); ?></div>
            <?php if((float) ($order->discount_amount ?? 0) > 0): ?>
                <div class="text-xs text-slate-400 mt-1">
                    ก่อนส่วนลด: <?php echo e($money($order->subtotal ?? $order->total)); ?>

                </div>
            <?php endif; ?>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 ring-1 ring-slate-100 dark:ring-slate-700">
            <div class="text-xs uppercase tracking-wide text-slate-500 mb-1">รายการ</div>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white">
                <?php echo e($order->items->count() ?? 0); ?>

            </div>
            <div class="text-xs text-slate-400 mt-1"><?php echo e($typeLabel); ?></div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 ring-1 ring-slate-100 dark:ring-slate-700">
            <div class="text-xs uppercase tracking-wide text-slate-500 mb-1">ชำระเงิน</div>
            <div class="text-2xl font-extrabold <?php echo e($order->paid_at ? 'text-emerald-600' : 'text-amber-600'); ?>">
                <?php if($order->paid_at): ?>
                    <i class="bi bi-check-circle-fill"></i> ชำระแล้ว
                <?php else: ?>
                    <i class="bi bi-clock"></i> ยังไม่ชำระ
                <?php endif; ?>
            </div>
            <?php if($order->paid_at): ?>
                <div class="text-xs text-slate-400 mt-1"><?php echo e(\Carbon\Carbon::parse($order->paid_at)->format('d/m/Y H:i')); ?></div>
            <?php endif; ?>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 ring-1 ring-slate-100 dark:ring-slate-700">
            <div class="text-xs uppercase tracking-wide text-slate-500 mb-1">ส่งมอบ</div>
            <div class="text-2xl font-extrabold <?php echo e($order->delivered_at ? 'text-emerald-600' : 'text-slate-400'); ?>">
                <?php if($order->delivered_at): ?>
                    <i class="bi bi-truck"></i> ส่งแล้ว
                <?php else: ?>
                    <i class="bi bi-dash-circle"></i> รอส่ง
                <?php endif; ?>
            </div>
            <?php if($order->delivered_at): ?>
                <div class="text-xs text-slate-400 mt-1"><?php echo e($order->delivered_at->format('d/m/Y H:i')); ?></div>
            <?php else: ?>
                <div class="text-xs text-slate-400 mt-1"><?php echo e($order->delivery_method ?? 'auto'); ?></div>
            <?php endif; ?>
        </div>
    </div>

    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        
        <div class="lg:col-span-2 space-y-6">

            
            <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm overflow-hidden">
                <header class="px-5 py-4 border-b border-slate-100 dark:border-slate-700">
                    <h2 class="font-bold text-slate-900 dark:text-white">
                        <i class="bi bi-receipt text-indigo-500"></i> ข้อมูลคำสั่งซื้อ
                    </h2>
                </header>
                <dl class="divide-y divide-slate-100 dark:divide-slate-700">
                    <div class="grid grid-cols-3 gap-4 px-5 py-3 text-sm">
                        <dt class="text-slate-500">หมายเลข</dt>
                        <dd class="col-span-2 font-mono font-semibold text-slate-900 dark:text-white">
                            <?php echo e($order->order_number ?? '#' . $order->id); ?>

                        </dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-5 py-3 text-sm">
                        <dt class="text-slate-500">ประเภท</dt>
                        <dd class="col-span-2 text-slate-900 dark:text-white"><?php echo e($typeLabel); ?></dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-5 py-3 text-sm">
                        <dt class="text-slate-500">วันที่สั่งซื้อ</dt>
                        <dd class="col-span-2 text-slate-900 dark:text-white">
                            <?php echo e($order->created_at?->format('d/m/Y H:i') ?? '-'); ?>

                            <?php if($order->created_at): ?>
                                <span class="text-slate-400 ml-1">(<?php echo e($order->created_at->diffForHumans()); ?>)</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <?php if($order->paid_at): ?>
                        <div class="grid grid-cols-3 gap-4 px-5 py-3 text-sm">
                            <dt class="text-slate-500">ชำระเมื่อ</dt>
                            <dd class="col-span-2 text-emerald-600 dark:text-emerald-400 font-medium">
                                <?php echo e(\Carbon\Carbon::parse($order->paid_at)->format('d/m/Y H:i')); ?>

                            </dd>
                        </div>
                    <?php endif; ?>
                    <?php if((float) ($order->discount_amount ?? 0) > 0): ?>
                        <div class="grid grid-cols-3 gap-4 px-5 py-3 text-sm">
                            <dt class="text-slate-500">ส่วนลด</dt>
                            <dd class="col-span-2 text-slate-900 dark:text-white">
                                <span class="text-rose-600 font-medium">−<?php echo e($money($order->discount_amount)); ?></span>
                                <?php if($order->coupon_code): ?>
                                    <code class="ml-2 px-2 py-0.5 rounded bg-rose-50 text-rose-700 text-xs"><?php echo e($order->coupon_code); ?></code>
                                <?php endif; ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                    <?php if($order->note): ?>
                        <div class="grid grid-cols-3 gap-4 px-5 py-3 text-sm">
                            <dt class="text-slate-500">หมายเหตุ</dt>
                            <dd class="col-span-2 text-slate-900 dark:text-white whitespace-pre-line"><?php echo e($order->note); ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if($order->delivery_method): ?>
                        <div class="grid grid-cols-3 gap-4 px-5 py-3 text-sm">
                            <dt class="text-slate-500">ช่องทางส่งมอบ</dt>
                            <dd class="col-span-2 text-slate-900 dark:text-white">
                                <code class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-xs"><?php echo e($order->delivery_method); ?></code>
                                <?php if($order->delivery_status): ?>
                                    <span class="text-xs text-slate-500 ml-2"><?php echo e($order->delivery_status); ?></span>
                                <?php endif; ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </section>

            
            <?php if($order->items && $order->items->count()): ?>
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm overflow-hidden">
                    <header class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                        <h2 class="font-bold text-slate-900 dark:text-white">
                            <i class="bi bi-images text-indigo-500"></i> รายการ
                            <span class="text-sm font-normal text-slate-500 ml-1">(<?php echo e($order->items->count()); ?>)</span>
                        </h2>
                    </header>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="text-left px-5 py-2.5 font-semibold">#</th>
                                    <th class="text-left px-2 py-2.5 font-semibold">รายการ</th>
                                    <th class="text-right px-2 py-2.5 font-semibold">จำนวน</th>
                                    <th class="text-right px-2 py-2.5 font-semibold">ราคา</th>
                                    <th class="text-right px-5 py-2.5 font-semibold">รวม</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <?php $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/30">
                                        <td class="px-5 py-3 text-slate-400"><?php echo e($loop->iteration); ?></td>
                                        <td class="px-2 py-3 text-slate-900 dark:text-white">
                                            <?php echo e($item->description ?? ($item->photo_id ? 'รูป #' . $item->photo_id : 'รายการ #' . $item->id)); ?>

                                        </td>
                                        <td class="px-2 py-3 text-right"><?php echo e($item->quantity ?? 1); ?></td>
                                        <td class="px-2 py-3 text-right text-slate-600"><?php echo e($money($item->price ?? 0)); ?></td>
                                        <td class="px-5 py-3 text-right font-semibold text-slate-900 dark:text-white">
                                            <?php echo e($money(($item->price ?? 0) * ($item->quantity ?? 1))); ?>

                                        </td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tbody>
                            <tfoot class="bg-slate-50 dark:bg-slate-900/50 text-sm">
                                <?php if((float) ($order->subtotal ?? 0) > 0 && (float) $order->subtotal != (float) $order->total): ?>
                                    <tr>
                                        <td colspan="4" class="px-5 py-2 text-right text-slate-500">รวมก่อนส่วนลด</td>
                                        <td class="px-5 py-2 text-right text-slate-900 dark:text-white"><?php echo e($money($order->subtotal)); ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="px-5 py-2 text-right text-slate-500">ส่วนลด</td>
                                        <td class="px-5 py-2 text-right text-rose-600">−<?php echo e($money($order->discount_amount)); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <tr class="border-t border-slate-200 dark:border-slate-600">
                                    <td colspan="4" class="px-5 py-3 text-right font-bold text-slate-900 dark:text-white">ยอดสุทธิ</td>
                                    <td class="px-5 py-3 text-right font-extrabold text-indigo-600 text-lg"><?php echo e($money($order->total)); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            
            <?php if($order->slips && $order->slips->count()): ?>
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm overflow-hidden">
                    <header class="px-5 py-4 border-b border-slate-100 dark:border-slate-700">
                        <h2 class="font-bold text-slate-900 dark:text-white">
                            <i class="bi bi-receipt-cutoff text-emerald-500"></i> สลิปชำระเงิน
                            <span class="text-sm font-normal text-slate-500 ml-1">(<?php echo e($order->slips->count()); ?>)</span>
                        </h2>
                    </header>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                        <?php $__currentLoopData = $order->slips; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slip): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php
                                $slipMeta = $statusMeta[$slip->verify_status] ?? ['label' => $slip->verify_status, 'tone' => 'slate', 'icon' => 'bi-circle'];
                                $slipBadge   = $toneClasses[$slipMeta['tone']] ?? $toneClasses['slate'];
                                $slipBadgeBg = $toneBg[$slipMeta['tone']]      ?? $toneBg['slate'];
                            ?>
                            <li class="px-5 py-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="text-xs font-mono text-slate-400">#<?php echo e($slip->id); ?></span>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset <?php echo e($slipBadge); ?>"
                                                  style="background:<?php echo e($slipBadgeBg); ?>;">
                                                <?php echo e($slipMeta['label']); ?>

                                            </span>
                                            <?php if(!is_null($slip->verify_score)): ?>
                                                <span class="text-xs text-slate-500">คะแนน <?php echo e($slip->verify_score); ?>/100</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-sm text-slate-700 dark:text-slate-300">
                                            <span class="font-semibold"><?php echo e($money($slip->amount)); ?></span>
                                            <?php if($slip->transfer_date): ?>
                                                <span class="text-slate-500">โอนเมื่อ <?php echo e(\Carbon\Carbon::parse($slip->transfer_date)->format('d/m/Y H:i')); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if($slip->slipok_trans_ref): ?>
                                            <div class="text-xs text-slate-500 mt-1">
                                                SlipOK Ref: <code class="font-mono"><?php echo e($slip->slipok_trans_ref); ?></code>
                                            </div>
                                        <?php endif; ?>
                                        <?php if(!empty($slip->fraud_flags)): ?>
                                            <div class="mt-2 flex flex-wrap gap-1">
                                                <?php $__currentLoopData = (array) $slip->fraud_flags; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $flag): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <span class="px-2 py-0.5 rounded text-xs ring-1 ring-rose-300/60 text-rose-700 dark:text-rose-300"
                                                          style="background:rgba(244,63,94,0.15);"><?php echo e($flag); ?></span>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if($slip->slip_path): ?>
                                        <a href="<?php echo e(\Illuminate\Support\Facades\Storage::disk(config('media.disk', 'r2'))->url($slip->slip_path)); ?>"
                                           target="_blank"
                                           class="text-xs text-indigo-600 hover:text-indigo-700 font-semibold inline-flex items-center gap-1">
                                            <i class="bi bi-image"></i> ดูสลิป
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                </section>
            <?php endif; ?>

            
            <?php if($order->transactions && $order->transactions->count()): ?>
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm overflow-hidden">
                    <header class="px-5 py-4 border-b border-slate-100 dark:border-slate-700">
                        <h2 class="font-bold text-slate-900 dark:text-white">
                            <i class="bi bi-credit-card text-indigo-500"></i> ธุรกรรม
                            <span class="text-sm font-normal text-slate-500 ml-1">(<?php echo e($order->transactions->count()); ?>)</span>
                        </h2>
                    </header>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="text-left px-5 py-2.5 font-semibold">Txn ID</th>
                                    <th class="text-left px-2 py-2.5 font-semibold">Gateway</th>
                                    <th class="text-right px-2 py-2.5 font-semibold">ยอด</th>
                                    <th class="text-left px-2 py-2.5 font-semibold">สถานะ</th>
                                    <th class="text-left px-5 py-2.5 font-semibold">เวลา</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <?php $__currentLoopData = $order->transactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $txn): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php
                                        $txnMeta = $statusMeta[$txn->status] ?? ['label' => $txn->status, 'tone' => 'slate', 'icon' => 'bi-circle'];
                                        $txnBadge   = $toneClasses[$txnMeta['tone']] ?? $toneClasses['slate'];
                                        $txnBadgeBg = $toneBg[$txnMeta['tone']]      ?? $toneBg['slate'];
                                    ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/30">
                                        <td class="px-5 py-3 font-mono text-xs"><?php echo e($txn->transaction_id); ?></td>
                                        <td class="px-2 py-3"><?php echo e($txn->payment_gateway ?? '-'); ?></td>
                                        <td class="px-2 py-3 text-right font-medium"><?php echo e($money($txn->amount)); ?></td>
                                        <td class="px-2 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 ring-inset <?php echo e($txnBadge); ?>"
                                                  style="background:<?php echo e($txnBadgeBg); ?>;">
                                                <?php echo e($txnMeta['label']); ?>

                                            </span>
                                        </td>
                                        <td class="px-5 py-3 text-xs text-slate-500"><?php echo e($txn->created_at?->format('d/m/Y H:i') ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            
            <?php if(($timeline ?? collect())->count() > 0 || ($activity ?? collect())->count() > 0): ?>
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm overflow-hidden">
                    <header class="px-5 py-4 border-b border-slate-100 dark:border-slate-700">
                        <h2 class="font-bold text-slate-900 dark:text-white">
                            <i class="bi bi-clock-history text-indigo-500"></i> Timeline
                        </h2>
                    </header>
                    <ol class="px-5 py-4 space-y-3 text-sm">
                        <?php
                            // Merge audit + activity logs into one chronological feed.
                            $events = collect();
                            foreach ($timeline ?? [] as $row) {
                                $events->push((object) [
                                    'when'   => $row->created_at,
                                    'kind'   => 'audit',
                                    'action' => $row->action,
                                    'actor'  => $row->actor_type . ($row->actor_id ? "#{$row->actor_id}" : ''),
                                    'detail' => $row->new_values,
                                ]);
                            }
                            foreach ($activity ?? [] as $row) {
                                $events->push((object) [
                                    'when'   => $row->created_at,
                                    'kind'   => 'activity',
                                    'action' => $row->action,
                                    'actor'  => 'admin',
                                    'detail' => $row->description ?? null,
                                ]);
                            }
                            $events = $events->sortByDesc('when')->values();
                        ?>
                        <?php $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ev): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li class="flex gap-3">
                                <div class="flex flex-col items-center pt-0.5">
                                    <div class="w-2 h-2 rounded-full <?php echo e($ev->kind === 'activity' ? 'bg-indigo-500' : 'bg-slate-400'); ?>"></div>
                                    <?php if(!$loop->last): ?>
                                        <div class="w-px flex-1 bg-slate-200 dark:bg-slate-600 mt-1"></div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1 pb-3">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <code class="text-xs font-mono px-1.5 py-0.5 rounded
                                            <?php echo e($ev->kind === 'activity' ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-600'); ?>">
                                            <?php echo e($ev->action); ?>

                                        </code>
                                        <span class="text-xs text-slate-500"><?php echo e($ev->actor); ?></span>
                                        <span class="text-xs text-slate-400 ml-auto">
                                            <?php echo e(\Carbon\Carbon::parse($ev->when)->format('d/m/Y H:i')); ?>

                                        </span>
                                    </div>
                                    <?php if(!empty($ev->detail)): ?>
                                        <div class="text-xs text-slate-600 dark:text-slate-400 mt-1 truncate" title="<?php echo e($ev->detail); ?>">
                                            <?php echo e(\Illuminate\Support\Str::limit($ev->detail, 200)); ?>

                                        </div>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ol>
                </section>
            <?php endif; ?>

        </div>

        
        <aside class="space-y-6">

            
            <?php if($order->user): ?>
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-5">
                    <h2 class="font-bold text-slate-900 dark:text-white mb-3">
                        <i class="bi bi-person-circle text-indigo-500"></i> ลูกค้า
                    </h2>
                    <div class="flex items-start gap-3 mb-3">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white font-bold flex items-center justify-center text-lg">
                            <?php echo e(mb_strtoupper(mb_substr($order->user->first_name ?? 'U', 0, 1, 'UTF-8'), 'UTF-8')); ?>

                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-slate-900 dark:text-white truncate">
                                <?php echo e(trim(($order->user->first_name ?? '') . ' ' . ($order->user->last_name ?? '')) ?: 'ไม่ระบุชื่อ'); ?>

                            </div>
                            <div class="text-xs text-slate-500 truncate"><?php echo e($order->user->email); ?></div>
                            <?php if($order->user->phone ?? null): ?>
                                <div class="text-xs text-slate-500 truncate"><?php echo e($order->user->phone); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-lg bg-slate-50 dark:bg-slate-900/40 px-3 py-2">
                            <div class="text-slate-500">User ID</div>
                            <div class="font-mono font-semibold">#<?php echo e($order->user->id); ?></div>
                        </div>
                        <?php if($order->user->created_at): ?>
                            <div class="rounded-lg bg-slate-50 dark:bg-slate-900/40 px-3 py-2">
                                <div class="text-slate-500">สมัครเมื่อ</div>
                                <div class="font-semibold"><?php echo e($order->user->created_at->format('d/m/y')); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php elseif($order->guest_email): ?>
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-5">
                    <h2 class="font-bold text-slate-900 dark:text-white mb-3">
                        <i class="bi bi-person-circle text-slate-500"></i> ลูกค้า (Guest)
                    </h2>
                    <div class="text-sm text-slate-700 dark:text-slate-300"><?php echo e($order->guest_email); ?></div>
                </section>
            <?php endif; ?>

            
            <?php if($order->event): ?>
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-5">
                    <h2 class="font-bold text-slate-900 dark:text-white mb-3">
                        <i class="bi bi-calendar-event text-emerald-500"></i> อีเวนต์
                    </h2>
                    <div class="font-semibold text-slate-900 dark:text-white mb-1"><?php echo e($order->event->name); ?></div>
                    <?php if($order->event->shoot_date): ?>
                        <div class="text-sm text-slate-500">
                            <i class="bi bi-camera"></i> ถ่ายเมื่อ <?php echo e(\Carbon\Carbon::parse($order->event->shoot_date)->format('d/m/Y')); ?>

                        </div>
                    <?php endif; ?>
                    <?php if($order->event->slug ?? null): ?>
                        <a href="<?php echo e(url('/events/' . $order->event->slug)); ?>" target="_blank"
                           class="text-xs text-indigo-600 hover:text-indigo-700 inline-flex items-center gap-1 mt-2">
                            <i class="bi bi-box-arrow-up-right"></i> ดูหน้าอีเวนต์
                        </a>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            
            <?php if($addonPurchase ?? null): ?>
                <?php
                    $snap = json_decode((string) $addonPurchase->snapshot, true) ?: [];
                    $addonStatusMeta = $statusMeta[$addonPurchase->status] ?? [
                        'label' => $addonPurchase->status, 'tone' => 'slate', 'icon' => 'bi-circle',
                    ];
                    $addonBadge   = $toneClasses[$addonStatusMeta['tone']] ?? $toneClasses['slate'];
                    $addonBadgeBg = $toneBg[$addonStatusMeta['tone']]      ?? $toneBg['slate'];
                ?>
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-5">
                    <h2 class="font-bold text-slate-900 dark:text-white mb-3">
                        <i class="bi bi-stars text-amber-500"></i> บริการเสริม
                    </h2>
                    <div class="font-semibold text-slate-900 dark:text-white"><?php echo e($snap['label'] ?? $addonPurchase->sku); ?></div>
                    <div class="text-xs text-slate-500 mt-0.5">SKU: <code><?php echo e($addonPurchase->sku); ?></code></div>
                    <div class="mt-3 flex items-center gap-2 text-xs">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full font-semibold ring-1 ring-inset <?php echo e($addonBadge); ?>"
                              style="background:<?php echo e($addonBadgeBg); ?>;">
                            <?php echo e($addonStatusMeta['label']); ?>

                        </span>
                        <?php if($addonPurchase->expires_at): ?>
                            <span class="text-slate-500">หมดอายุ <?php echo e(\Carbon\Carbon::parse($addonPurchase->expires_at)->format('d/m/Y')); ?></span>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            
            <?php if($order->subscriptionInvoice): ?>
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-5">
                    <h2 class="font-bold text-slate-900 dark:text-white mb-3">
                        <i class="bi bi-stack text-indigo-500"></i> ใบแจ้งหนี้แผน
                    </h2>
                    <div class="text-sm text-slate-700 dark:text-slate-300">
                        Invoice #<?php echo e($order->subscriptionInvoice->id); ?>

                    </div>
                    <?php if($order->subscriptionInvoice->period_start): ?>
                        <div class="text-xs text-slate-500 mt-1">
                            รอบ
                            <?php echo e(\Carbon\Carbon::parse($order->subscriptionInvoice->period_start)->format('d/m/y')); ?>

                            —
                            <?php echo e(\Carbon\Carbon::parse($order->subscriptionInvoice->period_end)->format('d/m/y')); ?>

                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            
            <?php if($order->payout): ?>
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-5">
                    <h2 class="font-bold text-slate-900 dark:text-white mb-3">
                        <i class="bi bi-cash-stack text-emerald-500"></i> ค่าคอมช่างภาพ
                    </h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-500">ยอดรวม</dt>
                            <dd class="font-medium"><?php echo e($money($order->payout->gross_amount)); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">ค่าคอม (<?php echo e(number_format(100 - (float) $order->payout->commission_rate, 0)); ?>%)</dt>
                            <dd class="text-rose-600">−<?php echo e($money($order->payout->platform_fee)); ?></dd>
                        </div>
                        <div class="flex justify-between border-t border-slate-100 dark:border-slate-700 pt-2 font-bold">
                            <dt class="text-slate-900 dark:text-white">ยอดสุทธิช่างภาพ</dt>
                            <dd class="text-emerald-600 text-lg"><?php echo e($money($order->payout->payout_amount)); ?></dd>
                        </div>
                        <div class="flex justify-between text-xs">
                            <dt class="text-slate-500">สถานะ</dt>
                            <dd>
                                <?php
                                    $poStatus = $order->payout->status;
                                    $poTone = match ($poStatus) {
                                        'paid'      => 'emerald',
                                        'pending'   => 'amber',
                                        'reversed'  => 'rose',
                                        default     => 'slate',
                                    };
                                ?>
                                <span class="inline-flex px-2 py-0.5 rounded-full font-semibold ring-1 ring-inset <?php echo e($toneClasses[$poTone]); ?>"
                                      style="background:<?php echo e($toneBg[$poTone]); ?>;">
                                    <?php echo e($poStatus); ?>

                                </span>
                            </dd>
                        </div>
                    </dl>
                </section>
            <?php endif; ?>

            
            <?php if($order->refund): ?>
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-5 ring-1 ring-rose-100">
                    <h2 class="font-bold text-rose-700 mb-3">
                        <i class="bi bi-arrow-counterclockwise"></i> คืนเงิน
                    </h2>
                    <div class="text-sm">
                        <div class="font-semibold"><?php echo e($money($order->refund->amount)); ?></div>
                        <div class="text-xs text-slate-500 mt-1">
                            สถานะ: <?php echo e($order->refund->status); ?>

                            · <?php echo e($order->refund->created_at?->diffForHumans()); ?>

                        </div>
                        <?php if($order->refund->reason ?? null): ?>
                            <div class="text-xs text-slate-600 mt-1"><?php echo e($order->refund->reason); ?></div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            
            <?php if($order->downloadTokens && $order->downloadTokens->count()): ?>
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-5">
                    <h2 class="font-bold text-slate-900 dark:text-white mb-3">
                        <i class="bi bi-cloud-download text-sky-500"></i> ลิงก์ดาวน์โหลด
                        <span class="text-sm font-normal text-slate-500 ml-1">(<?php echo e($order->downloadTokens->count()); ?>)</span>
                    </h2>
                    <ul class="space-y-1 text-xs">
                        <?php $__currentLoopData = $order->downloadTokens->take(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tok): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li class="flex justify-between gap-2">
                                <code class="font-mono text-slate-500 truncate"><?php echo e(\Illuminate\Support\Str::limit($tok->token, 16)); ?></code>
                                <span class="text-slate-400 shrink-0">
                                    <?php if($tok->expires_at): ?> หมด <?php echo e(\Carbon\Carbon::parse($tok->expires_at)->format('d/m/y')); ?> <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                </section>
            <?php endif; ?>

        </aside>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/admin/orders/show.blade.php ENDPATH**/ ?>