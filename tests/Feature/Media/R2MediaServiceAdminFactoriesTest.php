<?php

namespace Tests\Feature\Media;

use App\Services\Media\R2MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Verifies the new admin/system factory methods land at the right paths
 * and that platform-owned categories use user_0 (the reserved system id).
 *
 * No DB needed — Storage::fake('r2') gives us the in-memory bucket.
 */
class R2MediaServiceAdminFactoriesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('r2');
        config(['media.disk' => 'r2']);
        config(['media.r2_only' => false]);
    }

    public function test_system_branding_lands_under_user_0(): void
    {
        $service = $this->app->make(R2MediaService::class);
        $file    = UploadedFile::fake()->image('logo.png', 400, 100);

        $result = $service->uploadSystemBranding($file);

        $this->assertStringStartsWith('system/branding/user_0/', $result->key);
        $this->assertStringEndsWith('.png', $result->key);
        Storage::disk('r2')->assertExists($result->key);
    }

    public function test_system_watermark_uses_user_0(): void
    {
        $service = $this->app->make(R2MediaService::class);
        $file    = UploadedFile::fake()->image('wm.png', 800, 800);

        $result = $service->uploadSystemWatermark($file);

        $this->assertStringStartsWith('system/watermark/user_0/', $result->key);
    }

    public function test_seo_og_default_image_uses_user_0(): void
    {
        $service = $this->app->make(R2MediaService::class);
        $file    = UploadedFile::fake()->image('og.png', 1200, 630);

        $result = $service->uploadSystemSeoOg($file);

        $this->assertStringStartsWith('system/seo_og/user_0/', $result->key);
    }

    public function test_favicon_lands_under_user_0(): void
    {
        $service = $this->app->make(R2MediaService::class);
        $file    = UploadedFile::fake()->create('favicon.ico', 50, 'image/x-icon');

        $result = $service->uploadSystemFavicon($file);

        $this->assertStringStartsWith('system/favicon/user_0/', $result->key);
    }

    public function test_blog_affiliate_banner_includes_link_resource(): void
    {
        $service = $this->app->make(R2MediaService::class);
        $file    = UploadedFile::fake()->image('banner.jpg', 1200, 630);

        $result = $service->uploadBlogAffiliateBanner(authorUserId: 5, linkId: 42, file: $file);

        $this->assertStringStartsWith('blog/affiliate_banners/user_5/link_42/', $result->key);
    }

    public function test_line_richmenu_includes_menu_resource(): void
    {
        $service = $this->app->make(R2MediaService::class);
        $file    = UploadedFile::fake()->image('menu.png', 2500, 1686);

        $result = $service->uploadLineRichMenu(adminUserId: 1, menuId: 7, file: $file);

        $this->assertStringStartsWith('integrations/line_richmenu/user_1/menu_7/', $result->key);
    }

    public function test_delete_user_skips_system_user_id(): void
    {
        $service = $this->app->make(R2MediaService::class);

        $logo = $service->uploadSystemBranding(UploadedFile::fake()->image('logo.png'));
        $userFile = $service->uploadAvatar(99, UploadedFile::fake()->image('me.jpg'));

        // Sweep user 99 — system logo should survive
        $deleted = $service->deleteUser(99);

        $this->assertGreaterThanOrEqual(1, $deleted);
        Storage::disk('r2')->assertExists($logo->key);
        Storage::disk('r2')->assertMissing($userFile->key);
    }

    /**
     * The path schema MUST stay flat: `{system}/{entity}/user_X/[resource_X/]file`.
     * We assert this for every category by uploading a representative file
     * and matching the regex.
     */
    public function test_every_factory_emits_paths_matching_canonical_schema(): void
    {
        $service = $this->app->make(R2MediaService::class);

        $cases = [
            $service->uploadAvatar(1, UploadedFile::fake()->image('a.jpg'))->key,
            $service->uploadEventPhoto(2, 3, UploadedFile::fake()->image('a.jpg'))->key,
            $service->uploadEventCover(2, 3, UploadedFile::fake()->image('a.jpg'))->key,
            $service->uploadPaymentSlip(4, 5, UploadedFile::fake()->image('a.jpg'))->key,
            $service->uploadPortfolioImage(6, UploadedFile::fake()->image('a.jpg'))->key,
            $service->uploadBrandingAsset(6, UploadedFile::fake()->image('a.png'))->key,
            $service->uploadSystemBranding(UploadedFile::fake()->image('a.png'))->key,
            $service->uploadSystemWatermark(UploadedFile::fake()->image('a.png'))->key,
            $service->uploadSystemSeoOg(UploadedFile::fake()->image('a.png'))->key,
            $service->uploadBlogAffiliateBanner(1, 2, UploadedFile::fake()->image('a.png'))->key,
            $service->uploadLineRichMenu(1, 2, UploadedFile::fake()->image('a.png'))->key,
        ];

        foreach ($cases as $key) {
            // {system}/{entity}/user_{n}[/{prefix}{resource}]/{filename}
            $this->assertMatchesRegularExpression(
                '#^[a-z_]+/[a-z_]+/user_\d+(?:/[a-z_]+\w+)?/[A-Za-z0-9._\-]+$#',
                $key,
                "Path doesn't match canonical schema: $key",
            );
        }
    }
}
