<?php

namespace Tests\Feature\Marketing;

use App\Models\Marketing\LandingPage;
use App\Services\Marketing\LandingPageService;

class LandingPagePublicTest extends MarketingTestCase
{
    public function test_landing_page_404_when_feature_disabled(): void
    {
        $svc = app(LandingPageService::class);
        $lp = $svc->create(['title' => 'Disabled Test']);
        $svc->publish($lp);

        $response = $this->get('/lp/' . $lp->slug);
        $response->assertNotFound();
    }

    public function test_landing_page_404_when_draft(): void
    {
        $this->enableMarketing('landing_pages');
        $svc = app(LandingPageService::class);
        $lp = $svc->create(['title' => 'Still Draft']);
        // not published

        $response = $this->get('/lp/' . $lp->slug);
        $response->assertNotFound();
    }

    public function test_landing_page_renders_when_published(): void
    {
        $this->enableMarketing('landing_pages');
        $svc = app(LandingPageService::class);
        $lp = $svc->create([
            'title'    => 'Welcome!',
            'subtitle' => 'A special landing',
            'sections' => [
                ['type' => 'heading', 'data' => ['heading' => 'Hello']],
                ['type' => 'text',    'data' => ['body' => 'World body text']],
            ],
        ]);
        $svc->publish($lp);

        $response = $this->get('/lp/' . $lp->slug);
        $response->assertOk();
        $response->assertSee('Welcome!');

        $lp->refresh();
        $this->assertGreaterThanOrEqual(1, (int) $lp->views);
    }

    public function test_landing_page_cta_redirects_and_increments_conversion(): void
    {
        $this->enableMarketing('landing_pages');
        $svc = app(LandingPageService::class);
        $lp = $svc->create([
            'title'   => 'CTA Target',
            'cta_url' => 'https://example.com/go',
        ]);
        $svc->publish($lp);

        $response = $this->get("/lp/{$lp->id}/cta");
        $response->assertRedirect('https://example.com/go');

        $lp->refresh();
        $this->assertSame(1, (int) $lp->conversions);
    }

    public function test_push_vapid_endpoint_403_when_disabled(): void
    {
        $response = $this->getJson('/push/vapid-public');
        $response->assertStatus(403);
    }

    public function test_push_vapid_endpoint_returns_public_key_when_enabled(): void
    {
        $this->enableMarketing('push');
        \App\Models\AppSetting::set('marketing_push_vapid_public', 'MYKEY123');
        \App\Models\AppSetting::flushCache();

        $response = $this->getJson('/push/vapid-public');
        $response->assertOk()->assertJson(['ok' => true, 'publicKey' => 'MYKEY123']);
    }

    public function test_push_subscribe_403_when_disabled(): void
    {
        $response = $this->postJson('/push/subscribe', [
            'endpoint' => 'https://push.example.com/x',
            'keys' => ['p256dh' => 'a', 'auth' => 'b'],
        ]);
        $response->assertStatus(403);
    }

    public function test_push_subscribe_creates_subscription(): void
    {
        $this->enableMarketing('push');

        $response = $this->postJson('/push/subscribe', [
            'endpoint' => 'https://push.example.com/xyz',
            'keys' => ['p256dh' => 'ABCDEF', 'auth' => 'XYZ'],
        ]);
        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('marketing_push_subscriptions', [
            'endpoint' => 'https://push.example.com/xyz',
            'p256dh'   => 'ABCDEF',
        ]);
    }

    public function test_push_service_worker_route_returns_javascript(): void
    {
        $response = $this->get('/push-sw.js');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/javascript; charset=utf-8');
    }
}
