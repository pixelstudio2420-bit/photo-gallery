<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\PhotographerApiKey;
use App\Models\PhotographerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Public photographer API — v1.
 *
 * All endpoints in this controller assume `photographer.api` middleware
 * has already authenticated the request via Bearer token. The middleware
 * stashes the matched API key + photographer profile on the request:
 *
 *   $request->attributes->get('api_key')              // PhotographerApiKey
 *   $request->attributes->get('photographer_profile') // PhotographerProfile
 *
 * Every endpoint enforces ownership — a photographer can only ever read
 * their own events / photos / orders. Cross-tenant data leakage is
 * impossible because every query starts with `where photographer_id = $profile->user_id`.
 *
 * Scope checks (events:read, photos:read, orders:read, stats:read) run
 * via `requireScope()` so a partially-scoped key (e.g. read-only events)
 * gets a 403 instead of silently exposing data.
 *
 * Response envelope:
 *   { "success": bool, "data": ..., "meta": { "page": int, "per_page": int, "total": int } }
 */
class PhotographerApiController extends Controller
{
    /* ════════════════════════════════════════════════════════════
     *  GET /api/v1/photographer/me
     *  Scope: (none — implied by valid token)
     * ════════════════════════════════════════════════════════════ */
    public function me(Request $request): JsonResponse
    {
        $profile = $this->profile($request);
        $key     = $this->key($request);

        return $this->ok([
            'photographer_id'      => $profile->user_id,
            'display_name'         => $profile->display_name,
            'plan'                 => $profile->subscription_plan_code,
            'commission_rate'      => (float) ($profile->commission_rate ?? 0),
            'storage_used_bytes'   => (int) $profile->storage_used_bytes,
            'storage_quota_bytes'  => (int) $profile->storage_quota_bytes,
            'storage_used_pct'     => $profile->storage_quota_bytes > 0
                ? round(($profile->storage_used_bytes / $profile->storage_quota_bytes) * 100, 2)
                : 0,
            'api_key' => [
                'label'        => $key->label,
                'token_prefix' => $key->token_prefix,
                'scopes'       => $key->scopeList(),
                'last_used_at' => optional($key->last_used_at)->toIso8601String(),
            ],
        ]);
    }

    /* ════════════════════════════════════════════════════════════
     *  GET /api/v1/photographer/events
     *  Scope: events:read
     *  Query params: limit (1-100, default 50), page (default 1),
     *                status (active|published|draft), q (search title)
     * ════════════════════════════════════════════════════════════ */
    public function events(Request $request): JsonResponse
    {
        $this->requireScope($request, 'events:read');
        $profile = $this->profile($request);

        $perPage = (int) max(1, min(100, $request->query('limit', 50)));
        $page    = (int) max(1, $request->query('page', 1));

        $query = Event::where('photographer_id', $profile->user_id)
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('q'), fn ($q, $v) =>
                $q->where(fn ($x) => $x->where('name', 'ILIKE', '%'.$v.'%')->orWhere('slug', 'ILIKE', '%'.$v.'%'))
            )
            ->orderByDesc('created_at');

        $total = (clone $query)->count();
        $events = $query->skip(($page - 1) * $perPage)->take($perPage)
            ->get(['id', 'name', 'slug', 'status', 'visibility', 'price_per_photo', 'shoot_date', 'view_count', 'created_at', 'updated_at']);

        // Bulk-load photo counts per event so we don't N+1 in the UI.
        // Single grouped count query keyed by event_id.
        $photoCounts = $events->isNotEmpty()
            ? DB::table('event_photos')
                ->whereIn('event_id', $events->pluck('id'))
                ->selectRaw('event_id, COUNT(*) as cnt')
                ->groupBy('event_id')
                ->pluck('cnt', 'event_id')
            : collect();

        $events = $events->map(function ($e) use ($photoCounts) {
            $arr = $e->toArray();
            $arr['photo_count'] = (int) ($photoCounts[$e->id] ?? 0);
            return $arr;
        });

