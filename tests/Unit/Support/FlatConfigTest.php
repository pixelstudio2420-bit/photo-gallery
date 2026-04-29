<?php

namespace Tests\Unit\Support;

use App\Support\FlatConfig;
use Tests\TestCase;

/**
 * Pure-function tests for FlatConfig — confirm we sidestep Laravel's
 * dot-notation interpretation while still using the framework's config
 * facade for the parent path.
 */
class FlatConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Inject a synthetic config tree that uses BOTH nested keys and
        // flat keys with dots inside — the exact mismatch this helper exists for.
        config([
            'demo' => [
                'flat' => [
                    'a.b'   => ['name' => 'a-b'],
                    'x.y.z' => ['name' => 'x-y-z'],
                    'count' => 42,
                ],
                'nested' => [
                    'one' => ['two' => 'works the normal way'],
                ],
            ],
        ]);
    }

    public function test_get_resolves_a_flat_key_with_dots_inside(): void
    {
        // Stress test: the key 'a.b' would resolve to demo.flat.a.b
        // under Laravel's dot-notation, walking through a non-existent
        // 'a' sub-array. The helper bypasses that.
        $this->assertSame(['name' => 'a-b'],   FlatConfig::get('demo.flat', 'a.b'));
        $this->assertSame(['name' => 'x-y-z'], FlatConfig::get('demo.flat', 'x.y.z'));
    }

    public function test_get_returns_default_when_key_missing(): void
    {
        $this->assertNull(FlatConfig::get('demo.flat', 'missing'));
        $this->assertSame('fallback', FlatConfig::get('demo.flat', 'missing', 'fallback'));
    }

    public function test_get_returns_default_when_section_missing(): void
    {
        $this->assertSame([], FlatConfig::get('demo.does_not_exist', 'whatever', []));
    }

    public function test_array_coerces_to_array_or_default(): void
    {
        $this->assertSame(['name' => 'a-b'], FlatConfig::array('demo.flat', 'a.b'));
        // 'count' is an int — array() should fall back to the default.
        $this->assertSame(['fallback'], FlatConfig::array('demo.flat', 'count', ['fallback']));
        $this->assertSame([], FlatConfig::array('demo.flat', 'missing'));
    }

    public function test_int_coerces_numeric_or_default(): void
    {
        $this->assertSame(42, FlatConfig::int('demo.flat', 'count'));
        $this->assertSame(7,  FlatConfig::int('demo.flat', 'missing', 7));
        // Non-numeric value → default
        $this->assertSame(0,  FlatConfig::int('demo.flat', 'a.b'));
    }

    public function test_section_returns_full_keyed_array(): void
    {
        $section = FlatConfig::section('demo.flat');
        $this->assertArrayHasKey('a.b',   $section);
        $this->assertArrayHasKey('x.y.z', $section);
        $this->assertSame(42, $section['count']);
    }

    public function test_section_returns_empty_array_for_missing_path(): void
    {
        $this->assertSame([], FlatConfig::section('demo.totally_missing'));
    }

    public function test_get_works_against_real_app_config(): void
    {
        // Sanity: against the real config/media.php, the helper must find
        // the flat-keyed 'events.photos' category that the rest of the
        // codebase relies on.
        $cat = FlatConfig::array('media.categories', 'events.photos');
        $this->assertNotEmpty($cat);
        $this->assertArrayHasKey('visibility',         $cat);
        $this->assertArrayHasKey('allowed_mime',       $cat);
        $this->assertArrayHasKey('requires_resource',  $cat);
    }
}
