<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\DB;

class GeoAccessService
{
    /**
     * Look up geo-location for an IP.
     * Checks geo_ip_cache first (24h TTL). Falls back to ip-api.com.
     *
     * @return array{country_code:string,country_name:string,city:string,region:string,isp:string}|null
     */
    public function lookup(string $ip): ?array
    {
        if ($this->isPrivateIp($ip)) {
            return [
                'country_code' => 'XX',
                'country_name' => 'Private Network',
                'city'         => '',
                'region'       => '',
                'isp'          => '',
            ];
        }

        // Try cache first (24-hour TTL)
        $cutoff = now()->subHours(24)->format('Y-m-d H:i:s');
        $cached = DB::table('geo_ip_cache')
            ->where('ip', $ip)
            ->where('cached_at', '>=', $cutoff)
            ->first();

        if ($cached) {
            return [
                'country_code' => $cached->country_code ?? '',
                'country_name' => $cached->country_name ?? '',
                'city'         => $cached->city         ?? '',
                'region'       => $cached->region       ?? '',
                'isp'          => $cached->isp          ?? '',
            ];
        }

        // Fetch from ip-api.com
        try {
            $url  = 'http://ip-api.com/json/' . rawurlencode($ip)
                  . '?fields=status,countryCode,country,city,regionName,isp';
            $ctx  = stream_context_create([
                'http' => [
                    'timeout'        => 3,
                    'ignore_errors'  => true,
                ],
            ]);
            $raw  = @file_get_contents($url, false, $ctx);
            if ($raw === false) {
                return null;
            }

            $data = json_decode($raw, true);
            if (!$data || ($data['status'] ?? '') !== 'success') {
                return null;
            }

            $result = [
                'country_code' => $data['countryCode'] ?? '',
                'country_name' => $data['country']     ?? '',
                'city'         => $data['city']        ?? '',
                'region'       => $data['regionName']  ?? '',
                'isp'          => $data['isp']         ?? '',
            ];

            // Upsert into cache
            DB::table('geo_ip_cache')->updateOrInsert(
                ['ip' => $ip],
                array_merge(['ip' => $ip], $result, ['cached_at' => now()->format('Y-m-d H:i:s')])
            );

            return $result;

        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Determine whether the given IP is allowed access.
     *
     * Rules (from app_settings):
     *   security_geo_enabled    — '1' to enforce, anything else = always allow
     *   security_geo_mode       — 'allow_all' | 'allow_list' | 'block_list'
     *   security_geo_countries  — comma-separated country codes, e.g. "TH,US,SG"
     */
    public function checkAccess(string $ip): bool
    {
        // If geo access control is not enabled, always allow
        if (AppSetting::get('security_geo_enabled', '0') !== '1') {
            return true;
        }

        // Private/loopback IPs are always allowed
        if ($this->isPrivateIp($ip)) {
            return true;
        }

        $mode = AppSetting::get('security_geo_mode', 'allow_all');

        if ($mode === 'allow_all') {
            return true;
        }

        $countriesRaw = AppSetting::get('security_geo_countries', '');
        $countries    = array_filter(
            array_map('trim', explode(',', strtoupper($countriesRaw)))
        );

        if (empty($countries)) {
            // No list configured — be permissive
            return true;
        }

        $geo         = $this->lookup($ip);
        $countryCode = strtoupper($geo['country_code'] ?? '');

        if ($mode === 'allow_list') {
            return in_array($countryCode, $countries, true);
        }

        if ($mode === 'block_list') {
            return !in_array($countryCode, $countries, true);
        }

        // Unknown mode — default allow
        return true;
    }

    /**
     * Check whether an IP address is a private/reserved range.
     */
    public function isPrivateIp(string $ip): bool
    {
        // Handle IPv6 loopback
        if ($ip === '::1') {
            return true;
        }

        $long = ip2long($ip);
        if ($long === false) {
            // Could be IPv6 — treat as private for safety
            return true;
        }

        $privateRanges = [
            ['10.0.0.0',    '10.255.255.255'],
            ['172.16.0.0',  '172.31.255.255'],
            ['192.168.0.0', '192.168.255.255'],
            ['127.0.0.0',   '127.255.255.255'],
            ['169.254.0.0', '169.254.255.255'], // link-local
            ['100.64.0.0',  '100.127.255.255'], // shared address space
        ];

        foreach ($privateRanges as [$start, $end]) {
            if ($long >= ip2long($start) && $long <= ip2long($end)) {
                return true;
            }
        }

        return false;
    }
}
