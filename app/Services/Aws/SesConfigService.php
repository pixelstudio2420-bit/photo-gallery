<?php
namespace App\Services\Aws;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class SesConfigService
{
    /**
     * Apply SES configuration from AppSetting to Laravel's mail config at runtime.
     * Call this in a service provider or before sending mail.
     */
    public static function apply(): void
    {
        if (AppSetting::get('aws_ses_enabled', '0') !== '1') {
            return;
        }

        $region = AppSetting::get('aws_ses_region', '') ?: AppSetting::get('aws_default_region', 'us-east-1');
        $accessKey = AppSetting::get('aws_access_key_id', '');
        $secretKey = AppSetting::get('aws_secret_access_key', '');

        if (empty($accessKey) || empty($secretKey)) {
            return;
        }

        // Override Laravel mail config to use SES
        Config::set('mail.default', 'ses');
        Config::set('services.ses.key', $accessKey);
        Config::set('services.ses.secret', $secretKey);
        Config::set('services.ses.region', $region);

        $fromEmail = AppSetting::get('aws_ses_from_email', '');
        $fromName = AppSetting::get('aws_ses_from_name', '');

        if ($fromEmail) {
            Config::set('mail.from.address', $fromEmail);
        }
        if ($fromName) {
            Config::set('mail.from.name', $fromName);
        }
    }

    /**
     * Test SES connection by sending a test email.
     */
    public static function testConnection(string $toEmail): array
    {
        try {
            self::apply();

            \Illuminate\Support\Facades\Mail::raw(
                'This is a test email from ' . config('app.name') . ' via AWS SES. If you received this, SES is configured correctly.',
                function ($message) use ($toEmail) {
                    $message->to($toEmail)
                        ->subject('[Test] AWS SES Connection Test - ' . config('app.name'));
                }
            );

            return ['success' => true, 'message' => 'Test email sent successfully to ' . $toEmail];
        } catch (\Throwable $e) {
            Log::error('SES test failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public static function isEnabled(): bool
    {
        return AppSetting::get('aws_ses_enabled', '0') === '1'
            && AppSetting::get('aws_access_key_id', '') !== ''
            && AppSetting::get('aws_secret_access_key', '') !== '';
    }
}
