<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiKeyController extends Controller
{
    public function index(Request $request)
    {
        $query = ApiKey::orderByDesc('created_at');

        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'ilike', "%{$request->q}%")
                  ->orWhere('key_prefix', 'ilike', "%{$request->q}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'revoked') {
                $query->where('is_active', false);
            } elseif ($request->status === 'expired') {
                $query->whereNotNull('expires_at')->where('expires_at', '<', now());
            }
        }

        $keys = $query->paginate(20)->withQueryString();

        $stats = [
            'total'         => ApiKey::count(),
            'active'        => ApiKey::where('is_active', true)->count(),
            'expiring_soon' => ApiKey::expiringSoon(7)->count(),
            'total_usage'   => ApiKey::sum('usage_count'),
        ];

        return view('admin.api-keys.index', compact('keys', 'stats'));
    }

    public function create()
    {
        return view('admin.api-keys.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                  => 'required|string|max:100',
            'scopes'                => 'nullable|array',
            'scopes.*'              => 'string',
            'allowed_ips'           => 'nullable|string',
            'rate_limit_per_minute' => 'nullable|integer|min:1|max:1000',
            'expires_at'            => 'nullable|date|after:now',
        ]);

        $expiresAt = $validated['expires_at'] ? \Carbon\Carbon::parse($validated['expires_at']) : null;

        [$plainKey, $apiKey] = ApiKey::generate(
            $validated['name'],
            Auth::guard('admin')->id(),
            $validated['scopes'] ?? [],
            $expiresAt
        );

        // Additional fields
        if (!empty($validated['allowed_ips'])) {
            $ips = array_filter(array_map('trim', explode(',', $validated['allowed_ips'])));
            $apiKey->update(['allowed_ips' => $ips]);
        }

        if (!empty($validated['rate_limit_per_minute'])) {
            $apiKey->update(['rate_limit_per_minute' => $validated['rate_limit_per_minute']]);
        }

        // Flash the plain key (only shown once)
        session()->flash('new_api_key', $plainKey);
        session()->flash('new_api_key_name', $apiKey->name);

        return redirect()->route('admin.api-keys.index')
            ->with('success', 'สร้าง API Key เรียบร้อย — กรุณาคัดลอกคีย์ด้านบน (จะไม่แสดงอีก)');
    }

    public function show(ApiKey $apiKey)
    {
        return view('admin.api-keys.show', compact('apiKey'));
    }

    public function revoke(ApiKey $apiKey)
    {
        $apiKey->revoke();
        return back()->with('success', "ยกเลิก API Key \"{$apiKey->name}\" เรียบร้อย");
    }

    public function reactivate(ApiKey $apiKey)
    {
        $apiKey->update(['is_active' => true]);
        return back()->with('success', "เปิดใช้งาน API Key \"{$apiKey->name}\" เรียบร้อย");
    }

    public function destroy(ApiKey $apiKey)
    {
        $name = $apiKey->name;
        $apiKey->delete();
        return back()->with('success', "ลบ API Key \"{$name}\" เรียบร้อย");
    }
}
