<?php

namespace Tests\Unit\Media;

use App\Services\Media\MediaContext;
use App\Services\Media\MediaPathBuilder;
use Tests\TestCase;

class MediaPathBuilderTest extends TestCase
{
    private MediaPathBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new MediaPathBuilder();
    }

    public function test_event_photo_path_contains_full_schema(): void
    {
        $ctx  = MediaContext::make('events', 'photos', 45, 789);
        $path = $this->builder->build($ctx, 'DSC_001.jpg');

        $this->assertStringStartsWith('events/photos/user_45/event_789/', $path);
        $this->assertStringEndsWith('.jpg', $path);
        $this->assertStringContainsString('_dsc_001', $path); // sanitized stem
    }

    public function test_avatar_path_omits_resource_segment(): void
    {
        $ctx  = MediaContext::make('auth', 'avatar', 123);
        $path = $this->builder->build($ctx, 'profile.png');

        // expected: auth/avatar/user_123/{uuid}_profile.png
        $this->assertStringStartsWith('auth/avatar/user_123/', $path);
        $this->assertStringNotContainsString('_/', $path); // no empty segment
        $this->assertStringNotContainsString('user_123/event_', $path);
    }

    public function test_filename_includes_uuid_prefix(): void
    {
        $ctx  = MediaContext::make('blog', 'posts', 5, 141);
        $path = $this->builder->build($ctx, 'hero.jpg');

        $base = basename($path);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}_hero\.jpg$/',
            $base,
        );
    }

    public function test_filename_strips_path_traversal_attempts(): void
    {
        $ctx  = MediaContext::make('blog', 'posts', 5, 141);
        $path = $this->builder->build($ctx, '../../../etc/passwd.jpg');

        // The leaf must NOT contain `..` or `/`
        $this->assertStringNotContainsString('..', basename($path));
        // Schema has exactly 4 separators: system / entity / user_X / resource_Y / filename
        $this->assertSame(4, substr_count($path, '/'));
    }

    public function test_filename_strips_double_extension_php_attack(): void
    {
        $ctx  = MediaContext::make('blog', 'posts', 5, 141);
        $path = $this->builder->build($ctx, 'shell.php.jpg');

        // The double-extension must reduce to a single allowed extension.
        // `shell.php.jpg` → stem 'shell.php', ext 'jpg'. The stem still
        // contains `.php` (which is fine in object storage — R2 doesn't
        // execute it) BUT the path must end in .jpg.
        $this->assertStringEndsWith('.jpg', $path);
        // And the literal `.php` shouldn't be the trailing extension.
        $this->assertDoesNotMatchRegularExpression('/\.php$/i', $path);
    }

    public function test_filename_handles_unicode_by_stripping(): void
    {
        $ctx  = MediaContext::make('events', 'photos', 1, 1);
        $path = $this->builder->build($ctx, 'รูปงาน.jpg');

        // Unicode stripped, fallback stem 'file' kicks in — and ext preserved.
        $this->assertStringEndsWith('.jpg', $path);
        $this->assertMatchesRegularExpression('#^events/photos/user_1/event_1/[0-9a-f-]+_[a-z0-9.-]*\.jpg$#', $path);
    }

    public function test_filename_caps_long_stem(): void
    {
        $longName = str_repeat('a', 200) . '.jpg';
        $ctx  = MediaContext::make('events', 'photos', 1, 1);
        $path = $this->builder->build($ctx, $longName);

        // The leaf shouldn't be more than uuid (36) + '_' + 80 stem + '.jpg'
        $base = basename($path);
        $this->assertLessThanOrEqual(36 + 1 + 80 + 1 + 3, strlen($base));
    }

    public function test_directory_for_avatar_skips_resource_segment(): void
    {
        $ctx = MediaContext::make('auth', 'avatar', 99);
        $dir = $this->builder->directoryFor($ctx);

        $this->assertSame('auth/avatar/user_99', $dir);
    }

    public function test_directory_for_event_includes_resource_prefix(): void
    {
        $ctx = MediaContext::make('events', 'photos', 99, 42);
        $dir = $this->builder->directoryFor($ctx);

        $this->assertSame('events/photos/user_99/event_42', $dir);
    }

    public function test_validate_accepts_canonical_keys(): void
    {
        $valid = [
            'events/photos/user_45/event_789/abc123_DSC.jpg',
            'auth/avatar/user_1/avatar.jpg',
            'payments/slips/user_678/order_5511/uuid_slip.pdf',
        ];
        foreach ($valid as $key) {
            $this->assertNull($this->builder->validate($key), "Should accept: $key");
        }
    }

    public function test_validate_rejects_paths_outside_schema(): void
    {
        $invalid = [
            'uploads/random.jpg',                       // generic dumping ground
            'photos/45/foo.jpg',                        // missing system + user prefix
            'events/photos/45/event_789/foo.jpg',       // missing 'user_' prefix
            '../../etc/passwd',                         // path traversal
            '',                                         // empty
        ];
        foreach ($invalid as $key) {
            $this->assertNotNull($this->builder->validate($key), "Should reject: $key");
        }
    }

    public function test_build_with_filename_preserves_caller_supplied_name(): void
    {
        $ctx = MediaContext::make('events', 'thumbnails', 45, 789);
        $key = $this->builder->buildWithFilename($ctx, 'fixed-uuid_thumb.webp');

        $this->assertSame('events/thumbnails/user_45/event_789/fixed-uuid_thumb.webp', $key);
    }
}
