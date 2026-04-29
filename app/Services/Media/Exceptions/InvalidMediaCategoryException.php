<?php

namespace App\Services\Media\Exceptions;

class InvalidMediaCategoryException extends MediaException
{
    public static function notRegistered(string $key): self
    {
        return new self(
            "Media category '{$key}' is not declared in config/media.php. " .
            "Add it to the `categories` allowlist before uploading."
        );
    }

    public static function resourceRequired(string $key): self
    {
        return new self(
            "Media category '{$key}' requires a resourceId but none was provided."
        );
    }

    public static function resourceForbidden(string $key): self
    {
        return new self(
            "Media category '{$key}' does not accept a resourceId — pass null."
        );
    }
}
