@php
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
@endphp

<meta property="og:title" content="{{ $ogTitle }}" />
<meta property="og:description" content="{{ Str::limit(strip_tags($ogDescription), 200) }}" />
<meta property="og:url" content="{{ $ogUrl }}" />
<meta property="og:type" content="{{ $ogType }}" />
<meta property="og:site_name" content="{{ $_resolvedSiteName }}" />
<meta property="og:locale" content="th_TH" />
@if($ogImage)
<meta property="og:image" content="{{ $ogImage }}" />
<meta property="og:image:width" content="1200" />
<meta property="og:image:height" content="630" />
@endif
@if($fbAppId)
<meta property="fb:app_id" content="{{ $fbAppId }}" />
@endif

{{-- Twitter Card --}}
<meta name="twitter:card" content="{{ $twitterCard }}" />
<meta name="twitter:title" content="{{ $ogTitle }}" />
<meta name="twitter:description" content="{{ Str::limit(strip_tags($ogDescription), 200) }}" />
@if($ogImage)
<meta name="twitter:image" content="{{ $ogImage }}" />
@endif
