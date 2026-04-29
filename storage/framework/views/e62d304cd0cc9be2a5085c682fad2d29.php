<?php $__env->startSection('title', 'คิวงาน — Bookings'); ?>

<?php $__env->startPush('styles'); ?>
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
<style>
  /* ── Hero ──────────────────────────────────────────────────────── */
  .bk-hero {
    border-radius: 24px;
    background: linear-gradient(135deg, #0ea5e9 0%, #6366f1 50%, #8b5cf6 100%);
    color: white;
    padding: 1.5rem;
    box-shadow: 0 16px 40px -12px rgba(99,102,241,0.4);
    position: relative;
    overflow: hidden;
  }
  .bk-hero::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle at 100% 0%, rgba(255,255,255,0.18), transparent 50%);
    pointer-events: none;
  }

  /* ── Stat tile ─────────────────────────────────────────────────── */
  .bk-stat {
    background: rgba(255,255,255,0.12);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255,0.18);
    border-radius: 12px;
    padding: 0.75rem 1rem;
  }

  /* ── Pending booking card ──────────────────────────────────────── */
  .bk-pending-card {
    background: white;
    border: 1px solid rgb(252 211 77);
    border-left: 4px solid #f59e0b;
    border-radius: 14px;
    padding: 1rem 1.25rem;
  }
  .dark .bk-pending-card { background: rgb(15 23 42); border-color: rgba(245,158,11,0.4); }

  /* ── FullCalendar dark mode tweaks ─────────────────────────────── */
  .fc-theme-standard .fc-scrollgrid {
    border-color: #e2e8f0;
  }
  .dark .fc-theme-standard .fc-scrollgrid {
    border-color: rgba(255,255,255,0.1);
  }
  .dark .fc-theme-standard td, .dark .fc-theme-standard th {
    border-color: rgba(255,255,255,0.06);
  }
  .dark .fc {
    color: rgb(226 232 240);
  }
  .dark .fc-day-today { background: rgba(99,102,241,0.10) !important; }
  .fc-event { cursor: pointer; padding: 2px 4px; font-size: 11px; }
  .fc-event-title { font-weight: 600; }
  .fc-toolbar-title { font-size: 1.25rem !important; font-weight: 700; }
  .dark .fc-button-primary {
    background: rgba(99,102,241,0.2);
    border-color: rgba(99,102,241,0.4);
    color: #c7d2fe;
  }
  .dark .fc-button-primary:hover {
    background: rgba(99,102,241,0.4);
  }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-7xl mx-auto pb-16">

  
  <div class="bk-hero mb-5">
    <div class="relative grid grid-cols-1 lg:grid-cols-12 gap-4 items-center">
      <div class="lg:col-span-7">
        <div class="text-[10px] uppercase tracking-[0.25em] text-white/75 font-bold mb-1.5 flex items-center gap-1.5">
          <span class="inline-block w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
          คิวงาน · Bookings
        </div>
        <h1 class="text-2xl md:text-3xl font-extrabold leading-tight tracking-tight mb-1">
          ตารางงานถ่ายภาพ
        </h1>
        <p class="text-sm text-white/85">
          จัดการคิวงาน · LINE reminder อัตโนมัติ 4 ครั้งก่อนวันงาน
        </p>
      </div>

      <div class="lg:col-span-5 grid grid-cols-2 md:grid-cols-4 gap-2.5">
        <div class="bk-stat text-center">
          <div class="text-xl font-extrabold"><?php echo e($stats['pending']); ?></div>
          <div class="text-[10px] uppercase tracking-wider text-white/80 font-bold">รอยืนยัน</div>
        </div>
        <div class="bk-stat text-center">
          <div class="text-xl font-extrabold"><?php echo e($stats['upcoming']); ?></div>
          <div class="text-[10px] uppercase tracking-wider text-white/80 font-bold">กำลังจะมา</div>
        </div>
        <div class="bk-stat text-center">
          <div class="text-xl font-extrabold"><?php echo e($stats['this_month']); ?></div>
          <div class="text-[10px] uppercase tracking-wider text-white/80 font-bold">เดือนนี้</div>
        </div>
        <div class="bk-stat text-center">
          <div class="text-xl font-extrabold"><?php echo e($stats['total']); ?></div>
          <div class="text-[10px] uppercase tracking-wider text-white/80 font-bold">รวม</div>
        </div>
      </div>
    </div>
  </div>

  <?php if(session('success')): ?>
    <div class="mb-4 p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 flex items-start gap-2">
      <i class="bi bi-check-circle-fill mt-0.5 shrink-0"></i><span class="text-sm"><?php echo e(session('success')); ?></span>
    </div>
  <?php endif; ?>
  <?php if(session('error')): ?>
    <div class="mb-4 p-3.5 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-800 dark:text-rose-200 flex items-start gap-2">
      <i class="bi bi-exclamation-circle-fill mt-0.5 shrink-0"></i><span class="text-sm"><?php echo e(session('error')); ?></span>
    </div>
  <?php endif; ?>

  
  <?php if($pending->count() > 0): ?>
    <div class="mb-5">
      <h2 class="text-base font-bold text-slate-900 dark:text-white mb-2.5 flex items-center gap-2">
        <i class="bi bi-bell-fill text-amber-500 animate-pulse"></i>
        ต้องตอบรับ <?php echo e($pending->count()); ?> คิว
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <?php $__currentLoopData = $pending; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <div class="bk-pending-card">
            <div class="flex items-start justify-between gap-2 mb-2">
              <div class="min-w-0">
                <div class="font-bold text-sm text-slate-900 dark:text-white"><?php echo e($b->title); ?></div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                  <i class="bi bi-person"></i> <?php echo e($b->customer?->first_name ?? '?'); ?>

                  <?php if($b->customer_phone): ?> · <i class="bi bi-telephone"></i> <?php echo e($b->customer_phone); ?> <?php endif; ?>
                </div>
              </div>
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">
                <i class="bi bi-hourglass-split"></i> รอยืนยัน
              </span>
            </div>
            <div class="text-xs text-slate-600 dark:text-slate-300 space-y-1 mb-3">
              <div><i class="bi bi-calendar-event mr-1"></i> <?php echo e($b->scheduled_at->format('d/m/Y H:i')); ?> (<?php echo e($b->duration_minutes); ?> นาที)</div>
              <?php if($b->location): ?><div><i class="bi bi-geo-alt mr-1"></i> <?php echo e(Str::limit($b->location, 60)); ?></div><?php endif; ?>
              <?php if($b->agreed_price): ?><div><i class="bi bi-cash-coin mr-1 text-emerald-500"></i> <?php echo e(number_format($b->agreed_price)); ?> ฿</div><?php endif; ?>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
              <form action="<?php echo e(route('photographer.bookings.confirm', $b->id)); ?>" method="POST" class="inline">
                <?php echo csrf_field(); ?>
                <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-emerald-500 hover:bg-emerald-600 transition">
                  <i class="bi bi-check-circle"></i> ยืนยัน
                </button>
              </form>
              <a href="<?php echo e(route('photographer.bookings.show', $b->id)); ?>" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-100 dark:bg-white/5 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-white/10 transition no-underline">
                <i class="bi bi-eye"></i> ดูรายละเอียด
              </a>
            </div>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
  <?php endif; ?>

  
  <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 p-4 lg:p-5 shadow-sm">
    <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
      <h2 class="font-bold text-base text-slate-900 dark:text-white flex items-center gap-2">
        <i class="bi bi-calendar3 text-indigo-500"></i> ปฏิทินงาน
      </h2>
      <div class="flex items-center gap-2 text-[11px] flex-wrap">
        <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm" style="background:#f59e0b;"></span> รอยืนยัน</span>
        <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm" style="background:#10b981;"></span> ยืนยันแล้ว</span>
        <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm" style="background:#6366f1;"></span> เสร็จสิ้น</span>
        <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm" style="background:#ef4444;"></span> ยกเลิก</span>
      </div>
    </div>
    <div id="bookingCalendar" style="min-height:600px;"></div>
  </div>

  
  <?php if($upcoming->count() > 0): ?>
    <div class="mt-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 overflow-hidden">
      <div class="px-4 py-3 border-b border-slate-100 dark:border-white/5">
        <h2 class="font-bold text-sm text-slate-900 dark:text-white flex items-center gap-2">
          <i class="bi bi-clock-history text-violet-500"></i> งานที่กำลังจะมาถึง
        </h2>
      </div>
      <div class="divide-y divide-slate-100 dark:divide-white/5">
        <?php $__currentLoopData = $upcoming; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <a href="<?php echo e(route('photographer.bookings.show', $b->id)); ?>" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-white/[0.03] transition no-underline">
            <div class="w-12 h-12 rounded-xl flex flex-col items-center justify-center text-white shrink-0" style="background:linear-gradient(135deg,<?php echo e($b->color); ?>,<?php echo e($b->color); ?>99);">
              <div class="text-[9px] uppercase font-bold opacity-80"><?php echo e($b->scheduled_at->format('M')); ?></div>
              <div class="text-base font-extrabold leading-none"><?php echo e($b->scheduled_at->format('d')); ?></div>
            </div>
            <div class="flex-1 min-w-0">
              <div class="font-semibold text-sm text-slate-900 dark:text-white truncate"><?php echo e($b->title); ?></div>
              <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-2 flex-wrap">
                <span><i class="bi bi-clock"></i> <?php echo e($b->scheduled_at->format('H:i')); ?></span>
                <span><i class="bi bi-person"></i> <?php echo e($b->customer?->first_name ?? '?'); ?></span>
                <?php if($b->location): ?><span class="truncate"><i class="bi bi-geo-alt"></i> <?php echo e(Str::limit($b->location, 30)); ?></span><?php endif; ?>
              </div>
            </div>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold whitespace-nowrap" style="background:<?php echo e($b->color); ?>25; color:<?php echo e($b->color); ?>;">
              <?php echo e($b->status_label); ?>

            </span>
          </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
  <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const el = document.getElementById('bookingCalendar');
  if (!el) return;

  const cal = new FullCalendar.Calendar(el, {
    initialView: 'dayGridMonth',
    headerToolbar: {
      left:   'prev,next today',
      center: 'title',
      right:  'dayGridMonth,timeGridWeek,listMonth',
    },
    locale: 'th',
    buttonText: {
      today: 'วันนี้',
      month: 'เดือน',
      week:  'สัปดาห์',
      list:  'รายการ',
    },
    height: 'auto',
    events: {
      url: '<?php echo e(route('photographer.bookings.feed')); ?>',
      method: 'GET',
      failure: () => alert('โหลดข้อมูลปฏิทินไม่สำเร็จ'),
    },
    eventClick: function (info) {
      info.jsEvent.preventDefault();
      if (info.event.url) window.location.href = info.event.url;
    },
    eventDidMount: function (info) {
      // Tooltip with extended info
      const props = info.event.extendedProps || {};
      const tip = [
        props.status_label || '',
        props.customer_name ? '👤 ' + props.customer_name : '',
        props.location ? '📍 ' + props.location : '',
        props.price ? '💰 ' + Number(props.price).toLocaleString() + ' ฿' : '',
      ].filter(Boolean).join('  ·  ');
      info.el.title = tip;
    },
  });
  cal.render();
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.photographer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/photographer/bookings/index.blade.php ENDPATH**/ ?>