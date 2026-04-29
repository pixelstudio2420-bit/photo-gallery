<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\CloudflareCachePurgeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CachePurgeController extends Controller
{
    public function __construct(protected CloudflareCachePurgeService $cf) {}

    public function index()
    {
        $cfg    = $this->cf->config();
        $verify = $this->cf->isEnabled() ? $this->cf->verify() : null;
        $recent = session('cf_purge_history', []);

        return view('admin.cache-purge.index', compact('cfg', 'verify', 'recent'));
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'cloudflare_enabled'   => 'nullable|in:0,1',
            'cloudflare_zone_id'   => 'nullable|string|max:64',
            'cloudflare_api_token' => 'nullable|string|max:200',
            'cloudflare_base_url'  => 'nullable|url|max:255',
        ]);

        AppSetting::set('cloudflare_enabled',   $data['cloudflare_enabled'] ?? '0');
        AppSetting::set('cloudflare_zone_id',   trim((string) ($data['cloudflare_zone_id'] ?? '')));
        // Leave token untouched when the field is empty — lets admins update
        // other fields without re-entering the token each time.
        if (!empty($data['cloudflare_api_token'])) {
            AppSetting::set('cloudflare_api_token', trim($data['cloudflare_api_token']));
        }
        if (!empty($data['cloudflare_base_url'])) {
            AppSetting::set('cloudflare_base_url',  trim($data['cloudflare_base_url']));
        }

        return back()->with('success', 'บันทึกการตั้งค่าแล้ว');
    }

    public function purgeEverything()
    {
        $res = $this->cf->purgeEverything();
        $this->pushHistory('purge_everything', null, $res);
        return back()->with($res['ok'] ? 'success' : 'error',
            $res['ok'] ? 'ล้าง cache ทั้ง zone สำเร็จ' : ('ล้มเหลว: ' . ($res['error'] ?? 'unknown')));
    }

    public function purgeUrls(Request $request)
    {
        $data = $request->validate(['urls' => 'required|string']);
        $urls = collect(preg_split('/\R+/', $data['urls']))
            ->map(fn ($u) => trim($u))
            ->filter()
            ->values()
            ->all();

        $res = $this->cf->purgeFiles($urls);
        $this->pushHistory('purge_urls', $urls, $res);
        return back()->with($res['ok'] ? 'success' : 'error',
            $res['ok']
                ? ('ล้าง ' . count($urls) . ' URLs สำเร็จ')
                : ('ล้มเหลว: ' . (implode(', ', $res['errors'] ?? []) ?: 'unknown')));
    }

    public function purgeHosts(Request $request)
    {
        $data = $request->validate(['hosts' => 'required|string']);
        $hosts = collect(preg_split('/[\s,]+/', $data['hosts']))
            ->map(fn ($h) => trim($h))
            ->filter()
            ->values()
            ->all();

        $res = $this->cf->purgeHosts($hosts);
        $this->pushHistory('purge_hosts', $hosts, $res);
        return back()->with($res['ok'] ? 'success' : 'error',
            $res['ok'] ? 'ล้าง hosts สำเร็จ' : ('ล้มเหลว: ' . ($res['error'] ?? 'unknown')));
    }

    public function purgeTags(Request $request)
    {
        $data = $request->validate(['tags' => 'required|string']);
        $tags = collect(preg_split('/[\s,]+/', $data['tags']))
            ->map(fn ($t) => trim($t))
            ->filter()
            ->values()
            ->all();

        $res = $this->cf->purgeTags($tags);
        $this->pushHistory('purge_tags', $tags, $res);
        return back()->with($res['ok'] ? 'success' : 'error',
            $res['ok'] ? 'ล้าง tags สำเร็จ' : ('ล้มเหลว: ' . ($res['error'] ?? 'unknown')));
    }

    public function verify()
    {
        $res = $this->cf->verify();
        return back()->with($res['ok'] ? 'success' : 'error',
            $res['ok']
                ? ('ตรวจสอบสำเร็จ: ' . ($res['zone_name'] ?? '') . ' · status=' . ($res['zone_status'] ?? ''))
                : ('ตรวจสอบล้มเหลว: ' . ($res['error'] ?? 'unknown')));
    }

    /**
     * Keep last 10 purge attempts in session so admins see recent activity.
     */
    protected function pushHistory(string $kind, $payload, array $result): void
    {
        $entry = [
            'kind'    => $kind,
            'payload' => is_array($payload) ? array_slice($payload, 0, 5) : $payload,
            'ok'      => $result['ok'] ?? false,
            'error'   => $result['error'] ?? null,
            'at'      => now()->toIso8601String(),
        ];
        $hist = session('cf_purge_history', []);
        array_unshift($hist, $entry);
        session()->put('cf_purge_history', array_slice($hist, 0, 10));

        Log::info('cloudflare.purge', $entry);
    }
}
