<?php

namespace App\Services\Media;

/**
 * Returned from every R2 upload — never null, never partially populated.
 * The `key` is the canonical R2 object key (no leading slash).
 */
final class MediaUploadResult
{
    public function __construct(
        public readonly string $key,
        public readonly string $url,
        public readonly int    $sizeBytes,
        public readonly string $mimeType,
        public readonly string $visibility,
        public readonly string $disk,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key'        => $this->key,
            'url'        => $this->url,
            'size'       => $this->sizeBytes,
            'mime'       => $this->mimeType,
            'visibility' => $this->visibility,
            'disk'       => $this->disk,
        ];
    }
}
