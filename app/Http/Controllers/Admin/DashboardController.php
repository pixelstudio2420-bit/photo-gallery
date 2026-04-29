<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardAggregationService;
use App\Services\UserPresenceService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly UserPresenceService $presence,
        private readonly DashboardAggregationService $aggregation,
    ) {}

    public function index()
    {
        $stats           = $this->aggregation->coreStats();
        $digitalStats    = $this->aggregation->digitalStats();
        $commissionStats = $this->aggregation->commissionStats();

        $combinedTodayRevenue = (float) $stats['today_revenue'] + (float) $digitalStats['today_revenue'];
        $combinedMonthRevenue = (float) $stats['month_revenue'] + (float) $digitalStats['month_revenue'];
        $combinedTotalRevenue = (float) $stats['total_revenue'] + (float) $digitalStats['total_revenue'];

        $onlineUsers = $this->presence->getOnlineUsers();

        return view('admin.dashboard', [
            'stats'                  => $stats,
            'pendingRefunds'         => $this->aggregation->pendingRefunds(),
            'platformCommission'     => $this->aggregation->platformCommissionRate(),
            'commissionStats'        => $commissionStats,
            'digitalStats'           => $digitalStats,
            'topPhotographerPayouts' => $this->aggregation->topPhotographerPayouts(),
            'chartData'              => $this->aggregation->revenueChart(14),
            'photoDaily'             => $this->aggregation->photoSparkline(7),
            'digitalDaily'           => $this->aggregation->digitalSparkline(7),
            'pendingSlips'           => $this->aggregation->pendingSlips(),
            'pendingDigitalOrders'   => $this->aggregation->pendingDigitalOrders(),
            'latestOrders'           => $this->aggregation->latestOrders(),
            'topEvents'              => $this->aggregation->topEvents(),
            'latestUsers'            => $this->aggregation->latestUsers(),
            'combinedTodayRevenue'   => $combinedTodayRevenue,
            'combinedMonthRevenue'   => $combinedMonthRevenue,
            'combinedTotalRevenue'   => $combinedTotalRevenue,
            'onlineUsers'            => $onlineUsers,
            'onlineCount'            => $onlineUsers->count(),
        ]);
    }

    public function onlineUsers()
    {
        $onlineUsers = $this->presence->getOnlineUsers();

        return view('admin.online-users', [
            'onlineUsers' => $onlineUsers,
            'onlineCount' => $onlineUsers->count(),
        ]);
    }

    public function onlineUsersApi(): JsonResponse
    {
        $users = $this->presence->getOnlineUsers()->map(fn ($u) => [
            'id'            => $u->user_id,
            'name'          => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')),
            'email'         => $u->email ?? '',
            'device'        => $u->device_type ?? 'desktop',
            'browser'       => $u->browser ?? 'Unknown',
            'os'            => $u->os ?? 'Unknown',
            'ip_address'    => $u->ip_address ?? '',
            'last_activity' => $u->last_activity,
        ]);

        return response()->json([
            'count' => $users->count(),
            'users' => $users->values(),
        ]);
    }
}
