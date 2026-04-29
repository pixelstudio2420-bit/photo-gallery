<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\AppSetting;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::with(['user', 'event'])
            ->whereIn('status', ['completed', 'paid'])
            ->when($request->q, function ($q, $s) {
                $q->where(function ($sub) use ($s) {
                    $sub->where('id', $s)
                        ->orWhereHas('user', fn($u) => $u->where('first_name', 'ilike', "%{$s}%")
                            ->orWhere('last_name', 'ilike', "%{$s}%")
                            ->orWhere('email', 'ilike', "%{$s}%"));
                });
            })
            ->when($request->period, function ($q, $p) {
                return match ($p) {
                    'today' => $q->whereDate('created_at', today()),
                    'week'  => $q->where('created_at', '>=', now()->subDays(7)),
                    'month' => $q->where('created_at', '>=', now()->subDays(30)),
                    'year'  => $q->where('created_at', '>=', now()->subYear()),
                    default => $q,
                };
            })
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'total_invoices' => Order::whereIn('status', ['completed', 'paid'])->count(),
            'total_revenue'  => Order::whereIn('status', ['completed', 'paid'])->sum('total'),
            'this_month'     => Order::whereIn('status', ['completed', 'paid'])->where('created_at', '>=', now()->startOfMonth())->sum('total'),
            'today'          => Order::whereIn('status', ['completed', 'paid'])->whereDate('created_at', today())->sum('total'),
        ];

        return view('admin.invoices.index', compact('orders', 'stats'));
    }

    public function show(Order $order)
    {
        $order->load(['user', 'event', 'items']);

        $settings = [
            'company_name'    => AppSetting::get('company_name') ?: (AppSetting::get('site_name') ?: (string) config('app.name', 'Photo Gallery')),
            'company_address' => AppSetting::get('company_address', ''),
            'company_phone'   => AppSetting::get('company_phone', ''),
            'company_email'   => AppSetting::get('company_email', ''),
            'company_tax_id'  => AppSetting::get('company_tax_id', ''),
            'vat_enabled'     => AppSetting::get('vat_enabled', '0') === '1',
            'vat_rate'        => (float) AppSetting::get('vat_rate', '7'),
        ];

        return view('admin.invoices.show', compact('order', 'settings'));
    }
}
