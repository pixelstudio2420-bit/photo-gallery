<?php
  // Resolve site name with the same priority as ViewServiceProvider:
  //   $siteName (AppSetting site_name) → config('app.name') → 'Photo Gallery'
  $_resolvedSiteName = $siteName ?? config('app.name', 'Photo Gallery');
  $ogTitle = $ogTitle ?? ($pageTitle ?? $_resolvedSiteName);
  $ogDescription = $ogDescription ?? App\Models\AppSetting::get('og_site_description', $_resolvedSiteName . ' — ซื้อขายภาพถ่ายคุณภาพจากช่างภาพมืออาชีพ');
  $ogImage = $ogImage ?? App\Models\AppSetting::get('og_default_image', '');
  $ogUrl = $ogUrl ?? request()->url();
  $ogType = $ogType ?? 'website';
  $fbAppId = App\Models\AppSetting::get('og_fb_app_id', '');
  $twitterCard = App\Models\AppSetting::get('og_twitter_card_type', 'summary_large_image');
?>

<meta property="og:title" content="<?php echo e($ogTitle); ?>" />
<meta property="og:description" content="<?php echo e(Str::limit(strip_tags($ogDescription), 200)); ?>" />
<meta property="og:url" content="<?php echo e($ogUrl); ?>" />
<meta property="og:type" content="<?php echo e($ogType); ?>" />
<meta property="og:site_name" content="<?php echo e($_resolvedSiteName); ?>" />
<meta property="og:locale" content="th_TH" />
<?php if($ogImage): ?>
<meta property="og:image" content="<?php echo e($ogImage); ?>" />
<meta property="og:image:width" content="1200" />
<meta property="og:image:height" content="630" />
<?php endif; ?>
<?php if($fbAppId): ?>
<meta property="fb:app_id" content="<?php echo e($fbAppId); ?>" />
<?php endif; ?>


<meta name="twitter:card" content="<?php echo e($twitterCard); ?>" />
<meta name="twitter:title" content="<?php echo e($ogTitle); ?>" />
<meta name="twitter:description" content="<?php echo e(Str::limit(strip_tags($ogDescription), 200)); ?>" />
<?php if($ogImage): ?>
<meta name="twitter:image" content="<?php echo e($ogImage); ?>" />
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/layouts/partials/og-meta.blade.php ENDPATH**/ ?>