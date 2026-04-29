<?php

namespace App\Services\Media;

use App\Services\Media\Exceptions\InvalidMediaCategoryException;
use App\Support\FlatConfig;

/**
 * Immutable description of "where does this upload belong".
 *
 * The category key (e.g. "events.photos") indexes into config/media.php
 * so the path builder, validator, and service all agree on the same rules.
 *
 * Construction is funnelled through the static factory `make()` so we can
 * validate against the allowlist in exactly one place — the constructor
 * stays trivial, and unit tests can synthesise an instance directly when
 * needed.
 */
final class MediaContext
{
    public function __construct(
        public readonly string $system,
        public readonly string $entityType,
        public readonly int $userId,
        public readonly ?string $resourceId,
    ) {}

    public function categoryKey(): string
    {
        return "{$this->system}.{$this->entityType}";
    }

    /**
     * Validates inputs against config/media.php and returns a context.
     *
     * @throws InvalidMediaCategoryException on unknown system/entity_type
     *         or on resource mismatch (required/forbidden).
     */
    public static function make(string $system, string $entityType, int $userId, int|string|null $resourceId = null): self
    {
        $key      = "{$system}.{$entityType}";
        $category = FlatConfig::get('media.categories', $key);

        if (!is_array($category)) {
            throw InvalidMediaCategoryException::notRegistered($key);
        }

        $needsResource = (bool) ($category['requires_resource'] ?? false);

        if ($needsResource && ($resourceId === null || $resourceId === '')) {
            throw InvalidMediaCategoryException::resourceRequired($key);
        }
        if (!$needsResource && $resourceId !== null && $resourceId !== '') {
            throw InvalidMediaCategoryException::resourceForbidden($key);
        }

        return new self(
            system:     $system,
            entityType: $entityType,
            userId:     $userId,
            resourceId: $resourceId === null ? null : (string) $resourceId,
        );
    }
}
