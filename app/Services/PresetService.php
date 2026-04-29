<?php

namespace App\Services;

use App\Models\EventPhoto;
use App\Models\PhotographerPreset;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * PresetService
 * ─────────────
 * Owns the "apply preset to photo" pipeline:
 *
 *   render()     → take a GD image + preset settings → return modified GD image
 *   applyTo()    → render() + persist as new file path on storage
 *   parseXmp()   → extract Lightroom .xmp values → settings array
 *   previewBytes() → render preset on a sample image → return JPEG bytes
 *                    (used for live "before/after" preview in the UI)
 *
 * Pipeline order is deliberate (Lightroom-ish):
 *   1) Exposure              — multiply pixel values
 *   2) Whites / Blacks       — extend tonal range
 *   3) Highlights / Shadows  — selective per-luminance lift/cut
 *   4) Contrast              — sigmoid around mid-grey
 *   5) Clarity               — local mid-tone contrast (unsharp on luma)
 *   6) Temperature / Tint    — channel multiply
 *   7) Vibrance / Saturation — distance-from-grey scaling
 *   8) Grayscale             — desaturate (last so it overrides 7)
 *   9) Sharpness             — convolution kernel
 *  10) Vignette              — radial darken/lighten
 *
 * Pure GD — no Imagick / no external API. Fast enough for batch on
 * <2000-px images; for large originals we render on a downscaled copy
 * and let the existing thumbnail/watermark pipeline regenerate the
 * preview at delivery time (color is preserved through resize).
 */
class PresetService
{
    /**
     * Apply a preset to a photo and persist the rendered output.
     * Returns the new storage path (or null on failure).
     *
     * Strategy:
     *   - Read the original photo bytes
     *   - render() → modified GD image
     *   - Save to <originalDir>/preset/<photoId>.jpg
     *   - Stamp event_photos.{preset_id, preset_applied_path, preset_applied_at}
     *
     * The original is NEVER modified — preset output is a sibling file
     * so the photographer can revert by clearing the preset.
     */
    public function applyTo(EventPhoto $photo, PhotographerPreset $preset): ?string
    {
        $disk = $photo->storage_disk ?? 'public';
        $stored = Storage::disk($disk);
        if (!$stored->exists($photo->original_path)) return null;

        $tmpIn = tempnam(sys_get_temp_dir(), 'preset_in_');
        file_put_contents($tmpIn, $stored->get($photo->original_path));

        $img = $this->loadImage($tmpIn);
        if (!$img) { @unlink($tmpIn); return null; }

        try {
            $rendered = $this->render($img, $preset->merged_settings);

            $tmpOut = tempnam(sys_get_temp_dir(), 'preset_out_').'.jpg';
            imagejpeg($rendered, $tmpOut, 90);
            imagedestroy($rendered);

            $newPath = dirname($photo->original_path).'/preset/'
                .pathinfo($photo->filename, PATHINFO_FILENAME).'.jpg';
            $stored->put($newPath, file_get_contents($tmpOut));

            @unlink($tmpIn);
            @unlink($tmpOut);

            $photo->forceFill([
                'preset_id'           => $preset->id,
                'preset_applied_path' => $newPath,
                'preset_applied_at'   => now(),
            ])->save();

            return $newPath;
        } catch (\Throwable $e) {
            Log::warning('PresetService::applyTo failed', [
                'photo_id' => $photo->id, 'preset_id' => $preset->id, 'error' => $e->getMessage(),
            ]);
            @unlink($tmpIn);
            return null;
        } finally {
            if (is_resource($img) || $img instanceof \GdImage) {
                @imagedestroy($img);
            }
        }
    }

    /**
     * Render a preview JPEG (max 800px) of the given preset applied
     * to the given source bytes. Used by the AJAX live-preview slider.
     */
    public function previewBytes(array $settings, string $sourceBytes, int $maxSize = 800): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pv_in_');
        file_put_contents($tmp, $sourceBytes);
        $img = $this->loadImage($tmp);
        @unlink($tmp);
        if (!$img) return null;

        // Downscale before render — much faster on huge originals.
        $img = $this->fitWithin($img, $maxSize);

        $merged = array_merge(PhotographerPreset::DEFAULTS, $settings);
        $rendered = $this->render($img, $merged);

