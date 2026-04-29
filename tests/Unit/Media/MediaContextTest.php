<?php

namespace Tests\Unit\Media;

use App\Services\Media\Exceptions\InvalidMediaCategoryException;
use App\Services\Media\MediaContext;
use Tests\TestCase;

class MediaContextTest extends TestCase
{
    public function test_make_creates_context_for_known_category(): void
    {
        $ctx = MediaContext::make('events', 'photos', 45, 789);

        $this->assertSame('events', $ctx->system);
        $this->assertSame('photos', $ctx->entityType);
        $this->assertSame(45, $ctx->userId);
        $this->assertSame('789', $ctx->resourceId);
        $this->assertSame('events.photos', $ctx->categoryKey());
    }

    public function test_make_rejects_unknown_system(): void
    {
        $this->expectException(InvalidMediaCategoryException::class);
        $this->expectExceptionMessageMatches('/not declared/i');

        MediaContext::make('unknown_system', 'photos', 1, 1);
    }

    public function test_make_rejects_unknown_entity_type_under_known_system(): void
    {
        $this->expectException(InvalidMediaCategoryException::class);

        MediaContext::make('events', 'fake_bucket', 1, 1);
    }

    public function test_make_requires_resource_id_when_category_demands_it(): void
    {
        $this->expectException(InvalidMediaCategoryException::class);
        $this->expectExceptionMessageMatches('/requires a resourceId/i');

        // events.photos requires a resource (event_id)
        MediaContext::make('events', 'photos', 45, null);
    }

    public function test_make_forbids_resource_id_when_category_does_not_accept_one(): void
    {
        $this->expectException(InvalidMediaCategoryException::class);
        $this->expectExceptionMessageMatches('/does not accept a resourceId/i');

        // auth.avatar must be user-scoped only — passing a resource_id is a bug
        MediaContext::make('auth', 'avatar', 45, 'something');
    }

    public function test_make_accepts_string_resource_id_for_flexibility(): void
    {
        $ctx = MediaContext::make('events', 'photos', 45, 'event_str_id');
        $this->assertSame('event_str_id', $ctx->resourceId);
    }
}
