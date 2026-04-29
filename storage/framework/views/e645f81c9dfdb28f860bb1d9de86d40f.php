<?php
// Load footer settings from DB
$_footerKeys = [
  'footer_social_enabled','footer_social_facebook','footer_social_instagram',
  'footer_social_line','footer_social_tiktok','footer_social_youtube','footer_social_twitter',
  'footer_contact_enabled','footer_contact_email','footer_contact_phone',
  'footer_contact_line_id','footer_contact_address',
];
$_footerRows = \DB::table('app_settings')
  ->whereIn('key', $_footerKeys)
  ->pluck('value', 'key')
  ->toArray();

$_socialEnabled = ($_footerRows['footer_social_enabled'] ?? '1') === '1';
$_contactEnabled = ($_footerRows['footer_contact_enabled'] ?? '1') === '1';

// Legal pages — list published canonical + custom pages for the footer nav
try {
  $_legalPages = \App\Models\LegalPage::published()
      ->orderByRaw("FIELD(slug, 'privacy-policy', 'terms-of-service', 'refund-policy') DESC")
      ->orderBy('title')
      ->get(['slug', 'title']);
} catch (\Throwable $e) {
  $_legalPages = collect(); // Table not yet migrated — render nothing rather than crash
}

$_socialLinks = [];
$_socialMap = [
  'footer_social_facebook' => ['icon' => 'bi-facebook', 'label' => 'Facebook'],
  'footer_social_instagram' => ['icon' => 'bi-instagram', 'label' => 'Instagram'],
  'footer_social_line'   => ['icon' => 'bi-line',   'label' => 'LINE'],
  'footer_social_tiktok'  => ['icon' => 'bi-tiktok',  'label' => 'TikTok'],
  'footer_social_youtube'  => ['icon' => 'bi-youtube',  'label' => 'YouTube'],
  'footer_social_twitter'  => ['icon' => 'bi-twitter-x', 'label' => 'X (Twitter)'],
];
foreach ($_socialMap as $key => $info) {
  $url = trim($_footerRows[$key] ?? '');
  if ($url !== '') {
    $_socialLinks[] = ['url' => $url, 'icon' => $info['icon'], 'label' => $info['label']];
  }
}
?>

