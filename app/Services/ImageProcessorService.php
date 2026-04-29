<?php

namespace App\Services;

use GdImage;
use App\Models\AppSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageProcessorService
{
    /**
     * Default settings per context (fallbacks when admin hasn't configured).
     */
    private const DEFAULTS = [
        'cover'  => ['max_width' => 1920, 'max_height' => 1080, 'quality' => 85, 'format' => 'webp'],
        'avatar' => ['max_width' => 400,  'max_height' => 400,  'quality' => 80, 'format' => 'webp'],
        'slip'   => ['max_width' => 1200, 'max_height' => 1600, 'quality' => 90, 'format' => 'jpeg'],
        'seo'    => ['max_width' => 1200, 'max_height' => 630,  'quality' => 85, 'format' => 'jpeg'],
    ];

    /**
     * Process and store an uploaded image based on admin settings.
     *
     * Reads the img_* settings from AppSetting and performs:
     *   1) Auto-rotate (EXIF) for JPEG
     *   2) Resize to max dimensions
     *   3) Convert to configured format (webp/jpeg/png)
     *   4) Store with correct extension
     *
     * If processing is disabled (master or per-context), stores original.
     *
     * @param  UploadedFile $file      The uploaded file
     * @param  string       $context   Processing context: cover, avatar, slip, seo
     * @param  string       $directory Storage sub-directory e.g. 'events/covers'
     * @param  string       $disk      Storage disk (default: public)
     * @return string Stored path relative to disk root
     */
    public function processUpload(UploadedFile $file, string $context, string $directory, string $disk = 'public'): string
    {
        // ── Check if processing is enabled ──────────────────────────
        $masterEnabled  = (bool) AppSetting::get('img_processing_enabled', false);
        $contextEnabled = (bool) AppSetting::get("img_{$context}_enabled", false);

        if (!$masterEnabled || !$contextEnabled || !extension_loaded('gd')) {
            // Store original file unchanged
            return $file->store($directory, $disk);
        }

        // ── Read context settings ───────────────────────────────────
        $defaults  = self::DEFAULTS[$context] ?? self::DEFAULTS['cover'];
        $maxWidth  = (int) AppSetting::get("img_{$context}_max_width",  $defaults['max_width']);
        $maxHeight = (int) AppSetting::get("img_{$context}_max_height", $defaults['max_height']);
        $quality   = (int) AppSetting::get("img_{$context}_quality",    $defaults['quality']);
        $format    = AppSetting::get("img_{$context}_format", $defaults['format']); // webp, jpeg, png, original

        // ── Load & process image ────────────────────────────────────
        $sourcePath = $file->getRealPath();
        [$image, $srcType] = $this->load($sourcePath);

        if (!$image) {
            return $file->store($directory, $disk);
        }

        // Auto-rotate JPEG based on EXIF
        if ($srcType === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($sourcePath);
            $orientation = $exif['Orientation'] ?? 1;
            if ($orientation !== 1) {
                $rotated = match ($orientation) {
                    3       => imagerotate($image, 180, 0),
                    6       => imagerotate($image, -90, 0),
                    8       => imagerotate($image, 90, 0),
                    default => null,
                };
                if ($rotated) {
                    imagedestroy($image);
                    $image = $rotated;
                }
            }
        }

        // ── Resize if larger than max dimensions ────────────────────
        $srcW = imagesx($image);
        $srcH = imagesy($image);
        [$newW, $newH] = $this->calcAspectRatio($srcW, $srcH, $maxWidth, $maxHeight);

        if ($newW !== $srcW || $newH !== $srcH) {
            $targetType = ($format === 'original') ? $srcType : $this->formatToType($format);
            $resized = $this->createCanvas($newW, $newH, $targetType);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
            imagedestroy($image);
            $image = $resized;
        }

        // ── Determine output format ─────────────────────────────────
        $outputType = ($format === 'original') ? $srcType : $this->formatToType($format);
        $extension  = $this->typeToExtension($outputType);

        // ── Encode to binary ────────────────────────────────────────
        $binary = $this->encodeImage($image, $outputType, $quality);
        imagedestroy($image);

        if (!$binary) {
            return $file->store($directory, $disk);
        }

        // ── Store processed file ────────────────────────────────────
        $filename = Str::random(40) . '.' . $extension;
        $path     = rtrim($directory, '/') . '/' . $filename;

        Storage::disk($disk)->put($path, $binary);

        return $path;
    }

    /**
     * Payment-slip uploader with a hard ceiling on the stored file size.
     *
     * Slips are always compressed — unlike the generic processUpload() which
     * respects the admin's per-context toggles, this one treats the 2 MB
     * ceiling as a product rule (ลูกค้าอัปโหลดสลิป 4K ได้สบาย, แต่เราจะ
     * เก็บแบบกะทัดรัดให้ R2 storage cost ต่ำและ admin โหลดตรวจเร็ว).
     *
     * The cap is applied by an adaptive encode loop:
     *   1. Resize to slip dimensions (1200 × 1600).
     *   2. Try decreasing JPEG qualities until output ≤ $targetBytes.
     *   3. If still over at quality 50, down-scale by 0.8× and retry.
     *   4. Worst-case fallback at quality 40 + 0.5× scale — slips are small
     *      documents with mostly text/logos, so this is more than legible.
     *
     * @param UploadedFile $file        The uploaded slip image
     * @param string       $directory   Storage sub-directory (e.g. users/5/payment-slips)
     * @param string       $disk        Storage disk (default: uploadDriver via caller)
     * @param int          $targetBytes Max allowed output size in bytes (default 2 MiB)
     * @return string Stored path relative to disk root
     */
    public function processSlipUpload(
        UploadedFile $file,
        string $directory,
        string $disk = 'public',
        int $targetBytes = 2 * 1024 * 1024
    ): string {
        // If GD is missing, store as-is — slip verification still works off
        // the raw bytes; only the UI preview is larger than it needs to be.
        if (!extension_loaded('gd')) {
            return $file->store($directory, $disk);
        }

        $sourcePath = $file->getRealPath();
        [$image, $srcType] = $this->load($sourcePath);
        if (!$image) {
            return $file->store($directory, $disk);
        }

        // EXIF auto-rotate — phone cameras routinely upload sideways JPEGs.
        if ($srcType === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($sourcePath);
            $orientation = $exif['Orientation'] ?? 1;
            if ($orientation !== 1) {
                $rotated = match ($orientation) {
                    3       => imagerotate($image, 180, 0),
                    6       => imagerotate($image, -90, 0),
                    8       => imagerotate($image, 90, 0),
                    default => null,
                };
                if ($rotated) {
                    imagedestroy($image);
                    $image = $rotated;
                }
            }
        }

        // Allow admin overrides to stay — default at slip presets.
        $defaults  = self::DEFAULTS['slip'];
        $maxWidth  = (int) AppSetting::get('img_slip_max_width',  $defaults['max_width']);
        $maxHeight = (int) AppSetting::get('img_slip_max_height', $defaults['max_height']);

        $srcW = imagesx($image);
        $srcH = imagesy($image);
        [$newW, $newH] = $this->calcAspectRatio($srcW, $srcH, $maxWidth, $maxHeight);
        if ($newW !== $srcW || $newH !== $srcH) {
            $canvas = $this->createCanvas($newW, $newH, IMAGETYPE_JPEG);
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
            imagedestroy($image);
            $image = $canvas;
        }

        // Adaptive-encode loop — aim for ≤ target bytes. Slips are almost
        // all JPEG in practice (banking apps); forcing JPEG output keeps the
        // size predictable (no transparency-blowup from PNG).
        $qualities  = [90, 85, 80, 75, 70, 65, 60, 55, 50];
        $binary     = '';
        foreach ($qualities as $q) {
            $binary = $this->encodeImage($image, IMAGETYPE_JPEG, $q);
            if ($binary !== false && strlen($binary) <= $targetBytes) {
                break;
            }
        }

        // Still too big at quality 50? Down-scale in two steps and retry.
        if ($binary === false || strlen($binary) > $targetBytes) {
            foreach ([0.8, 0.5] as $scale) {
                $sw = max(600, (int) round(imagesx($image) * $scale));
                $sh = max(800, (int) round(imagesy($image) * $scale));
                $shrunk = $this->createCanvas($sw, $sh, IMAGETYPE_JPEG);
                imagecopyresampled($shrunk, $image, 0, 0, 0, 0, $sw, $sh, imagesx($image), imagesy($image));
                foreach ([60, 50, 40] as $q) {
                    $candidate = $this->encodeImage($shrunk, IMAGETYPE_JPEG, $q);
                    if ($candidate !== false && strlen($candidate) <= $targetBytes) {
                        $binary = $candidate;
                        imagedestroy($image);
                        $image  = $shrunk;
                        break 2;
                    }
                }
                imagedestroy($shrunk);
            }
        }

        imagedestroy($image);

        if ($binary === false || $binary === '') {
            return $file->store($directory, $disk);
        }

        $filename = Str::random(40) . '.jpg';
        $path     = rtrim($directory, '/') . '/' . $filename;
        Storage::disk($disk)->put($path, $binary);
        return $path;
    }

    /**
     * Check whether image processing is enabled for a given context.
     */
    public function isEnabled(string $context = ''): bool
    {
        $master = (bool) AppSetting::get('img_processing_enabled', false);
        if (!$master) return false;
        if ($context) {
            return (bool) AppSetting::get("img_{$context}_enabled", false);
        }
        return $master;
    }

    // ── Format helpers ──────────────────────────────────────────────

    private function formatToType(string $format): int
    {
        return match (strtolower($format)) {
            'webp' => IMAGETYPE_WEBP,
            'png'  => IMAGETYPE_PNG,
            'gif'  => IMAGETYPE_GIF,
            default => IMAGETYPE_JPEG, // jpeg, jpg
        };
    }

    private function typeToExtension(int $type): string
    {
        return match ($type) {
            IMAGETYPE_WEBP => 'webp',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_GIF  => 'gif',
            default        => 'jpg',
        };
    }

    private function encodeImage(GdImage $image, int $type, int $quality): string|false
    {
        ob_start();
        match ($type) {
            IMAGETYPE_PNG  => (function () use ($image, $quality) {
                imagealphablending($image, false);
                imagesavealpha($image, true);
                imagepng($image, null, min(9, (int) round((100 - $quality) / 10)));
            })(),
            IMAGETYPE_GIF  => imagegif($image),
            IMAGETYPE_WEBP => imagewebp($image, null, $quality),
            default        => imagejpeg($image, null, $quality),
        };
        return ob_get_clean();
    }

    /**
     * Resize image maintaining aspect ratio.
     * Returns the processed binary image data (JPEG).
     *
     * @param string $path     Absolute path to the source image
     * @param int    $maxWidth  Maximum width in pixels
     * @param int    $maxHeight Maximum height in pixels
     * @return string Binary image data
     */
    public function resize(string $path, int $maxWidth, int $maxHeight): string
    {
        $raw = file_get_contents($path);

        if (!extension_loaded('gd') || !file_exists($path)) {
            return $raw;
        }

        [$image, $srcType] = $this->load($path);
        if (!$image) {
            return $raw;
        }

        $srcW = imagesx($image);
        $srcH = imagesy($image);

        [$newW, $newH] = $this->calcAspectRatio($srcW, $srcH, $maxWidth, $maxHeight);

        if ($newW === $srcW && $newH === $srcH) {
            $result = $this->capture($image, $srcType);
            imagedestroy($image);
            return $result ?: $raw;
        }

        $resized = $this->createCanvas($newW, $newH, $srcType);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
        imagedestroy($image);

        $result = $this->capture($resized, $srcType);
        imagedestroy($resized);

        return $result ?: $raw;
    }

    /**
     * Create a square thumbnail by crop-to-fit.
     *
     * @param string $path Absolute path to the source image
     * @param int    $size Side length of the square thumbnail
     * @return string Binary image data (JPEG)
     */
    public function thumbnail(string $path, int $size = 300): string
    {
        $raw = file_get_contents($path);

        if (!extension_loaded('gd') || !file_exists($path)) {
            return $raw;
        }

        [$image, $srcType] = $this->load($path);
        if (!$image) {
            return $raw;
        }

        $srcW = imagesx($image);
        $srcH = imagesy($image);

        // Determine crop coordinates (center crop)
        $ratio = $srcW / $srcH;

        if ($ratio > 1) {
            // Wider than tall: fit height, crop width
            $cropH = $srcH;
            $cropW = $srcH;
            $cropX = (int) (($srcW - $cropW) / 2);
            $cropY = 0;
        } else {
            // Taller than wide: fit width, crop height
            $cropW = $srcW;
            $cropH = $srcW;
            $cropX = 0;
            $cropY = (int) (($srcH - $cropH) / 2);
        }

        $thumb = imagecreatetruecolor($size, $size);

        // Preserve transparency for PNG/WebP
        if ($srcType === IMAGETYPE_PNG || $srcType === IMAGETYPE_WEBP) {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
            imagefill($thumb, 0, 0, $transparent);
        }

        imagecopyresampled($thumb, $image, 0, 0, $cropX, $cropY, $size, $size, $cropW, $cropH);
        imagedestroy($image);

        $result = $this->capture($thumb, IMAGETYPE_JPEG);
        imagedestroy($thumb);

        return $result ?: $raw;
    }

    /**
     * Compress an original gallery photo using admin-configured settings.
     *
     * Reads the `photo_compress_*` AppSetting keys (exposed via the
     * "อัปโหลด & แสดงภาพ" admin page) and applies:
     *   1. Auto-rotate via EXIF (before optional strip)
     *   2. Resize within max_width × max_height (aspect-preserving)
     *   3. Re-encode in chosen format at chosen quality
     *   4. Strips EXIF by virtue of re-encoding in GD
     *
     * When `photo_compress_enabled` is OFF, returns the original bytes
     * unchanged (so the pipeline stays idempotent).
     *
     * @param string $path Absolute path to the source image.
     * @return array{bytes: string, extension: string, mime: string, compressed: bool}
     */
    public function compressOriginal(string $path): array
    {
        $raw  = @file_get_contents($path);
        $info = @getimagesize($path);
        $srcType = $info[2] ?? 0;

        $passthrough = function (string $ext = null, string $mime = null) use ($raw, $srcType) {
            return [
                'bytes'      => $raw ?: '',
                'extension'  => $ext ?? $this->typeToExtension($srcType ?: IMAGETYPE_JPEG),
                'mime'       => $mime ?? (image_type_to_mime_type($srcType ?: IMAGETYPE_JPEG)),
                'compressed' => false,
            ];
        };

        if (!extension_loaded('gd') || !$raw || !$info) {
            return $passthrough();
        }

        $enabled = (string) AppSetting::get('photo_compress_enabled', '1') === '1';
        if (!$enabled) {
            return $passthrough();
        }

        $maxW    = max(800, (int) AppSetting::get('photo_compress_max_width',  2560));
        $maxH    = max(800, (int) AppSetting::get('photo_compress_max_height', 2560));
        $quality = max(50, min(100, (int) AppSetting::get('photo_compress_quality', 85)));
        $format  = strtolower((string) AppSetting::get('photo_compress_format', 'jpeg'));

        [$image, ] = $this->load($path);
        if (!$image) {
            return $passthrough();
        }

        // EXIF auto-rotate (JPEG only). Re-encoding after this also strips
        // EXIF since GD's encoders don't preserve it.
        if ($srcType === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $orientation = $exif['Orientation'] ?? 1;
            if ($orientation !== 1) {
                $rotated = match ($orientation) {
                    3       => imagerotate($image, 180, 0),
                    6       => imagerotate($image, -90, 0),
                    8       => imagerotate($image, 90, 0),
                    default => null,
                };
                if ($rotated) {
                    imagedestroy($image);
                    $image = $rotated;
                }
            }
        }

        $srcW = imagesx($image);
        $srcH = imagesy($image);
        [$newW, $newH] = $this->calcAspectRatio($srcW, $srcH, $maxW, $maxH);

        $outputType = match ($format) {
            'webp'     => IMAGETYPE_WEBP,
            'original' => $srcType ?: IMAGETYPE_JPEG,
            default    => IMAGETYPE_JPEG,
        };

        if ($newW !== $srcW || $newH !== $srcH) {
            $canvas = $this->createCanvas($newW, $newH, $outputType);
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
            imagedestroy($image);
            $image = $canvas;
        }

        $binary = $this->encodeImage($image, $outputType, $quality);
        imagedestroy($image);

        if (!$binary) {
            return $passthrough();
        }

        return [
            'bytes'      => $binary,
            'extension'  => $this->typeToExtension($outputType),
            'mime'       => image_type_to_mime_type($outputType),
            'compressed' => true,
        ];
    }

    /**
     * Resize with an explicit quality knob — used by the gallery pipeline
     * for both thumbnails and watermarked previews so admins can dial
     * file-size vs. visual-quality independently of the generic
     * `resize()` helper (which is hard-coded to quality 90/85).
     *
     * Preserves the source image's native type. Returns binary bytes.
     */
    public function resizeWithQuality(string $path, int $maxWidth, int $maxHeight, int $quality): string
    {
        $raw = @file_get_contents($path) ?: '';

        if (!extension_loaded('gd') || !file_exists($path)) {
            return $raw;
        }

        [$image, $srcType] = $this->load($path);
        if (!$image) {
            return $raw;
        }

        $srcW = imagesx($image);
        $srcH = imagesy($image);
        [$newW, $newH] = $this->calcAspectRatio($srcW, $srcH, $maxWidth, $maxHeight);

        if ($newW === $srcW && $newH === $srcH) {
            $out = $this->encodeImage($image, $srcType, $quality);
            imagedestroy($image);
            return $out ?: $raw;
        }

        $canvas = $this->createCanvas($newW, $newH, $srcType);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
        imagedestroy($image);

        $out = $this->encodeImage($canvas, $srcType, $quality);
        imagedestroy($canvas);

        return $out ?: $raw;
    }

    /**
     * Square thumbnail with explicit quality control (center-crop).
     * Always outputs JPEG — thumbs never need alpha.
     */
    public function thumbnailWithQuality(string $path, int $size, int $quality): string
    {
        $raw = @file_get_contents($path) ?: '';

        if (!extension_loaded('gd') || !file_exists($path)) {
            return $raw;
        }

        [$image, ] = $this->load($path);
        if (!$image) {
            return $raw;
        }

        $srcW = imagesx($image);
        $srcH = imagesy($image);

        if ($srcW >= $srcH) {
            $cropW = $srcH;
            $cropH = $srcH;
            $cropX = (int) (($srcW - $cropW) / 2);
            $cropY = 0;
        } else {
            $cropW = $srcW;
            $cropH = $srcW;
            $cropX = 0;
            $cropY = (int) (($srcH - $cropH) / 2);
        }

        $thumb = imagecreatetruecolor($size, $size);
        imagecopyresampled($thumb, $image, 0, 0, $cropX, $cropY, $size, $size, $cropW, $cropH);
        imagedestroy($image);

        $out = $this->encodeImage($thumb, IMAGETYPE_JPEG, $quality);
        imagedestroy($thumb);

        return $out ?: $raw;
    }

    /**
     * Optimize image by re-encoding at reduced quality.
     *
     * @param string $path    Absolute path to the source image
     * @param int    $quality JPEG/WebP quality (0-100)
     * @return string Binary image data
     */
    public function optimize(string $path, int $quality = 85): string
    {
        $raw = file_get_contents($path);

        if (!extension_loaded('gd') || !file_exists($path)) {
            return $raw;
        }

        [$image, $srcType] = $this->load($path);
        if (!$image) {
            return $raw;
        }

        ob_start();
        match ($srcType) {
            IMAGETYPE_PNG  => imagepng($image, null, min(9, (int) round((100 - $quality) / 10))),
            IMAGETYPE_WEBP => imagewebp($image, null, $quality),
            IMAGETYPE_GIF  => imagegif($image),
            default        => imagejpeg($image, null, $quality),
        };
        $result = ob_get_clean();
        imagedestroy($image);

        return $result ?: $raw;
    }

    /**
     * Get image dimensions.
     *
     * @param string $path Absolute path to the image
     * @return array{width: int, height: int}
     */
    public function getDimensions(string $path): array
    {
        $info = @getimagesize($path);

        if (!$info) {
            return ['width' => 0, 'height' => 0];
        }

        return ['width' => $info[0], 'height' => $info[1]];
    }

    /**
     * Convert image to a different format.
     *
     * @param string $path   Absolute path to the source image
     * @param string $format Target format: 'webp', 'jpeg', 'png', 'gif'
     * @return string Binary image data in the target format
     */
    public function convert(string $path, string $format = 'webp'): string
    {
        $raw = file_get_contents($path);

        if (!extension_loaded('gd') || !file_exists($path)) {
            return $raw;
        }

        [$image, ] = $this->load($path);
        if (!$image) {
            return $raw;
        }

        ob_start();
        match (strtolower($format)) {
            'png'        => (function () use ($image) {
                imagealphablending($image, false);
                imagesavealpha($image, true);
                imagepng($image);
            })(),
            'gif'        => imagegif($image),
            'webp'       => imagewebp($image, null, 85),
            default      => imagejpeg($image, null, 90), // jpeg / jpg
        };
        $result = ob_get_clean();
        imagedestroy($image);

        return $result ?: $raw;
    }

    /**
     * Auto-rotate image based on EXIF orientation data.
     *
     * @param string $path Absolute path to the source image
     * @return string Binary image data (corrected orientation)
     */
    public function autoRotate(string $path): string
    {
        $raw = file_get_contents($path);

        if (!extension_loaded('gd') || !file_exists($path)) {
            return $raw;
        }

        $imageInfo = @getimagesize($path);
        if (!$imageInfo) {
            return $raw;
        }

        $srcType = $imageInfo[2];

        // EXIF is only available for JPEG
        if ($srcType !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) {
            return $raw;
        }

        $exif = @exif_read_data($path);
        $orientation = $exif['Orientation'] ?? 1;

        if ($orientation === 1) {
            return $raw; // No rotation needed
        }

        [$image, ] = $this->load($path);
        if (!$image) {
            return $raw;
        }

        $rotated = match ($orientation) {
            3       => imagerotate($image, 180, 0),
            6       => imagerotate($image, -90, 0),
            8       => imagerotate($image, 90, 0),
            default => $image,
        };

        if ($rotated !== $image) {
            imagedestroy($image);
        }

        ob_start();
        imagejpeg($rotated, null, 90);
        $result = ob_get_clean();
        imagedestroy($rotated);

        return $result ?: $raw;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Load an image file and return [GdImage, imageType].
     *
     * @return array{GdImage|false, int}
     */
    private function load(string $path): array
    {
        $info = @getimagesize($path);
        if (!$info) {
            return [false, 0];
        }

        $type  = $info[2];
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_GIF  => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default        => false,
        };

        return [$image, $type];
    }

    /**
     * Calculate new dimensions preserving aspect ratio.
     *
     * @return array{int, int} [width, height]
     */
    private function calcAspectRatio(int $srcW, int $srcH, int $maxW, int $maxH): array
    {
        if ($srcW <= $maxW && $srcH <= $maxH) {
            return [$srcW, $srcH]; // Already fits, no resize needed
        }

        $ratioW = $maxW / $srcW;
        $ratioH = $maxH / $srcH;
        $ratio  = min($ratioW, $ratioH);

        return [(int) round($srcW * $ratio), (int) round($srcH * $ratio)];
    }

    /**
     * Create a blank canvas preserving transparency for PNG/WebP.
     */
    private function createCanvas(int $width, int $height, int $srcType): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);

        if ($srcType === IMAGETYPE_PNG || $srcType === IMAGETYPE_WEBP) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefill($canvas, 0, 0, $transparent);
        }

        return $canvas;
    }

    /**
     * Capture image output to a string buffer.
     *
     * @return string|false
     */
    private function capture(GdImage $image, int $type): string|false
    {
        ob_start();
        match ($type) {
            IMAGETYPE_PNG  => imagepng($image),
            IMAGETYPE_GIF  => imagegif($image),
            IMAGETYPE_WEBP => imagewebp($image, null, 85),
            default        => imagejpeg($image, null, 90),
        };
        return ob_get_clean();
    }
}
