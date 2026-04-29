<?php $__env->startSection('title', 'Dashboard'); ?>



<?php $__env->startSection('content'); ?>
<?php
  use Carbon\Carbon;

  $combinedTodayOrders = ($stats['today_orders'] ?? 0) + (int)($digitalStats['today_orders'] ?? 0);
  $totalCombinedRevenue = $combinedTotalRevenue ?? 0;
  $photoPct   = $totalCombinedRevenue > 0 ? round(($stats['total_revenue'] ?? 0) / $totalCombinedRevenue * 100) : 0;
  $digitalPct = 100 - $photoPct;

  // --- Build the smooth area chart (14-day revenue) --------------------
  $chartW = 720;
  $chartH = 220;
  $padX   = 18;
  $padTop = 18;
  $padBot = 36;

  $points = $chartData->map(fn($d) => [
      'date'    => $d->d,
      'revenue' => (float) $d->revenue,
      'orders'  => (int)   $d->orders,
  ])->values()->all();

  // Ensure 14 days (pad with zeros if missing) so the chart baseline stays stable
  $filledPoints = [];
  for ($i = 13; $i >= 0; $i--) {
      $ds = Carbon::now()->subDays($i)->toDateString();
      $match = collect($points)->firstWhere('date', $ds);
      $filledPoints[] = [
          'date'    => $ds,
          'revenue' => $match['revenue'] ?? 0,
          'orders'  => $match['orders']  ?? 0,
      ];
  }

  $maxRev = max(1, collect($filledPoints)->max('revenue') ?: 1);
  $nPts   = count($filledPoints);

  $coords = [];
  foreach ($filledPoints as $i => $p) {
      $x = $padX + ($i * (($chartW - 2 * $padX) / max(1, $nPts - 1)));
      $y = $padTop + ($chartH - $padTop - $padBot) * (1 - ($p['revenue'] / $maxRev));
      $coords[] = ['x' => round($x, 2), 'y' => round($y, 2), 'p' => $p];
  }

  // Catmull-Rom → Bezier curve smoothing
  $pathLine = '';
  $pathArea = '';
  if (count($coords) > 0) {
      $pathLine = 'M ' . $coords[0]['x'] . ' ' . $coords[0]['y'];
      for ($i = 0; $i < count($coords) - 1; $i++) {
          $p0 = $coords[max(0, $i - 1)];
          $p1 = $coords[$i];
          $p2 = $coords[$i + 1];
          $p3 = $coords[min(count($coords) - 1, $i + 2)];
          $cp1x = $p1['x'] + ($p2['x'] - $p0['x']) / 6;
          $cp1y = $p1['y'] + ($p2['y'] - $p0['y']) / 6;
          $cp2x = $p2['x'] - ($p3['x'] - $p1['x']) / 6;
          $cp2y = $p2['y'] - ($p3['y'] - $p1['y']) / 6;
          $pathLine .= ' C ' . round($cp1x, 2) . ' ' . round($cp1y, 2) . ', '
                             . round($cp2x, 2) . ' ' . round($cp2y, 2) . ', '
                             . $p2['x'] . ' ' . $p2['y'];
      }
      $baseY    = $chartH - $padBot;
      $pathArea = $pathLine
                . ' L ' . $coords[count($coords) - 1]['x'] . ' ' . $baseY
                . ' L ' . $coords[0]['x'] . ' ' . $baseY . ' Z';
  }

  $totalAlerts = (int)($stats['pending_slips'] ?? 0)
               + (int)($pendingRefunds ?? 0)
               + (int)($stats['pending_photographers'] ?? 0)
               + (int)($digitalStats['pending_review'] ?? 0);
?>

