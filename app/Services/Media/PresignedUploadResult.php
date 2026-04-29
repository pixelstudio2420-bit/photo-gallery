<?php

namespace App\Services\Media;

/**
 * Used for direct browser → R2 uploads via presigned PUT URL.
 *
 * The browser issues:
 *     PUT {url}
 *     Content-Type: {expected_mime}
 *     {file bytes}
 *
 * After the PUT succeeds, the browser POSTs `key` back to the application
 * which records it in the DB.
 */
final class PresignedUploadResult
{
    public function __construct(
        public readonly string $url,           // signed PUT URL the browser hits
        public readonly string $key,           // final R2 object key
        public readonly string $expectedMime,  // MUST be sent as Content-Type by the browser
        public readonly int    $expiresAt,     // unix ts when the URL stops working
        public readonly int    $maxBytes,      // hard cap the browser must enforce client-side too
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'url'           => $this->url,
            'key'           => $this->key,
            'expected_mime' => $this->expectedMime,
            'expires_at'    => $this->expiresAt,
            'max_bytes'     => $this->maxBytes,
        ];
    }
}
