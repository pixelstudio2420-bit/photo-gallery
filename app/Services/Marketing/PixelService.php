<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pixel manager — renders client-side scripts + fires server-side events.
 *
 * Supports:
 *   - Meta Pixel (client) + Meta Conversions API (server, iOS-safe)
 *   - Google Analytics 4 (gtag.js) + Measurement Protocol (server)
 *   - Google Tag Manager
 *   - Google Ads Conversion tracking
 *   - LINE Tag (LAP pixel)
 *   - TikTok Pixel
 *
 * Event vocabulary (Meta standard — auto-translated for other platforms):
 *   PageView, ViewContent, AddToCart, InitiateCheckout, AddPaymentInfo,
 *   Purchase, Lead, CompleteRegistration, Search
 */
class PixelService
{
    public function __construct(protected MarketingService $marketing) {}

    /**
     * Render all head-stage pixel scripts (base code) as a single HTML string.
     * Call this from <head>.
     */
    public function renderHead(): string
    {
        if (!$this->marketing->masterEnabled()) return '';

        $html = '';
        if ($this->marketing->pixelEnabled('gtm'))        $html .= $this->gtmHead();
        if ($this->marketing->pixelEnabled('ga4'))        $html .= $this->ga4Head();
        if ($this->marketing->pixelEnabled('fb'))         $html .= $this->fbHead();
        if ($this->marketing->pixelEnabled('google_ads')) $html .= $this->googleAdsHead();
        if ($this->marketing->pixelEnabled('line_tag'))   $html .= $this->lineTagHead();
        if ($this->marketing->pixelEnabled('tiktok'))     $html .= $this->tiktokHead();

        return $html;
    }

    /**
     * Render the body-open noscript tags (required by GTM + FB fallback).
     */
    public function renderBody(): string
    {
        if (!$this->marketing->masterEnabled()) return '';

        $html = '';
        if ($this->marketing->pixelEnabled('gtm')) $html .= $this->gtmBody();
        if ($this->marketing->pixelEnabled('fb'))  $html .= $this->fbNoscript();

        return $html;
    }

    /**
     * Render a single event fire as client-side JavaScript.
     *
     * @param string $event  Meta-style event name (Purchase, AddToCart, etc.)
     * @param array  $data   Event data (value, currency, contents[], order_id, etc.)
     */
    public function renderEvent(string $event, array $data = []): string
    {
        if (!$this->marketing->masterEnabled()) return '';

        $scripts = [];
        if ($this->marketing->pixelEnabled('fb'))         $scripts[] = $this->fbEvent($event, $data);
        if ($this->marketing->pixelEnabled('ga4'))        $scripts[] = $this->ga4Event($event, $data);
        if ($this->marketing->pixelEnabled('google_ads') && $event === 'Purchase') $scripts[] = $this->googleAdsConversion($data);
        if ($this->marketing->pixelEnabled('line_tag'))   $scripts[] = $this->lineTagEvent($event, $data);
        if ($this->marketing->pixelEnabled('tiktok'))     $scripts[] = $this->tiktokEvent($event, $data);

        $scripts = array_filter($scripts);
        if (empty($scripts)) return '';

        return "<script>\n" . implode("\n", $scripts) . "\n</script>\n";
    }

