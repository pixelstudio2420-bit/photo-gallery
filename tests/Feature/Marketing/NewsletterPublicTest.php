<?php

namespace Tests\Feature\Marketing;

use App\Models\AppSetting;
use App\Models\Marketing\Subscriber;
use Illuminate\Support\Facades\Mail;

class NewsletterPublicTest extends MarketingTestCase
{
    public function test_subscribe_returns_error_when_newsletter_disabled(): void
    {
        $response = $this->postJson('/newsletter/subscribe', ['email' => 'test@x.com']);
        $response->assertOk()->assertJson(['ok' => false]);
    }

    public function test_subscribe_success_when_enabled(): void
    {
        $this->enableMarketing('newsletter');
        AppSetting::set('marketing_newsletter_double_optin', '0');
        AppSetting::flushCache();

        $response = $this->postJson('/newsletter/subscribe', [
            'email' => 'hello@test.com',
            'name'  => 'Hello',
        ]);
        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('marketing_subscribers', ['email' => 'hello@test.com']);
    }

    public function test_subscribe_validates_email_format(): void
    {
        $this->enableMarketing('newsletter');
        $response = $this->postJson('/newsletter/subscribe', ['email' => 'not-an-email']);
        $response->assertStatus(422);
    }

    public function test_confirm_route_marks_subscriber_confirmed(): void
    {
        $this->enableMarketing('newsletter');
        AppSetting::set('marketing_newsletter_double_optin', '1');
        AppSetting::flushCache();
        Mail::fake();

        $this->postJson('/newsletter/subscribe', ['email' => 'confirm@test.com']);
        $sub = Subscriber::where('email', 'confirm@test.com')->first();
        $this->assertNotNull($sub);

        $response = $this->get('/newsletter/confirm/' . $sub->confirm_token);
        $response->assertOk();

        $sub->refresh();
        $this->assertSame('confirmed', $sub->status);
    }

    public function test_unsubscribe_with_no_email_shows_ask_view(): void
    {
        $this->enableMarketing('newsletter');
        $response = $this->get('/newsletter/unsubscribe');
        $response->assertOk();
    }

    public function test_unsubscribe_post_marks_unsubscribed(): void
    {
        $this->enableMarketing('newsletter');
        AppSetting::set('marketing_newsletter_double_optin', '0');
        AppSetting::flushCache();

        $this->postJson('/newsletter/subscribe', ['email' => 'leave@test.com']);
        $response = $this->post('/newsletter/unsubscribe', [
            'email'  => 'leave@test.com',
            'reason' => 'too many emails',
        ]);
        $response->assertOk();
        $sub = Subscriber::where('email', 'leave@test.com')->first();
        $this->assertSame('unsubscribed', $sub->status);
    }
}
