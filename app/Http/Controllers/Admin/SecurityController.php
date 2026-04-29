<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\SecurityScannerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SecurityController extends Controller
{
    public function __construct(
        private SecurityScannerService $scanner
    ) {}

    /**
     * Show the security dashboard.
     */
    public function dashboard()
    {
        // Load cached scan result
        $scanJson   = AppSetting::get('security_scan_result');
        $scanResult = $scanJson ? json_decode($scanJson, true) : null;

        // Recent security logs (last 50)
        $securityLogs = DB::table('security_logs')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        // Blocked IPs
        $blockedIps = DB::table('security_ip_rules')
            ->where('rule_type', 'blacklist')
            ->orderByDesc('created_at')
            ->get();

        // Recent threat incidents
        $threatIncidents = DB::table('threat_incidents')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('admin.security.dashboard', compact(
            'scanResult',
            'securityLogs',
            'blockedIps',
            'threatIncidents'
        ));
    }

    /**
     * Run the full security scan and cache the result.
     */
    public function scan(Request $request)
    {
        $this->scanner->runFullScan();

        return redirect()->route('admin.security.dashboard')
            ->with('success', 'Security scan completed.');
    }

    /**
     * Block an IP address.
     */
    public function blockIp(Request $request)
    {
        $request->validate([
            'ip'     => 'required|ip',
            'reason' => 'nullable|string|max:255',
        ]);

        $exists = DB::table('security_ip_rules')
            ->where('ip', $request->ip_address ?? $request->input('ip'))
            ->where('rule_type', 'blacklist')
            ->exists();

        if (!$exists) {
            DB::table('security_ip_rules')->insert([
                'ip'         => $request->input('ip'),
                'rule_type'  => 'blacklist',
                'reason'     => $request->input('reason', 'Manually blocked by admin'),
                'expires_at' => null,
                'created_at' => now(),
            ]);
        }

        return redirect()->route('admin.security.dashboard')
            ->with('success', 'IP ' . $request->input('ip') . ' has been blocked.');
    }

    /**
     * Remove an IP from the block list.
     */
    public function unblockIp(Request $request)
    {
        $request->validate([
            'ip' => 'required|ip',
        ]);

        DB::table('security_ip_rules')
            ->where('ip', $request->input('ip'))
            ->where('rule_type', 'blacklist')
            ->delete();

        return redirect()->route('admin.security.dashboard')
            ->with('success', 'IP ' . $request->input('ip') . ' has been unblocked.');
    }
}
