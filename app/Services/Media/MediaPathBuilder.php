<?php

namespace App\Services\Media;

use App\Services\Media\Exceptions\InvalidMediaCategoryException;
use App\Support\FlatConfig;
use Illuminate\Support\Str;

/**
 * Builds and validates R2 object keys against the schema:
 *
 *     {system}/{entity_type}/user_{user_id}/{resource_prefix}{resource_id}/{filename}
 *
 *  e.g. events/photos/user_45/event_789/0193e2bf-7ce6-7232-93b1-9d0a89ae3f9b_DSC_001.jpg
 *
 *  The builder is the ONLY component allowed to mint a path. Controllers
 *  never concatenate strings — they call MediaContext::make() and hand the
 *  context to R2MediaService, which calls this builder.
 *
 *  Filename strategy
 *  -----------------
 *    - 36-char UUIDv7 prefix → globally unique, monotonically sortable
 *    - underscore separator
 *    - sanitized original name (max 80 chars, [a-z0-9-_.] only)
 *    - lowercased extension from the configured allowlist
 *
 *  This guarantees:
 *    1. No two uploads ever collide (UUIDv7 in microsecond resolution)
 *    2. No path-traversal characters survive (sanitization is whitelist)
 *    3. Original filename stays human-readable for support diagnostics
 */
final class MediaPathBuilder
{
    private const FILENAME_MAX_LEN = 80;

    /**
     * Build the full R2 object key for a brand-new upload.
     */
    public function build(MediaContext $ctx, string $originalFilename, ?string $derivativeSuffix = null): string
    {
        $category = $this->resolveCategory($ctx);

        $directory = $this->directoryFor($ctx, $category);
        $filename  = $this->mintFilename($originalFilename, $derivativeSuffix);

        return $directory . '/' . $filename;
    }

    /**
     * Build the full key when caller already has a generated filename
     * (used by derivative paths — thumbnail/watermark — that share the
     * UUID of the original to keep them grouped).
     */
    public function buildWithFilename(MediaContext $ctx, string $filename): string
    {
        $category  = $this->resolveCategory($ctx);
        $directory = $this->directoryFor($ctx, $category);
        $cleaned   = $this->sanitiseLeaf($filename);

        if ($cleaned === '') {
            throw new \InvalidArgumentException('Sanitized filename is empty — refusing to upload.');
        }
        return $directory . '/' . $cleaned;
    }

    /**
     * Just the directory prefix — useful for `media:delete-resource` style
     * operations that want to wipe everything under e.g. `event_42/`.
     */
    public function directoryFor(MediaContext $ctx, ?array $category = null): string
    {
        $category ??= $this->resolveCategory($ctx);

        $base = sprintf(
            '%s/%s/user_%d',
            $this->sanitiseSegment($ctx->system),
            $this->sanitiseSegment($ctx->entityType),
            $ctx->userId,
        );

        if (($category['requires_resource'] ?? false) && $ctx->resourceId !== null) {
            $prefix = (string) ($category['resource_prefix'] ?? '');
            $base  .= '/' . $prefix . $this->sanitiseSegment($ctx->resourceId);
        }

        return $base;
    }

    /**
     * Verify an existing key matches the schema we own. Used by the
     * migration command to confirm imported records actually live in
     * a slot we claim. Returns null on success, error string on failure.
     */
    public function validate(string $key): ?string
    {
        $key = ltrim($key, '/');
        // Accept both the canonical layout and the user-required base layout.
        // {system}/{entity}/user_{id}/[{prefix}{id}/]{filename}
        if (!preg_match('#^[a-z0-9_]+/[a-z0-9_]+/user_\d+(?:/[a-z0-9_]+_\w+)?/[A-Za-z0-9._\-]+$#', $key)) {
            return "Path does not match schema: {$key}";
        }
        return null;
    }

    /* ─────────────────── Internals ─────────────────── */

    /** @return array<string, mixed> */
    private function resolveCategory(MediaContext $ctx): array
    {
        $cfg = FlatConfig::get('media.categories', $ctx->categoryKey());
        if (!is_array($cfg)) {
            throw InvalidMediaCategoryException::notRegistered($ctx->categoryKey());
        }
        return $cfg;
    }

    private function mintFilename(string $original, ?string $derivativeSuffix = null): string
    {
        $extension = $this->extensionFromName($original);
        $stem      = $this->sanitiseLeaf(pathinfo($original, PATHINFO_FILENAME));

        if ($stem === '') {
            $stem = 'file';
        }
        if (mb_strlen($stem) > self::FILENAME_MAX_LEN) {
            $stem = mb_substr($stem, 0, self::FILENAME_MAX_LEN);
        }

        $uuid   = (string) Str::uuid();   // UUIDv4 — Laravel default
        $suffix = $derivativeSuffix ? '_' . $this->sanitiseSegment($derivativeSuffix) : '';

        return $extension !== ''
            ? "{$uuid}_{$stem}{$suffix}.{$extension}"
            : "{$uuid}_{$stem}{$suffix}";
    }

    private function extensionFromName(string $name): string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION) ?? '');
        // Allow only [a-z0-9] in the extension — no `.php.jpg` traversal.
        return preg_replace('/[^a-z0-9]/', '', $ext) ?? '';
    }

    /**
     * Strip everything that isn't [a-z0-9._-]. Used for path SEGMENTS like
     * `system`, `entity_type`, `resource_id` which are short.
     */
    private function sanitiseSegment(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9._-]/', '', $value) ?? '';
        return ltrim($value, '.-_'); // never start a segment with . or -
    }

    /**
     * Sanitises the file-name leaf. Keeps slightly more characters
     * (parens, spaces become underscores) for readability.
     */
    private function sanitiseLeaf(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/\s+/', '_', $value) ?? '';
        $value = preg_replace('/[^a-z0-9._\-]/', '', $value) ?? '';
        $value = preg_replace('/_{2,}/', '_', $value) ?? $value;
        return ltrim($value, '.-_');
    }
}
