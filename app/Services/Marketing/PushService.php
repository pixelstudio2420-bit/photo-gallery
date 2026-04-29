<?php

namespace App\Services\Marketing;

use App\Models\AppSetting;
use App\Models\Marketing\PushCampaign;
use App\Models\Marketing\PushSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Web Push (VAPID) Service.
 *
 * Uses minishlink/web-push if installed; otherwise falls back to a
 * mark-as-sent pseudo-mode (counts but does not actually deliver).
 * Real delivery requires:
 *   composer require minishlink/web-push
 *
 * All methods short-circuit to false when marketing_push_enabled=0.
 */
class PushService
{
    public function __construct(protected MarketingService $marketing)
    {
    }

    public function enabled(): bool
    {
        return $this->marketing->enabled('push');
    }

    public function hasLibrary(): bool
    {
        return class_exists(\Minishlink\WebPush\WebPush::class);
    }

    public function publicVapidKey(): string
    {
        return (string) AppSetting::get('marketing_push_vapid_public', '');
    }

    public function privateVapidKey(): string
    {
        return (string) AppSetting::get('marketing_push_vapid_private', '');
    }

    public function subject(): string
    {
        return (string) AppSetting::get('marketing_push_vapid_subject', 'mailto:admin@example.com');
    }

    /**
     * Generate VAPID keys using native openssl if no library.
     * Returns [public_b64url, private_b64url].
     */
    public function generateVapidKeys(): array
    {
        if ($this->hasLibrary()) {
            $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
            return [$keys['publicKey'], $keys['privateKey']];
        }

        // Native fallback: prime256v1 curve (P-256)
        $pkey = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if (! $pkey) {
            throw new \RuntimeException('Cannot generate EC keys — openssl EC support missing.');
        }

        $details = openssl_pkey_get_details($pkey);
        $x = str_pad($details['ec']['x'] ?? '', 32, "\0", STR_PAD_LEFT);
        $y = str_pad($details['ec']['y'] ?? '', 32, "\0", STR_PAD_LEFT);
        $d = str_pad($details['ec']['d'] ?? '', 32, "\0", STR_PAD_LEFT);

        $public  = "\x04" . $x . $y; // uncompressed point
        $private = $d;

        return [$this->b64urlEncode($public), $this->b64urlEncode($private)];
    }

    /**
     * Store (or refresh) a browser subscription.
     */
    public function subscribe(array $sub, ?int $userId = null, ?string $locale = null, ?string $ua = null): PushSubscription
    {
        $model = PushSubscription::updateOrCreate(
            ['endpoint' => $sub['endpoint']],
            [
                'p256dh'       => $sub['keys']['p256dh'] ?? '',
                'auth'         => $sub['keys']['auth'] ?? '',
                'user_id'      => $userId,
                'locale'       => $locale,
                'ua'           => $ua,
                'status'       => 'active',
                'last_seen_at' => now(),
            ]
        );
        return $model;
    }

    public function unsubscribe(string $endpoint): void
    {
        PushSubscription::where('endpoint', $endpoint)->update(['status' => 'revoked']);
    }

    /**
     * Send a push campaign. Returns [targets, sent, failed].
     */
    public function send(PushCampaign $campaign): array
    {
        if (! $this->enabled()) {
            $campaign->update(['status' => 'failed']);
            return ['targets' => 0, 'sent' => 0, 'failed' => 0];
        }

        $query = PushSubscription::active();
        switch ($campaign->segment) {
            case 'users':
                $query->whereNotNull('user_id'); break;
            case 'guests':
                $query->whereNull('user_id'); break;
            case 'tag':
                if ($campaign->segment_value) {
                    $query->whereJsonContains('tags', $campaign->segment_value);
                }
                break;
            // 'all' = no filter
        }

        $subs = $query->get();
        $campaign->update([
            'status'  => 'sending',
            'targets' => $subs->count(),
        ]);

        $payload = json_encode([
            'title'     => $campaign->title,
            'body'      => $campaign->body,
            'icon'      => $campaign->icon ?: asset('favicon.ico'),
            'click_url' => $campaign->click_url ?: url('/'),
            'cid'       => $campaign->id,
        ]);

        $sent = 0;
        $failed = 0;

        if ($this->hasLibrary() && $this->publicVapidKey() && $this->privateVapidKey()) {
            try {
                $auth = [
                    'VAPID' => [
                        'subject'    => $this->subject(),
                        'publicKey'  => $this->publicVapidKey(),
                        'privateKey' => $this->privateVapidKey(),
                    ],
                ];
                $webPush = new \Minishlink\WebPush\WebPush($auth);

                foreach ($subs as $s) {
                    $wpSub = \Minishlink\WebPush\Subscription::create($s->toWebPushArray());
                    $webPush->queueNotification($wpSub, $payload);
                }

                foreach ($webPush->flush() as $report) {
                    $endpoint = method_exists($report, 'getEndpoint') ? $report->getEndpoint() : null;
                    if ($report->isSuccess()) {
                        $sent++;
                    } else {
                        $failed++;
                        if ($endpoint) {
                            // 404/410 = gone — mark stale
                            $reason = method_exists($report, 'getReason') ? (string) $report->getReason() : '';
                            if (str_contains($reason, '410') || str_contains($reason, '404')) {
                                PushSubscription::where('endpoint', $endpoint)->update(['status' => 'stale']);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Push send failed', ['cid' => $campaign->id, 'err' => $e->getMessage()]);
                $campaign->update(['status' => 'failed']);
                return ['targets' => $subs->count(), 'sent' => 0, 'failed' => $subs->count()];
            }
        } else {
            // Fallback — mark delivery attempt without actually pushing.
            // Useful in dev when minishlink/web-push isn't installed.
            //
            // BEFORE: this branch reported `sent = subs->count()` which
            // misled admins into thinking notifications had been
            // delivered when no actual push had fired. Now we mark
            // the campaign with status='draft_stub' (a no-op outcome)
            // and surface the true situation to the dashboard.
            Log::warning('Push send: web-push library not installed; campaign NOT delivered', [
                'cid' => $campaign->id,
                'targets' => $subs->count(),
                'hint' => 'composer require minishlink/web-push',
            ]);
            $campaign->update([
                'status'  => 'failed',
                'sent'    => 0,
                'failed'  => $subs->count(),
                'sent_at' => now(),
            ]);
            return [
                'targets' => $subs->count(),
                'sent'    => 0,
                'failed'  => $subs->count(),
                'stub'    => true,
                'error'   => 'web-push library not installed (run composer require minishlink/web-push)',
            ];
        }

        $campaign->update([
            'status'  => 'sent',
            'sent'    => $sent,
            'failed'  => $failed,
            'sent_at' => now(),
        ]);

        return ['targets' => $subs->count(), 'sent' => $sent, 'failed' => $failed];
    }

    public function recordClick(int $campaignId): void
    {
        PushCampaign::where('id', $campaignId)->increment('clicks');
    }

    public function summary(): array
    {
        return [
            'subscribers' => PushSubscription::active()->count(),
            'stale'       => PushSubscription::where('status', 'stale')->count(),
            'revoked'     => PushSubscription::where('status', 'revoked')->count(),
            'campaigns'   => PushCampaign::count(),
            'total_sent'  => (int) PushCampaign::sum('sent'),
            'total_clicks'=> (int) PushCampaign::sum('clicks'),
        ];
    }

    protected function b64urlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
