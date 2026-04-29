<?php

namespace App\Services\Payment;

/**
 * Immutable fingerprint of a slip image, used for cross-user dedup +
 * audit trail.
 *
 * Why three fields, not just sha256?
 *   - sha256 catches byte-identical duplicates (the most common abuse)
 *   - byte size + mime catch trivial re-encodes (resize-to-99% retains
 *     visual info but changes hash)
 *   - perceptual hash (pHash) — when available — catches crops/recolors
 *     that change SHA but keep the slip visually identical. We compute
 *     it via average-DCT pHash without an external library so the
 *     dependency surface stays small. pHash is OPTIONAL — null when
 *     the GD extension isn't available on the host.
 */
final class SlipFingerprint
{
    public function __construct(
        public readonly string  $sha256,
        public readonly int     $bytes,
        public readonly string  $mime,
        public readonly ?string $pHash = null,
    ) {
        if (strlen($sha256) !== 64) {
            throw new \InvalidArgumentException('sha256 must be 64 hex chars');
        }
        if ($bytes < 0) {
            throw new \InvalidArgumentException('bytes cannot be negative');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'sha256' => $this->sha256,
            'bytes'  => $this->bytes,
            'mime'   => $this->mime,
            'phash'  => $this->pHash,
        ];
    }
}