        return $this->ok($events, [
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
        ]);
    }

    /* ════════════════════════════════════════════════════════════
     *  GET /api/v1/photographer/events/{event}
     *  Scope: events:read
     * ════════════════════════════════════════════════════════════ */
    public function eventShow(Request $request, int $event): JsonResponse
    {
        $this->requireScope($request, 'events:read');
        $profile = $this->profile($request);

        $ev = Event::where('id', $event)
            ->where('photographer_id', $profile->user_id)
            ->first();
        if (!$ev) return $this->notFound('event_not_found');

        $photoCount = (int) DB::table('event_photos')->where('event_id', $ev->id)->count();

        return $this->ok([
            'id'              => $ev->id,
            'name'            => $ev->name,
            'slug'            => $ev->slug,
            'description'     => $ev->description,
            'status'          => $ev->status,
            'visibility'      => $ev->visibility,
            'price_per_photo' => (float) ($ev->price_per_photo ?? 0),
            'shoot_date'      => optional($ev->shoot_date)->toIso8601String(),
            'view_count'      => (int) ($ev->view_count ?? 0),
            'photo_count'     => $photoCount,
            'created_at'      => optional($ev->created_at)->toIso8601String(),
            'updated_at'      => optional($ev->updated_at)->toIso8601String(),
        ]);
    }

    /* ════════════════════════════════════════════════════════════
     *  GET /api/v1/photographer/events/{event}/photos
     *  Scope: photos:read
     *  Query params: limit (1-500, default 100), page (default 1)
     * ════════════════════════════════════════════════════════════ */
    public function eventPhotos(Request $request, int $event): JsonResponse
    {
        $this->requireScope($request, 'photos:read');
        $profile = $this->profile($request);

        // Verify ownership before listing photos
        $owns = Event::where('id', $event)->where('photographer_id', $profile->user_id)->exists();
        if (!$owns) return $this->notFound('event_not_found');

        $perPage = (int) max(1, min(500, $request->query('limit', 100)));
        $page    = (int) max(1, $request->query('page', 1));

        $query = EventPhoto::where('event_id', $event)->orderBy('sort_order');
        $total = (clone $query)->count();

        $photos = $query->skip(($page - 1) * $perPage)->take($perPage)
            ->get(['id', 'event_id', 'filename', 'file_size', 'width', 'height',
                   'quality_score', 'best_shot_score', 'face_count', 'ai_tags',
                   'caption', 'sort_order', 'created_at']);

        return $this->ok($photos, [
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
        ]);
    }

    /* ════════════════════════════════════════════════════════════
     *  GET /api/v1/photographer/photos/{photo}
     *  Scope: photos:read
     * ════════════════════════════════════════════════════════════ */
    public function photoShow(Request $request, int $photo): JsonResponse
    {
        $this->requireScope($request, 'photos:read');
        $profile = $this->profile($request);

        // Verify the photo belongs to one of this photographer's events
        $row = EventPhoto::where('event_photos.id', $photo)
            ->join('event_events', 'event_events.id', '=', 'event_photos.event_id')
            ->where('event_events.photographer_id', $profile->user_id)
            ->select('event_photos.*')
            ->first();
        if (!$row) return $this->notFound('photo_not_found');

        return $this->ok([
            'id'              => $row->id,
            'event_id'        => $row->event_id,
            'filename'        => $row->filename,
            'file_size'       => (int) $row->file_size,
            'width'           => (int) $row->width,
            'height'          => (int) $row->height,
            'quality_score'   => $row->quality_score !== null ? (float) $row->quality_score : null,
            'best_shot_score' => $row->best_shot_score !== null ? (float) $row->best_shot_score : null,
            'face_count'      => (int) ($row->face_count ?? 0),
            'caption'         => $row->caption,
            'ai_tags'         => is_array($row->ai_tags) ? $row->ai_tags : (json_decode($row->ai_tags ?? '[]', true) ?: []),
            'sort_order'      => (int) ($row->sort_order ?? 0),
            'status'          => $row->status,
            'created_at'      => optional($row->created_at)->toIso8601String(),
        ]);
    }

    /* ════════════════════════════════════════════════════════════
     *  GET /api/v1/photographer/orders
     *  Scope: orders:read
     *  Query params: limit (1-100, default 50), page (default 1),
     *                status (paid|pending|cancelled), event_id
     * ════════════════════════════════════════════════════════════ */
    public function orders(Request $request): JsonResponse
    {
        $this->requireScope($request, 'orders:read');
        $profile = $this->profile($request);

        if (!Schema::hasTable('orders')) return $this->ok([], ['page' => 1, 'per_page' => 0, 'total' => 0]);

        $perPage = (int) max(1, min(100, $request->query('limit', 50)));
        $page    = (int) max(1, $request->query('page', 1));

        $query = DB::table('orders')
            ->join('event_events', 'event_events.id', '=', 'orders.event_id')
            ->where('event_events.photographer_id', $profile->user_id)
            ->when($request->query('status'), fn ($q, $v) => $q->where('orders.status', $v))
            ->when($request->query('event_id'), fn ($q, $v) => $q->where('orders.event_id', (int) $v))
            ->select(
                'orders.id',
                'orders.order_number',
                'orders.event_id',
                'event_events.name as event_name',
                'orders.user_id',
                'orders.total',
                'orders.subtotal',
                'orders.discount_amount',
                'orders.status',
                'orders.delivery_method',
                'orders.delivery_status',
                'orders.paid_at',
                'orders.delivered_at',
                'orders.created_at'
            )
            ->orderByDesc('orders.created_at');

        $total  = (clone $query)->count();
        $orders = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return $this->ok($orders, [
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
        ]);
    }

    /* ════════════════════════════════════════════════════════════
     *  GET /api/v1/photographer/orders/{order}
     *  Scope: orders:read
     * ════════════════════════════════════════════════════════════ */
    public function orderShow(Request $request, int $order): JsonResponse
    {
        $this->requireScope($request, 'orders:read');
        $profile = $this->profile($request);

        if (!Schema::hasTable('orders')) return $this->notFound('order_not_found');

        $row = DB::table('orders')
            ->join('event_events', 'event_events.id', '=', 'orders.event_id')
            ->where('orders.id', $order)
            ->where('event_events.photographer_id', $profile->user_id)
            ->select(
                'orders.*',
                'event_events.name as event_name',
                'event_events.slug as event_slug'
            )
            ->first();
        if (!$row) return $this->notFound('order_not_found');

        // Order items (photos purchased)
        $items = Schema::hasTable('order_items')
            ? DB::table('order_items')->where('order_id', $order)->get()
            : collect();

        return $this->ok([
            'id'              => $row->id,
            'order_number'    => $row->order_number,
            'event_id'        => $row->event_id,
            'event_name'      => $row->event_name,
            'event_slug'      => $row->event_slug,
            'user_id'         => $row->user_id,
            'guest_email'     => $row->guest_email,
            'subtotal'        => (float) ($row->subtotal ?? 0),
            'discount_amount' => (float) ($row->discount_amount ?? 0),
            'total'           => (float) ($row->total ?? 0),
            'status'          => $row->status,
            'delivery_method' => $row->delivery_method,
            'delivery_status' => $row->delivery_status,
            'paid_at'         => $row->paid_at,
            'delivered_at'    => $row->delivered_at,
            'created_at'      => $row->created_at,
            'items_count'     => $items->count(),
            'items'           => $items->take(50)->values(),
        ]);
    }

    /* ════════════════════════════════════════════════════════════
     *  GET /api/v1/photographer/stats
     *  Scope: stats:read
     *  Aggregate dashboard numbers — useful for external dashboards
     *  / Slack bots / studio operations.
     * ════════════════════════════════════════════════════════════ */
    public function stats(Request $request): JsonResponse
    {
        $this->requireScope($request, 'stats:read');
        $profile = $this->profile($request);

        $eventsCount = Event::where('photographer_id', $profile->user_id)->count();
        $eventsActive = Event::where('photographer_id', $profile->user_id)
            ->whereIn('status', ['active', 'published'])->count();

        $photoCount = (int) DB::table('event_photos')
            ->join('event_events', 'event_events.id', '=', 'event_photos.event_id')
            ->where('event_events.photographer_id', $profile->user_id)
            ->count();

        $orders = ['paid_count' => 0, 'paid_total_thb' => 0.0, 'pending_count' => 0];
        if (Schema::hasTable('orders')) {
            $row = DB::table('orders')
                ->join('event_events', 'event_events.id', '=', 'orders.event_id')
                ->where('event_events.photographer_id', $profile->user_id)
                ->selectRaw("
                    COUNT(*) FILTER (WHERE orders.status = 'paid') as paid_count,
                    COALESCE(SUM(orders.total) FILTER (WHERE orders.status = 'paid'), 0) as paid_total_thb,
                    COUNT(*) FILTER (WHERE orders.status = 'pending') as pending_count
                ")->first();
            $orders = [
                'paid_count'     => (int) ($row->paid_count ?? 0),
                'paid_total_thb' => (float) ($row->paid_total_thb ?? 0),
                'pending_count'  => (int) ($row->pending_count ?? 0),
            ];
        }

        return $this->ok([
            'photographer_id' => $profile->user_id,
            'events' => [
                'total'  => $eventsCount,
                'active' => $eventsActive,
            ],
            'photos' => [
                'total' => $photoCount,
            ],
            'orders'  => $orders,
            'storage' => [
                'used_bytes'  => (int) $profile->storage_used_bytes,
                'quota_bytes' => (int) $profile->storage_quota_bytes,
                'used_pct'    => $profile->storage_quota_bytes > 0
                    ? round(($profile->storage_used_bytes / $profile->storage_quota_bytes) * 100, 2)
                    : 0,
            ],
            'plan' => [
                'code'            => $profile->subscription_plan_code,
                'commission_rate' => (float) ($profile->commission_rate ?? 0),
            ],
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /* ─── Internals ─────────────────────────────────────────────── */

    protected function profile(Request $request): PhotographerProfile
    {
        return $request->attributes->get('photographer_profile');
    }

    protected function key(Request $request): PhotographerApiKey
    {
        return $request->attributes->get('api_key');
    }

    protected function requireScope(Request $request, string $scope): void
    {
        $key = $this->key($request);
        // A wildcard scope `*` grants everything. Otherwise the exact
        // scope string must be present in the comma-separated list.
        if (in_array('*', $key->scopeList(), true)) return;
        if (!$key->hasScope($scope)) {
            abort(response()->json([
                'success' => false,
                'error'   => 'insufficient_scope',
                'message' => "API key missing required scope: {$scope}",
                'required_scope' => $scope,
                'granted_scopes' => $key->scopeList(),
            ], 403));
        }
    }

    protected function ok($data, ?array $meta = null): JsonResponse
    {
        $payload = ['success' => true, 'data' => $data];
        if ($meta) $payload['meta'] = $meta;
        return response()->json($payload);
    }

    protected function notFound(string $code = 'not_found'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error'   => $code,
        ], 404);
    }
}