    /**
     * Fire a server-side event (Meta Conversions API + GA4 Measurement Protocol).
     * Call from controller after Purchase, Lead, etc. Async-safe.
     */
    public function fireServerEvent(string $event, array $data = []): void
    {
        if (!$this->marketing->masterEnabled()) return;

        try {
            if ($this->marketing->pixelEnabled('fb_capi')) {
                $this->sendFbCapi($event, $data);
            }
            if ($this->marketing->pixelEnabled('ga4') && $this->marketing->get('ga4_api_secret')) {
                $this->sendGa4Mp($event, $data);
            }
        } catch (\Throwable $e) {
            Log::warning('PixelService server event failed', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }

    // ═══ Meta Pixel ═══════════════════════════════════════════════════

    protected function fbHead(): string
    {
        $id = $this->esc($this->marketing->get('fb_pixel_id', ''));
        if (!$id) return '';
        return <<<HTML
<!-- Meta Pixel -->
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '{$id}');
fbq('track', 'PageView');
</script>

HTML;
    }

    protected function fbNoscript(): string
    {
        $id = $this->esc($this->marketing->get('fb_pixel_id', ''));
        if (!$id) return '';
        return <<<HTML
<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={$id}&ev=PageView&noscript=1"/></noscript>

HTML;
    }

    protected function fbEvent(string $event, array $data): string
    {
        $json = $this->jsData($data);
        return "fbq('track', " . json_encode($event) . ", {$json});";
    }

    protected function sendFbCapi(string $event, array $data): void
    {
        $pixelId = $this->marketing->get('fb_pixel_id', '');
        $token   = $this->marketing->get('fb_conversions_api_token', '');
        if (!$pixelId || !$token) return;

        $payload = [
            'data' => [[
                'event_name'       => $event,
                'event_time'       => time(),
                'action_source'    => 'website',
                'event_source_url' => request()->url(),
                'user_data'        => $this->hashUserData($data),
                'custom_data'      => array_intersect_key($data, array_flip([
                    'value', 'currency', 'content_ids', 'content_type', 'content_name',
                    'contents', 'num_items', 'order_id',
                ])),
            ]],
        ];

        $testCode = $this->marketing->get('fb_test_event_code', '');
        if ($testCode) $payload['test_event_code'] = $testCode;

        Http::timeout(5)
            ->asJson()
            ->post("https://graph.facebook.com/v18.0/{$pixelId}/events?access_token={$token}", $payload);
    }

    /**
     * Hash PII per Meta's CAPI requirements (SHA-256, trimmed lowercase).
     */
    protected function hashUserData(array $data): array
    {
        $hash = fn($v) => $v ? hash('sha256', strtolower(trim((string) $v))) : null;
        $user = array_filter([
            'em'         => $hash($data['email'] ?? null),
            'ph'         => $hash(preg_replace('/[^0-9]/', '', $data['phone'] ?? '')),
            'fn'         => $hash($data['first_name'] ?? null),
            'external_id' => $hash($data['user_id'] ?? null),
            'client_ip_address' => request()->ip(),
            'client_user_agent' => request()->userAgent(),
            'fbc'        => request()->cookie('_fbc'),
            'fbp'        => request()->cookie('_fbp'),
        ]);
        return $user;
    }

    // ═══ Google Analytics 4 ═══════════════════════════════════════════

    protected function ga4Head(): string
    {
        $id = $this->esc($this->marketing->get('ga4_measurement_id', ''));
        if (!$id) return '';
        return <<<HTML
<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id={$id}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '{$id}');
</script>

HTML;
    }

    /**
     * Translate Meta-style event to GA4 event (recommended events).
     */
    protected function ga4Event(string $event, array $data): string
    {
        $map = [
            'PageView'             => 'page_view',
            'ViewContent'          => 'view_item',
            'AddToCart'            => 'add_to_cart',
            'InitiateCheckout'     => 'begin_checkout',
            'AddPaymentInfo'       => 'add_payment_info',
            'Purchase'             => 'purchase',
            'Lead'                 => 'generate_lead',
            'CompleteRegistration' => 'sign_up',
            'Search'               => 'search',
        ];
        $ga = $map[$event] ?? strtolower($event);

        // GA4 ecommerce shape
        $payload = [
            'currency'      => $data['currency'] ?? 'THB',
            'value'         => $data['value'] ?? null,
            'transaction_id' => $data['order_id'] ?? null,
            'items'         => $data['contents'] ?? null,
        ];
        $payload = array_filter($payload, fn($v) => $v !== null);
        $json = json_encode($payload);
        return "if(window.gtag) gtag('event', " . json_encode($ga) . ", {$json});";
    }

    protected function sendGa4Mp(string $event, array $data): void
    {
        $id     = $this->marketing->get('ga4_measurement_id', '');
        $secret = $this->marketing->get('ga4_api_secret', '');
        if (!$id || !$secret) return;

        $clientId = request()->cookie('_ga') ?: bin2hex(random_bytes(8));
        $map = ['Purchase' => 'purchase', 'Lead' => 'generate_lead', 'ViewContent' => 'view_item'];
        $gaEvent = $map[$event] ?? strtolower($event);

        Http::timeout(5)
            ->asJson()
            ->post("https://www.google-analytics.com/mp/collect?measurement_id={$id}&api_secret={$secret}", [
                'client_id' => $clientId,
                'events'    => [[
                    'name'   => $gaEvent,
                    'params' => array_filter([
                        'value'          => $data['value'] ?? null,
                        'currency'       => $data['currency'] ?? 'THB',
                        'transaction_id' => $data['order_id'] ?? null,
                    ], fn($v) => $v !== null),
                ]],
            ]);
    }

    // ═══ Google Tag Manager ═══════════════════════════════════════════

    protected function gtmHead(): string
    {
        $id = $this->esc($this->marketing->get('gtm_container_id', ''));
        if (!$id) return '';
        return <<<HTML
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{$id}');</script>

HTML;
    }

