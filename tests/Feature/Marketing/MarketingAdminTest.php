<?php

namespace Tests\Feature\Marketing;

use App\Models\Admin;
use App\Models\AppSetting;
use App\Models\Marketing\LandingPage;

class MarketingAdminTest extends MarketingTestCase
{
    protected function actingAsAdmin(): Admin
    {
        $admin = Admin::create([
            'email'         => 'admin@test.com',
            'password_hash' => bcrypt('secret'),
            'first_name'    => 'Admin',
            'last_name'     => 'User',
            'role'          => Admin::ROLE_SUPERADMIN,
            'is_active'     => true,
        ]);
        $this->be($admin, 'admin');
        return $admin;
    }

    public function test_admin_marketing_index_requires_auth(): void
    {
        $response = $this->get('/admin/marketing');
        $response->assertRedirect(); // redirect to login
    }

    public function test_admin_marketing_index_renders_when_authenticated(): void
    {
        $this->actingAsAdmin();
        $response = $this->get('/admin/marketing');
        $response->assertOk();
    }

    public function test_admin_toggle_master_switch(): void
    {
        $this->actingAsAdmin();
        $this->assertFalse((bool) AppSetting::get('marketing_enabled', '0'));

        $response = $this->post('/admin/marketing/toggle', [
            'feature' => 'master',
            'enabled' => '1',
        ]);
        $response->assertRedirect();

        AppSetting::flushCache();
        $this->assertSame('1', AppSetting::get('marketing_enabled'));
    }

    public function test_admin_landing_page_index(): void
    {
        $this->actingAsAdmin();
        $response = $this->get('/admin/marketing/landing');
        $response->assertOk();
    }

    public function test_admin_create_landing_page(): void
    {
        $this->actingAsAdmin();
        $response = $this->post('/admin/marketing/landing', [
            'title'    => 'Admin Made Page',
            'slug'     => 'admin-made',
            'subtitle' => 'From admin',
            'theme'    => 'indigo',
            'status'   => 'draft',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('marketing_landing_pages', ['slug' => 'admin-made']);
    }

    public function test_admin_update_landing_page(): void
    {
        $this->actingAsAdmin();
        $lp = LandingPage::create([
            'title' => 'Old', 'slug' => 'old-one', 'status' => 'draft',
            'theme' => 'indigo',
        ]);

        $response = $this->put("/admin/marketing/landing/{$lp->id}", [
            'title'  => 'New Title',
            'slug'   => 'old-one',
            'status' => 'published',
            'theme'  => 'emerald',
        ]);
        $response->assertRedirect();

        $lp->refresh();
        $this->assertSame('New Title', $lp->title);
        $this->assertSame('published', $lp->status);
    }

    public function test_admin_delete_landing_page(): void
    {
        $this->actingAsAdmin();
        $lp = LandingPage::create([
            'title' => 'ToDelete', 'slug' => 'todelete', 'status' => 'draft',
        ]);
        $response = $this->delete("/admin/marketing/landing/{$lp->id}");
        $response->assertRedirect();
        $this->assertDatabaseMissing('marketing_landing_pages', ['id' => $lp->id]);
    }

    public function test_admin_push_index(): void
    {
        $this->actingAsAdmin();
        $response = $this->get('/admin/marketing/push');
        $response->assertOk();
    }

    public function test_admin_analytics_v2(): void
    {
        $this->actingAsAdmin();
        $response = $this->get('/admin/marketing/analytics-v2');
        $response->assertOk();
    }

    public function test_admin_subscribers_list(): void
    {
        $this->actingAsAdmin();
        $response = $this->get('/admin/marketing/subscribers');
        $response->assertOk();
    }

    public function test_admin_referral(): void
    {
        $this->actingAsAdmin();
        $response = $this->get('/admin/marketing/referral');
        $response->assertOk();
    }

    public function test_admin_loyalty(): void
    {
        $this->actingAsAdmin();
        $response = $this->get('/admin/marketing/loyalty');
        $response->assertOk();
    }

    public function test_admin_pixels(): void
    {
        $this->actingAsAdmin();
        $response = $this->get('/admin/marketing/pixels');
        $response->assertOk();
    }

    public function test_admin_pixels_update(): void
    {
        $this->actingAsAdmin();
        $response = $this->post('/admin/marketing/pixels', [
            'ga4_enabled'        => '1',
            'ga4_measurement_id' => 'G-TEST1234',
            'fb_pixel_enabled'   => '0',
        ]);
        $response->assertRedirect();

        AppSetting::flushCache();
        $this->assertSame('G-TEST1234', AppSetting::get('marketing_ga4_measurement_id'));
    }
}