<footer class="bg-gradient-to-b from-slate-900 to-slate-950 text-white pt-16 pb-8">
  <div class="relative max-w-7xl mx-auto px-4">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-8">
      <?php
        // Mirror the navbar's brand-logo resolution so the footer picks up
        // the same uploaded logo. Kept local to the footer so the navbar
        // and footer can evolve independently if marketing ever wants
        // different crops.
        $_footerLogoUrl = null;
        if (!empty($siteLogo)) {
          try {
            $_footerLogoUrl = app(\App\Services\StorageManager::class)->resolveUrl($siteLogo);
          } catch (\Throwable) { /* fall through to icon */ }
        }
        $_footerBrandName = $siteName ?: config('app.name');
      ?>
      <div>
        <div class="flex items-center gap-2 mb-3">
          <?php if($_footerLogoUrl): ?>
            <img src="<?php echo e($_footerLogoUrl); ?>" alt="<?php echo e($_footerBrandName); ?>"
                 class="h-8 w-auto max-w-[140px] object-contain"
                 onerror="this.onerror=null;this.replaceWith(Object.assign(document.createElement('i'),{className:'bi bi-camera2'}));">
          <?php else: ?>
            <i class="bi bi-camera2"></i>
          <?php endif; ?>
          <h5 class="font-bold mb-0 text-white text-lg"><?php echo e($_footerBrandName); ?></h5>
        </div>
        <p class="text-gray-400 mb-3 text-sm leading-relaxed">
          แพลตฟอร์มค้นหาและซื้อรูปภาพจากงานอีเวนต์<br>
          โดยช่างภาพมืออาชีพ ค้นหา เลือก และดาวน์โหลดได้ง่าย
        </p>
        <?php if($_socialEnabled && !empty($_socialLinks)): ?>
        <div class="flex gap-2">
          <?php $__currentLoopData = $_socialLinks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <a href="<?php echo e($sl['url']); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo e($sl['label']); ?>" class="w-9 h-9 flex items-center justify-center rounded-lg bg-white/10 hover:bg-white/20 text-white transition"><i class="bi <?php echo e($sl['icon']); ?>"></i></a>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <?php endif; ?>
      </div>
      <div>
        <h6 class="font-semibold mb-3 text-white"><?php echo e(__('common.see_more')); ?></h6>
        <ul class="list-none space-y-2">
          <li><a href="<?php echo e(route('home')); ?>" class="text-gray-400 hover:text-white transition"><?php echo e(__('nav.home')); ?></a></li>
          <li><a href="<?php echo e(route('events.index')); ?>" class="text-gray-400 hover:text-white transition"><?php echo e(__('nav.events')); ?></a></li>
          <li><a href="<?php echo e(route('blog.index')); ?>" class="text-gray-400 hover:text-white transition"><?php echo e(__('nav.blog')); ?></a></li>
          <?php if(auth()->guard()->check()): ?>
          <li><a href="<?php echo e(route('profile')); ?>" class="text-gray-400 hover:text-white transition"><?php echo e(__('nav.my_account')); ?></a></li>
          <li><a href="<?php echo e(route('profile.orders')); ?>" class="text-gray-400 hover:text-white transition"><?php echo e(__('nav.my_orders')); ?></a></li>
          <?php else: ?>
          <li><a href="<?php echo e(route('auth.login')); ?>" class="text-gray-400 hover:text-white transition"><?php echo e(__('nav.login')); ?></a></li>
          <li><a href="<?php echo e(route('auth.register')); ?>" class="text-gray-400 hover:text-white transition"><?php echo e(__('nav.register')); ?></a></li>
          <?php endif; ?>
          <li><a href="<?php echo e(route('help')); ?>" class="text-gray-400 hover:text-white transition"><i class="bi bi-question-circle mr-1"></i><?php echo e(__('nav.help')); ?></a></li>
         
        </ul>
      </div>
      <div>
        <h6 class="font-semibold mb-3 text-white"><?php echo e(__('nav.become_photographer')); ?></h6>
        <ul class="list-none space-y-2">
          <?php if(auth()->guard()->check()): ?>
            <?php if(Auth::user()->photographerProfile && Auth::user()->photographerProfile->status === 'approved'): ?>
            <li><a href="<?php echo e(route('photographer.dashboard')); ?>" class="text-gray-400 hover:text-white transition"><?php echo e(__('nav.photographer_dashboard')); ?></a></li>
            <li><a href="<?php echo e(route('photographer.events.index')); ?>" class="text-gray-400 hover:text-white transition"><?php echo e(__('photographer.my_events')); ?></a></li>
            <?php else: ?>
            <li><a href="<?php echo e(route('photographer.register')); ?>" class="text-gray-400 hover:text-white transition"><?php echo e(__('nav.become_photographer')); ?></a></li>
            <?php endif; ?>
          <?php else: ?>
          <li><a href="<?php echo e(route('photographer.login')); ?>" class="text-gray-400 hover:text-white transition"><?php echo e(__('nav.login')); ?></a></li>
          <li><a href="<?php echo e(route('photographer.register')); ?>" class="text-gray-400 hover:text-white transition"><?php echo e(__('nav.become_photographer')); ?></a></li>
          <?php endif; ?>
          <li><a href="<?php echo e(route('help')); ?>" class="text-gray-400 hover:text-white transition"><i class="bi bi-book mr-1"></i><?php echo e(__('nav.help')); ?></a></li>
        </ul>
      </div>
      <?php if($_contactEnabled): ?>
      <div>
        <h6 class="font-semibold mb-3 text-white"><?php echo e(__('nav.contact')); ?></h6>
        <ul class="list-none space-y-2">
          <?php if(!empty($_footerRows['footer_contact_email'])): ?>
          <li><a href="mailto:<?php echo e($_footerRows['footer_contact_email']); ?>" class="text-gray-400 hover:text-white transition"><i class="bi bi-envelope mr-2"></i><?php echo e($_footerRows['footer_contact_email']); ?></a></li>
          <?php endif; ?>
          <?php if(!empty($_footerRows['footer_contact_phone'])): ?>
          <li><a href="tel:<?php echo e($_footerRows['footer_contact_phone']); ?>" class="text-gray-400 hover:text-white transition"><i class="bi bi-telephone mr-2"></i><?php echo e($_footerRows['footer_contact_phone']); ?></a></li>
          <?php endif; ?>
          <?php if(!empty($_footerRows['footer_contact_line_id'])): ?>
          <li><a href="https://line.me/ti/p/~<?php echo e($_footerRows['footer_contact_line_id']); ?>" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-white transition"><i class="bi bi-line mr-2"></i><?php echo e($_footerRows['footer_contact_line_id']); ?></a></li>
          <?php endif; ?>
          <?php if(!empty($_footerRows['footer_contact_address'])): ?>
          <li><span class="text-gray-400"><i class="bi bi-geo-alt mr-2"></i><?php echo e($_footerRows['footer_contact_address']); ?></span></li>
          <?php endif; ?>
          <?php if(empty($_footerRows['footer_contact_email']) && empty($_footerRows['footer_contact_phone']) && empty($_footerRows['footer_contact_line_id']) && empty($_footerRows['footer_contact_address'])): ?>
          <li><span class="text-gray-400 text-sm">ยังไม่ได้ตั้งค่าข้อมูลติดต่อ</span></li>
          <?php endif; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>

    
    <?php
      $_seoCfg = config('seo_landings');
      $_topProvinces = ['bangkok' => 'กรุงเทพ', 'chiang-mai' => 'เชียงใหม่', 'phuket' => 'ภูเก็ต', 'pattaya' => 'พัทยา'];
    ?>
    <?php if(!empty($_seoCfg) && !empty($_seoCfg['niches'])): ?>
    <div class="mt-8 pt-6 border-t border-white/10">
      <h6 class="font-semibold mb-3 text-white text-sm">เลือกประเภทช่างภาพ · ทุกจังหวัดทั่วไทย</h6>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-1 text-xs">
        <?php $__currentLoopData = $_seoCfg['niches']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $_nSlug => $_n): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <div class="leading-relaxed">
            <a href="<?php echo e(route('seo.landing.niche', ['niche' => $_nSlug])); ?>" class="text-gray-300 hover:text-white font-medium">
              <i class="bi <?php echo e($_n['icon']); ?> mr-1"></i><?php echo e($_n['label']); ?>

            </a>
            <span class="text-gray-600">·</span>
            <?php $__currentLoopData = $_topProvinces; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $_pSlug => $_pLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <a href="<?php echo e(route('seo.landing.province', ['niche' => $_nSlug, 'province' => $_pSlug])); ?>" class="text-gray-500 hover:text-gray-300"><?php echo e($_pLabel); ?></a><?php if(!$loop->last): ?><span class="text-gray-700">,</span> <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
    <?php endif; ?>

    <hr class="border-white/10 mt-6">
    <div class="flex flex-wrap justify-between items-center gap-3 mt-6">
      <small class="text-gray-500">&copy; <?php echo e(date('Y')); ?> <?php echo e($siteName ?? config('app.name')); ?>. <?php echo e(__('nav.all_rights_reserved')); ?></small>
      <?php if($_legalPages->isNotEmpty()): ?>
      <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
        <?php $__currentLoopData = $_legalPages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $_lp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <a href="<?php echo e(route('legal.show', $_lp->slug)); ?>" class="text-gray-500 hover:text-gray-300 transition"><?php echo e($_lp->title); ?></a>
          <?php if(!$loop->last): ?><span class="text-gray-700">·</span><?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
      <?php endif; ?>
      <small class="text-gray-600">Made with <i class="bi bi-heart-fill text-red-500"></i> in Thailand</small>
    </div>
  </div>
</footer>
<?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/layouts/partials/footer.blade.php ENDPATH**/ ?>