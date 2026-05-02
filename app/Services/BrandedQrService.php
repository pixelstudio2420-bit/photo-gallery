<?php

namespace App\Services;

use App\Models\AppSetting;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Font\Font;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Log;

/**
 * Branded QR code generator.
 *
 * Wraps endroid/qr-code to produce QR images that carry the site identity:
 *   - Site logo (when admin has uploaded one via /admin/settings) embedded
 *     in the centre — uses error correction H so the QR still scans even
 *     after the logo punches out the middle ~20% of modules.
 *   - "loadroop.com" label printed below — survives screenshotting,
 *     printing, and social-share previews where the embedded URL itself
 *     might be hidden or shortened.
 *
 * NOT for use with PromptPay, 2FA TOTP, or any payment QR — those formats
 * require pristine QR data without logo overlay (BOT spec for PromptPay,
 * RFC 6238 / authenticator apps for 2FA). Stick to the unbranded helper
 * for those flows.
 */
class BrandedQrService
{
    /**
     * Generate a branded QR PNG.
     *
     * @param string      $payload  Data to encode (URL, plain text, etc.)
     * @param string|null $label    Override label. Pass empty string to suppress.
     *                              null → use defaultBrandLabel() (host of APP_URL).
     * @param int         $size     QR pixel size. Label is rendered below this.
     * @return string PNG bytes
     */
    public function generate(string $payload, ?string $label = null, int $size = 300): string
    {
        // Resolve label: explicit string wins, null = derive from APP_URL.
        // Empty string means "no label" — caller wants logo-only branding.
        $label = $label ?? $this->defaultBrandLabel();

        $logoPath = $this->resolveLogoLocalPath();

        // Build args using named arguments so we only pass what's set —
        // endroid v6 Builder treats omitted params as their constructor
        // default (no logo / no label), which is exactly what we want
        // when the site has no logo configured yet.
        $args = [
            'writer'               => new PngWriter(),
            'data'                 => $payload,
            // High EC = ~30% damage tolerance. Required when overlaying a
            // logo (logo punches out ~20% of modules) — Low/Medium fail to
            // scan reliably under the logo.
            'errorCorrectionLevel' => ErrorCorrectionLevel::High,
            'size'                 => $size,
            'margin'               => 12,
            // Near-black instead of pure #000 — slightly softer print look,
            // still well above the contrast threshold needed for scanners.
            'foregroundColor'      => new Color(20, 20, 30),
            'backgroundColor'      => new Color(255, 255, 255),
        ];

        if ($logoPath !== null) {
            $args['logoPath']               = $logoPath;
            // 20% of QR width — endroid recommends ≤25% with EC=H. We pick
            // 20% to leave a comfort margin: scanners get faster lock-on
            // and survive lossy JPEG compression on social previews.
            $args['logoResizeToWidth']      = (int) ($size * 0.20);
            // Punch out a clean white square behind the logo so the QR's
            // black modules don't bleed into the logo image — without this
            // the visual reads as "smudged" and reduces scan reliability.
            $args['logoPunchoutBackground'] = true;
        }

        if ($label !== '') {
            $args['labelText']      = $label;
            // Default font ships with the package (open_sans.ttf). 14pt
            // looks balanced under a 300px QR; scales reasonably at 200/600.
            $args['labelFont']      = new Font(
                base_path('vendor/endroid/qr-code/assets/open_sans.ttf'),
                14,
            );
            // Indigo-500 — matches the brand accent used in the navbar/CTAs.
            $args['labelTextColor'] = new Color(99, 102, 241);
        }

        try {
            return (new Builder(...$args))->build()->getString();
        } catch (\Throwable $e) {
            // If the logo file is corrupt or the data is too long for ECC=H,
            // retry without the logo + with default ECC. Better to ship a
            // plain QR than 500 the user.
            Log::warning('BrandedQrService: build failed, retrying plain', [
                'error' => $e->getMessage(),
                'len'   => strlen($payload),
            ]);
            unset($args['logoPath'], $args['logoResizeToWidth'], $args['logoPunchoutBackground']);
            $args['errorCorrectionLevel'] = ErrorCorrectionLevel::Medium;
            return (new Builder(...$args))->build()->getString();
        }
    }

    /**
     * Default brand label = APP_URL host without "www." prefix.
     *
     * Falls back to "loadroop.com" rather than nothing so a misconfigured
     * .env still produces a recognisable label instead of an empty caption.
     */
    public function defaultBrandLabel(): string
    {
        $host = parse_url((string) config('app.url', ''), PHP_URL_HOST) ?: 'loadroop.com';
        return (string) preg_replace('/^www\./i', '', $host);
    }

    /**
     * Materialise the site logo to a local filesystem path so endroid (which
     * reads via GD imagecreatefromX) can load it.
     *
     * Cache strategy: on disk under storage/app/cache/qr-logo, keyed by
     * md5(storage_key). When admin uploads a new logo the key changes →
     * the new file gets fetched on next request. We never delete old ones
     * (cheap), but they age out naturally if the cache dir is cleared.
     */
    private function resolveLogoLocalPath(): ?string
    {
        $logoKey = trim((string) AppSetting::get('site_logo', ''));
        if ($logoKey === '') {
            return null;
        }

        $cacheDir = storage_path('app/cache/qr-logo');
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        // Preserve the original extension so GD picks the right decoder
        // (imagecreatefrompng vs imagecreatefromjpeg). Default to .img if
        // the path has no extension — GD will sniff the bytes anyway.
        $ext        = pathinfo($logoKey, PATHINFO_EXTENSION) ?: 'img';
        $cachedPath = $cacheDir . DIRECTORY_SEPARATOR . md5($logoKey) . '.' . strtolower($ext);

        if (is_file($cachedPath) && filesize($cachedPath) > 100) {
            return $cachedPath;
        }

        try {
            $url   = app(StorageManager::class)->resolveUrl($logoKey);
            $bytes = @file_get_contents($url);
            if ($bytes === false || strlen($bytes) < 100) {
                return null;
            }
            file_put_contents($cachedPath, $bytes);
            return $cachedPath;
        } catch (\Throwable $e) {
            Log::warning('BrandedQrService: failed to materialise logo', [
                'logo_key' => $logoKey,
                'error'    => $e->getMessage(),
            ]);
            return null;
        }
    }
}
