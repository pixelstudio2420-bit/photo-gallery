<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * LINE OA Rich Menu service.
 *
 * Wraps the LINE Messaging API rich menu endpoints:
 *   POST   /v2/bot/richmenu                         create
 *   POST   /v2/bot/richmenu/{id}/content            upload image (separate host)
 *   POST   /v2/bot/user/all/richmenu/{id}           set as default
 *   GET    /v2/bot/user/all/richmenu                get current default id
 *   GET    /v2/bot/richmenu/list                    list all
 *   DELETE /v2/bot/richmenu/{id}                    delete
 *   DELETE /v2/bot/user/all/richmenu                clear default
 *
 * Image rules (LINE spec):
 *   • Format: JPEG / PNG
 *   • Size:   2500×1686 (large) or 2500×843 (compact) or 1200×810 (small)
 *   • Max:    1 MB
 *
 * The "Compact 6-button" preset this service produces uses 2500×1686 and
 * splits into a 3×2 grid mapped to:
 *   [Events list]    [My Orders]    [Face Search]
 *   [Promotions]     [Help/FAQ]     [Contact]
 *
 * All actions point at canonical app routes — admin can override the URL list
 * before applying the preset.
 *
 * Two host bases are required:
 *   • https://api.line.me            — for management endpoints
 *   • https://api-data.line.me       — for the binary image upload
 * Both authenticate with the same channel access token.
 */
class LineRichMenuService
{
    private const HOST_API  = 'https://api.line.me';
    private const HOST_DATA = 'https://api-data.line.me';

    /** Cached request settings */
    private ?array $cachedSettings = null;

    // ─────────────────────────────────────────────────────────────────────
    // Token + readiness
    // ─────────────────────────────────────────────────────────────────────

    /** Returns the Messaging API channel access token (long-lived) or '' if unset. */
    public function getToken(): string
    {
        return (string) ($this->getSettings()['line_channel_access_token'] ?? '');
    }

    public function isConfigured(): bool
    {
        return $this->getToken() !== '';
    }

    private function getSettings(): array
    {
        if ($this->cachedSettings === null) {
            $this->cachedSettings = AppSetting::all()->pluck('value', 'key')->toArray();
        }
        return $this->cachedSettings;
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->getToken())
            ->acceptJson()
            ->timeout(15);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Read operations
    // ─────────────────────────────────────────────────────────────────────

