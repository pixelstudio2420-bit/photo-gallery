<?php

namespace App\Services\Marketing;

use App\Models\AppSetting;
use App\Models\Marketing\Campaign;
use App\Models\Marketing\CampaignRecipient;
use App\Models\Marketing\Subscriber;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Email campaign orchestration.
 *
 * Segments:
 *   - all              → all confirmed subscribers
 *   - subscribers      → alias of 'all'
 *   - vip              → loyalty tier gold+ / top spenders
 *   - dormant          → no orders in 90+ days
 *   - tag              → subscribers with specific tag (segment.value = tag name)
 *   - users            → all registered users with email
 */
class CampaignService
{
    public function __construct(protected MarketingService $marketing) {}

    public function enabled(): bool
    {
        return $this->marketing->enabled('email_campaigns');
    }

    // ── CRUD ─────────────────────────────────────────────────

    public function create(array $data, ?int $adminId = null): Campaign
    {
        return Campaign::create([
            'name'          => $data['name'],
            'subject'       => $data['subject'],
            'channel'       => $data['channel'] ?? 'email',
            'body_markdown' => $data['body_markdown'] ?? null,
            'body_html'     => $data['body_html'] ?? null,
            'segment'       => $data['segment'] ?? ['type' => 'all'],
            'status'        => $data['status'] ?? 'draft',
            'scheduled_at'  => $data['scheduled_at'] ?? null,
            'created_by'    => $adminId,
        ]);
    }

    public function update(Campaign $campaign, array $data): Campaign
    {
        $campaign->fill([
            'name'          => $data['name']          ?? $campaign->name,
            'subject'       => $data['subject']       ?? $campaign->subject,
            'body_markdown' => $data['body_markdown'] ?? $campaign->body_markdown,
            'body_html'     => $data['body_html']     ?? $campaign->body_html,
            'segment'       => $data['segment']       ?? $campaign->segment,
            'scheduled_at'  => $data['scheduled_at']  ?? $campaign->scheduled_at,
        ]);
        $campaign->save();
        return $campaign;
    }

    // ── Segment resolution ───────────────────────────────────

    /**
     * Resolve a segment definition to an iterable of recipients.
     * Returns array of ['email', 'name', 'user_id', 'subscriber_id'].
     */
    public function resolveRecipients(Campaign $campaign): array
    {
        $segment = $campaign->segment ?? ['type' => 'all'];
        $type    = $segment['type'] ?? 'all';
        $value   = $segment['value'] ?? null;

        return match ($type) {
            'vip'       => $this->vipRecipients(),
            'dormant'   => $this->dormantRecipients(),
            'tag'       => $this->tagRecipients((string) $value),
            'users'     => $this->userRecipients(),
            default     => $this->allSubscribers(),
        };
    }

    protected function allSubscribers(): array
    {
        return Subscriber::confirmed()->get()->map(fn($s) => [
            'email'         => $s->email,
            'name'          => $s->name,
            'user_id'       => $s->user_id,
            'subscriber_id' => $s->id,
        ])->all();
    }

    protected function userRecipients(): array
    {
        return User::whereNotNull('email')->get()->map(fn($u) => [
            'email'         => $u->email,
            'name'          => $u->name ?? null,
            'user_id'       => $u->id,
            'subscriber_id' => null,
        ])->all();
    }

    protected function vipRecipients(): array
    {
        // Users in loyalty tier gold+ AND who have confirmed subscriber
        $userIds = DB::table('marketing_loyalty_accounts')
            ->whereIn('tier', ['gold', 'platinum'])
            ->pluck('user_id')
            ->all();
        if (empty($userIds)) return [];
        return Subscriber::confirmed()->whereIn('user_id', $userIds)->get()->map(fn($s) => [
            'email'         => $s->email,
            'name'          => $s->name,
            'user_id'       => $s->user_id,
            'subscriber_id' => $s->id,
        ])->all();
    }

    protected function dormantRecipients(): array
    {
        // Confirmed subscribers whose linked user hasn't placed order in 90 days
        $cutoff = now()->subDays(90);
        $activeUserIds = DB::table('orders')->where('created_at', '>=', $cutoff)->pluck('user_id')->unique()->all();
        $query = Subscriber::confirmed();
        if (!empty($activeUserIds)) {
            $query->whereNotIn('user_id', $activeUserIds);
        }
        return $query->get()->map(fn($s) => [
            'email'         => $s->email,
            'name'          => $s->name,
            'user_id'       => $s->user_id,
            'subscriber_id' => $s->id,
        ])->all();
    }

    protected function tagRecipients(string $tag): array
    {
        return Subscriber::confirmed()->hasTag($tag)->get()->map(fn($s) => [
            'email'         => $s->email,
            'name'          => $s->name,
            'user_id'       => $s->user_id,
            'subscriber_id' => $s->id,
        ])->all();
    }

    // ── Send / dispatch ──────────────────────────────────────

