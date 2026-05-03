<?php

namespace App\Services;

use App\Models\AppSetting;

/**
 * Generates OpenAPI 3.0 specification for the Photo Gallery API.
 *
 * This service builds the spec programmatically so it stays in sync with
 * actual controller changes. Manually-defined schemas keep responses
 * accurately documented.
 */
class ApiDocumentationService
{
    /**
     * Build the complete OpenAPI 3.0 specification.
     */
    public function build(): array
    {
        $siteName = AppSetting::get('site_name') ?: (string) config('app.name', 'Photo Gallery');
        $baseUrl  = rtrim(config('app.url', url('/')), '/');

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title'       => "{$siteName} API",
                'description' => $this->getDescription(),
                'version'     => '1.0.0',
                'contact'     => [
                    'name'  => 'Support',
                    'email' => AppSetting::get('support_email', 'support@example.com'),
                ],
                'license' => [
                    'name' => 'Proprietary',
                ],
            ],
            'servers' => [
                ['url' => $baseUrl, 'description' => 'Production'],
            ],
            'tags' => $this->getTags(),
            'components' => [
                'securitySchemes' => $this->getSecuritySchemes(),
                'schemas'         => $this->getSchemas(),
                'responses'       => $this->getCommonResponses(),
                'parameters'      => $this->getCommonParameters(),
            ],
            'paths' => $this->getPaths(),
        ];
    }

    /**
     * Main API overview description.
     */
    private function getDescription(): string
    {
        return <<<MD
# Photo Gallery REST API

Complete REST API for the Photo Gallery platform. This API supports customer purchases, photographer management, cart operations, reviews, chat, notifications, and payment webhooks.

## Authentication

Most endpoints require authentication via **session cookie** (obtained by logging in through the web app). For machine-to-machine access (e.g., integrations, mobile apps), use **API Keys** via the `X-API-Key` header.

### Available Auth Methods

1. **Session Cookie** (default for web app)
2. **API Key** — include `X-API-Key: <your_key>` header (admin-generated)
3. **No auth required** — public endpoints (webhooks use signature verification)

## Rate Limiting

All API endpoints are rate-limited:
- **Authenticated**: 60 requests/minute per user
- **Unauthenticated**: 20 requests/minute per IP
- **Webhooks**: 120 requests/minute per source

Rate limit info returned in headers:
- `X-RateLimit-Limit`
- `X-RateLimit-Remaining`
- `X-RateLimit-Reset`

## Response Format

All successful responses follow this structure:
```json
{
  "success": true,
  "data": { ... }
}
```

Errors follow:
```json
{
  "success": false,
  "error": "Error message",
  "code": "ERROR_CODE"
}
```

## Pagination

Paginated endpoints return Laravel-style pagination:
```json
{
  "data": [...],
  "current_page": 1,
  "last_page": 10,
  "per_page": 20,
  "total": 200,
  "next_page_url": "...",
  "prev_page_url": null
}
```
MD;
    }

    /**
     * API tags for grouping endpoints.
     */
    private function getTags(): array
    {
        return [
            ['name' => 'Photographer API v1', 'description' => 'Bearer-authenticated read API for a photographer\'s own events, photos, orders, and stats. 60 req/min per key. Studio plan (or any plan with `api_access` feature flag).'],
            ['name' => 'Authentication',  'description' => 'Login, register, password reset'],
            ['name' => 'Cart',            'description' => 'Shopping cart operations'],
            ['name' => 'Wishlist',        'description' => 'User wishlist management'],
            ['name' => 'Notifications',   'description' => 'User + admin notifications'],
            ['name' => 'Chat',            'description' => 'Real-time chat messaging'],
            ['name' => 'Chatbot',         'description' => 'AI chatbot endpoints'],
            ['name' => 'Drive',           'description' => 'Google Drive photo proxy'],
            ['name' => 'Face Search',     'description' => 'AI face search for events'],
            ['name' => 'Coupons',         'description' => 'Validate coupon codes'],
            ['name' => 'Blog AI',         'description' => 'AI content generation for blog'],
            ['name' => 'Language',        'description' => 'Multi-language switching'],
            ['name' => 'Locations',       'description' => 'Thai provinces/districts lookup'],
            ['name' => 'Reviews',         'description' => 'Review management (helpful, report)'],
            ['name' => 'Webhooks',        'description' => 'Payment gateway + integration webhooks'],
            ['name' => 'Admin',           'description' => 'Admin-only endpoints'],
        ];
    }

    /**
     * Security schemes (auth methods).
     */
    private function getSecuritySchemes(): array
    {
        return [
            'sessionAuth' => [
                'type' => 'apiKey',
                'in'   => 'cookie',
                'name' => 'laravel_session',
                'description' => 'Session cookie from web app login',
            ],
            'apiKeyAuth' => [
                'type' => 'apiKey',
                'in'   => 'header',
                'name' => 'X-API-Key',
                'description' => 'Admin-generated API key',
            ],
            'photographerBearer' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'pgk_<48 hex chars>',
                'description' => 'Photographer-scoped Bearer token. Created at /photographer/api-keys (Studio plan or any plan with `api_access` feature). Send as `Authorization: Bearer pgk_…`.',
            ],
            'csrfToken' => [
                'type' => 'apiKey',
                'in'   => 'header',
                'name' => 'X-CSRF-TOKEN',
                'description' => 'CSRF token for state-changing requests (from meta tag)',
            ],
        ];
    }

    /**
     * Reusable schema definitions.
     */
    private function getSchemas(): array
    {
        return [
            'SuccessResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'data'    => ['type' => 'object'],
                    'message' => ['type' => 'string'],
                ],
            ],
            'ErrorResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => false],
                    'error'   => ['type' => 'string', 'example' => 'Unauthorized'],
                    'code'    => ['type' => 'string', 'example' => 'UNAUTHORIZED'],
                ],
            ],
            'ValidationError' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string'],
                    'errors'  => [
                        'type' => 'object',
                        'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
            ],
            'Notification' => [
                'type' => 'object',
                'properties' => [
                    'id'         => ['type' => 'integer', 'example' => 123],
                    'type'       => ['type' => 'string', 'example' => 'order'],
                    'title'      => ['type' => 'string', 'example' => 'คำสั่งซื้อใหม่'],
                    'message'    => ['type' => 'string', 'example' => 'ยอด ฿500 รอการชำระเงิน'],
                    'is_read'    => ['type' => 'boolean'],
                    'action_url' => ['type' => 'string', 'nullable' => true],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'read_at'    => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                ],
            ],
            'CartItem' => [
                'type' => 'object',
                'properties' => [
                    'file_id'    => ['type' => 'string', 'example' => '1ABC_drive_file_id'],
                    'event_id'   => ['type' => 'integer'],
                    'name'       => ['type' => 'string'],
                    'thumbnail'  => ['type' => 'string'],
                    'price'      => ['type' => 'number', 'format' => 'float'],
                    'package_id' => ['type' => 'integer', 'nullable' => true],
                ],
            ],
            'Cart' => [
                'type' => 'object',
                'properties' => [
                    'items'     => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/CartItem']],
                    'count'     => ['type' => 'integer'],
                    'subtotal'  => ['type' => 'number'],
                    'discount'  => ['type' => 'number'],
                    'total'     => ['type' => 'number'],
                ],
            ],
            'Review' => [
                'type' => 'object',
                'properties' => [
                    'id'                   => ['type' => 'integer'],
                    'user_id'              => ['type' => 'integer'],
                    'photographer_id'      => ['type' => 'integer'],
                    'event_id'             => ['type' => 'integer'],
                    'rating'               => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                    'comment'              => ['type' => 'string', 'nullable' => true],
                    'is_visible'           => ['type' => 'boolean'],
                    'is_verified_purchase' => ['type' => 'boolean'],
                    'helpful_count'        => ['type' => 'integer'],
                    'photographer_reply'   => ['type' => 'string', 'nullable' => true],
                    'admin_reply'          => ['type' => 'string', 'nullable' => true],
                    'created_at'           => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'Event' => [
                'type' => 'object',
                'properties' => [
                    'id'              => ['type' => 'integer'],
                    'title'           => ['type' => 'string'],
                    'slug'            => ['type' => 'string'],
                    'description'     => ['type' => 'string', 'nullable' => true],
                    'event_date'      => ['type' => 'string', 'format' => 'date'],
                    'location'        => ['type' => 'string', 'nullable' => true],
                    'cover_image'     => ['type' => 'string', 'nullable' => true],
                    'price_per_photo' => ['type' => 'number'],
                    'view_count'      => ['type' => 'integer'],
                    'is_active'       => ['type' => 'boolean'],
                ],
            ],
            'BlogPost' => [
                'type' => 'object',
                'properties' => [
                    'id'             => ['type' => 'integer'],
                    'title'          => ['type' => 'string'],
                    'slug'           => ['type' => 'string'],
                    'excerpt'        => ['type' => 'string', 'nullable' => true],
                    'content'        => ['type' => 'string'],
                    'featured_image' => ['type' => 'string', 'nullable' => true],
                    'status'         => ['type' => 'string', 'enum' => ['draft', 'scheduled', 'published', 'archived']],
                    'reading_time'   => ['type' => 'integer'],
                    'view_count'     => ['type' => 'integer'],
                    'published_at'   => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                ],
            ],
        ];
    }

    /**
     * Reusable response definitions.
     */
    private function getCommonResponses(): array
    {
        return [
            'Unauthorized' => [
                'description' => 'Authentication required',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                        'example' => ['success' => false, 'error' => 'Unauthenticated', 'code' => 'AUTH_REQUIRED'],
                    ],
                ],
            ],
            'Forbidden' => [
                'description' => 'Permission denied',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                    ],
                ],
            ],
            'NotFound' => [
                'description' => 'Resource not found',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                    ],
                ],
            ],
            'ValidationError' => [
                'description' => 'Validation failed',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ValidationError'],
                    ],
                ],
            ],
            'RateLimitExceeded' => [
                'description' => 'Too many requests',
                'headers' => [
                    'Retry-After'    => ['schema' => ['type' => 'integer']],
                    'X-RateLimit-Limit' => ['schema' => ['type' => 'integer']],
                ],
            ],
        ];
    }

    /**
     * Reusable parameter definitions.
     */
    private function getCommonParameters(): array
    {
        return [
            'page' => [
                'name'        => 'page',
                'in'          => 'query',
                'description' => 'Page number for pagination',
                'schema'      => ['type' => 'integer', 'default' => 1, 'minimum' => 1],
            ],
            'perPage' => [
                'name'        => 'per_page',
                'in'          => 'query',
                'description' => 'Items per page',
                'schema'      => ['type' => 'integer', 'default' => 20, 'maximum' => 100],
            ],
        ];
    }

    /**
     * Build all API paths (grouped by tag).
     */
    private function getPaths(): array
    {
        return array_merge(
            $this->photographerApiPaths(),  // Bearer-authed v1 photographer API
            $this->notificationPaths(),
            $this->cartPaths(),
            $this->wishlistPaths(),
            $this->reviewPaths(),
            $this->chatPaths(),
            $this->chatbotPaths(),
            $this->couponPaths(),
            $this->drivePaths(),
            $this->languagePaths(),
            $this->locationPaths(),
            $this->blogPaths(),
            $this->webhookPaths()
        );
    }

    /**
     * Photographer API v1 — Bearer token authenticated, scope-gated.
     *
     * All endpoints share:
     *   - Auth: `Authorization: Bearer pgk_<48 hex chars>`
     *   - Tags: `Photographer API v1`
     *   - 401 on missing/invalid token, 403 on scope mismatch, 404 on
     *     entity not owned by the token's photographer, 429 on rate
     *     limit (60/min per token).
     */
    private function photographerApiPaths(): array
    {
        $tag       = 'Photographer API v1';
        $bearer    = [['photographerBearer' => []]];
        $errors    = [
            '401' => ['$ref' => '#/components/responses/Unauthorized'],
            '403' => ['description' => 'Insufficient scope or plan does not include api_access',
                      'content' => ['application/json' => ['example' => ['success' => false, 'error' => 'insufficient_scope', 'required_scope' => 'orders:read']]]],
            '404' => ['description' => 'Resource not found or not owned by this photographer',
                      'content' => ['application/json' => ['example' => ['success' => false, 'error' => 'event_not_found']]]],
            '429' => ['description' => 'Rate limit exceeded — 60 requests/minute per API key'],
        ];

        return [
            '/api/v1/photographer/me' => [
                'get' => [
                    'tags' => [$tag],
                    'summary' => 'Authenticated photographer profile + key metadata',
                    'description' => 'Returns the photographer that owns the API key, their plan, storage usage, and the API key metadata (label, prefix, scopes).',
                    'security' => $bearer,
                    'responses' => array_merge([
                        '200' => ['description' => 'Photographer profile', 'content' => ['application/json' => ['example' => [
                            'success' => true,
                            'data' => [
                                'photographer_id' => 42,
                                'display_name'    => 'Rin Photography',
                                'plan'            => 'pro',
                                'commission_rate' => 0.0,
                                'storage_used_bytes'  => 12_500_000_000,
                                'storage_quota_bytes' => 107_374_182_400,
                                'storage_used_pct'    => 11.64,
                                'api_key' => [
                                    'label'        => 'Slideshow display',
                                    'token_prefix' => 'pgk_a3b9',
                                    'scopes'       => ['events:read', 'photos:read'],
                                    'last_used_at' => '2026-05-04T08:32:00+07:00',
                                ],
                            ],
                        ]]]],
                    ], $errors),
                ],
            ],
            '/api/v1/photographer/stats' => [
                'get' => [
                    'tags' => [$tag],
                    'summary' => 'Aggregate dashboard stats',
                    'description' => 'Total events, photo count, paid order count + sum, pending orders, storage usage. Useful for external dashboards / Slack bots / studio displays. Requires `stats:read` scope.',
                    'security' => $bearer,
                    'responses' => array_merge([
                        '200' => ['description' => 'Stats snapshot', 'content' => ['application/json' => ['example' => [
                            'success' => true,
                            'data' => [
                                'photographer_id' => 42,
                                'events'  => ['total' => 38, 'active' => 12],
                                'photos'  => ['total' => 18234],
                                'orders'  => ['paid_count' => 89, 'paid_total_thb' => 47800.0, 'pending_count' => 3],
                                'storage' => ['used_bytes' => 12_500_000_000, 'quota_bytes' => 107_374_182_400, 'used_pct' => 11.64],
                                'plan'    => ['code' => 'pro', 'commission_rate' => 0.0],
                                'generated_at' => '2026-05-04T08:32:00+07:00',
                            ],
                        ]]]],
                    ], $errors),
                ],
            ],
            '/api/v1/photographer/events' => [
                'get' => [
                    'tags' => [$tag],
                    'summary' => 'List events (paginated)',
                    'description' => 'Returns events owned by the API key\'s photographer. Requires `events:read` scope.',
                    'security' => $bearer,
                    'parameters' => [
                        ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50]],
                        ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1]],
                        ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['draft','active','published','closed']]],
                        ['name' => 'q', 'in' => 'query', 'description' => 'Substring match on name + slug', 'schema' => ['type' => 'string']],
                    ],
                    'responses' => array_merge([
                        '200' => ['description' => 'Event list', 'content' => ['application/json' => ['example' => [
                            'success' => true,
                            'data' => [
                                ['id' => 101, 'name' => 'Marathon BKK 2026', 'slug' => 'marathon-bkk-2026', 'status' => 'published', 'price_per_photo' => 89, 'shoot_date' => '2026-04-12', 'view_count' => 1284, 'photo_count' => 1245, 'created_at' => '2026-04-10T10:00:00+07:00'],
                            ],
                            'meta' => ['page' => 1, 'per_page' => 50, 'total' => 38],
                        ]]]],
                    ], $errors),
                ],
            ],
            '/api/v1/photographer/events/{event}' => [
                'get' => [
                    'tags' => [$tag],
                    'summary' => 'Get one event',
                    'security' => $bearer,
                    'parameters' => [
                        ['name' => 'event', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => array_merge([
                        '200' => ['description' => 'Event detail', 'content' => ['application/json' => ['example' => [
                            'success' => true,
                            'data' => ['id' => 101, 'name' => 'Marathon BKK 2026', 'slug' => 'marathon-bkk-2026', 'description' => '...', 'status' => 'published', 'visibility' => 'public', 'price_per_photo' => 89.0, 'shoot_date' => '2026-04-12T00:00:00+07:00', 'view_count' => 1284, 'photo_count' => 1245],
                        ]]]],
                    ], $errors),
                ],
            ],
            '/api/v1/photographer/events/{event}/photos' => [
                'get' => [
                    'tags' => [$tag],
                    'summary' => 'List photos for an event (paginated)',
                    'description' => 'Photos belonging to one of the photographer\'s events. Requires `photos:read` scope.',
                    'security' => $bearer,
                    'parameters' => [
                        ['name' => 'event', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                        ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'default' => 100]],
                        ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1]],
                    ],
                    'responses' => array_merge([
                        '200' => ['description' => 'Photo list', 'content' => ['application/json' => ['example' => [
                            'success' => true,
                            'data' => [
                                ['id' => 5001, 'event_id' => 101, 'filename' => 'IMG_3421.jpg', 'file_size' => 4_521_600, 'width' => 6000, 'height' => 4000, 'quality_score' => 0.87, 'best_shot_score' => 0.92, 'face_count' => 3, 'caption' => null, 'sort_order' => 0, 'created_at' => '2026-04-12T14:30:00+07:00'],
                            ],
                            'meta' => ['page' => 1, 'per_page' => 100, 'total' => 1245],
                        ]]]],
                    ], $errors),
                ],
            ],
            '/api/v1/photographer/photos/{photo}' => [
                'get' => [
                    'tags' => [$tag],
                    'summary' => 'Get one photo by ID (with AI metadata)',
                    'security' => $bearer,
                    'parameters' => [
                        ['name' => 'photo', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => array_merge([
                        '200' => ['description' => 'Photo detail with AI metadata', 'content' => ['application/json' => ['example' => [
                            'success' => true,
                            'data' => ['id' => 5001, 'event_id' => 101, 'filename' => 'IMG_3421.jpg', 'file_size' => 4521600, 'width' => 6000, 'height' => 4000, 'quality_score' => 0.87, 'best_shot_score' => 0.92, 'face_count' => 3, 'caption' => null, 'ai_tags' => ['running', 'outdoor', 'race-bib'], 'sort_order' => 0, 'status' => 'published'],
                        ]]]],
                    ], $errors),
                ],
            ],
            '/api/v1/photographer/orders' => [
                'get' => [
                    'tags' => [$tag],
                    'summary' => 'List orders (paginated)',
                    'description' => 'Orders for events owned by this photographer. Requires `orders:read` scope.',
                    'security' => $bearer,
                    'parameters' => [
                        ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50]],
                        ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1]],
                        ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['paid','pending','cancelled','refunded']]],
                        ['name' => 'event_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => array_merge([
                        '200' => ['description' => 'Order list', 'content' => ['application/json' => ['example' => [
                            'success' => true,
                            'data' => [
                                ['id' => 9001, 'order_number' => 'ORD-9001', 'event_id' => 101, 'event_name' => 'Marathon BKK 2026', 'user_id' => 12345, 'total' => 178.0, 'subtotal' => 178.0, 'discount_amount' => 0, 'status' => 'paid', 'delivery_method' => 'line', 'delivery_status' => 'delivered', 'paid_at' => '2026-04-15T19:22:00+07:00', 'delivered_at' => '2026-04-15T19:22:31+07:00', 'created_at' => '2026-04-15T19:20:00+07:00'],
                            ],
                            'meta' => ['page' => 1, 'per_page' => 50, 'total' => 89],
                        ]]]],
                    ], $errors),
                ],
            ],
            '/api/v1/photographer/orders/{order}' => [
                'get' => [
                    'tags' => [$tag],
                    'summary' => 'Get one order with line items',
                    'security' => $bearer,
                    'parameters' => [
                        ['name' => 'order', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => array_merge([
                        '200' => ['description' => 'Order detail with up to 50 line items'],
                    ], $errors),
                ],
            ],
        ];
    }

    // ─── Path Groups ─────────────────────────────────────────────────────

    private function notificationPaths(): array
    {
        return [
            '/api/notifications' => [
                'get' => [
                    'tags' => ['Notifications'],
                    'summary' => 'Get user notifications',
                    'description' => 'Returns the authenticated user\'s notifications. Supports `since` param for polling only new items.',
                    'security' => [['sessionAuth' => []]],
                    'parameters' => [
                        ['name' => 'since', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date-time'], 'description' => 'Return only notifications created after this timestamp'],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Notifications list',
                            'content' => ['application/json' => ['example' => [
                                'success' => true,
                                'unread_count' => 3,
                                'notifications' => [
                                    ['id' => 1, 'type' => 'order', 'title' => 'คำสั่งซื้อใหม่', 'message' => 'ยอด ฿500', 'is_read' => false, 'created_at' => '2026-04-17T12:00:00Z'],
                                ],
                            ]]],
                        ],
                        '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    ],
                ],
            ],
            '/api/notifications/unread-count' => [
                'get' => [
                    'tags' => ['Notifications'],
                    'summary' => 'Get unread count (lightweight)',
                    'description' => 'Returns only the unread count. Optimized for badge polling.',
                    'security' => [['sessionAuth' => []]],
                    'responses' => [
                        '200' => [
                            'description' => 'Unread count',
                            'content' => ['application/json' => ['example' => ['success' => true, 'unread_count' => 5]]],
                        ],
                    ],
                ],
            ],
            '/api/notifications/{id}/read' => [
                'post' => [
                    'tags' => ['Notifications'],
                    'summary' => 'Mark notification as read',
                    'security' => [['sessionAuth' => [], 'csrfToken' => []]],
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Marked as read', 'content' => ['application/json' => ['example' => ['success' => true]]]],
                    ],
                ],
            ],
            '/api/notifications/read-all' => [
                'post' => [
                    'tags' => ['Notifications'],
                    'summary' => 'Mark all notifications as read',
                    'security' => [['sessionAuth' => [], 'csrfToken' => []]],
                    'responses' => [
                        '200' => ['description' => 'All marked as read'],
                    ],
                ],
            ],
            '/api/presence' => [
                'post' => [
                    'tags' => ['Notifications'],
                    'summary' => 'Track user presence',
                    'description' => 'Updates last_seen timestamp. Poll every 1-5 minutes to maintain "online" status.',
                    'security' => [['sessionAuth' => []]],
                    'responses' => [
                        '200' => ['description' => 'Presence updated', 'content' => ['application/json' => ['example' => ['online' => true, 'timestamp' => '2026-04-17T12:00:00Z']]]],
                    ],
                ],
            ],
        ];
    }

    private function cartPaths(): array
    {
        return [
            '/api/cart' => [
                'get' => [
                    'tags' => ['Cart'],
                    'summary' => 'Get current cart contents',
                    'security' => [['sessionAuth' => []]],
                    'responses' => [
                        '200' => [
                            'description' => 'Cart contents',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Cart']]],
                        ],
                    ],
                ],
            ],
            '/api/cart/add' => [
                'post' => [
                    'tags' => ['Cart'],
                    'summary' => 'Add item to cart',
                    'security' => [['sessionAuth' => [], 'csrfToken' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['file_id', 'event_id'],
                                'properties' => [
                                    'file_id'   => ['type' => 'string'],
                                    'event_id'  => ['type' => 'integer'],
                                    'name'      => ['type' => 'string'],
                                    'thumbnail' => ['type' => 'string'],
                                ],
                            ],
                        ]],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Item added', 'content' => ['application/json' => ['example' => ['success' => true, 'count' => 3]]]],
                        '422' => ['$ref' => '#/components/responses/ValidationError'],
                    ],
                ],
            ],
            '/api/cart/remove/{file_id}' => [
                'delete' => [
                    'tags' => ['Cart'],
                    'summary' => 'Remove item from cart',
                    'security' => [['sessionAuth' => [], 'csrfToken' => []]],
                    'parameters' => [['name' => 'file_id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                    'responses' => [
                        '200' => ['description' => 'Item removed'],
                    ],
                ],
            ],
            '/api/cart/clear' => [
                'post' => [
                    'tags' => ['Cart'],
                    'summary' => 'Clear all cart items',
                    'security' => [['sessionAuth' => [], 'csrfToken' => []]],
                    'responses' => ['200' => ['description' => 'Cart cleared']],
                ],
            ],
            '/api/cart/coupon' => [
                'post' => [
                    'tags' => ['Cart', 'Coupons'],
                    'summary' => 'Apply coupon code',
                    'security' => [['sessionAuth' => [], 'csrfToken' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => [
                            'schema' => ['type' => 'object', 'required' => ['code'], 'properties' => ['code' => ['type' => 'string']]],
                        ]],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Coupon applied', 'content' => ['application/json' => ['example' => ['success' => true, 'discount' => 50.0, 'message' => 'คูปองใช้งานได้']]]],
                        '422' => ['description' => 'Invalid or expired coupon'],
                    ],
                ],
            ],
        ];
    }

    private function wishlistPaths(): array
    {
        return [
            '/api/wishlist' => [
                'get' => [
                    'tags' => ['Wishlist'],
                    'summary' => 'Get user wishlist',
                    'security' => [['sessionAuth' => []]],
                    'responses' => [
                        '200' => ['description' => 'Wishlist items'],
                    ],
                ],
            ],
            '/api/wishlist/toggle' => [
                'post' => [
                    'tags' => ['Wishlist'],
                    'summary' => 'Toggle item in wishlist (add if missing, remove if exists)',
                    'security' => [['sessionAuth' => [], 'csrfToken' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['event_id'],
                                'properties' => ['event_id' => ['type' => 'integer']],
                            ],
                        ]],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Toggled', 'content' => ['application/json' => ['example' => ['success' => true, 'in_wishlist' => true]]]],
                    ],
                ],
            ],
        ];
    }

    private function reviewPaths(): array
    {
        return [
            '/reviews/{review}/helpful' => [
                'post' => [
                    'tags' => ['Reviews'],
                    'summary' => 'Toggle helpful vote on a review',
                    'description' => 'Cannot vote on your own reviews.',
                    'security' => [['sessionAuth' => [], 'csrfToken' => []]],
                    'parameters' => [['name' => 'review', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'responses' => [
                        '200' => [
                            'description' => 'Vote toggled',
                            'content' => ['application/json' => ['example' => ['success' => true, 'is_helpful' => true, 'helpful_count' => 5]]],
                        ],
                        '422' => ['description' => 'Cannot vote on own review'],
                    ],
                ],
            ],
            '/reviews/{review}/report' => [
                'post' => [
                    'tags' => ['Reviews'],
                    'summary' => 'Report a review as inappropriate',
                    'security' => [['sessionAuth' => [], 'csrfToken' => []]],
                    'parameters' => [['name' => 'review', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['reason'],
                                'properties' => [
                                    'reason'      => ['type' => 'string', 'enum' => ['spam', 'offensive', 'fake', 'irrelevant', 'private_info', 'other']],
                                    'description' => ['type' => 'string', 'maxLength' => 500],
                                ],
                            ],
                        ]],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Report submitted'],
                        '422' => ['description' => 'Already reported'],
                    ],
                ],
            ],
        ];
    }

    private function chatPaths(): array
    {
        return [
            '/api/chat' => [
                'get' => [
                    'tags' => ['Chat'],
                    'summary' => 'Get chat conversations',
                    'security' => [['sessionAuth' => []]],
                    'responses' => ['200' => ['description' => 'Conversations list']],
                ],
            ],
            '/api/chat/{conversation}/messages' => [
                'get' => [
                    'tags' => ['Chat'],
                    'summary' => 'Get messages in a conversation',
                    'security' => [['sessionAuth' => []]],
                    'parameters' => [
                        ['name' => 'conversation', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                        ['name' => 'since', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date-time']],
                    ],
                    'responses' => ['200' => ['description' => 'Messages']],
                ],
                'post' => [
                    'tags' => ['Chat'],
                    'summary' => 'Send a message',
                    'security' => [['sessionAuth' => [], 'csrfToken' => []]],
                    'parameters' => [['name' => 'conversation', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['message'],
                                'properties' => ['message' => ['type' => 'string', 'maxLength' => 2000]],
                            ],
                        ]],
                    ],
                    'responses' => ['200' => ['description' => 'Message sent']],
                ],
            ],
        ];
    }

    private function chatbotPaths(): array
    {
        return [
            '/api/chatbot' => [
                'post' => [
                    'tags' => ['Chatbot'],
                    'summary' => 'Ask AI chatbot a question',
                    'description' => 'Get an AI-powered response to customer questions about events, orders, or general help.',
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['message'],
                                'properties' => [
                                    'message' => ['type' => 'string', 'maxLength' => 500],
                                    'context' => ['type' => 'object', 'description' => 'Optional context (event_id, order_id, etc.)'],
                                ],
                            ],
                        ]],
                    ],
                    'responses' => [
                        '200' => ['description' => 'AI response', 'content' => ['application/json' => ['example' => ['success' => true, 'reply' => 'ยินดีช่วยเหลือครับ...']]]],
                    ],
                ],
            ],
        ];
    }

    private function couponPaths(): array
    {
        return [
            '/api/coupons/validate' => [
                'post' => [
                    'tags' => ['Coupons'],
                    'summary' => 'Validate a coupon code',
                    'security' => [['sessionAuth' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => [
                            'schema' => ['type' => 'object', 'required' => ['code'], 'properties' => ['code' => ['type' => 'string']]],
                        ]],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Validation result'],
                    ],
                ],
            ],
        ];
    }

    private function drivePaths(): array
    {
        return [
            '/api/drive/image/{file_id}' => [
                'get' => [
                    'tags' => ['Drive'],
                    'summary' => 'Proxy Google Drive image',
                    'description' => 'Fetches image from Google Drive through the server (hides API key, caches, applies watermark for unpaid events).',
                    'parameters' => [
                        ['name' => 'file_id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                        ['name' => 'size', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['thumb', 'medium', 'large', 'original']]],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Image binary', 'content' => ['image/jpeg' => ['schema' => ['type' => 'string', 'format' => 'binary']]]],
                        '404' => ['$ref' => '#/components/responses/NotFound'],
                    ],
                ],
            ],
        ];
    }

    private function languagePaths(): array
    {
        return [
            '/lang/{locale}' => [
                'get' => [
                    'tags' => ['Language'],
                    'summary' => 'Switch interface language',
                    'parameters' => [['name' => 'locale', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['th', 'en', 'zh']]]],
                    'responses' => [
                        '302' => ['description' => 'Redirect back with locale set'],
                        '200' => ['description' => 'JSON response (for AJAX callers)', 'content' => ['application/json' => ['example' => ['success' => true, 'locale' => 'en']]]],
                    ],
                ],
            ],
            '/lang/current' => [
                'get' => [
                    'tags' => ['Language'],
                    'summary' => 'Get current locale info + supported locales',
                    'responses' => ['200' => ['description' => 'Locale info']],
                ],
            ],
        ];
    }

    private function locationPaths(): array
    {
        return [
            '/admin/api/locations/districts' => [
                'get' => [
                    'tags' => ['Locations'],
                    'summary' => 'Get districts by province',
                    'security' => [['sessionAuth' => []]],
                    'parameters' => [['name' => 'province_id', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'responses' => ['200' => ['description' => 'Districts list']],
                ],
            ],
            '/admin/api/locations/subdistricts' => [
                'get' => [
                    'tags' => ['Locations'],
                    'summary' => 'Get subdistricts by district',
                    'security' => [['sessionAuth' => []]],
                    'parameters' => [['name' => 'district_id', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'responses' => ['200' => ['description' => 'Subdistricts list']],
                ],
            ],
        ];
    }

    private function blogPaths(): array
    {
        return [
            '/admin/blog/ai/generate-article' => [
                'post' => [
                    'tags' => ['Blog AI', 'Admin'],
                    'summary' => 'Generate full SEO article from keyword',
                    'description' => 'Uses AI (OpenAI/Claude/Gemini) to generate a complete SEO-optimized article.',
                    'security' => [['sessionAuth' => [], 'csrfToken' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['keyword'],
                                'properties' => [
                                    'keyword'     => ['type' => 'string', 'example' => 'การถ่ายภาพพรีเวดดิ้ง'],
                                    'word_count'  => ['type' => 'integer', 'default' => 1500, 'minimum' => 300, 'maximum' => 5000],
                                    'tone'        => ['type' => 'string', 'enum' => ['professional', 'casual', 'friendly']],
                                    'language'    => ['type' => 'string', 'enum' => ['th', 'en'], 'default' => 'th'],
                                    'provider'    => ['type' => 'string', 'enum' => ['openai', 'claude', 'gemini']],
                                    'include_faq' => ['type' => 'boolean', 'default' => true],
                                ],
                            ],
                        ]],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Generated article',
                            'content' => ['application/json' => ['example' => [
                                'success' => true,
                                'data' => [
                                    'title' => 'การถ่ายภาพพรีเวดดิ้ง: คู่มือฉบับสมบูรณ์',
                                    'content' => '<p>...</p>',
                                    'meta_title' => '...',
                                    'meta_description' => '...',
                                    'tags' => ['พรีเวดดิ้ง', 'ถ่ายภาพ'],
                                    'tokens_used' => 3500,
                                    'cost_usd' => 0.025,
                                ],
                            ]]],
                        ],
                    ],
                ],
            ],
            '/admin/blog/ai/summarize' => [
                'post' => [
                    'tags' => ['Blog AI', 'Admin'],
                    'summary' => 'Summarize content or URL',
                    'security' => [['sessionAuth' => [], 'csrfToken' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'content' => ['type' => 'string', 'description' => 'Text to summarize'],
                                    'url'     => ['type' => 'string', 'format' => 'uri', 'description' => 'URL to fetch and summarize'],
                                    'format'  => ['type' => 'string', 'enum' => ['paragraph', 'bullet_points', 'tldr', 'key_points']],
                                ],
                            ],
                        ]],
                    ],
                    'responses' => ['200' => ['description' => 'Summary generated']],
                ],
            ],
        ];
    }

    private function webhookPaths(): array
    {
        return [
            '/api/webhooks/stripe' => [
                'post' => [
                    'tags' => ['Webhooks'],
                    'summary' => 'Stripe payment webhook',
                    'description' => "Receives Stripe payment events.\n\n**Signature verification**: Uses `Stripe-Signature` header with webhook secret.\n\n**Events handled**: `payment_intent.succeeded`, `payment_intent.payment_failed`, `charge.refunded`",
                    'parameters' => [['name' => 'Stripe-Signature', 'in' => 'header', 'required' => true, 'schema' => ['type' => 'string']]],
                    'responses' => [
                        '200' => ['description' => 'Webhook processed'],
                        '400' => ['description' => 'Invalid signature'],
                    ],
                ],
            ],
            '/api/webhooks/omise' => [
                'post' => [
                    'tags' => ['Webhooks'],
                    'summary' => 'Omise payment webhook',
                    'responses' => ['200' => ['description' => 'Processed']],
                ],
            ],
            '/api/webhooks/paypal' => [
                'post' => [
                    'tags' => ['Webhooks'],
                    'summary' => 'PayPal IPN webhook',
                    'responses' => ['200' => ['description' => 'Processed']],
                ],
            ],
            '/api/webhooks/linepay' => [
                'post' => [
                    'tags' => ['Webhooks'],
                    'summary' => 'LINE Pay payment webhook',
                    'responses' => ['200' => ['description' => 'Processed']],
                ],
            ],
            '/api/webhooks/2c2p' => [
                'post' => [
                    'tags' => ['Webhooks'],
                    'summary' => '2C2P payment webhook',
                    'responses' => ['200' => ['description' => 'Processed']],
                ],
            ],
            '/api/webhooks/truemoney' => [
                'post' => [
                    'tags' => ['Webhooks'],
                    'summary' => 'TrueMoney Wallet webhook',
                    'responses' => ['200' => ['description' => 'Processed']],
                ],
            ],
            '/api/webhooks/slipok' => [
                'post' => [
                    'tags' => ['Webhooks'],
                    'summary' => 'SlipOK slip verification callback',
                    'description' => 'Receives results from SlipOK API for automated slip verification.',
                    'responses' => ['200' => ['description' => 'Processed']],
                ],
            ],
            '/api/webhooks/google-drive' => [
                'post' => [
                    'tags' => ['Webhooks'],
                    'summary' => 'Google Drive change notification',
                    'description' => 'Receives push notifications when files in watched Drive folders change.',
                    'responses' => ['200' => ['description' => 'Processed']],
                ],
            ],
            '/api/webhooks/line' => [
                'post' => [
                    'tags' => ['Webhooks'],
                    'summary' => 'LINE Notify callback',
                    'responses' => ['200' => ['description' => 'Processed']],
                ],
            ],
        ];
    }
}