    protected function gtmBody(): string
    {
        $id = $this->esc($this->marketing->get('gtm_container_id', ''));
        if (!$id) return '';
        return <<<HTML
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={$id}"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>

HTML;
    }

    // ═══ Google Ads ═══════════════════════════════════════════════════

    protected function googleAdsHead(): string
    {
        $id = $this->esc($this->marketing->get('google_ads_conversion_id', ''));
        if (!$id) return '';
        return <<<HTML
<!-- Google Ads -->
<script async src="https://www.googletagmanager.com/gtag/js?id={$id}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date()); gtag('config', '{$id}');
</script>

HTML;
    }

    protected function googleAdsConversion(array $data): string
    {
        $id    = $this->esc($this->marketing->get('google_ads_conversion_id', ''));
        $label = $this->esc($this->marketing->get('google_ads_conversion_label', ''));
        if (!$id || !$label) return '';
        $value    = (float) ($data['value'] ?? 0);
        $currency = $this->esc($data['currency'] ?? 'THB');
        $txId     = $this->esc($data['order_id'] ?? '');
        return "if(window.gtag) gtag('event', 'conversion', {"
            . "'send_to': '{$id}/{$label}',"
            . "'value': {$value},"
            . "'currency': '{$currency}',"
            . "'transaction_id': '{$txId}'});";
    }

    // ═══ LINE Tag (LAP) ═══════════════════════════════════════════════

    protected function lineTagHead(): string
    {
        $id = $this->esc($this->marketing->get('line_tag_id', ''));
        if (!$id) return '';
        return <<<HTML
<!-- LINE Tag -->
<script>
(function(g,d,o){g._ltq=g._ltq||[];g._lt=g._lt||function(){g._ltq.push(arguments)};
var h=location.protocol==='https:'?'https://d.line-scdn.net':'http://d.line-cdn.net';
var s=d.createElement('script');s.async=1;s.src=o||h+'/n/line_tag/public/release/v1/lt.js';
var t=d.getElementsByTagName('script')[0];t.parentNode.insertBefore(s,t);})(window,document);
_lt('init',{customerType:'lap',tagId:'{$id}'});
_lt('send','pv',['{$id}']);
</script>

HTML;
    }

    protected function lineTagEvent(string $event, array $data): string
    {
        $id  = $this->esc($this->marketing->get('line_tag_id', ''));
        if (!$id) return '';
        $map = [
            'ViewContent'          => 'ViewPage',
            'AddToCart'            => 'AddToCart',
            'InitiateCheckout'     => 'CheckOut',
            'Purchase'             => 'Conversion',
            'CompleteRegistration' => 'Registration',
            'Lead'                 => 'Registration',
        ];
        $lt = $map[$event] ?? null;
        if (!$lt) return '';
        $value = (float) ($data['value'] ?? 0);
        return "if(window._lt) _lt('send','cv',{type:'{$lt}',value:{$value}},['{$id}']);";
    }

    // ═══ TikTok Pixel ═════════════════════════════════════════════════

    protected function tiktokHead(): string
    {
        $id = $this->esc($this->marketing->get('tiktok_pixel_id', ''));
        if (!$id) return '';
        return <<<HTML
<!-- TikTok Pixel -->
<script>
!function (w, d, t) {
  w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};
  for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e};
  ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{};ttq._i[e]=[];ttq._i[e]._u=i;ttq._t=ttq._t||{};ttq._t[e]=+new Date;ttq._o=ttq._o||{};ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript";o.async=!0;o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};
  ttq.load('{$id}'); ttq.page();
}(window, document, 'ttq');
</script>

HTML;
    }

    protected function tiktokEvent(string $event, array $data): string
    {
        $map = ['ViewContent'=>'ViewContent','AddToCart'=>'AddToCart','InitiateCheckout'=>'InitiateCheckout','Purchase'=>'PlaceAnOrder','CompleteRegistration'=>'CompleteRegistration'];
        $tt = $map[$event] ?? null;
        if (!$tt) return '';
        $json = $this->jsData($data);
        return "if(window.ttq) ttq.track(" . json_encode($tt) . ", {$json});";
    }

    // ═══ Helpers ══════════════════════════════════════════════════════

    protected function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    protected function jsData(array $data): string
    {
        $shape = array_filter([
            'value'         => $data['value'] ?? null,
            'currency'      => $data['currency'] ?? 'THB',
            'content_ids'   => $data['content_ids'] ?? null,
            'content_type'  => $data['content_type'] ?? null,
            'content_name'  => $data['content_name'] ?? null,
            'contents'      => $data['contents'] ?? null,
            'num_items'     => $data['num_items'] ?? null,
        ], fn($v) => $v !== null);
        return json_encode($shape);
    }
}
