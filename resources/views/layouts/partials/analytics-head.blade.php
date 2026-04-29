{{-- Google Analytics (GA4) --}}
@if(App\Models\AppSetting::get('ga4_enabled', '0') === '1' && ($gaId = App\Models\AppSetting::get('ga4_measurement_id', '')))
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '{{ $gaId }}', {
  page_path: window.location.pathname,
  send_page_view: true
});
</script>
@endif

{{-- Facebook Pixel --}}
@if(App\Models\AppSetting::get('fb_pixel_enabled', '0') === '1' && ($pixelId = App\Models\AppSetting::get('fb_pixel_id', '')))
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '{{ $pixelId }}');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={{ $pixelId }}&ev=PageView&noscript=1"/></noscript>
@endif
