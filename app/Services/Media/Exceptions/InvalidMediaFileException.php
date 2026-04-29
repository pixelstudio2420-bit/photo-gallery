<?php

namespace App\Services\Media\Exceptions;

class InvalidMediaFileException extends MediaException
{
    public static function tooLarge(int $sizeBytes, int $maxBytes): self
    {
        return new self(sprintf(
            'File too large: %s exceeds the %s limit for this category.',
            self::humanize($sizeBytes),
            self::humanize($maxBytes),
        ));
    }

    public static function disallowedMime(string $mime, array $allowed): self
    {
        return new self(sprintf(
            "MIME type '%s' is not allowed for this category. Allowed: %s",
            $mime,
            implode(', ', $allowed),
        ));
    }

    public static function disallowedExtension(string $ext, array $allowed): self
    {
        return new self(sprintf(
            "File extension '.%s' is not allowed for this category. Allowed: %s",
            $ext,
            implode(', ', array_map(fn ($e) => '.' . $e, $allowed)),
        ));
    }

    public static function unreadable(string $path): self
    {
        return new self("Cannot read uploaded file at: {$path}");
    }

    private static function humanize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1024 * 1024 * 1024) return round($bytes / 1024 / 1024, 1) . ' MB';
        return round($bytes / 1024 / 1024 / 1024, 2) . ' GB';
    }
}
