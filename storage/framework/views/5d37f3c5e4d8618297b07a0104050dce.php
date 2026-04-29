<?php $__env->startSection('title', $title); ?>

<?php $__env->startPush('styles'); ?>
<style>
  /* Scoped styles — match the photographer landing's accent palette but
     theme to the niche's accent. The CSS variable is set inline below. */
  .sl-hero {
    background:
      radial-gradient(900px 500px at 15% -10%, color-mix(in oklab, var(--sl-accent) 25%, transparent), transparent 60%),
      radial-gradient(700px 500px at 85% 15%, color-mix(in oklab, var(--sl-accent) 18%, transparent), transparent 60%),
      linear-gradient(160deg,#f8fafc 0%,#f1f5f9 60%,#ede9fe 100%);
  }
  html.dark .sl-hero {
    background:
      radial-gradient(900px 500px at 15% -10%, color-mix(in oklab, var(--sl-accent) 30%, transparent), transparent 60%),
      radial-gradient(700px 500px at 85% 15%, color-mix(in oklab, var(--sl-accent) 22%, transparent), transparent 60%),
      linear-gradient(160deg,#020617 0%,#0f172a 60%,#1e1b4b 100%);
  }
  .sl-pill { background: color-mix(in oklab, var(--sl-accent) 15%, transparent); color: var(--sl-accent); }
  .sl-grad-text { color: var(--sl-accent); }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content-full'); ?>
<article style="--sl-accent: <?php echo e($nicheCfg['accent_hex']); ?>;">

  
  <section class="sl-hero py-14 sm:py-20">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">

      
      <nav class="text-xs text-slate-500 dark:text-slate-400 mb-5" aria-label="breadcrumb">
        <a href="<?php echo e(route('home')); ?>" class="hover:underline">หน้าแรก</a>
        <span class="mx-1.5">›</span>
        <a href="<?php echo e(route('events.index')); ?>" class="hover:underline">ช่างภาพ</a>
        <span class="mx-1.5">›</span>
        <a href="<?php echo e(route('seo.landing.niche', ['niche' => $niche])); ?>" class="hover:underline"><?php echo e($nicheCfg['label']); ?></a>
        <?php if($provinceCfg): ?>
          <span class="mx-1.5">›</span>
          <span class="text-slate-700 dark:text-slate-300"><?php echo e($provinceCfg['label']); ?></span>
        <?php endif; ?>
      </nav>

      <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold sl-pill mb-4">
        <i class="bi <?php echo e($nicheCfg['icon']); ?>"></i>
        <?php echo e($nicheCfg['plural']); ?><?php echo e($provinceCfg ? ' · ' . $provinceCfg['short'] : ' ทั่วประเทศ'); ?>

      </span>

      <h1 class="font-extrabold leading-[1.1] tracking-tight text-3xl sm:text-5xl lg:text-6xl text-slate-900 dark:text-white mb-5">
        <?php echo e(str_replace(':scope:', $scope, $nicheCfg['h1_pat'])); ?>

        <span class="block text-2xl sm:text-3xl lg:text-4xl mt-2 sl-grad-text font-extrabold">
          ค้นหาด้วย AI · ส่งรูปเข้า LINE
        </span>
      </h1>

      <p class="text-base sm:text-lg text-slate-600 dark:text-slate-300 leading-relaxed mb-7 max-w-2xl">
        <?php echo e($description); ?>

      </p>

      
      <div class="flex flex-wrap gap-3">
        <a href="<?php echo e(url('/face-search')); ?>"
           class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl font-bold text-white shadow-xl"
           style="background: var(--sl-accent); box-shadow: 0 12px 30px -8px var(--sl-accent);">
          <i class="bi bi-person-bounding-box"></i>
          ค้นหาตัวเองด้วย AI
        </a>
        <a href="<?php echo e(route('events.index')); ?><?php echo e(!empty($nicheCfg['category_slug']) ? '?category=' . $nicheCfg['category_slug'] : ''); ?>"
           class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl font-bold border-2 border-slate-300 dark:border-white/20 text-slate-700 dark:text-white bg-white/60 dark:bg-white/5 hover:bg-white dark:hover:bg-white/10 transition">
          <i class="bi bi-grid-3x3-gap"></i>
          ดูอีเวนต์ทั้งหมด
        </a>
        <a href="<?php echo e(url('/become-photographer')); ?>"
           class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl font-bold border-2 border-slate-300 dark:border-white/20 text-slate-700 dark:text-white bg-white/60 dark:bg-white/5 hover:bg-white dark:hover:bg-white/10 transition">
          <i class="bi bi-calendar-check"></i>
          จองช่างภาพ
        </a>
      </div>
    </div>
  </section>

  
  <section class="py-12 bg-white dark:bg-slate-950">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
        <?php $__currentLoopData = $usp_bullets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900/60 p-4">
          <i class="bi <?php echo e($u['icon']); ?> text-2xl mb-2" style="color: var(--sl-accent);"></i>
          <h3 class="font-bold text-sm text-slate-900 dark:text-white mb-1"><?php echo e($u['title']); ?></h3>
          <p class="text-xs text-slate-600 dark:text-slate-400 leading-snug"><?php echo e($u['body']); ?></p>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
  </section>

  
  <?php if($events->count() > 0): ?>
  <section class="py-12 sm:py-16 bg-gradient-to-br from-slate-50 to-indigo-50/40 dark:from-slate-900 dark:to-indigo-950/30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
      <div class="flex items-end justify-between mb-6">
        <div>
          <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white">
            อีเวนต์<?php echo e($nicheCfg['label']); ?><?php echo e($provinceCfg ? ' · ' . $provinceCfg['short'] : ''); ?>

          </h2>
          <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">งานล่าสุดที่ตรงกับการค้นหาของคุณ</p>
        </div>
        <a href="<?php echo e(route('events.index')); ?>" class="hidden sm:inline-flex items-center gap-1 text-sm font-semibold sl-grad-text hover:underline">
          ดูทั้งหมด <i class="bi bi-arrow-right"></i>
        </a>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <?php $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a href="<?php echo e(route('events.show', $event->slug ?: $event->id)); ?>"
           class="group rounded-2xl overflow-hidden bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-white/10 hover:-translate-y-1 transition">
          <div class="aspect-[4/3] bg-slate-100 dark:bg-slate-800 overflow-hidden relative">
            <?php if($event->cover_image): ?>
              
              <img src="<?php echo e($event->cover_image); ?>"
                   alt="<?php echo e($event->name); ?>"
                   loading="lazy" decoding="async" fetchpriority="low"
                   class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
            <?php else: ?>
              <div class="w-full h-full flex items-center justify-center text-slate-400">
                <i class="bi <?php echo e($nicheCfg['icon']); ?> text-4xl"></i>
              </div>
            <?php endif; ?>
          </div>
          <div class="p-3">
            <h3 class="font-bold text-sm text-slate-900 dark:text-white mb-1 line-clamp-2 group-hover:underline"><?php echo e($event->name); ?></h3>
            <?php if($event->shoot_date): ?>
              <p class="text-xs text-slate-500 dark:text-slate-400">
                <i class="bi bi-calendar3"></i>
                <?php echo e(\Carbon\Carbon::parse($event->shoot_date)->translatedFormat('j M Y')); ?>

              </p>
            <?php endif; ?>
          </div>
        </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  
  <?php if($photographers->count() > 0): ?>
  <section class="py-12 sm:py-16 bg-white dark:bg-slate-950">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
      <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white mb-1">
        <?php echo e($nicheCfg['plural']); ?>แนะนำ<?php echo e($provinceCfg ? ' · ' . $provinceCfg['short'] : ''); ?>

      </h2>
      <p class="text-sm text-slate-600 dark:text-slate-400 mb-6">มืออาชีพที่ลูกค้าเลือกใช้ซ้ำมากที่สุด</p>
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        <?php $__currentLoopData = $photographers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a href="<?php echo e(route('photographers.show', $p->user_id)); ?>"
           class="group rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 p-4 text-center hover:-translate-y-1 transition">
          <div class="w-16 h-16 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 mb-2 overflow-hidden flex items-center justify-center">
            <?php if(!empty($p->avatar)): ?>
              <img src="<?php echo e($p->avatar); ?>" alt="<?php echo e($p->display_name); ?>" loading="lazy" class="w-full h-full object-cover">
            <?php else: ?>
              <i class="bi bi-person-fill text-2xl text-slate-400"></i>
            <?php endif; ?>
          </div>
          <h3 class="font-bold text-sm text-slate-900 dark:text-white mb-0.5 line-clamp-1"><?php echo e($p->display_name ?? $p->photographer_code); ?></h3>
          <p class="text-xs text-slate-500 dark:text-slate-400"><?php echo e($p->events_count); ?> อีเวนต์</p>
        </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  
  <section class="py-12 sm:py-16 bg-slate-50 dark:bg-slate-900/40">
    <div class="max-w-3xl mx-auto px-4 sm:px-6">
      <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white mb-1 text-center">คำถามที่พบบ่อย</h2>
      <p class="text-sm text-slate-600 dark:text-slate-400 mb-8 text-center">
        เกี่ยวกับ<?php echo e($nicheCfg['label']); ?><?php echo e($provinceCfg ? ' · ' . $provinceCfg['short'] : ''); ?>

      </p>
      <div class="space-y-3" x-data="{ open: 0 }">
        <?php $__currentLoopData = $faqs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $faq): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-white/10 overflow-hidden">
          <button type="button"
                  @click="open = (open === <?php echo e($i); ?> ? null : <?php echo e($i); ?>)"
                  class="w-full flex items-center justify-between gap-3 px-5 py-4 text-left hover:bg-slate-50 dark:hover:bg-white/5">
            <span class="font-semibold text-slate-900 dark:text-white"><?php echo e($faq['q']); ?></span>
            <i class="bi" :class="open === <?php echo e($i); ?> ? 'bi-dash-circle' : 'bi-plus-circle'" style="color: var(--sl-accent);"></i>
          </button>
          <div x-show="open === <?php echo e($i); ?>" x-collapse class="px-5 pb-4 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
            <?php echo e($faq['a']); ?>

          </div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
  </section>

  
  <?php if(!empty($related_provinces) || !empty($related_niches)): ?>
  <section class="py-12 bg-white dark:bg-slate-950 border-t border-slate-200 dark:border-white/10">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 grid grid-cols-1 md:grid-cols-2 gap-8">

      <?php if(!empty($related_provinces)): ?>
      <div>
        <h3 class="text-base font-bold text-slate-900 dark:text-white mb-3">
          ดู<?php echo e($nicheCfg['label']); ?>ในจังหวัดอื่น
        </h3>
        <div class="flex flex-wrap gap-2">
          <?php $__currentLoopData = $related_provinces; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slug => $prov): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a href="<?php echo e(route('seo.landing.province', ['niche' => $niche, 'province' => $slug])); ?>"
               class="inline-block px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-xs font-medium text-slate-700 dark:text-slate-300 transition">
              <?php echo e($nicheCfg['label']); ?> <?php echo e($prov['short']); ?>

            </a>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if(!empty($related_niches)): ?>
      <div>
        <h3 class="text-base font-bold text-slate-900 dark:text-white mb-3">
          ประเภทช่างภาพอื่น<?php echo e($provinceCfg ? ' · ' . $provinceCfg['short'] : ''); ?>

        </h3>
        <div class="flex flex-wrap gap-2">
          <?php $__currentLoopData = $related_niches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slug => $cfg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
              $relUrl = $provinceCfg
                ? route('seo.landing.province', ['niche' => $slug, 'province' => array_search($provinceCfg, config('seo_landings.provinces')) ?: ''])
                : route('seo.landing.niche', ['niche' => $slug]);
            ?>
            <a href="<?php echo e($relUrl); ?>"
               class="inline-block px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 text-xs font-medium text-slate-700 dark:text-slate-300 transition">
              <i class="bi <?php echo e($cfg['icon']); ?> mr-1"></i> <?php echo e($cfg['label']); ?>

            </a>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  
  <section class="py-10 bg-slate-50 dark:bg-slate-900/40 border-t border-slate-200 dark:border-white/10">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 text-center">
      <p class="text-xs text-slate-500 dark:text-slate-500 leading-relaxed">
        คำค้นที่เกี่ยวข้อง:
        <span class="text-slate-700 dark:text-slate-300">
          <?php $__currentLoopData = ($nicheCfg['long_tail'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $term): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php echo e($term); ?><?php if(!$loop->last): ?> · <?php endif; ?>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          <?php if($provinceCfg): ?>
            · <?php echo e($nicheCfg['label']); ?> <?php echo e($provinceCfg['short']); ?>

            · <?php echo e($nicheCfg['pretty_keyword']); ?> <?php echo e($provinceCfg['short']); ?>

          <?php endif; ?>
        </span>
      </p>
    </div>
  </section>

</article>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/public/seo-landing.blade.php ENDPATH**/ ?>