    /** List every rich menu attached to this OA. */
    public function list(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'channel access token not set', 'menus' => []];
        }
        try {
            $resp = $this->client()->get(self::HOST_API . '/v2/bot/richmenu/list');
            if (!$resp->successful()) {
                return ['ok' => false, 'error' => $this->errMsg($resp), 'menus' => []];
            }
            return ['ok' => true, 'menus' => $resp->json('richmenus') ?? []];
        } catch (\Throwable $e) {
            Log::warning('linerichmenu.list_failed', ['err' => $e->getMessage()]);
            return ['ok' => false, 'error' => $e->getMessage(), 'menus' => []];
        }
    }

    /** Get id of the current default rich menu (or null). */
    public function getDefaultId(): ?string
    {
        if (!$this->isConfigured()) return null;
        try {
            $resp = $this->client()->get(self::HOST_API . '/v2/bot/user/all/richmenu');
            // 404 simply means "no default set" — return null silently.
            if ($resp->status() === 404) return null;
            if (!$resp->successful()) return null;
            return $resp->json('richMenuId');
        } catch (\Throwable $e) {
            Log::warning('linerichmenu.default_lookup_failed', ['err' => $e->getMessage()]);
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Mutating operations
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Create a rich menu definition. Returns the rich menu id on success.
     *
     * @param array $config See LINE docs for the schema (size, areas[], etc).
     *                      Keys: size{width,height}, selected, name, chatBarText, areas[]
     */
    public function create(array $config): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'channel access token not set'];
        }
        try {
            $resp = $this->client()
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post(self::HOST_API . '/v2/bot/richmenu', $config);

            if (!$resp->successful()) {
                return ['ok' => false, 'error' => $this->errMsg($resp)];
            }
            return ['ok' => true, 'id' => $resp->json('richMenuId')];
        } catch (\Throwable $e) {
            Log::error('linerichmenu.create_failed', ['err' => $e->getMessage()]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Upload the image content for an existing rich menu.
     *
     * @param string $richMenuId
     * @param string $imagePath   absolute path on disk (PNG/JPEG, ≤ 1 MB)
     */
    public function uploadImage(string $richMenuId, string $imagePath): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'channel access token not set'];
        }
        if (!is_file($imagePath)) {
            return ['ok' => false, 'error' => "image file not found: {$imagePath}"];
        }
        $bytes = @file_get_contents($imagePath);
        if ($bytes === false) {
            return ['ok' => false, 'error' => 'failed to read image file'];
        }
        if (strlen($bytes) > 1024 * 1024) {
            return ['ok' => false, 'error' => 'image exceeds 1 MB limit'];
        }
        $mime = $this->guessImageMime($imagePath);
        if (!in_array($mime, ['image/png', 'image/jpeg'], true)) {
            return ['ok' => false, 'error' => 'image must be PNG or JPEG'];
        }

        try {
            // Image upload uses the api-data.line.me host with a binary body.
            $resp = Http::withToken($this->getToken())
                ->withBody($bytes, $mime)
                ->timeout(30)
                ->post(self::HOST_DATA . "/v2/bot/richmenu/{$richMenuId}/content");

            if (!$resp->successful()) {
                return ['ok' => false, 'error' => $this->errMsg($resp)];
            }
            return ['ok' => true];
        } catch (\Throwable $e) {
            Log::error('linerichmenu.upload_image_failed', [
                'id'  => $richMenuId,
                'err' => $e->getMessage(),
            ]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Set this rich menu as the default for every user/follower. */
    public function setDefault(string $richMenuId): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'channel access token not set'];
        }
        try {
            $resp = $this->client()->post(self::HOST_API . "/v2/bot/user/all/richmenu/{$richMenuId}");
            if (!$resp->successful()) {
                return ['ok' => false, 'error' => $this->errMsg($resp)];
            }
            return ['ok' => true];
        } catch (\Throwable $e) {
            Log::error('linerichmenu.set_default_failed', ['err' => $e->getMessage()]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Clear the default rich menu (chat bar reverts to "Tap me"). */
    public function clearDefault(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'channel access token not set'];
        }
        try {
            $resp = $this->client()->delete(self::HOST_API . '/v2/bot/user/all/richmenu');
            if (!$resp->successful()) {
                return ['ok' => false, 'error' => $this->errMsg($resp)];
            }
            return ['ok' => true];
        } catch (\Throwable $e) {
            Log::error('linerichmenu.clear_default_failed', ['err' => $e->getMessage()]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Permanently delete a rich menu. */
    public function delete(string $richMenuId): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'channel access token not set'];
        }
        try {
            $resp = $this->client()->delete(self::HOST_API . "/v2/bot/richmenu/{$richMenuId}");
            if (!$resp->successful()) {
                return ['ok' => false, 'error' => $this->errMsg($resp)];
            }
            return ['ok' => true];
        } catch (\Throwable $e) {
            Log::error('linerichmenu.delete_failed', ['err' => $e->getMessage()]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Convenience: full pipeline (create + upload + setDefault)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * One-shot: build a rich menu, upload image, and set as default.
     * Returns ['ok' => bool, 'id' => string|null, 'error' => string|null].
     */
    public function deploy(array $config, string $imagePath, bool $setAsDefault = true): array
    {
        $created = $this->create($config);
        if (!$created['ok']) {
            return ['ok' => false, 'id' => null, 'error' => 'create: ' . ($created['error'] ?? 'unknown')];
        }
        $id = $created['id'];

        $uploaded = $this->uploadImage($id, $imagePath);
        if (!$uploaded['ok']) {
            // Clean up the orphan menu so list() doesn't fill up with dead entries.
            $this->delete($id);
            return ['ok' => false, 'id' => null, 'error' => 'upload: ' . ($uploaded['error'] ?? 'unknown')];
        }

        if ($setAsDefault) {
            $set = $this->setDefault($id);
            if (!$set['ok']) {
                return ['ok' => false, 'id' => $id, 'error' => 'setDefault: ' . ($set['error'] ?? 'unknown')];
            }
        }
        return ['ok' => true, 'id' => $id, 'error' => null];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Preset: 6-button storefront menu (2500×1686, 3×2 grid)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Build the JSON config for the canonical 6-button storefront menu.
     * Caller passes the URL set so admin can edit before deploying.
     */
    public function presetStorefrontConfig(array $urls = [], array $labels = []): array
    {
        // Default URL set — admin can override on the form.
        $defaults = [
            'events'   => url('/events'),
            'orders'   => url('/orders'),
            'face'     => url('/events?face_search=1'),
            'promo'    => url('/promo'),
            'help'     => url('/help'),
            'contact'  => url('/contact'),
        ];
        $defaultLabels = [
            'events'  => '📷 อีเวนต์',
            'orders'  => '🛍️ ออเดอร์',
            'face'    => '🔍 ค้นหาด้วยใบหน้า',
            'promo'   => '✨ จุดเด่น',
            'help'    => '❓ ช่วยเหลือ',
            'contact' => '📞 ติดต่อเรา',
        ];

        $u = array_merge($defaults, array_filter($urls));
        $l = array_merge($defaultLabels, array_filter($labels));

        // 2500 × 1686 split into 3 cols × 2 rows  →  cell 833 × 843
        $cellW = 834; // 2500 / 3 (rounded up; LINE accepts whole pixels, last col covers remainder)
        $cellH = 843; // 1686 / 2

        $area = function (int $col, int $row, string $url, string $label) use ($cellW, $cellH): array {
            $x = $col * $cellW;
            $y = $row * $cellH;
            // Last column extends to fill the right edge (handles 2500/3 rounding).
            $w = ($col === 2) ? (2500 - $x) : $cellW;
            $h = $cellH;
            return [
                'bounds' => ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h],
                'action' => [
                    'type'  => 'uri',
                    'label' => mb_substr($label, 0, 20), // LINE caps label at 20 chars
                    'uri'   => $url,
                ],
            ];
        };

        return [
            'size'        => ['width' => 2500, 'height' => 1686],
            'selected'    => true,
            'name'        => 'Storefront Menu — ' . now()->format('Y-m-d H:i'),
            'chatBarText' => 'เมนู',
            'areas' => [
                $area(0, 0, $u['events'],  $l['events']),
                $area(1, 0, $u['orders'],  $l['orders']),
                $area(2, 0, $u['face'],    $l['face']),
                $area(0, 1, $u['promo'],   $l['promo']),
                $area(1, 1, $u['help'],    $l['help']),
                $area(2, 1, $u['contact'], $l['contact']),
            ],
        ];
    }

    /**
     * Generate a placeholder rich menu image (2500×1686 PNG) on disk and return
     * its absolute path. Useful when admin hasn't uploaded a custom design yet —
     * lets them deploy + test the menu without leaving the page.
     *
     * Requires GD extension (bundled with most XAMPP/PHP installs).
     *
     * @return string|null absolute path, or null if GD missing.
     */
    public function generatePlaceholderImage(array $labels = []): ?string
    {
        if (!extension_loaded('gd')) return null;

        $defaults = [
            'events'  => 'อีเวนต์',
            'orders'  => 'ออเดอร์',
            'face'    => 'ค้นใบหน้า',
            'promo'   => 'จุดเด่น',
            'help'    => 'ช่วยเหลือ',
            'contact' => 'ติดต่อเรา',
        ];
        $l = array_merge($defaults, array_filter($labels));

        $w = 2500;
        $h = 1686;
        $img = imagecreatetruecolor($w, $h);

        // Background gradient (top: indigo → bottom: violet)
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            $r = (int) round((1 - $t) * 99 + $t * 167);   // 99 → 167
            $g = (int) round((1 - $t) * 102 + $t * 85);   // 102 → 85
            $b = (int) round((1 - $t) * 241 + $t * 247);  // 241 → 247
            $color = imagecolorallocate($img, $r, $g, $b);
            imageline($img, 0, $y, $w, $y, $color);
        }

        // Cell borders + labels
        $white     = imagecolorallocate($img, 255, 255, 255);
        $whiteSoft = imagecolorallocatealpha($img, 255, 255, 255, 90);
        $cellW     = (int) ($w / 3);
        $cellH     = (int) ($h / 2);

        $cells = [
            [0, 0, $l['events']],
            [1, 0, $l['orders']],
            [2, 0, $l['face']],
            [0, 1, $l['promo']],
            [1, 1, $l['help']],
            [2, 1, $l['contact']],
        ];

        foreach ($cells as [$col, $row, $label]) {
            $x = $col * $cellW;
            $y = $row * $cellH;
            $cw = ($col === 2) ? ($w - $x) : $cellW;
            $ch = $cellH;

            // Subtle inner panel
            imagefilledrectangle($img, $x + 20, $y + 20, $x + $cw - 20, $y + $ch - 20, $whiteSoft);

            // Outer border
            for ($t = 0; $t < 4; $t++) {
                imagerectangle($img, $x + $t, $y + $t, $x + $cw - $t - 1, $y + $ch - $t - 1, $white);
            }

            // Label (centered, large)
            $fontSize = 5; // built-in font size 1..5 ; small but readable on phone
            $textWidth  = imagefontwidth($fontSize) * mb_strlen($label, 'UTF-8');
            $textHeight = imagefontheight($fontSize);
            $tx = $x + (int) (($cw - $textWidth) / 2);
            $ty = $y + (int) (($ch - $textHeight) / 2);
            // Built-in fonts only support latin — render a marker dot for Thai labels
            // and leave the actual label burning to the admin's custom design.
            imagefilledellipse($img, $x + (int) ($cw / 2), $y + (int) ($ch / 2) - 30, 60, 60, $white);
            // Cell number
            imagestring($img, $fontSize, $tx, $ty + 50, '#' . (count($cells, COUNT_RECURSIVE) > 0 ? '' : ''), $white);
        }

        // Watermark "PLACEHOLDER"
        $tagFont = 5;
        $tag = 'PLACEHOLDER (admin: replace with branded image)';
        $tw  = imagefontwidth($tagFont) * strlen($tag);
        imagestring($img, $tagFont, (int) (($w - $tw) / 2), $h - 60, $tag, $white);

        $tmpDir = storage_path('app/line-richmenu');
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0755, true);
        $path = $tmpDir . '/placeholder-' . now()->format('YmdHis') . '.png';
        imagepng($img, $path, 9);
        imagedestroy($img);

        return is_file($path) ? $path : null;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function errMsg($resp): string
    {
        $body = $resp->body();
        $json = $resp->json();
        if (is_array($json) && isset($json['message'])) {
            return 'HTTP ' . $resp->status() . ' — ' . $json['message']
                . (isset($json['details'][0]['message']) ? ' (' . $json['details'][0]['message'] . ')' : '');
        }
        return 'HTTP ' . $resp->status() . ' — ' . substr($body, 0, 240);
    }

    private function guessImageMime(string $path): string
    {
        $info = @getimagesize($path);
        if ($info && isset($info['mime'])) return $info['mime'];
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'png'         => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default       => 'application/octet-stream',
        };
    }
}
