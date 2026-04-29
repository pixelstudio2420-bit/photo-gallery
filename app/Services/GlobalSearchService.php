<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * GlobalSearchService — admin-only global search across events, users,
 * orders and blog posts.
 */
class GlobalSearchService
{
    /**
     * Perform a global search.
     *
     * @return array{events:array,users:array,orders:array,blog_posts:array}
     */
    public function search(string $query, int $limit = 5): array
    {
        $q = trim($query);

        if ($q === '') {
            return [
                'events'     => [],
                'users'      => [],
                'orders'     => [],
                'blog_posts' => [],
            ];
        }

        return [
            'events'     => $this->searchEvents($q, $limit),
            'users'      => $this->searchUsers($q, $limit),
            'orders'     => $this->searchOrders($q, $limit),
            'blog_posts' => $this->searchBlogPosts($q, $limit),
        ];
    }

    /* ──────────────────────────── Events ──────────────────────────── */
    protected function searchEvents(string $q, int $limit): array
    {
        try {
            return Event::where(function ($query) use ($q) {
                    $query->where('name', 'ilike', "%{$q}%")
                          ->orWhere('slug', 'ilike', "%{$q}%");
                })
                ->limit($limit)
                ->get()
                ->map(function ($e) {
                    return [
                        'id'       => $e->id,
                        'title'    => $e->name,
                        'subtitle' => ($e->status ? ucfirst($e->status) . ' · ' : '')
                                       . ($e->shoot_date ? \Carbon\Carbon::parse($e->shoot_date)->format('d/m/Y') : 'ไม่กำหนด'),
                        'url'      => url('/admin/events/' . $e->id . '/edit'),
                        'icon'     => 'bi-calendar-event',
                    ];
                })
                ->all();
        } catch (\Throwable $e) {
            Log::warning('GlobalSearchService::searchEvents failed: ' . $e->getMessage());
            return [];
        }
    }

    /* ──────────────────────────── Users ──────────────────────────── */
    protected function searchUsers(string $q, int $limit): array
    {
        try {
            return User::where(function ($query) use ($q) {
                    $query->where('first_name', 'ilike', "%{$q}%")
                          ->orWhere('last_name', 'ilike', "%{$q}%")
                          ->orWhere('email', 'ilike', "%{$q}%");
                })
                ->limit($limit)
                ->get()
                ->map(function ($u) {
                    $name = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
                    return [
                        'id'       => $u->id,
                        'title'    => $name ?: ($u->email ?? 'User #' . $u->id),
                        'subtitle' => $u->email ?: ('ID: ' . $u->id),
                        'url'      => url('/admin/users/' . $u->id),
                        'icon'     => 'bi-person-circle',
                    ];
                })
                ->all();
        } catch (\Throwable $e) {
            Log::warning('GlobalSearchService::searchUsers failed: ' . $e->getMessage());
            return [];
        }
    }

    /* ──────────────────────────── Orders ──────────────────────────── */
    protected function searchOrders(string $q, int $limit): array
    {
        try {
            return Order::where('order_number', 'ilike', "%{$q}%")
                ->limit($limit)
                ->get()
                ->map(function ($o) {
                    return [
                        'id'       => $o->id,
                        'title'    => '#' . ($o->order_number ?: $o->id),
                        'subtitle' => ($o->status ? ucfirst($o->status) : '-')
                                        . ' · ฿' . number_format((float) ($o->total ?? 0), 2),
                        'url'      => url('/admin/orders/' . $o->id),
                        'icon'     => 'bi-bag-check',
                    ];
                })
                ->all();
        } catch (\Throwable $e) {
            Log::warning('GlobalSearchService::searchOrders failed: ' . $e->getMessage());
            return [];
        }
    }

    /* ──────────────────────────── Blog Posts ──────────────────────────── */
    protected function searchBlogPosts(string $q, int $limit): array
    {
        try {
            if (!class_exists(BlogPost::class)) {
                return [];
            }
            return BlogPost::where(function ($query) use ($q) {
                    $query->where('title', 'ilike', "%{$q}%")
                          ->orWhere('slug', 'ilike', "%{$q}%");
                })
                ->limit($limit)
                ->get()
                ->map(function ($p) {
                    return [
                        'id'       => $p->id,
                        'title'    => $p->title,
                        'subtitle' => ($p->status ?? 'draft')
                                       . ($p->published_at ? ' · ' . \Carbon\Carbon::parse($p->published_at)->format('d/m/Y') : ''),
                        'url'      => url('/admin/blog/posts/' . $p->id . '/edit'),
                        'icon'     => 'bi-journal-text',
                    ];
                })
                ->all();
        } catch (\Throwable $e) {
            Log::warning('GlobalSearchService::searchBlogPosts failed: ' . $e->getMessage());
            return [];
        }
    }
}
