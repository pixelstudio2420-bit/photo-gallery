<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\MailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendWishlistDigest extends Command
{
    protected $signature = 'wishlist:send-digest {--days=7 : Only include new events from last N days}';

    protected $description = 'ส่งอีเมลสรุป wishlist ให้ลูกค้าที่มี event ใหม่น่าสนใจ';

    public function handle(MailService $mail): int
    {
        $days = (int) $this->option('days');
        $since = now()->subDays($days);

        $this->info("กำลังค้นหา users ที่มี wishlist...");

        // Get all users that have wishlist items
        $userIds = Wishlist::select('user_id')->distinct()->pluck('user_id');

        if ($userIds->isEmpty()) {
            $this->line('ไม่มี users ที่มี wishlist');
            return 0;
        }

        $this->info("พบ {$userIds->count()} users ที่มี wishlist");

        $sentCount = 0;
        $skippedCount = 0;
        $bar = $this->output->createProgressBar($userIds->count());
        $bar->start();

        foreach ($userIds as $userId) {
            try {
                $user = User::find($userId);
                if (!$user || !$user->email || $user->status !== 'active') {
                    $skippedCount++;
                    $bar->advance();
                    continue;
                }

                // Find wishlisted events + their categories
                $userWishlist = Wishlist::where('user_id', $userId)
                    ->with('event')
                    ->get();

                $wishlistedEventIds = $userWishlist->pluck('event_id')->filter()->unique()->all();
                $categoryIds = $userWishlist
                    ->pluck('event.category_id')
                    ->filter()
                    ->unique()
                    ->all();

                if (empty($categoryIds)) {
                    $skippedCount++;
                    $bar->advance();
                    continue;
                }

                // Find recently-added events in same categories not yet in wishlist
                $suggestions = Event::with(['photographerProfile', 'category'])
                    ->whereIn('category_id', $categoryIds)
                    ->whereNotIn('id', $wishlistedEventIds ?: [0])
                    ->whereIn('status', ['active', 'published'])
                    ->where('created_at', '>=', $since)
                    ->orderByDesc('created_at')
                    ->limit(5)
                    ->get();

                if ($suggestions->isEmpty()) {
                    $skippedCount++;
                    $bar->advance();
                    continue;
                }

                // Build a lightweight payload
                $events = $suggestions->map(function ($e) {
                    return [
                        'id'        => $e->id,
                        'name'      => $e->name,
                        'url'       => url('/events/' . ($e->slug ?: $e->id)),
                        'cover'     => $e->cover_image_url,
                        'shoot_date'=> $e->shoot_date ? \Carbon\Carbon::parse($e->shoot_date)->format('d/m/Y') : null,
                        'price'     => (float) ($e->price_per_photo ?? 0),
                        'is_free'   => (bool) $e->is_free,
                        'category'  => $e->category?->name,
                    ];
                })->all();

                $sent = $mail->sendTemplate(
                    $user->email,
                    'อีเวนต์ใหม่ที่คุณน่าจะชอบ',
                    'emails.customer.wishlist-digest',
                    [
                        'name'                => $user->first_name ?? 'คุณลูกค้า',
                        'events'              => $events,
                        'preferencesUrl'      => url('/profile/notification-preferences'),
                        'wishlistUrl'         => url('/wishlist'),
                    ],
                    'wishlist_digest'
                );

                if ($sent) {
                    $sentCount++;
                } else {
                    $skippedCount++;
                }
            } catch (\Throwable $e) {
                Log::warning("Wishlist digest failed for user #{$userId}: " . $e->getMessage());
                $skippedCount++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("ส่ง digest เรียบร้อย {$sentCount} ฉบับ (ข้าม {$skippedCount} users)");

        return 0;
    }
}