        ob_start();
        imagejpeg($rendered, null, 88);
        $bytes = ob_get_clean();
        imagedestroy($rendered);
        return $bytes;
    }

    /**
     * Apply preset settings to a GD image. Returns a NEW GD image
     * (the input is consumed).
     */
    public function render(\GdImage $img, array $s): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);

        // Allocate output once; we'll write back per-pixel.
        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);

        // Pre-compute scaling coefficients.
        $exposure   = (float) ($s['exposure']    ?? 0);   // -1..+1 EV
        $contrast   = (float) ($s['contrast']    ?? 0);   // -100..+100
        $highlights = (float) ($s['highlights']  ?? 0);
        $shadows    = (float) ($s['shadows']     ?? 0);
        $whites     = (float) ($s['whites']      ?? 0);
        $blacks     = (float) ($s['blacks']      ?? 0);
        $vibrance   = (float) ($s['vibrance']    ?? 0);
        $saturation = (float) ($s['saturation']  ?? 0);
        $temperature= (float) ($s['temperature'] ?? 0);
        $tint       = (float) ($s['tint']        ?? 0);
        $clarity    = (float) ($s['clarity']     ?? 0);
        $vignette   = (float) ($s['vignette']    ?? 0);
        $grayscale  = (bool)  ($s['grayscale']   ?? false);

        // Multiplier for exposure: 2^EV
        $expMul = pow(2, $exposure);

        // Sigmoid-ish contrast around 128 (mid-grey).
        // f(v) = 128 + (v-128) * (1 + contrast/100)
        $contrastFactor = 1 + ($contrast / 100);

        // Temperature: warmer (positive) lifts R, drops B.
        $tempR = 1 + ($temperature / 200);  // ±0.5 max
        $tempB = 1 - ($temperature / 200);

        // Tint: positive (magenta) lifts R+B, drops G. Negative (green) inverse.
        $tintR = 1 + ($tint / 400);
        $tintG = 1 - ($tint / 200);
        $tintB = 1 + ($tint / 400);

        // Vignette radius
        $cx = $w / 2; $cy = $h / 2;
        $maxDist = sqrt($cx*$cx + $cy*$cy);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($img, $x, $y);
                $a = ($rgb >> 24) & 0x7F;
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                // 1) Exposure
                $r *= $expMul; $g *= $expMul; $b *= $expMul;

                // 2) Whites / Blacks — pull endpoints
                if ($whites !== 0.0) {
                    $r += $whites * (max($r, $g, $b) > 200 ? 1.2 : 0.4);
                    $g += $whites * (max($r, $g, $b) > 200 ? 1.2 : 0.4);
                    $b += $whites * (max($r, $g, $b) > 200 ? 1.2 : 0.4);
                }
                if ($blacks !== 0.0) {
                    $r += $blacks * (min($r, $g, $b) < 60 ? 1.2 : 0.4);
                    $g += $blacks * (min($r, $g, $b) < 60 ? 1.2 : 0.4);
                    $b += $blacks * (min($r, $g, $b) < 60 ? 1.2 : 0.4);
                }

                // 3) Highlights / Shadows — luminance-weighted
                $luma = 0.299*$r + 0.587*$g + 0.114*$b;
                if ($highlights !== 0.0 && $luma > 128) {
                    $w_h = ($luma - 128) / 127;
                    $delta = $highlights * 0.6 * $w_h;
                    $r += $delta; $g += $delta; $b += $delta;
                }
                if ($shadows !== 0.0 && $luma < 128) {
                    $w_s = (128 - $luma) / 127;
                    $delta = $shadows * 0.6 * $w_s;
                    $r += $delta; $g += $delta; $b += $delta;
                }

                // 4) Contrast (around 128)
                if ($contrast !== 0.0) {
                    $r = 128 + ($r - 128) * $contrastFactor;
                    $g = 128 + ($g - 128) * $contrastFactor;
                    $b = 128 + ($b - 128) * $contrastFactor;
                }

                // 6) Temperature / Tint  (skip clarity here — needs neighbours; done in pass 2)
                $r *= $tempR * $tintR;
                $g *= $tintG;
                $b *= $tempB * $tintB;

                // 7) Vibrance + Saturation
                if ($vibrance !== 0.0 || $saturation !== 0.0) {
                    $avg = ($r + $g + $b) / 3;
                    // Saturation = uniform pull toward/away from grey
                    $satFactor = 1 + ($saturation / 100);
                    $r = $avg + ($r - $avg) * $satFactor;
                    $g = $avg + ($g - $avg) * $satFactor;
                    $b = $avg + ($b - $avg) * $satFactor;

                    // Vibrance = saturation that protects already-saturated pixels
                    if ($vibrance !== 0.0) {
                        $maxC = max($r, $g, $b);
                        $minC = min($r, $g, $b);
                        $sat  = $maxC > 0 ? ($maxC - $minC) / $maxC : 0;
                        $vibFactor = 1 + ($vibrance / 100) * (1 - $sat);
                        $avg2 = ($r + $g + $b) / 3;
                        $r = $avg2 + ($r - $avg2) * $vibFactor;
                        $g = $avg2 + ($g - $avg2) * $vibFactor;
                        $b = $avg2 + ($b - $avg2) * $vibFactor;
                    }
                }

                // 8) Grayscale
                if ($grayscale) {
                    $gray = (int) (0.299*$r + 0.587*$g + 0.114*$b);
                    $r = $g = $b = $gray;
                }

                // 10) Vignette (radial)
                if ($vignette !== 0.0) {
                    $dx = $x - $cx; $dy = $y - $cy;
                    $dist = sqrt($dx*$dx + $dy*$dy) / $maxDist;
                    // Strongest at edges; cosine falloff
                    $vMul = 1 + ($vignette / 100) * ($dist * $dist - 0.3);
                    $vMul = max(0.2, min(1.5, $vMul));
                    $r *= $vMul; $g *= $vMul; $b *= $vMul;
                }

                $r = max(0, min(255, (int) round($r)));
                $g = max(0, min(255, (int) round($g)));
                $b = max(0, min(255, (int) round($b)));

                imagesetpixel($out, $x, $y, ($a << 24) | ($r << 16) | ($g << 8) | $b);
            }
        }

        // 5) Clarity (mid-tone local contrast) — apply via sharpen filter
        if ($clarity > 0) {
            imagefilter($out, IMG_FILTER_CONTRAST, -min(20, (int) round($clarity / 5)));
        }

        // 9) Sharpness — convolution kernel
        $sharp = (float) ($s['sharpness'] ?? 0);
        if ($sharp > 0) {
            $strength = $sharp / 100; // 0..1
            $kernel = [
                [0, -$strength, 0],
                [-$strength, 1 + 4*$strength, -$strength],
                [0, -$strength, 0],
            ];
            @imageconvolution($out, $kernel, 1, 0);
        }

        imagedestroy($img);
        return $out;
    }

    /**
     * Parse a Lightroom .xmp file → normalised settings array.
     * Lightroom uses crs:* attributes inside <rdf:Description>. Values
     * are strings like "+0.50" or "-25" or "True". We extract the
     * top-level adjustments and ignore HSL/curves (GD can't replicate).
     */
    public function parseXmp(string $xmpContent): array
    {
        $out = [];
        // Match crs:KeyName="value" — values can be in single or double quotes.
        if (!preg_match_all('/crs:([A-Za-z0-9_]+)\s*=\s*[\'"]([^\'"]+)[\'"]/', $xmpContent, $matches)) {
            return $out;
        }

        $map = [
            'Exposure2012'   => 'exposure',     // already in EV stops
            'Contrast2012'   => 'contrast',
            'Highlights2012' => 'highlights',
            'Shadows2012'    => 'shadows',
            'Whites2012'     => 'whites',
            'Blacks2012'     => 'blacks',
            'Vibrance'       => 'vibrance',
            'Saturation'     => 'saturation',
            'Temperature'    => 'temperature',  // Kelvin — needs special handling
            'Tint'           => 'tint',
            'Clarity2012'    => 'clarity',
            'Sharpness'      => 'sharpness',
            'ConvertToGrayscale' => 'grayscale',
            'PostCropVignetteAmount' => 'vignette',
        ];

        for ($i = 0; $i < count($matches[1]); $i++) {
            $key = $matches[1][$i];
            $val = $matches[2][$i];

            if (!isset($map[$key])) continue;
            $ourKey = $map[$key];

            if ($ourKey === 'grayscale') {
                $out[$ourKey] = strtolower($val) === 'true';
                continue;
            }

            // Temperature in Lightroom is an absolute Kelvin value.
            // Map 5500K (neutral) → 0; 7500K (warm) → +50; 3500K (cool) → -50.
            if ($ourKey === 'temperature' && (int) $val > 1000) {
                $kelvin = (int) $val;
                $out[$ourKey] = round(($kelvin - 5500) / 40, 0); // ~ -50..+50 for 3500..7500
                continue;
            }

            $out[$ourKey] = (float) $val;
        }

        return $out;
    }

    // ─── Internal helpers ────────────────────────────────────────────────

    private function loadImage(string $path): ?\GdImage
    {
        $info = @getimagesize($path);
        if (!$info) return null;
        return match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            IMAGETYPE_GIF  => @imagecreatefromgif($path),
            default        => null,
        } ?: null;
    }

    private function fitWithin(\GdImage $img, int $maxSize): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w <= $maxSize && $h <= $maxSize) return $img;

        $scale = $maxSize / max($w, $h);
        $sw = (int) ($w * $scale);
        $sh = (int) ($h * $scale);
        $resized = imagecreatetruecolor($sw, $sh);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $sw, $sh, $w, $h);
        imagedestroy($img);
        return $resized;
    }
}
