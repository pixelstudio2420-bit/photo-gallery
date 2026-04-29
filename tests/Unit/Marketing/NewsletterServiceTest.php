<?php

namespace Tests\Unit\Marketing;

use App\Models\AppSetting;
use App\Models\Marketing\Subscriber;
use App\Services\Marketing\NewsletterService;
use Illuminate\Support\Facades\Mail;

class NewsletterServiceTest extends MarketingUnitTestCase
{
    public function test_subscribe_fails_when_disabled(): void
    {
        $svc = app(NewsletterService::class);
        $result = $svc->subscribe('test@example.com');

        $this->assertFalse($result['ok']);
        $this->assertNull($result['subscriber']);
    }

    public function test_subscribe_creates_pending_when_double_optin_on(): void
    {
        $this->enableMarketing('newsletter');
        AppSetting::set('marketing_newsletter_double_optin', '1');
        AppSetting::flushCache();
        Mail::fake();

        $svc = app(NewsletterService::class);
        $result = $svc->subscribe('user@test.com', ['name' => 'Alice']);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['needs_confirmation']);
        $sub = Subscriber::where('email', 'user@test.com')->first();
        $this->assertNotNull($sub);
        $this->assertSame('pending', $sub->status);
        $this->assertSame('Alice', $sub->name);
        $this->assertNotNull($sub->confirm_token);
    }

    public function test_subscribe_creates_confirmed_when_double_optin_off(): void
    {
        $this->enableMarketing('newsletter');
        AppSetting::set('marketing_newsletter_double_optin', '0');
        AppSetting::flushCache();

        $svc = app(NewsletterService::class);
        $result = $svc->subscribe('instant@test.com');

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['needs_confirmation']);
        $sub = Subscriber::where('email', 'instant@test.com')->first();
        $this->assertSame('confirmed', $sub->status);
        $this->assertNotNull($sub->confirmed_at);
    }

    public function test_subscribe_rejects_invalid_email(): void
    {
        $this->enableMarketing('newsletter');
        $svc = app(NewsletterService::class);
        $result = $svc->subscribe('not-an-email');
        $this->assertFalse($result['ok']);
    }

    public function test_subscribe_normalizes_email_lowercase_trim(): void
    {
        $this->enableMarketing('newsletter');
        Mail::fake();

        $svc = app(NewsletterService::class);
        $svc->subscribe('  UPPER@Test.COM  ');

        $this->assertDatabaseHas('marketing_subscribers', ['email' => 'upper@test.com']);
    }

    public function test_confirm_with_valid_token_marks_confirmed(): void
    {
        $this->enableMarketing('newsletter');
        Mail::fake();

        $svc = app(NewsletterService::class);
        $svc->subscribe('confirm@test.com');
        $sub = Subscriber::where('email', 'confirm@test.com')->first();
        $token = $sub->confirm_token;

        $result = $svc->confirm($token);
        $this->assertTrue($result['ok']);

        $sub->refresh();
        $this->assertSame('confirmed', $sub->status);
        $this->assertNotNull($sub->confirmed_at);
    }

    public function test_confirm_with_invalid_token_fails(): void
    {
        $this->enableMarketing('newsletter');
        $svc = app(NewsletterService::class);
        $result = $svc->confirm('not-a-real-token');
        $this->assertFalse($result['ok']);
    }

    public function test_unsubscribe_marks_user_unsubscribed(): void
    {
        $this->enableMarketing('newsletter');
        AppSetting::set('marketing_newsletter_double_optin', '0');
        AppSetting::flushCache();
        Mail::fake();

        $svc = app(NewsletterService::class);
        $svc->subscribe('unsub@test.com');

        $result = $svc->unsubscribe('unsub@test.com', 'too many emails');
        $this->assertTrue($result['ok']);

        $sub = Subscriber::where('email', 'unsub@test.com')->first();
        $this->assertSame('unsubscribed', $sub->status);
        $this->assertNotNull($sub->unsubscribed_at);
    }

    public function test_resubscribe_after_unsubscribe(): void
    {
        $this->enableMarketing('newsletter');
        AppSetting::set('marketing_newsletter_double_optin', '1');
        AppSetting::flushCache();
        Mail::fake();

        $svc = app(NewsletterService::class);
        $svc->subscribe('resub@test.com');
        $svc->unsubscribe('resub@test.com');

        $result = $svc->subscribe('resub@test.com');
        $this->assertTrue($result['ok']);

        $sub = Subscriber::where('email', 'resub@test.com')->first();
        $this->assertSame('pending', $sub->status);
        $this->assertNull($sub->unsubscribed_at);
    }
}
