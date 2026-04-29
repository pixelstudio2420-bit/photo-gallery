<?php

namespace App\Services;

use App\Models\DownloadToken;
use App\Models\OrderItem;
use Illuminate\Support\Str;

class DownloadService
{
    /**
     * Generate download tokens for order items
     */
    public static function generateTokens(int $orderId): array
    {
        $items = OrderItem::where('order_id', $orderId)->get();
        $tokens = [];

        foreach ($items as $item) {
            $token = DownloadToken::create([
                'token'         => Str::random(64),
                'order_id'      => $orderId,
                'order_item_id' => $item->id,
                'file_id'       => $item->file_id,
                'user_id'       => $item->order->user_id ?? null,
                'expires_at'    => now()->addDays(7),
                'max_downloads' => 5,
                'download_count' => 0,
            ]);
            $tokens[] = $token;
        }

        return $tokens;
    }

    /**
     * Validate a download token
     */
    public static function validateToken(string $token): ?DownloadToken
    {
        $downloadToken = DownloadToken::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$downloadToken) {
            return null;
        }

        if ($downloadToken->download_count >= $downloadToken->max_downloads) {
            return null;
        }

        return $downloadToken;
    }

    /**
     * Record a download
     */
    public static function recordDownload(DownloadToken $token): void
    {
        $token->increment('download_count');
        $token->update(['last_downloaded_at' => now()]);
    }
}
