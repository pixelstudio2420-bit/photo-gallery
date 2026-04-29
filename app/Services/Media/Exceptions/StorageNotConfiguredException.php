<?php

namespace App\Services\Media\Exceptions;

class StorageNotConfiguredException extends MediaException
{
    public static function r2Required(): self
    {
        return new self(
            'Cloudflare R2 is not configured but STORAGE_R2_ONLY=true. ' .
            'Either set the CLOUDFLARE_R2_* env vars and enable r2_enabled in app_settings, ' .
            'or set STORAGE_R2_ONLY=false for local dev.'
        );
    }

    public static function uploadFailed(string $key, ?\Throwable $previous = null): self
    {
        return new self(
            "R2 reported a successful PUT but the object {$key} is not retrievable. " .
            "This usually indicates a misconfigured bucket lifecycle, expired API token, " .
            "or AccessDenied on Object Read. Investigate the R2 dashboard immediately.",
            0,
            $previous,
        );
    }
}
