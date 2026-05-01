<?php

namespace App\Services;

use App\Models\AppSetting;
use GdImage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Watermark compositor used by the photo processing pipeline.
 *
 * Applies either a text overlay or a PNG/SVG image watermark onto the
 * original photo according to admin settings (see /admin/settings/watermark).
 *
 * Historical quirk we deliberately handle here:
 *   The settings page writes `watermark_image_path` and `watermark_size_percent`,
 *   but early builds of this service read `watermark_image` / `watermark_size`.
 *   That silent divergence meant "Image Watermark" and the size slider did
 *   nothing even though the form saved happily. getSetting() below resolves
 *   the new keys first and falls back to the legacy names so old installs
 *   don't break.
 */
class WatermarkService
{
    /** @var array|null Cached settings to avoid repeated DB queries */
    private static ?array $cachedSettings = null;

    /**
     * Alias map for settings that exist under two names. First key = canonical
     * (used by the current admin UI), second key = legacy fallback so older
     * seeded data keeps working.
     */
    private const SETTING_ALIASES = [
        'watermark_image' => ['watermark_image_path', 'watermark_image'],
        'watermark_size'  => ['watermark_size_percent', 'watermark_size'],
    ];

    /**
     * Check if watermarking is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->getSetting('watermark_enabled', '0') === '1';
    }

    /**
     * Apply watermark to an image file and return the watermarked image as binary data.
     *
     * @param string $imagePath Absolute path to the image file
     * @return string Binary image data (JPEG, quality 90)
     */
    public function apply(string $imagePath): string
    {
        // Read raw file data first for fallback
        $rawData = @file_get_contents($imagePath);
        if ($rawData === false) {
            return '';
        }

        if (!$this->isEnabled()) {
            return $rawData;
        }

        if (!extension_loaded('gd')) {
            Log::warning('WatermarkService: GD extension not loaded, skipping watermark.');
            return $rawData;
        }

        if (!file_exists($imagePath)) {
            return $rawData;
        }

        $imageInfo = @getimagesize($imagePath);
        if (!$imageInfo) {
            return $rawData;
        }

        $srcType = $imageInfo[2];
        $image = $this->createGdImage($imagePath, $srcType);

        if (!$image) {
            return $rawData;
        }

        // Enable alphablending on the dest so text/image composites respect
        // per-pixel alpha. Without this, transparent PNGs paint as black.
        imagealphablending($image, true);

        $type = $this->getSetting('watermark_type', 'text');

        try {
            if ($type === 'image') {
                $image = $this->applyImage($image);
            } else {
                $image = $this->applyText($image);
            }
        } catch (\Throwable $e) {
            Log::warning('WatermarkService: apply failed, returning raw image', [
                'error' => $e->getMessage(),
                'type'  => $type,
            ]);
            imagedestroy($image);
            return $rawData;
        }

        // Capture JPEG output at quality 90
        ob_start();
        imagejpeg($image, null, 90);
        $result = ob_get_clean();
        imagedestroy($image);

        return $result ?: $rawData;
    }

