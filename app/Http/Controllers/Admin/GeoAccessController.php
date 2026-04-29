<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\GeoAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GeoAccessController extends Controller
{
    public function __construct(
        private GeoAccessService $geo
    ) {}

    /**
     * Show the geo-access settings dashboard.
     */
    public function index()
    {
        // Current settings
        $enabled    = AppSetting::get('security_geo_enabled', '0') === '1';
        $mode       = AppSetting::get('security_geo_mode', 'allow_all');
        $countriesRaw = AppSetting::get('security_geo_countries', '');
        $countries  = array_values(array_filter(
            array_map('trim', explode(',', strtoupper($countriesRaw)))
        ));

        // Cache stats
        $cacheStats = [
            'total_entries' => DB::table('geo_ip_cache')->count(),
            'fresh_entries' => DB::table('geo_ip_cache')
                ->where('cached_at', '>=', now()->subDay())
                ->count(),
            'stale_entries' => DB::table('geo_ip_cache')
                ->where('cached_at', '<', now()->subDays(7))
                ->count(),
        ];

        // Top countries seen recently
        $topCountries = DB::table('geo_ip_cache')
            ->where('cached_at', '>=', now()->subDays(7))
            ->whereNotNull('country_code')
            ->where('country_code', '!=', '')
            ->selectRaw('country_code, country_name, COUNT(*) as ip_count')
            ->groupBy('country_code', 'country_name')
            ->orderByDesc('ip_count')
            ->limit(20)
            ->get();

        // Recent lookups (last 50)
        $recent = DB::table('geo_ip_cache')
            ->orderByDesc('cached_at')
            ->limit(50)
            ->get();

        return view('admin.security.geo-access', compact(
            'enabled',
            'mode',
            'countries',
            'countriesRaw',
            'cacheStats',
            'topCountries',
            'recent'
        ));
    }

    /**
     * Update geo-access settings.
     */
    public function update(Request $request)
    {
        $request->validate([
            'enabled'   => 'nullable',
            'mode'      => 'required|in:allow_all,allow_list,block_list',
            'countries' => 'nullable|string|max:500',
        ]);

        AppSetting::set('security_geo_enabled', $request->boolean('enabled') ? '1' : '0');
        AppSetting::set('security_geo_mode',    $request->input('mode'));

        // Normalize: uppercase, trim, dedupe ISO codes (2 chars) only
        $codes = array_filter(
            array_unique(array_map('trim', explode(',', strtoupper($request->input('countries', ''))))),
            fn($c) => strlen($c) === 2 && ctype_alpha($c)
        );
        AppSetting::set('security_geo_countries', implode(',', $codes));

        return back()->with('success', 'อัปเดตการตั้งค่า Geo Access เรียบร้อย');
    }

    /**
     * Look up an arbitrary IP — admin diagnostic tool.
     */
    public function lookup(Request $request)
    {
        $request->validate([
            'ip' => 'required|ip',
        ]);

        $ip     = $request->input('ip');
        $result = $this->geo->lookup($ip);
        $allowed = $this->geo->checkAccess($ip);

        return back()
            ->with('lookup_result', [
                'ip'      => $ip,
                'data'    => $result,
                'allowed' => $allowed,
            ])
            ->withInput();
    }

    /**
     * Purge stale geo-ip cache rows on demand.
     */
    public function purgeCache(Request $request)
    {
        $days  = (int) $request->input('days', 7);
        $days  = max(1, min(365, $days));

        $deleted = DB::table('geo_ip_cache')
            ->where('cached_at', '<', now()->subDays($days))
            ->delete();

        return back()->with('success', "ลบ geo cache {$deleted} รายการ (เก่ากว่า {$days} วัน)");
    }
}