<style>
  /* Admin dashboard — Bento studio palette -------------------------- */
  .adm-canvas { position:relative; isolation:isolate; }
  .adm-canvas::before {
    content:''; position:absolute; inset:-40px -40px 60% -40px; z-index:-1;
    background:
      radial-gradient(60% 70% at 10% 0%,  rgba(99,102,241,.14), transparent 60%),
      radial-gradient(55% 70% at 90% 10%, rgba(236,72,153,.10), transparent 65%),
      radial-gradient(50% 65% at 50% 100%, rgba(16,185,129,.08), transparent 70%);
    filter: blur(28px); pointer-events:none;
  }
  .dark .adm-canvas::before {
    background:
      radial-gradient(60% 70% at 10% 0%,  rgba(79,70,229,.28),  transparent 60%),
      radial-gradient(55% 70% at 90% 10%, rgba(219,39,119,.18), transparent 65%),
      radial-gradient(50% 65% at 50% 100%, rgba(5,150,105,.12), transparent 70%);
  }

  /* Hero card ------------------------------------------------------- */
  .adm-hero {
    position:relative; overflow:hidden;
    border-radius: 28px;
    background:
      linear-gradient(135deg, #4f46e5 0%, #7c3aed 45%, #db2777 100%);
    color:#fff;
    box-shadow: 0 30px 70px -30px rgba(79,70,229,.6);
  }
  .adm-hero::before {
    content:''; position:absolute; inset:0;
    background:
      radial-gradient(60% 90% at 100% 0%, rgba(255,255,255,.18), transparent 55%),
      radial-gradient(55% 80% at 0% 100%, rgba(16,185,129,.22), transparent 55%);
    mix-blend-mode: screen; pointer-events:none;
  }
  .adm-hero::after {
    content:''; position:absolute; inset:0;
    background-image:
      linear-gradient(rgba(255,255,255,.06) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,.06) 1px, transparent 1px);
    background-size: 42px 42px;
    mask-image: radial-gradient(75% 100% at 50% 0%, black, transparent 80%);
    pointer-events:none;
  }
  .adm-hero-badge {
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    background: rgba(255,255,255,.14);
    border: 1px solid rgba(255,255,255,.25);
  }

  /* KPI tiles ------------------------------------------------------- */
  .adm-kpi {
    position:relative; overflow:hidden; border-radius:20px;
    background: rgba(255,255,255,.98);
    border: 1px solid rgba(15,23,42,.06);
    box-shadow: 0 1px 2px rgba(15,23,42,.04);
    transition: transform .25s ease, box-shadow .25s ease;
  }
  .adm-kpi:hover { transform: translateY(-2px); box-shadow: 0 14px 32px -14px rgba(15,23,42,.22); }
  .dark .adm-kpi {
    background: rgba(15,23,42,.72);
    border-color: rgba(255,255,255,.06);
    box-shadow: 0 1px 2px rgba(0,0,0,.3);
  }
  .dark .adm-kpi:hover { box-shadow: 0 16px 40px -12px rgba(0,0,0,.5); }
  .adm-kpi::before {
    content:''; position:absolute; inset:0 auto 0 0; width:4px;
    background: var(--kpi-accent, #6366f1);
    border-radius: 4px 0 0 4px;
  }
  .adm-kpi::after {
    content:''; position:absolute; width:140px; height:140px; right:-40px; top:-40px;
    border-radius:50%;
    background: var(--kpi-accent, #6366f1); opacity:.08;
    filter: blur(10px); pointer-events:none;
  }
  .dark .adm-kpi::after { opacity:.18; }

  /* Surface card ---------------------------------------------------- */
  .adm-card {
    position:relative; overflow:hidden; border-radius:22px;
    background: rgba(255,255,255,.98);
    border: 1px solid rgba(15,23,42,.06);
    box-shadow: 0 1px 2px rgba(15,23,42,.04);
  }
  .dark .adm-card {
    background: rgba(15,23,42,.72);
    border-color: rgba(255,255,255,.06);
    box-shadow: 0 1px 2px rgba(0,0,0,.3);
  }

  /* Quick action dock ---------------------------------------------- */
  .adm-qa {
    position:relative; overflow:hidden; border-radius: 18px;
    background: rgba(255,255,255,.9);
    border: 1px solid rgba(15,23,42,.06);
    transition: transform .2s ease, border-color .2s ease;
  }
  .dark .adm-qa {
    background: rgba(15,23,42,.6);
    border-color: rgba(255,255,255,.06);
  }
  .adm-qa:hover { transform: translateY(-2px); border-color: rgba(99,102,241,.5); }
  .adm-qa::before {
    content:''; position:absolute; inset:auto 0 0 0; height:3px;
    background: var(--qa-accent, #6366f1);
    transform: scaleX(0); transform-origin: left center;
    transition: transform .3s ease;
  }
  .adm-qa:hover::before { transform: scaleX(1); }

  /* Alert chips ----------------------------------------------------- */
  .adm-alert {
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    transition: transform .2s ease, box-shadow .2s ease;
  }
  .adm-alert:hover { transform: translateY(-1px); }

  /* Tier stepper shimmer ------------------------------------------- */
  @keyframes adm-shimmer { 0% { background-position: -200% 0; } 100% { background-position: 200% 0; } }

  /* Chart grid ticks ----------------------------------------------- */
  .adm-grid-line { stroke: rgba(100,116,139,.12); stroke-dasharray: 4 4; }
  .dark .adm-grid-line { stroke: rgba(255,255,255,.06); }

  .adm-axis { fill: #64748b; font-size: 10px; }
  .dark .adm-axis { fill: #94a3b8; }

  .adm-hotspot:hover ~ .adm-tooltip { opacity:1; transform: translateY(0); }
</style>

<div class="adm-canvas max-w-[1440px] mx-auto pb-16">

  
  <div class="adm-hero p-6 md:p-8 mb-6">
    <div class="relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
      <div class="lg:col-span-7">
        <div class="flex items-center gap-2 mb-3 flex-wrap">
          <span class="adm-hero-badge inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-1 rounded-full text-white">
            <i class="bi bi-shield-check"></i> Admin Control Center
          </span>
          <span class="adm-hero-badge inline-flex items-center gap-1.5 text-[11px] font-medium px-2.5 py-1 rounded-full text-white/95">
            <i class="bi bi-calendar-week"></i> <?php echo e(now()->translatedFormat('l, j F Y')); ?>

          </span>
          <?php if($onlineCount > 0): ?>
            <span class="adm-hero-badge inline-flex items-center gap-1.5 text-[11px] font-medium px-2.5 py-1 rounded-full text-white/95">
              <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-300 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-400"></span>
              </span>
              <?php echo e($onlineCount); ?> ออนไลน์
            </span>
          <?php endif; ?>
        </div>
        <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight leading-tight mb-1">
          สวัสดี, <?php echo e(session('admin_name', 'Admin')); ?>

        </h1>
        <p class="text-white/80 text-sm md:text-[15px] max-w-xl">
          ภาพรวมระบบแบบเรียลไทม์ — รายได้, ออเดอร์, และงานที่รอดำเนินการของคุณอยู่ที่นี่ทั้งหมด
        </p>

        <div class="mt-5 flex flex-wrap gap-2">
          <a href="<?php echo e(route('admin.orders.index')); ?>"
             class="adm-hero-badge inline-flex items-center gap-1.5 text-[13px] font-semibold px-4 py-2 rounded-full text-white hover:bg-white/25 transition">
            <i class="bi bi-bag"></i> ออเดอร์
          </a>
          <a href="<?php echo e(route('admin.finance.index')); ?>"
             class="adm-hero-badge inline-flex items-center gap-1.5 text-[13px] font-semibold px-4 py-2 rounded-full text-white hover:bg-white/25 transition">
            <i class="bi bi-graph-up-arrow"></i> การเงิน
          </a>
          <a href="<?php echo e(route('admin.photographers.index')); ?>"
             class="adm-hero-badge inline-flex items-center gap-1.5 text-[13px] font-semibold px-4 py-2 rounded-full text-white hover:bg-white/25 transition">
            <i class="bi bi-camera"></i> ช่างภาพ
          </a>
          <a href="<?php echo e(route('admin.settings.general')); ?>"
             class="adm-hero-badge inline-flex items-center gap-1.5 text-[13px] font-semibold px-4 py-2 rounded-full text-white hover:bg-white/25 transition">
            <i class="bi bi-sliders2"></i> ตั้งค่า
          </a>
        </div>
      </div>

      
      <div class="lg:col-span-5">
        <div class="adm-hero-badge rounded-2xl p-5 text-white">
          <div class="flex items-center justify-between mb-3">
            <span class="text-[11px] font-bold uppercase tracking-[0.16em] text-white/80">
              <i class="bi bi-activity mr-1"></i>Live Snapshot
            </span>
            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-white/15">
              วันนี้
            </span>
          </div>
          <div class="text-4xl font-extrabold tracking-tight leading-none">
            ฿<?php echo e(number_format($combinedTodayRevenue, 0)); ?>

          </div>
          <div class="text-[12px] text-white/75 mt-1">
            รายได้รวม · <?php echo e($combinedTodayOrders); ?> ออเดอร์
          </div>

          <div class="grid grid-cols-3 gap-2 mt-4">
            <div class="rounded-xl p-2.5 bg-white/10 border border-white/15">
              <div class="text-[10px] text-white/70 uppercase tracking-wider">เดือนนี้</div>
              <div class="text-[15px] font-bold mt-0.5">฿<?php echo e(number_format($combinedMonthRevenue, 0)); ?></div>
            </div>
            <div class="rounded-xl p-2.5 bg-white/10 border border-white/15">
              <div class="text-[10px] text-white/70 uppercase tracking-wider">ทั้งหมด</div>
              <div class="text-[15px] font-bold mt-0.5">฿<?php echo e(number_format($totalCombinedRevenue, 0)); ?></div>
            </div>
            <div class="rounded-xl p-2.5 bg-white/10 border border-white/15">
              <div class="text-[10px] text-white/70 uppercase tracking-wider">สมาชิก</div>
              <div class="text-[15px] font-bold mt-0.5"><?php echo e(number_format($stats['total_users'] ?? 0)); ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  
  <?php if($totalAlerts > 0): ?>
    <div class="flex flex-wrap items-center gap-2.5 mb-6">
      <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mr-1">
        <i class="bi bi-bell-fill text-amber-500"></i> รอการดำเนินการ
      </span>

      <?php if(($stats['pending_slips'] ?? 0) > 0): ?>
        <a href="<?php echo e(route('admin.payments.slips')); ?>"
           class="adm-alert inline-flex items-center gap-1.5 text-[13px] font-semibold px-3.5 py-1.5 rounded-full
                  bg-amber-500/10 text-amber-700 dark:text-amber-300
                  border border-amber-500/30 hover:bg-amber-500/20">
          <i class="bi bi-receipt"></i> สลิป
          <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-amber-500 text-white"><?php echo e($stats['pending_slips']); ?></span>
        </a>
      <?php endif; ?>
      <?php if(($pendingRefunds ?? 0) > 0): ?>
        <a href="<?php echo e(route('admin.finance.refunds')); ?>"
           class="adm-alert inline-flex items-center gap-1.5 text-[13px] font-semibold px-3.5 py-1.5 rounded-full
                  bg-rose-500/10 text-rose-700 dark:text-rose-300
                  border border-rose-500/30 hover:bg-rose-500/20">
          <i class="bi bi-arrow-return-left"></i> คืนเงิน
          <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-rose-500 text-white"><?php echo e($pendingRefunds); ?></span>
        </a>
      <?php endif; ?>
      <?php if(($stats['pending_photographers'] ?? 0) > 0): ?>
        <a href="<?php echo e(route('admin.photographers.index')); ?>"
           class="adm-alert inline-flex items-center gap-1.5 text-[13px] font-semibold px-3.5 py-1.5 rounded-full
                  bg-blue-500/10 text-blue-700 dark:text-blue-300
                  border border-blue-500/30 hover:bg-blue-500/20">
          <i class="bi bi-camera"></i> ช่างภาพ
          <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-blue-500 text-white"><?php echo e($stats['pending_photographers']); ?></span>
        </a>
      <?php endif; ?>
      <?php if((int)($digitalStats['pending_review'] ?? 0) > 0): ?>
        <a href="<?php echo e(route('admin.digital-orders.index')); ?>?status=pending_review"
           class="adm-alert inline-flex items-center gap-1.5 text-[13px] font-semibold px-3.5 py-1.5 rounded-full
                  bg-violet-500/10 text-violet-700 dark:text-violet-300
                  border border-violet-500/30 hover:bg-violet-500/20">
          <i class="bi bi-box-seam"></i> ดิจิทัล
          <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-violet-500 text-white"><?php echo e($digitalStats['pending_review']); ?></span>
        </a>
      <?php endif; ?>

      <span class="ml-auto text-[11px] text-slate-500 dark:text-slate-400 font-mono">
        รวม <?php echo e($totalAlerts); ?> รายการ
      </span>
    </div>
  <?php endif; ?>

  
  <?php
    $kpiTiles = [
      [
        'label'   => 'รายได้วันนี้',
        'value'   => '฿' . number_format($combinedTodayRevenue, 0),
        'sub'     => $combinedTodayOrders . ' ออเดอร์' . ((float)($digitalStats['today_revenue'] ?? 0) > 0 ? ' · ดิจิทัล ฿' . number_format((float)$digitalStats['today_revenue'], 0) : ''),
        'icon'    => 'bi-cash-stack',
        'accent'  => '#10b981',
        'iconBg'  => 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300',
      ],
      [
        'label'   => 'รายได้เดือนนี้',
        'value'   => '฿' . number_format($combinedMonthRevenue, 0),
        'sub'     => 'ภาพ ฿' . number_format($stats['month_revenue'] ?? 0, 0) . ' · ดิจิทัล ฿' . number_format((float)($digitalStats['month_revenue'] ?? 0), 0),
        'icon'    => 'bi-graph-up-arrow',
        'accent'  => '#6366f1',
        'iconBg'  => 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-300',
      ],
      [
        'label'   => 'สมาชิก / ช่างภาพ',
        'value'   => number_format($stats['total_users'] ?? 0) . ' / ' . number_format($stats['total_photographers'] ?? 0),
        'sub'     => ($stats['new_users_today'] ?? 0) > 0 ? '+' . $stats['new_users_today'] . ' สมัครวันนี้' : 'สมาชิก / ช่างภาพ',
        'icon'    => 'bi-people-fill',
        'accent'  => '#0ea5e9',
        'iconBg'  => 'bg-sky-500/15 text-sky-600 dark:text-sky-300',
      ],
      [
        'label'   => 'อีเวนต์ทั้งหมด',
        'value'   => number_format($stats['total_events'] ?? 0),
        'sub'     => 'เปิดใช้งาน ' . number_format($stats['active_events'] ?? 0) . ' อีเวนต์',
        'icon'    => 'bi-calendar-event-fill',
        'accent'  => '#f59e0b',
        'iconBg'  => 'bg-amber-500/15 text-amber-600 dark:text-amber-300',
      ],
    ];
  ?>
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php $__currentLoopData = $kpiTiles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="adm-kpi p-5" style="--kpi-accent:<?php echo e($t['accent']); ?>;">
        <div class="flex items-start justify-between gap-2 mb-3">
          <div class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-[0.14em] pl-1.5">
            <?php echo e($t['label']); ?>

          </div>
          <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl <?php echo e($t['iconBg']); ?> shrink-0">
            <i class="bi <?php echo e($t['icon']); ?> text-base"></i>
          </span>
        </div>
        <div class="text-[22px] font-extrabold text-slate-900 dark:text-white tracking-tight leading-tight pl-1.5">
          <?php echo e($t['value']); ?>

        </div>
        <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1.5 pl-1.5 leading-snug">
          <?php echo e($t['sub']); ?>

        </div>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>

  
  <div class="adm-card p-5 mb-6">
    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
      <div class="flex items-center gap-3">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl
                     bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white shadow-md shadow-violet-500/30">
          <i class="bi bi-percent text-lg"></i>
        </span>
        <div>
          <h6 class="font-bold text-slate-900 dark:text-white text-[15px] leading-tight">Platform Commission</h6>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-2 mt-0.5">
            <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded-full
                         bg-violet-500/15 text-violet-700 dark:text-violet-300">
              อัตรา <?php echo e($platformCommission ?? 20); ?>%
            </span>
            ค่าธรรมเนียม + ยอด Payout รายเดือน
          </div>
        </div>
      </div>
      <a href="<?php echo e(route('admin.payments.payouts')); ?>"
         class="inline-flex items-center gap-1 text-[12px] font-medium px-3 py-1.5 rounded-full transition
                bg-slate-100 dark:bg-slate-800
                text-slate-600 dark:text-slate-300
                border border-slate-200 dark:border-white/10
                hover:bg-slate-200 dark:hover:bg-slate-700">
        Payout ทั้งหมด <i class="bi bi-arrow-right"></i>
      </a>
    </div>

    <?php
      $cCards = [
        ['lbl' => 'ค่าคอมวันนี้', 'val' => (float)($commissionStats['today_platform_fee'] ?? 0), 'sub' => 'จากรายได้ ฿' . number_format($combinedTodayRevenue, 0), 'icon' => 'bi-sun', 'c' => '#8b5cf6'],
        ['lbl' => 'ค่าคอมเดือนนี้', 'val' => (float)($commissionStats['month_platform_fee'] ?? 0), 'sub' => 'Payout ฿' . number_format((float)($commissionStats['month_payout'] ?? 0), 0), 'icon' => 'bi-calendar-range', 'c' => '#6366f1'],
        ['lbl' => 'ค่าคอมรวมทั้งหมด', 'val' => (float)($commissionStats['total_platform_fee'] ?? 0), 'sub' => 'Payout รวม ฿' . number_format((float)($commissionStats['total_payout'] ?? 0), 0), 'icon' => 'bi-wallet2', 'c' => '#10b981'],
        ['lbl' => 'รอจ่ายช่างภาพ', 'val' => (float)($commissionStats['pending_payout_amount'] ?? 0), 'sub' => number_format((int)($commissionStats['pending_payout'] ?? 0)) . ' รายการ', 'icon' => 'bi-hourglass-split', 'c' => '#f59e0b'],
      ];
    ?>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <?php $__currentLoopData = $cCards; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="relative rounded-xl p-4 overflow-hidden
                    bg-slate-50 dark:bg-slate-800/50
                    border border-slate-200 dark:border-white/5">
          <div class="absolute top-0 right-0 w-20 h-20 rounded-full opacity-10 blur-xl"
               style="background: <?php echo e($cc['c']); ?>; transform: translate(20%, -20%);"></div>
          <div class="relative">
            <div class="flex items-center justify-between mb-2">
              <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-[0.12em]"><?php echo e($cc['lbl']); ?></span>
              <i class="bi <?php echo e($cc['icon']); ?> text-[13px]" style="color:<?php echo e($cc['c']); ?>;"></i>
            </div>
            <div class="text-lg font-extrabold tracking-tight" style="color:<?php echo e($cc['c']); ?>;">
              ฿<?php echo e(number_format($cc['val'], 0)); ?>

            </div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate"><?php echo e($cc['sub']); ?></div>
          </div>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  </div>

  
  <div class="mb-6">
    <?php echo $__env->make('admin.partials.online-users', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  </div>

  
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">

    
    <div class="lg:col-span-2">
      <div class="adm-card p-5 h-full">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
          <div class="flex items-center gap-3">
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl
                         bg-gradient-to-br from-indigo-500 to-violet-500 text-white shadow shadow-indigo-500/30">
              <i class="bi bi-graph-up text-base"></i>
            </span>
            <div>
              <h6 class="font-bold text-slate-900 dark:text-white text-[15px] leading-tight">รายได้ 14 วันล่าสุด</h6>
              <div class="text-[11px] text-slate-500 dark:text-slate-400">ภาพรวมยอดขายรูปภาพรายวัน</div>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-1 rounded-full
                         bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">
              เดือนนี้ ฿<?php echo e(number_format($stats['month_revenue'] ?? 0, 0)); ?>

            </span>
            <a href="<?php echo e(route('admin.finance.index')); ?>"
               class="text-[12px] font-medium px-3 py-1.5 rounded-full transition
                      bg-indigo-50 dark:bg-indigo-500/15
                      text-indigo-600 dark:text-indigo-300
                      hover:bg-indigo-100 dark:hover:bg-indigo-500/25">
              รายละเอียด <i class="bi bi-arrow-right"></i>
            </a>
          </div>
        </div>

        <?php if($chartData->isEmpty()): ?>
          <div class="h-[220px] flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
            <i class="bi bi-bar-chart text-5xl mb-2 opacity-50"></i>
            <span class="text-sm">ยังไม่มีข้อมูลรายได้</span>
          </div>
        <?php else: ?>
          <div class="relative w-full">
            <svg viewBox="0 0 <?php echo e($chartW); ?> <?php echo e($chartH); ?>" class="w-full h-[220px]" preserveAspectRatio="none">
              <defs>
                <linearGradient id="adm-rev-area" x1="0" x2="0" y1="0" y2="1">
                  <stop offset="0%"   stop-color="#6366f1" stop-opacity="0.35"/>
                  <stop offset="55%"  stop-color="#8b5cf6" stop-opacity="0.18"/>
                  <stop offset="100%" stop-color="#8b5cf6" stop-opacity="0"/>
                </linearGradient>
                <linearGradient id="adm-rev-line" x1="0" x2="1" y1="0" y2="0">
                  <stop offset="0%"   stop-color="#4f46e5"/>
                  <stop offset="100%" stop-color="#a855f7"/>
                </linearGradient>
              </defs>

              
              <?php for($i = 0; $i <= 3; $i++): ?>
                <?php $gy = $padTop + (($chartH - $padTop - $padBot) / 3) * $i; ?>
                <line x1="<?php echo e($padX); ?>" x2="<?php echo e($chartW - $padX); ?>" y1="<?php echo e($gy); ?>" y2="<?php echo e($gy); ?>" class="adm-grid-line"/>
                <text x="<?php echo e($padX - 4); ?>" y="<?php echo e($gy + 3); ?>" text-anchor="end" class="adm-axis">
                  ฿<?php echo e(number_format(round($maxRev * (1 - $i / 3)))); ?>

                </text>
              <?php endfor; ?>

              
              <path d="<?php echo e($pathArea); ?>" fill="url(#adm-rev-area)"/>

              
              <path d="<?php echo e($pathLine); ?>" fill="none" stroke="url(#adm-rev-line)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>

              
              <?php $__currentLoopData = $coords; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <g>
                  <circle cx="<?php echo e($c['x']); ?>" cy="<?php echo e($c['y']); ?>" r="3.5" fill="#fff" stroke="#6366f1" stroke-width="2"/>
                  <circle cx="<?php echo e($c['x']); ?>" cy="<?php echo e($c['y']); ?>" r="14" fill="transparent" class="adm-hotspot">
                    <title><?php echo e(Carbon::parse($c['p']['date'])->translatedFormat('j M')); ?> · ฿<?php echo e(number_format($c['p']['revenue'], 0)); ?> · <?php echo e($c['p']['orders']); ?> ออเดอร์</title>
                  </circle>
                </g>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

              
              <?php $__currentLoopData = $coords; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if($i % 2 === 0 || $i === count($coords) - 1): ?>
                  <text x="<?php echo e($c['x']); ?>" y="<?php echo e($chartH - 14); ?>" text-anchor="middle" class="adm-axis">
                    <?php echo e(Carbon::parse($c['p']['date'])->translatedFormat('j M')); ?>

                  </text>
                <?php endif; ?>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </svg>
          </div>
        <?php endif; ?>
      </div>
    </div>

    
    <div class="flex flex-col gap-5">

      
      <div class="adm-card overflow-hidden">
        <div class="flex items-center justify-between px-4 pt-4 pb-3">
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg
                         bg-amber-500/15 text-amber-600 dark:text-amber-300">
              <i class="bi bi-receipt text-sm"></i>
            </span>
            <div>
              <div class="font-semibold text-sm text-slate-900 dark:text-white leading-tight">สลิปรอตรวจ</div>
              <div class="text-[10px] text-slate-500 dark:text-slate-400">
                <?php if(($stats['pending_slips'] ?? 0) > 0): ?>
                  <?php echo e($stats['pending_slips']); ?> รายการรอดำเนินการ
                <?php else: ?>
                  ไม่มีสลิปค้าง
                <?php endif; ?>
              </div>
            </div>
          </div>
          <a href="<?php echo e(route('admin.payments.slips')); ?>"
             class="text-[11px] font-medium px-2.5 py-1 rounded-full transition
                    border border-amber-400 dark:border-amber-500/50
                    text-amber-600 dark:text-amber-300
                    hover:bg-amber-500 hover:text-white hover:border-amber-500">
            ทั้งหมด
          </a>
        </div>
        <?php if($pendingSlips->isEmpty()): ?>
          <div class="text-center py-8">
            <i class="bi bi-check2-circle text-emerald-500 text-4xl block mb-1"></i>
            <span class="text-sm text-slate-500 dark:text-slate-400">ไม่มีสลิปรอตรวจ</span>
          </div>
        <?php else: ?>
          <div class="divide-y divide-slate-100 dark:divide-white/5">
            <?php $__currentLoopData = $pendingSlips; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slip): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <a href="<?php echo e(route('admin.payments.slips')); ?>?id=<?php echo e($slip->id); ?>"
                 class="block px-4 py-2.5 transition hover:bg-slate-50 dark:hover:bg-slate-800/40">
                <div class="flex items-center justify-between gap-3">
                  <div class="flex items-center gap-2.5 min-w-0">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg shrink-0
                                 bg-gradient-to-br from-amber-100 to-orange-100
                                 dark:from-amber-500/20 dark:to-orange-500/15
                                 text-amber-700 dark:text-amber-300 text-[11px] font-bold">
                      <?php echo e(strtoupper(mb_substr($slip->first_name ?? 'U', 0, 1))); ?>

                    </span>
                    <div class="min-w-0">
                      <div class="text-[13px] font-semibold text-slate-900 dark:text-white truncate">
                        <?php echo e(trim(($slip->first_name ?? '') . ' ' . ($slip->last_name ?? ''))); ?>

                      </div>
                      <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                        <?php echo e($slip->event_name ?? ''); ?>

                      </div>
                    </div>
                  </div>
                  <div class="text-right shrink-0">
                    <div class="font-bold text-emerald-600 dark:text-emerald-400 text-[13px]">
                      ฿<?php echo e(number_format((float)($slip->total ?? 0), 0)); ?>

                    </div>
                    <div class="text-[10px] text-slate-500 dark:text-slate-400">
                      <?php echo e(Carbon::parse($slip->created_at)->diffForHumans()); ?>

                    </div>
                  </div>
                </div>
              </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </div>
        <?php endif; ?>
      </div>

      
      <div class="adm-card p-4">
        <div class="flex items-center gap-2 mb-3">
          <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-indigo-500/15 text-indigo-600 dark:text-indigo-300">
            <i class="bi bi-lightning-charge-fill text-xs"></i>
          </span>
          <span class="font-semibold text-sm text-slate-900 dark:text-white">ลัดเร็ว</span>
        </div>
        <?php
          $quicklinks = [
            ['url' => route('admin.finance.index'),          'icon' => 'bi-graph-up-arrow',  'label' => 'ภาพรวมการเงิน',  'c' => '#10b981'],
            ['url' => route('admin.finance.transactions'),   'icon' => 'bi-list-ul',         'label' => 'รายการชำระเงิน',  'c' => '#3b82f6'],
            ['url' => route('admin.finance.reconciliation'), 'icon' => 'bi-clipboard-check', 'label' => 'กระทบยอด',        'c' => '#06b6d4'],
            ['url' => route('admin.settings.general'),       'icon' => 'bi-sliders2',        'label' => 'ตั้งค่าระบบ',     'c' => '#6366f1'],
          ];
        ?>
        <div class="grid grid-cols-2 gap-2">
          <?php $__currentLoopData = $quicklinks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ql): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a href="<?php echo e($ql['url']); ?>"
               class="adm-qa flex flex-col items-start gap-2 p-3" style="--qa-accent:<?php echo e($ql['c']); ?>;">
              <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg"
                    style="background: <?php echo e($ql['c']); ?>26; color: <?php echo e($ql['c']); ?>;">
                <i class="bi <?php echo e($ql['icon']); ?> text-sm"></i>
              </span>
              <span class="text-[12px] font-semibold text-slate-800 dark:text-slate-200 leading-tight">
                <?php echo e($ql['label']); ?>

              </span>
            </a>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
      </div>
    </div>
  </div>

  
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">

    
    <div class="adm-card overflow-hidden">
      <div class="px-5 py-4 flex items-center justify-between
                  bg-gradient-to-br from-blue-50 to-sky-50
                  dark:from-blue-500/10 dark:to-sky-500/5
                  border-b border-slate-100 dark:border-white/5">
        <div class="flex items-center gap-3">
          <span class="inline-flex items-center justify-center w-11 h-11 rounded-2xl
                       bg-gradient-to-br from-blue-500 to-sky-500 text-white shadow-md shadow-blue-500/30">
            <i class="bi bi-image text-xl"></i>
          </span>
          <div>
            <h6 class="font-bold text-slate-900 dark:text-white text-[15px] leading-tight">ยอดขายรูปภาพ</h6>
            <span class="text-[11px] text-slate-500 dark:text-slate-400">Photo Sales · <?php echo e($photoPct); ?>% ของรายได้รวม</span>
          </div>
        </div>
        <a href="<?php echo e(route('admin.orders.index')); ?>"
           class="inline-flex items-center justify-center w-9 h-9 rounded-xl transition
                  bg-blue-500/10 dark:bg-blue-500/20
                  text-blue-600 dark:text-blue-300
                  hover:bg-blue-500 hover:text-white">
          <i class="bi bi-arrow-right"></i>
        </a>
      </div>
      <div class="p-5">
        <div class="grid grid-cols-2 gap-3 mb-4">
          <div>
            <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">รายได้เดือนนี้</div>
            <div class="text-[22px] font-bold text-blue-600 dark:text-blue-400 tracking-tight">
              ฿<?php echo e(number_format($stats['month_revenue'] ?? 0, 0)); ?>

            </div>
          </div>
          <div class="text-right">
            <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">รายได้รวม</div>
            <div class="text-[22px] font-bold text-slate-900 dark:text-white tracking-tight">
              ฿<?php echo e(number_format($stats['total_revenue'] ?? 0, 0)); ?>

            </div>
          </div>
        </div>

        <div class="mb-4 h-[56px]">
          <canvas id="photoSparkline" height="56"></canvas>
        </div>

        <div class="grid grid-cols-3 gap-2">
          <?php
            $photoMini = [
              ['v' => '฿' . number_format($stats['today_revenue'] ?? 0, 0),  'l' => 'วันนี้'],
              ['v' => number_format($stats['paid_orders'] ?? 0),             'l' => 'สำเร็จ'],
              ['v' => number_format($stats['today_orders'] ?? 0),            'l' => 'วันนี้'],
            ];
          ?>
          <?php $__currentLoopData = $photoMini; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="rounded-xl p-2.5 text-center
                        bg-blue-50/70 dark:bg-blue-500/10
                        border border-blue-100 dark:border-blue-500/20">
              <div class="font-bold text-blue-700 dark:text-blue-300 text-sm tracking-tight"><?php echo e($m['v']); ?></div>
              <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5"><?php echo e($m['l']); ?></div>
            </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
      </div>
    </div>

    
    <div class="adm-card overflow-hidden">
      <div class="px-5 py-4 flex items-center justify-between
                  bg-gradient-to-br from-violet-50 to-fuchsia-50
                  dark:from-violet-500/10 dark:to-fuchsia-500/5
                  border-b border-slate-100 dark:border-white/5">
        <div class="flex items-center gap-3">
          <span class="inline-flex items-center justify-center w-11 h-11 rounded-2xl
                       bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white shadow-md shadow-violet-500/30">
            <i class="bi bi-box-seam text-xl"></i>
          </span>
          <div>
            <h6 class="font-bold text-slate-900 dark:text-white text-[15px] leading-tight">ยอดขายดิจิทัลไฟล์</h6>
            <span class="text-[11px] text-slate-500 dark:text-slate-400">Digital Products · <?php echo e($digitalPct); ?>% ของรายได้รวม</span>
          </div>
        </div>
        <a href="<?php echo e(route('admin.digital-orders.index')); ?>"
           class="inline-flex items-center justify-center w-9 h-9 rounded-xl transition
                  bg-violet-500/10 dark:bg-violet-500/20
                  text-violet-600 dark:text-violet-300
                  hover:bg-violet-500 hover:text-white">
          <i class="bi bi-arrow-right"></i>
        </a>
      </div>
      <div class="p-5">
        <div class="grid grid-cols-2 gap-3 mb-4">
          <div>
            <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">รายได้เดือนนี้</div>
            <div class="text-[22px] font-bold text-violet-600 dark:text-violet-400 tracking-tight">
              ฿<?php echo e(number_format((float)($digitalStats['month_revenue'] ?? 0), 0)); ?>

            </div>
          </div>
          <div class="text-right">
            <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">รายได้รวม</div>
            <div class="text-[22px] font-bold text-slate-900 dark:text-white tracking-tight">
              ฿<?php echo e(number_format((float)($digitalStats['total_revenue'] ?? 0), 0)); ?>

            </div>
          </div>
        </div>

        <div class="mb-4 h-[56px]">
          <canvas id="digitalSparkline" height="56"></canvas>
        </div>

        <div class="grid grid-cols-3 gap-2">
          <?php
            $digMini = [
              ['v' => '฿' . number_format((float)($digitalStats['today_revenue'] ?? 0), 0), 'l' => 'วันนี้'],
              ['v' => number_format((int)($digitalStats['paid_orders'] ?? 0)),              'l' => 'สำเร็จ'],
              ['v' => number_format((int)($digitalStats['pending_review'] ?? 0)),           'l' => 'รอตรวจ'],
            ];
          ?>
          <?php $__currentLoopData = $digMini; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="rounded-xl p-2.5 text-center
                        bg-violet-50/70 dark:bg-violet-500/10
                        border border-violet-100 dark:border-violet-500/20">
              <div class="font-bold text-violet-700 dark:text-violet-300 text-sm tracking-tight"><?php echo e($m['v']); ?></div>
              <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5"><?php echo e($m['l']); ?></div>
            </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
      </div>
    </div>
  </div>

  
  <?php if($totalCombinedRevenue > 0): ?>
    <div class="adm-card p-4 mb-6">
      <div class="flex items-center justify-between mb-2.5">
        <span class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
          <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg
                       bg-gradient-to-br from-indigo-500 to-violet-500 text-white">
            <i class="bi bi-bar-chart-line text-xs"></i>
          </span>
          สัดส่วนรายได้รวม
        </span>
        <span class="text-sm font-extrabold text-slate-700 dark:text-slate-300 tracking-tight">
          ฿<?php echo e(number_format($totalCombinedRevenue, 0)); ?>

        </span>
      </div>
      <div class="flex overflow-hidden h-8 rounded-full
                  bg-slate-100 dark:bg-slate-800/70
                  border border-slate-200 dark:border-white/10">
        <div class="flex items-center justify-center font-semibold text-white text-[11px]
                    bg-gradient-to-r from-blue-500 to-sky-400 transition-all duration-500"
             style="width:<?php echo e($photoPct); ?>%;">
          <?php if($photoPct >= 15): ?><i class="bi bi-image mr-1"></i>ภาพ <?php echo e($photoPct); ?>%<?php endif; ?>
        </div>
        <div class="flex items-center justify-center font-semibold text-white text-[11px]
                    bg-gradient-to-r from-violet-500 to-fuchsia-400 transition-all duration-500"
             style="width:<?php echo e($digitalPct); ?>%;">
          <?php if($digitalPct >= 15): ?><i class="bi bi-box-seam mr-1"></i>ดิจิทัล <?php echo e($digitalPct); ?>%<?php endif; ?>
        </div>
      </div>
      <div class="flex justify-between mt-3">
        <div class="flex items-center gap-1.5">
          <div class="w-2.5 h-2.5 rounded-full bg-blue-500"></div>
          <span class="text-[12px] text-slate-600 dark:text-slate-400">
            รูปภาพ <span class="font-semibold text-slate-800 dark:text-slate-200">฿<?php echo e(number_format($stats['total_revenue'] ?? 0, 0)); ?></span>
          </span>
        </div>
        <div class="flex items-center gap-1.5">
          <div class="w-2.5 h-2.5 rounded-full bg-violet-500"></div>
          <span class="text-[12px] text-slate-600 dark:text-slate-400">
            ดิจิทัล <span class="font-semibold text-slate-800 dark:text-slate-200">฿<?php echo e(number_format((float)($digitalStats['total_revenue'] ?? 0), 0)); ?></span>
          </span>
        </div>
      </div>
    </div>
  <?php endif; ?>

  
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">

    
    <div class="lg:col-span-2">
      <div class="adm-card overflow-hidden">
        <div class="flex items-center justify-between px-5 pt-4 pb-3">
          <div class="flex items-center gap-3">
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl
                         bg-gradient-to-br from-indigo-500 to-violet-500 text-white">
              <i class="bi bi-bag text-base"></i>
            </span>
            <div>
              <h6 class="font-bold text-slate-900 dark:text-white text-[15px] leading-tight">ออเดอร์ล่าสุด</h6>
              <div class="text-[11px] text-slate-500 dark:text-slate-400">8 รายการล่าสุด</div>
            </div>
          </div>
          <a href="<?php echo e(route('admin.orders.index')); ?>"
             class="text-[12px] font-medium px-3 py-1.5 rounded-full transition
                    bg-indigo-50 dark:bg-indigo-500/15
                    text-indigo-600 dark:text-indigo-300
                    hover:bg-indigo-100 dark:hover:bg-indigo-500/25">
            ดูทั้งหมด <i class="bi bi-arrow-right"></i>
          </a>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-slate-50 dark:bg-slate-800/50 border-y border-slate-200 dark:border-white/10">
                <th class="pl-5 pr-4 py-3 text-left text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider whitespace-nowrap">ลูกค้า</th>
                <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider whitespace-nowrap">อีเวนต์</th>
                <th class="px-4 py-3 text-center text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">รูป</th>
                <th class="px-4 py-3 text-right text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">ยอด</th>
                <th class="px-4 py-3 text-center text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">สถานะ</th>
                <th class="pr-5 pl-4 py-3 text-left text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider whitespace-nowrap">เวลา</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
              <?php $__empty_1 = true; $__currentLoopData = $latestOrders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <?php
                  $statusMap = [
                    'paid'            => ['cls' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300', 'label' => 'ชำระแล้ว'],
                    'pending_payment' => ['cls' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',         'label' => 'รอชำระ'],
                    'pending_review'  => ['cls' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',             'label' => 'รอตรวจสอบ'],
                    'cancelled'       => ['cls' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',             'label' => 'ยกเลิก'],
                    'refunded'        => ['cls' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',     'label' => 'คืนเงิน'],
                    'cart'            => ['cls' => 'bg-slate-100 text-slate-700 dark:bg-slate-500/15 dark:text-slate-300',         'label' => 'ในตะกร้า'],
                  ];
                  $sc = $statusMap[$order->status ?? ''] ?? ['cls' => 'bg-slate-100 text-slate-700 dark:bg-slate-500/15 dark:text-slate-300', 'label' => ucfirst($order->status ?? '')];
                  $initial = strtoupper(mb_substr($order->first_name ?? 'U', 0, 1));
                ?>
                <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/40">
                  <td class="pl-5 pr-4 py-3">
                    <div class="flex items-center gap-2.5">
                      <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg shrink-0 text-white text-[11px] font-bold"
                            style="background:linear-gradient(135deg,#6366f1,#8b5cf6);">
                        <?php echo e($initial); ?>

                      </span>
                      <div>
                        <div class="text-[13px] font-semibold text-slate-900 dark:text-white">
                          <?php echo e(trim(($order->first_name ?? '') . ' ' . ($order->last_name ?? ''))); ?>

                        </div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">
                          <?php echo e($order->order_number ?? ''); ?>

                        </div>
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-[13px] text-slate-700 dark:text-slate-300 truncate max-w-[130px]">
                    <?php echo e($order->event_name ?? '—'); ?>

                  </td>
                  <td class="px-4 py-3 text-center">
                    <span class="inline-block text-[11px] font-semibold px-2 py-0.5 rounded-full
                                 bg-slate-100 dark:bg-slate-800
                                 text-slate-700 dark:text-slate-300">
                      <?php echo e($order->photo_count ?? 0); ?>

                    </span>
                  </td>
                  <td class="px-4 py-3 text-right font-bold text-indigo-600 dark:text-indigo-400 text-[13px] font-mono tracking-tight">
                    ฿<?php echo e(number_format((float)($order->total ?? 0), 0)); ?>

                  </td>
                  <td class="px-4 py-3 text-center">
                    <span class="inline-block text-[11px] font-semibold px-2.5 py-1 rounded-full <?php echo e($sc['cls']); ?>">
                      <?php echo e($sc['label']); ?>

                    </span>
                  </td>
                  <td class="pr-5 pl-4 py-3 text-[11px] text-slate-500 dark:text-slate-400 whitespace-nowrap">
                    <?php echo e(Carbon::parse($order->created_at)->diffForHumans()); ?>

                  </td>
                </tr>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                  <td colspan="6" class="text-center py-10">
                    <div class="inline-flex flex-col items-center gap-2 text-slate-400 dark:text-slate-500">
                      <i class="bi bi-inbox text-3xl"></i>
                      <span class="text-sm">ยังไม่มีออเดอร์</span>
                    </div>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    
    <div class="flex flex-col gap-5">

      
      <div class="adm-card overflow-hidden grow">
        <div class="px-4 pt-4 pb-3 flex items-center gap-2">
          <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg
                       bg-gradient-to-br from-amber-400 to-orange-500 text-white">
            <i class="bi bi-trophy-fill text-xs"></i>
          </span>
          <div>
            <div class="font-semibold text-sm text-slate-900 dark:text-white leading-tight">Events ยอดนิยม</div>
            <div class="text-[10px] text-slate-500 dark:text-slate-400">เรียงตามรายได้รวม</div>
          </div>
        </div>
        <?php if($topEvents->isEmpty()): ?>
          <div class="text-center py-6 text-slate-500 dark:text-slate-400 text-sm">ยังไม่มีข้อมูล</div>
        <?php else: ?>
          <div class="divide-y divide-slate-100 dark:divide-white/5">
            <?php $__currentLoopData = $topEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $ev): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php
                $rankCls = match(true) {
                  $i === 0 => 'bg-gradient-to-br from-amber-400 to-orange-500 text-white shadow shadow-amber-500/30',
                  $i === 1 => 'bg-gradient-to-br from-slate-300 to-slate-400 text-white',
                  $i === 2 => 'bg-gradient-to-br from-orange-400 to-amber-600 text-white',
                  default  => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300',
                };
              ?>
              <div class="px-4 py-2.5 transition hover:bg-slate-50 dark:hover:bg-slate-800/40">
                <div class="flex items-center gap-2.5">
                  <span class="inline-flex items-center justify-center w-7 h-7 rounded-full shrink-0 text-[11px] font-bold <?php echo e($rankCls); ?>">
                    <?php echo e($i + 1); ?>

                  </span>
                  <div class="grow min-w-0">
                    <div class="text-[13px] font-semibold text-slate-900 dark:text-white truncate">
                      <?php echo e($ev->name ?? '—'); ?>

                    </div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400">
                      <?php echo e($ev->order_count ?? 0); ?> orders
                    </div>
                  </div>
                  <span class="font-bold text-emerald-600 dark:text-emerald-400 text-[13px] whitespace-nowrap tabular-nums">
                    ฿<?php echo e(number_format((float)($ev->revenue ?? 0), 0)); ?>

                  </span>
                </div>
              </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </div>
        <?php endif; ?>
      </div>

      
      <div class="adm-card overflow-hidden">
        <div class="flex items-center justify-between px-4 pt-4 pb-3">
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg
                         bg-gradient-to-br from-cyan-400 to-sky-500 text-white">
              <i class="bi bi-person-plus-fill text-xs"></i>
            </span>
            <div>
              <div class="font-semibold text-sm text-slate-900 dark:text-white leading-tight">สมาชิกล่าสุด</div>
              <?php if(($stats['new_users_today'] ?? 0) > 0): ?>
                <div class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold">+<?php echo e($stats['new_users_today']); ?> วันนี้</div>
              <?php else: ?>
                <div class="text-[10px] text-slate-500 dark:text-slate-400">5 รายล่าสุด</div>
              <?php endif; ?>
            </div>
          </div>
          <a href="<?php echo e(route('admin.users.index')); ?>"
             class="text-[11px] font-medium px-2.5 py-1 rounded-full transition
                    border border-cyan-400 dark:border-cyan-500/50
                    text-cyan-600 dark:text-cyan-300
                    hover:bg-cyan-500 hover:text-white hover:border-cyan-500">
            ทั้งหมด
          </a>
        </div>
        <div class="divide-y divide-slate-100 dark:divide-white/5">
          <?php $__empty_1 = true; $__currentLoopData = $latestUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="px-4 py-2.5 transition hover:bg-slate-50 dark:hover:bg-slate-800/40">
              <div class="flex items-center gap-2.5">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full shrink-0
                             bg-gradient-to-br from-cyan-400 to-sky-500 text-white text-[11px] font-bold">
                  <?php echo e(strtoupper(mb_substr($u->first_name ?? 'U', 0, 1))); ?>

                </span>
                <div class="grow min-w-0">
                  <div class="text-[13px] font-semibold text-slate-900 dark:text-white truncate">
                    <?php echo e(trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''))); ?>

                  </div>
                  <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                    <?php echo e($u->email ?? ''); ?>

                  </div>
                </div>
                <span class="text-[10px] text-slate-500 dark:text-slate-400 whitespace-nowrap">
                  <?php echo e(Carbon::parse($u->created_at)->diffForHumans()); ?>

                </span>
              </div>
            </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="text-center text-slate-500 dark:text-slate-400 py-4 text-sm">ยังไม่มีสมาชิก</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  
  <?php if($pendingDigitalOrders->isNotEmpty() || $topPhotographerPayouts->isNotEmpty()): ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">

      
      <?php if($pendingDigitalOrders->isNotEmpty()): ?>
        <div class="adm-card overflow-hidden h-full">
          <div class="flex items-center justify-between px-4 pt-4 pb-3">
            <div class="flex items-center gap-2">
              <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg
                           bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white">
                <i class="bi bi-box-seam-fill text-xs"></i>
              </span>
              <div>
                <div class="font-semibold text-sm text-slate-900 dark:text-white leading-tight">ดิจิทัลรอตรวจสอบ</div>
                <?php if((int)($digitalStats['pending_review'] ?? 0) > 0): ?>
                  <div class="text-[10px] text-violet-600 dark:text-violet-400 font-semibold">
                    <?php echo e($digitalStats['pending_review']); ?> รายการรอดำเนินการ
                  </div>
                <?php endif; ?>
              </div>
            </div>
            <a href="<?php echo e(route('admin.digital-orders.index')); ?>?status=pending_review"
               class="text-[11px] font-medium px-2.5 py-1 rounded-full transition
                      border border-violet-400 dark:border-violet-500/50
                      text-violet-600 dark:text-violet-300
                      hover:bg-violet-500 hover:text-white hover:border-violet-500">
              ทั้งหมด
            </a>
          </div>
          <div class="divide-y divide-slate-100 dark:divide-white/5">
            <?php $__currentLoopData = $pendingDigitalOrders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $do): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <a href="<?php echo e(route('admin.digital-orders.index')); ?>?status=pending_review"
                 class="block px-4 py-2.5 transition hover:bg-slate-50 dark:hover:bg-slate-800/40">
                <div class="flex items-center justify-between gap-3">
                  <div class="flex items-center gap-2.5 min-w-0">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg shrink-0
                                 bg-gradient-to-br from-violet-500/15 to-fuchsia-500/15
                                 text-violet-600 dark:text-violet-300">
                      <i class="bi bi-receipt text-sm"></i>
                    </span>
                    <div class="min-w-0">
                      <div class="text-[13px] font-semibold text-slate-900 dark:text-white truncate">
                        <?php echo e(trim(($do->first_name ?? '') . ' ' . ($do->last_name ?? ''))); ?>

                      </div>
                      <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                        <?php echo e($do->product_name ?? ''); ?>

                      </div>
                    </div>
                  </div>
                  <div class="text-right shrink-0">
                    <div class="font-bold text-violet-600 dark:text-violet-400 text-[13px] tabular-nums">
                      ฿<?php echo e(number_format((float)($do->amount ?? 0), 0)); ?>

                    </div>
                    <div class="text-[10px] text-slate-500 dark:text-slate-400">
                      <?php echo e(Carbon::parse($do->created_at)->diffForHumans()); ?>

                    </div>
                  </div>
                </div>
              </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </div>
        </div>
      <?php endif; ?>

      
      <?php if($topPhotographerPayouts->isNotEmpty()): ?>
        <div class="adm-card overflow-hidden h-full <?php echo e($pendingDigitalOrders->isNotEmpty() ? '' : 'lg:col-span-2'); ?>">
          <div class="flex items-center justify-between px-5 pt-4 pb-3">
            <div class="flex items-center gap-3">
              <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl
                           bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white">
                <i class="bi bi-camera-fill text-sm"></i>
              </span>
              <div>
                <div class="font-semibold text-sm text-slate-900 dark:text-white leading-tight">ค่าคอมช่างภาพเดือนนี้</div>
                <div class="text-[10px] text-slate-500 dark:text-slate-400">Top 5 ตามค่าธรรมเนียม</div>
              </div>
            </div>
            <a href="<?php echo e(route('admin.payments.payouts')); ?>"
               class="text-[11px] font-medium px-2.5 py-1 rounded-full transition
                      bg-slate-100 dark:bg-slate-800
                      border border-slate-200 dark:border-white/10
                      text-slate-600 dark:text-slate-300
                      hover:bg-slate-200 dark:hover:bg-slate-700">
              ทั้งหมด
            </a>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-slate-50 dark:bg-slate-800/50 border-y border-slate-200 dark:border-white/10">
                  <th class="pl-5 pr-4 py-3 text-left text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">ช่างภาพ</th>
                  <th class="px-4 py-3 text-center text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">คอม%</th>
                  <th class="px-4 py-3 text-center text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">ออเดอร์</th>
                  <th class="px-4 py-3 text-right text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">ยอดขาย</th>
                  <th class="pr-5 pl-4 py-3 text-right text-[11px] font-bold text-violet-600 dark:text-violet-400 uppercase tracking-wider">แพลตฟอร์มได้</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                <?php $__currentLoopData = $topPhotographerPayouts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/40">
                    <td class="pl-5 pr-4 py-3">
                      <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg shrink-0 text-white text-[10px] font-bold"
                              style="background:linear-gradient(135deg,#8b5cf6,#ec4899);">
                          <?php echo e(strtoupper(mb_substr($tp->display_name ?? 'P', 0, 1))); ?>

                        </span>
                        <span class="text-[13px] font-semibold text-slate-900 dark:text-white truncate max-w-[160px]">
                          <?php echo e($tp->display_name ?? '—'); ?>

                        </span>
                      </div>
                    </td>
                    <td class="px-4 py-3 text-center">
                      <span class="inline-block text-[11px] font-semibold px-2 py-0.5 rounded-full
                                   bg-slate-100 dark:bg-slate-800
                                   text-slate-700 dark:text-slate-300
                                   border border-slate-200 dark:border-white/10">
                        <?php echo e(number_format(100 - ($tp->commission_rate ?? 80), 0)); ?>%
                      </span>
                    </td>
                    <td class="px-4 py-3 text-center text-[13px] text-slate-700 dark:text-slate-300">
                      <?php echo e(number_format($tp->order_count ?? 0)); ?>

                    </td>
                    <td class="px-4 py-3 text-right text-[13px] text-slate-700 dark:text-slate-300 font-mono tabular-nums">
                      ฿<?php echo e(number_format((float)($tp->gross ?? 0), 0)); ?>

                    </td>
                    <td class="pr-5 pl-4 py-3 text-right text-[13px] font-bold text-violet-600 dark:text-violet-400 font-mono tabular-nums">
                      ฿<?php echo e(number_format((float)($tp->fee ?? 0), 0)); ?>

                    </td>
                  </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

</div>
<?php $__env->stopSection(); ?>


<?php $__env->startPush('scripts'); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function () {
  'use strict';

  const isDark = () => document.documentElement.classList.contains('dark');

  // ── Sparkline helper (reused for photo + digital) ──────────────────────
  const sparklineInstances = [];
  function createSparkline(canvasId, rawData, lineColor, fillColor) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    const today = new Date();
    const days = [];
    for (let i = 6; i >= 0; i--) {
      const d = new Date(today);
      d.setDate(d.getDate() - i);
      const ds = d.toISOString().split('T')[0];
      const found = rawData.find(r => r.d === ds);
      days.push({ d: ds, revenue: found ? parseFloat(found.revenue) : 0 });
    }

    const inst = new Chart(canvas, {
      type: 'line',
      data: {
        labels: days.map(r => {
          const dt = new Date(r.d);
          return dt.toLocaleDateString('th-TH', { day: 'numeric', month: 'short' });
        }),
        datasets: [{
          data: days.map(r => r.revenue),
          borderColor: lineColor,
          backgroundColor: fillColor,
          borderWidth: 2,
          pointRadius: 0,
          pointHoverRadius: 4,
          pointHoverBackgroundColor: lineColor,
          fill: true,
          tension: 0.4,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(15,23,42,0.92)',
            titleFont: { size: 11 },
            bodyFont: { size: 11 },
            padding: 8,
            displayColors: false,
            callbacks: { label: ctx => '฿' + ctx.parsed.y.toLocaleString('th-TH') }
          }
        },
        scales: {
          x: { display: false },
          y: { display: false, beginAtZero: true }
        },
        interaction: { mode: 'index', intersect: false }
      }
    });
    sparklineInstances.push(inst);
  }

  createSparkline('photoSparkline',   <?php echo json_encode($photoDaily, 15, 512) ?>,   '#3b82f6', 'rgba(59,130,246,0.14)');
  createSparkline('digitalSparkline', <?php echo json_encode($digitalDaily, 15, 512) ?>, '#8b5cf6', 'rgba(139,92,246,0.14)');
})();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/admin/dashboard.blade.php ENDPATH**/ ?>