    /**
     * Apply text watermark using GD (imagettftext or fallback to imagestring).
     *
     * Position handling:
     *   • diagonal   — single line of text rotated ~30° across the image center
     *   • tiled      — repeated grid of text across the whole image
     *   • center / corners — single placement at the named anchor
     */
    private function applyText(GdImage $image): GdImage
    {
        $text     = $this->getSetting('watermark_text', (string) config('app.name', 'Photo Gallery'));
        $opacity  = (int) $this->getSetting('watermark_opacity', '50');
        $colorHex = $this->getSetting('watermark_color', '#FFFFFF');
        $position = $this->getSetting('watermark_position', 'center');
        $sizePct  = (int) $this->getSetting('watermark_size', '30');

        $imgW = imagesx($image);
        $imgH = imagesy($image);

        // Parse hex color (strip #, pad short form like #fff → ffffff)
        $hex = ltrim($colorHex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Map opacity 0–100 → alpha 0–127 (127 = fully transparent, 0 = opaque)
        $alpha = (int) (127 * (1 - max(0, min(100, $opacity)) / 100));
        $textColor   = imagecolorallocatealpha($image, $r, $g, $b, $alpha);
        $shadowAlpha = min(127, $alpha + 20);
        $shadowColor = imagecolorallocatealpha($image, 0, 0, 0, $shadowAlpha);

        // Font size relative to image width and text length.
        // Tighter divisor for tiled so the repeated pattern doesn't overwhelm.
        $textLen   = max(1, mb_strlen($text));
        $divisor   = $position === 'tiled' ? 2.5 : 1.5;
        $fontSize  = max(12, (int) ($imgW * $sizePct / 100 / $textLen * $divisor));
        $fontSize  = min($fontSize, 250);

        $fontFile = $this->getFontPath();

        if ($fontFile && file_exists($fontFile) && function_exists('imagettftext')) {
            $this->drawTextTtf($image, $text, $fontFile, $fontSize, $position,
                               $textColor, $shadowColor, $imgW, $imgH);
        } else {
            $this->drawTextBuiltin($image, $text, $position, $textColor, $shadowColor, $imgW, $imgH);
        }

        return $image;
    }

    /**
     * TrueType path — covers every position including diagonal + tiled.
     */
    private function drawTextTtf(
        GdImage $image, string $text, string $fontFile, int $fontSize,
        string $position, int $textColor, int $shadowColor, int $imgW, int $imgH
    ): void {
        $sh = max(1, (int) ($fontSize * 0.04));

        if ($position === 'diagonal') {
            $angle = 30; // counter-clockwise
            $box   = imagettfbbox($fontSize, $angle, $fontFile, $text);
            $minX  = min($box[0], $box[2], $box[4], $box[6]);
            $maxX  = max($box[0], $box[2], $box[4], $box[6]);
            $minY  = min($box[1], $box[3], $box[5], $box[7]);
            $maxY  = max($box[1], $box[3], $box[5], $box[7]);
            $textW = $maxX - $minX;
            $textH = $maxY - $minY;

            // Center the rotated bbox on the image.
            $x = (int) (($imgW - $textW) / 2) - $minX;
            $y = (int) (($imgH - $textH) / 2) - $minY;

            imagettftext($image, $fontSize, $angle, $x + $sh, $y + $sh, $shadowColor, $fontFile, $text);
            imagettftext($image, $fontSize, $angle, $x, $y, $textColor, $fontFile, $text);
            return;
        }

        if ($position === 'tiled') {
            $box   = imagettfbbox($fontSize, 0, $fontFile, $text);
            $textW = abs($box[4] - $box[0]);
            $textH = abs($box[5] - $box[1]);

            // Tile spacing multiplier (see drawImageTiled() for the same
            // contract). 100 = default, 40 = tight, 200 = airy.
            $spacingPct = max(40, min(200, (int) $this->getSetting('watermark_tile_spacing', '100')));
            $factor = $spacingPct / 100.0;

            $stepX = max($textW + (int) ($fontSize * 1.5 * $factor), 120);
            $stepY = max((int) ($textH * 3 * $factor), 80);

            // Start from -stepX/2 so the top row isn't hugged against the edge.
            for ($yy = $textH; $yy < $imgH + $textH; $yy += $stepY) {
                $xOffset = (intdiv($yy, $stepY) % 2 === 0) ? 0 : (int) ($stepX / 2);
                for ($xx = -$xOffset; $xx < $imgW; $xx += $stepX) {
                    imagettftext($image, $fontSize, 0, $xx + $sh, $yy + $sh, $shadowColor, $fontFile, $text);
                    imagettftext($image, $fontSize, 0, $xx, $yy, $textColor, $fontFile, $text);
                }
            }
            return;
        }

        // Single-shot placement.
        $box   = imagettfbbox($fontSize, 0, $fontFile, $text);
        $textW = abs($box[4] - $box[0]);
        $textH = abs($box[5] - $box[1]);
        [$x, $y] = $this->calculatePosition($imgW, $imgH, $textW, $textH, $position);

        imagettftext($image, $fontSize, 0, $x + $sh, $y + $sh, $shadowColor, $fontFile, $text);
        imagettftext($image, $fontSize, 0, $x, $y, $textColor, $fontFile, $text);
    }

    /**
     * Built-in-font fallback when no TTF is available (and no rotation).
     * Approximates diagonal as center, tiled as a grid using font 5.
     */
    private function drawTextBuiltin(
        GdImage $image, string $text, string $position,
        int $textColor, int $shadowColor, int $imgW, int $imgH
    ): void {
        $font  = 5;
        $textW = imagefontwidth($font) * strlen($text);
        $textH = imagefontheight($font);

        if ($position === 'tiled') {
            $stepX = max($textW + 40, 120);
            $stepY = $textH * 4;
            for ($yy = 10; $yy < $imgH; $yy += $stepY) {
                for ($xx = 10; $xx < $imgW; $xx += $stepX) {
                    imagestring($image, $font, $xx + 1, $yy + 1, $text, $shadowColor);
                    imagestring($image, $font, $xx, $yy, $text, $textColor);
                }
            }
            return;
        }

        // Fallback diagonal = centered (no rotation support in built-in).
        $effectivePosition = $position === 'diagonal' ? 'center' : $position;
        [$x, $y] = $this->calculatePosition($imgW, $imgH, $textW, $textH, $effectivePosition);
        $yStr = max(0, $y - $textH);
        imagestring($image, $font, $x + 1, $yStr + 1, $text, $shadowColor);
        imagestring($image, $font, $x, $yStr, $text, $textColor);
    }

    /**
     * Apply image watermark overlay using GD.
     *
     * This is the painful one — `imagecopymerge()` does not respect the
     * source PNG's alpha channel, so naively calling it flattens a
     * transparent logo into a white/black rectangle. We work around that
     * with a manual pre-multiply pass: we bump every pixel's alpha by
     * (1 - opacity/100) and then use `imagecopy()` with alphablending on
     * the destination, which *does* honour alpha.
     */
    private function applyImage(GdImage $image): GdImage
    {
        $wmPath   = $this->getSetting('watermark_image', '');
        $opacity  = (int) $this->getSetting('watermark_opacity', '50');
        $position = $this->getSetting('watermark_position', 'center');
        $sizePct  = (int) $this->getSetting('watermark_size', '30');

        if (empty($wmPath)) {
            Log::info('WatermarkService: type=image but no watermark_image_path saved; skipping.');
            return $image;
        }

        $fullPath = $this->resolveWatermarkFile($wmPath);
        if ($fullPath === null) {
            Log::warning('WatermarkService: watermark file not found on any disk', ['path' => $wmPath]);
            return $image;
        }

        $wmInfo = @getimagesize($fullPath);
        if (!$wmInfo) {
            return $image;
        }

        $wmImg = $this->createGdImage($fullPath, $wmInfo[2]);
        if (!$wmImg) {
            return $image;
        }

        $imgW = imagesx($image);
        $imgH = imagesy($image);
        $wmW  = imagesx($wmImg);
        $wmH  = imagesy($wmImg);

        // Scale watermark to sizePct of image width (preserve aspect).
        $targetW = max(1, (int) ($imgW * $sizePct / 100));
        $targetH = max(1, (int) ($wmH * $targetW / $wmW));

        $scaled = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        $transparent = imagecolorallocatealpha($scaled, 0, 0, 0, 127);
        imagefilledrectangle($scaled, 0, 0, $targetW, $targetH, $transparent);
        imagecopyresampled($scaled, $wmImg, 0, 0, 0, 0, $targetW, $targetH, $wmW, $wmH);
        imagedestroy($wmImg);

        // Bump alpha per-pixel so we honour the opacity slider WITHOUT
        // killing the PNG's own transparency.
        $this->premultiplyAlpha($scaled, $opacity);

        if ($position === 'tiled') {
            $this->drawImageTiled($image, $scaled, $imgW, $imgH, $targetW, $targetH);
        } elseif ($position === 'diagonal') {
            $this->drawImageDiagonal($image, $scaled, $imgW, $imgH, $targetW, $targetH);
        } else {
            [$x, $y] = $this->calculatePositionImage($imgW, $imgH, $targetW, $targetH, $position);
            imagecopy($image, $scaled, $x, $y, 0, 0, $targetW, $targetH);
        }

        imagedestroy($scaled);
        return $image;
    }

    /**
     * Iterate every pixel and push its alpha toward "more transparent" by
     * (1 - opacity/100). Fully-transparent pixels stay transparent,
     * fully-opaque pixels become `127 * (1 - opacity/100)` transparent.
     */
    private function premultiplyAlpha(GdImage $img, int $opacity): void
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $factor = max(0, min(100, $opacity)) / 100.0;
        // Early-out when opacity=100 (no change needed).
        if ($factor >= 1.0) {
            return;
        }
        imagealphablending($img, false);
        imagesavealpha($img, true);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorat($img, $x, $y);
                $a = ($rgba >> 24) & 0x7F;
                // Already transparent → nothing to do.
                if ($a === 127) {
                    continue;
                }
                // Scale opaqueness (127 - a) by factor, then convert back.
                $opaqueness = (127 - $a) * $factor;
                $newA = 127 - (int) round($opaqueness);
                if ($newA === $a) {
                    continue;
                }
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $bl = $rgba & 0xFF;
                imagesetpixel($img, $x, $y, ($newA << 24) | ($r << 16) | ($g << 8) | $bl);
            }
        }
    }

    private function drawImageTiled(
        GdImage $dest, GdImage $wm, int $imgW, int $imgH, int $wmW, int $wmH
    ): void {
        // Tile-spacing multiplier from settings (40-200, default 100).
        // 100 reproduces the original gutter (~40% of wm width).
        // 40   = very tight (~16% gutter), useful for piracy protection.
        // 200  = airy (~80% gutter), preserves more of the image.
        $spacingPct = max(40, min(200, (int) $this->getSetting('watermark_tile_spacing', '100')));
        $factor = $spacingPct / 100.0;

        $stepX = max($wmW + (int) ($wmW * 0.4 * $factor), 80);
        $stepY = max($wmH + (int) ($wmH * 0.6 * $factor), 80);

        imagealphablending($dest, true);
        for ($y = 0; $y < $imgH; $y += $stepY) {
            $xOff = (intdiv($y, $stepY) % 2 === 0) ? 0 : (int) ($stepX / 2);
            for ($x = -$xOff; $x < $imgW; $x += $stepX) {
                imagecopy($dest, $wm, $x, $y, 0, 0, $wmW, $wmH);
            }
        }
    }

    private function drawImageDiagonal(
        GdImage $dest, GdImage $wm, int $imgW, int $imgH, int $wmW, int $wmH
    ): void {
        // Rotate the scaled watermark -30°. imagerotate returns a new canvas.
        $transparent = imagecolorallocatealpha($wm, 0, 0, 0, 127);
        $rotated = imagerotate($wm, 30, $transparent);
        if (!$rotated) {
            $x = (int) (($imgW - $wmW) / 2);
            $y = (int) (($imgH - $wmH) / 2);
            imagealphablending($dest, true);
            imagecopy($dest, $wm, $x, $y, 0, 0, $wmW, $wmH);
            return;
        }
        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);

        $rW = imagesx($rotated);
        $rH = imagesy($rotated);
        $x = (int) (($imgW - $rW) / 2);
        $y = (int) (($imgH - $rH) / 2);

        imagealphablending($dest, true);
        imagecopy($dest, $rotated, $x, $y, 0, 0, $rW, $rH);
        imagedestroy($rotated);
    }

    /**
     * Resolve a saved watermark path to an absolute file on disk.
     *
     * Checks in order:
     *   1. Raw path (if absolute and exists)
     *   2. storage/app/public/... (local public disk — the usual case)
     *   3. StorageManager primary disk (R2/S3): download to a temp file
     *
     * Returns null when nothing can be found.
     */
    private function resolveWatermarkFile(string $wmPath): ?string
    {
        // Absolute path passed in directly
        if (is_file($wmPath)) {
            return $wmPath;
        }

        // Local public disk
        $local = storage_path('app/public/' . ltrim($wmPath, '/'));
        if (is_file($local)) {
            return $local;
        }

        // Fall back to whatever disk the StorageManager considers primary
        // (e.g. R2). Download to temp and return that path — caller treats
        // the result as read-only so the leak is contained to this request.
        try {
            $manager = app(StorageManager::class);
            $disk    = $manager->primaryDriver();
            if ($disk && Storage::disk($disk)->exists($wmPath)) {
                $bytes = Storage::disk($disk)->get($wmPath);
                if ($bytes !== null && $bytes !== false) {
                    $tmp = tempnam(sys_get_temp_dir(), 'wm_') . '.' . pathinfo($wmPath, PATHINFO_EXTENSION);
                    if (@file_put_contents($tmp, $bytes) !== false) {
                        return $tmp;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::debug('WatermarkService: remote watermark fetch failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Calculate X/Y for text placement. X is the LEFT edge, Y is the BASELINE
     * (imagettftext / imagestring y-origin convention for TrueType).
     */
    private function calculatePosition(int $imgW, int $imgH, int $wmW, int $wmH, string $position): array
    {
        $margin = (int) ($imgW * 0.03);

        switch ($position) {
            case 'center':
                $x = (int) (($imgW - $wmW) / 2);
                $y = (int) (($imgH + $wmH) / 2);
                break;
            case 'top-left':
                $x = $margin;
                $y = $margin + $wmH;
                break;
            case 'top-right':
                $x = $imgW - $wmW - $margin;
                $y = $margin + $wmH;
                break;
            case 'bottom-left':
                $x = $margin;
                $y = $imgH - $margin;
                break;
            case 'bottom-right':
            default:
                $x = $imgW - $wmW - $margin;
                $y = $imgH - $margin;
                break;
        }

        return [$x, $y];
    }

    /**
     * Same as calculatePosition() but y-origin is the TOP edge — what
     * imagecopy / imagecopyresampled expect.
     */
    private function calculatePositionImage(int $imgW, int $imgH, int $wmW, int $wmH, string $position): array
    {
        $margin = (int) ($imgW * 0.03);

        switch ($position) {
            case 'center':
                $x = (int) (($imgW - $wmW) / 2);
                $y = (int) (($imgH - $wmH) / 2);
                break;
            case 'top-left':
                $x = $margin;
                $y = $margin;
                break;
            case 'top-right':
                $x = $imgW - $wmW - $margin;
                $y = $margin;
                break;
            case 'bottom-left':
                $x = $margin;
                $y = $imgH - $wmH - $margin;
                break;
            case 'bottom-right':
            default:
                $x = $imgW - $wmW - $margin;
                $y = $imgH - $wmH - $margin;
                break;
        }

        return [$x, $y];
    }

    /**
     * Get a single watermark setting with a default fallback.
     *
     * Respects the SETTING_ALIASES map so callers can keep asking for
     * `watermark_image` / `watermark_size` while the DB column name moved
     * to `watermark_image_path` / `watermark_size_percent`.
     */
    private function getSetting(string $key, string $default = ''): string
    {
        $all = $this->getSettings();

        // Try aliases first so the new (UI-side) name wins.
        foreach (self::SETTING_ALIASES[$key] ?? [$key] as $candidate) {
            $value = $all[$candidate] ?? null;
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return $default;
    }

    /**
     * Get all watermark settings (cached per-instance call chain to avoid
     * re-hitting AppSetting for every single key).
     */
    private function getSettings(): array
    {
        if (self::$cachedSettings !== null) {
            return self::$cachedSettings;
        }

        $keys = [
            'watermark_enabled',
            'watermark_type',
            'watermark_text',
            'watermark_opacity',
            'watermark_color',
            'watermark_position',
            // Canonical (current UI) + legacy aliases.
            'watermark_size_percent', 'watermark_size',
            'watermark_image_path',   'watermark_image',
        ];

        self::$cachedSettings = [];

        try {
            $all = AppSetting::getAll();
            foreach ($keys as $key) {
                self::$cachedSettings[$key] = $all[$key] ?? '';
            }
        } catch (\Exception $e) {
            // DB not available — use empty defaults
        }

        return self::$cachedSettings;
    }

    /** Useful for tests / after admin saves — forces a fresh read. */
    public static function flushCache(): void
    {
        self::$cachedSettings = null;
    }

    /**
     * Create a GD image resource from a file path based on image type.
     */
    private function createGdImage(string $path, int $type): GdImage|false
    {
        $img = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_GIF  => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default        => false,
        };
        if ($img && $type === IMAGETYPE_PNG) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }
        return $img;
    }

    /**
     * Find a suitable TrueType font file (Noto Sans Thai or system fallback).
     */
    private function getFontPath(): ?string
    {
        $candidates = [
            public_path('fonts/NotoSansThai-Regular.ttf'),
            public_path('fonts/watermark.ttf'),
            resource_path('fonts/NotoSansThai-Regular.ttf'),
            storage_path('fonts/NotoSansThai-Regular.ttf'),
            'C:/Windows/Fonts/arial.ttf',
            'C:/Windows/Fonts/tahoma.ttf',
            '/usr/share/fonts/truetype/noto/NotoSans-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