    /**
     * Send the campaign immediately (synchronous; for small lists).
     * For large lists, recommend queueing from a Job.
     *
     * @return array{ok: bool, sent: int, failed: int}
     */
    public function send(Campaign $campaign): array
    {
        if (!$this->enabled()) {
            return ['ok' => false, 'sent' => 0, 'failed' => 0, 'message' => 'Email campaigns ปิดอยู่'];
        }
        if (!in_array($campaign->status, ['draft', 'scheduled'])) {
            return ['ok' => false, 'sent' => 0, 'failed' => 0, 'message' => 'Campaign นี้ส่งไปแล้วหรือถูกยกเลิก'];
        }

        $recipients = $this->resolveRecipients($campaign);
        $campaign->update(['status' => 'sending', 'total_recipients' => count($recipients)]);

        $from = $this->fromAddress();
        $sent = 0;
        $failed = 0;

        foreach ($recipients as $r) {
            try {
                $rec = CampaignRecipient::create([
                    'campaign_id'    => $campaign->id,
                    'email'          => $r['email'],
                    'user_id'        => $r['user_id'],
                    'subscriber_id'  => $r['subscriber_id'],
                    'tracking_token' => CampaignRecipient::newTrackingToken(),
                    'status'         => 'queued',
                ]);
                $html = $this->renderBodyForRecipient($campaign, $rec);
                Mail::html($html, function ($m) use ($r, $from, $campaign) {
                    $m->to($r['email'], $r['name'] ?? null)
                      ->from($from['address'], $from['name'])
                      ->subject($campaign->subject);
                });
                $rec->update(['status' => 'sent', 'sent_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('campaign.send_failed', ['campaign' => $campaign->id, 'email' => $r['email'], 'err' => $e->getMessage()]);
                if (isset($rec)) {
                    $rec->update(['status' => 'failed', 'error' => substr($e->getMessage(), 0, 500)]);
                }
                $failed++;
            }
        }

        $campaign->update([
            'status'     => 'sent',
            'sent_at'    => now(),
            'sent_count' => $sent,
        ]);

        return ['ok' => true, 'sent' => $sent, 'failed' => $failed];
    }

    public function cancel(Campaign $campaign): bool
    {
        if ($campaign->status === 'sent') return false;
        $campaign->update(['status' => 'cancelled']);
        return true;
    }

    // ── Helpers ──────────────────────────────────────────────

    protected function renderBodyForRecipient(Campaign $campaign, CampaignRecipient $rec): string
    {
        $body = $campaign->body_html ?: $this->markdownToHtml($campaign->body_markdown ?? '');
        $name = e($rec->email);

        // Substitute placeholders
        $body = str_replace(
            ['{{email}}', '{{name}}'],
            [e($rec->email), e($rec->email)],
            $body
        );

        $unsubUrl = route('newsletter.unsubscribe', ['email' => $rec->email]);
        $trackOpen = route('marketing.track.open', ['token' => $rec->tracking_token]);
        $unsubText = AppSetting::get('marketing_email_unsubscribe_text', 'ยกเลิกการรับอีเมล');

        $footer = <<<HTML
<hr style="border:0;border-top:1px solid #eee;margin:24px 0">
<p style="font-size:12px;color:#999;text-align:center">
    <a href="{$unsubUrl}" style="color:#999">{$unsubText}</a>
</p>
<img src="{$trackOpen}" width="1" height="1" alt="" style="display:block">
HTML;
        return '<div style="font-family:Arial,sans-serif;max-width:620px;margin:0 auto;padding:24px">'
             . $body . $footer . '</div>';
    }

    protected function markdownToHtml(string $md): string
    {
        // Extremely light markdown-ish: bold, line breaks, links.
        $html = e($md);
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\[(.+?)\]\((https?:\/\/[^\s)]+)\)/', '<a href="$2">$1</a>', $html);
        $html = nl2br($html);
        return $html;
    }

    protected function fromAddress(): array
    {
        return [
            'address' => AppSetting::get('marketing_email_from_address', config('mail.from.address'))
                         ?: config('mail.from.address'),
            'name'    => AppSetting::get('marketing_email_from_name', config('mail.from.name'))
                         ?: (config('mail.from.name') ?? config('app.name')),
        ];
    }

    // ── Tracking pings ──────────────────────────────────────

    public function trackOpen(string $token): void
    {
        $rec = CampaignRecipient::where('tracking_token', $token)->first();
        if (!$rec || $rec->opened_at) return;
        $rec->update(['status' => 'opened', 'opened_at' => now()]);
        Campaign::where('id', $rec->campaign_id)->increment('open_count');
    }

    public function trackClick(string $token): void
    {
        $rec = CampaignRecipient::where('tracking_token', $token)->first();
        if (!$rec) return;
        if (!$rec->clicked_at) {
            $rec->update(['status' => 'clicked', 'clicked_at' => now()]);
            Campaign::where('id', $rec->campaign_id)->increment('click_count');
        }
    }
}
