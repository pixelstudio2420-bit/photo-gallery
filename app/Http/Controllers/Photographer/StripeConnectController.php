<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StripeConnectController extends Controller
{
    /**
     * Show Stripe Connect status page.
     */
    public function show()
    {
        if (AppSetting::get('stripe_connect_enabled') !== '1') {
            return redirect()->back()->with('error', 'Stripe Connect ยังไม่ได้เปิดใช้งาน กรุณาติดต่อผู้ดูแลระบบ');
        }

        $user = Auth::user();

        // Safely read Stripe columns — they may not exist yet
        $stripeAccountId        = null;
        $stripeChargesEnabled   = false;
        $stripePayoutsEnabled   = false;
        $stripeDetailsSubmitted = false;

        try {
            $stripeAccountId        = $user->stripe_account_id        ?? null;
            $stripeChargesEnabled   = (bool) ($user->stripe_charges_enabled   ?? false);
            $stripePayoutsEnabled   = (bool) ($user->stripe_payouts_enabled   ?? false);
            $stripeDetailsSubmitted = (bool) ($user->stripe_details_submitted ?? false);
        } catch (\Throwable $e) {
            \Log::warning('Stripe columns may not exist: ' . $e->getMessage());
        }

        $platformCommission = AppSetting::get('platform_commission', '20');

        return view('photographer.stripe-connect', compact(
            'stripeAccountId',
            'stripeChargesEnabled',
            'stripePayoutsEnabled',
            'stripeDetailsSubmitted',
            'platformCommission'
        ));
    }

    /**
     * Start Stripe Express onboarding.
     */
    public function onboard(Request $request)
    {
        if (AppSetting::get('stripe_connect_enabled') !== '1') {
            return redirect()->back()->with('error', 'Stripe Connect ยังไม่ได้เปิดใช้งาน');
        }

        if (!class_exists(\Stripe\Stripe::class)) {
            return redirect()->route('photographer.stripe-connect')
                ->with('error', 'Stripe SDK ยังไม่ได้ติดตั้ง กรุณารัน: composer require stripe/stripe-php');
        }

        try {
            \Stripe\Stripe::setApiKey(\App\Services\Payment\StripeGateway::secretKey());

            $user = Auth::user();
            $stripeAccountId = null;

            try {
                $stripeAccountId = $user->stripe_account_id ?? null;
            } catch (\Throwable $e) {
                // Column may not exist
            }

            // Create a new Express account if needed
            if (!$stripeAccountId) {
                $account = \Stripe\Account::create([
                    'type'    => 'express',
                    'email'   => $user->email,
                    'country' => 'TH',
                    'capabilities' => [
                        'card_payments' => ['requested' => true],
                        'transfers'     => ['requested' => true],
                    ],
                ]);

                $stripeAccountId = $account->id;

                try {
                    $user->update(['stripe_account_id' => $stripeAccountId]);
                } catch (\Throwable $e) {
                    \Log::warning('Could not save stripe_account_id: ' . $e->getMessage());
                }
            }

            // Generate onboarding link
            $accountLink = \Stripe\AccountLink::create([
                'account'     => $stripeAccountId,
                'refresh_url' => route('photographer.stripe-connect.refresh'),
                'return_url'  => route('photographer.stripe-connect.return'),
                'type'        => 'account_onboarding',
            ]);

            return redirect($accountLink->url);

        } catch (\Throwable $e) {
            \Log::error('Stripe onboard error: ' . $e->getMessage());
            return redirect()->route('photographer.stripe-connect')
                ->with('error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ Stripe: ' . $e->getMessage());
        }
    }

    /**
     * Handle return from Stripe onboarding.
     */
    public function return(Request $request)
    {
        if (!class_exists(\Stripe\Stripe::class)) {
            return redirect()->route('photographer.stripe-connect')
                ->with('warning', 'Stripe SDK ยังไม่ได้ติดตั้ง');
        }

        try {
            $user = Auth::user();
            $stripeAccountId = null;

            try {
                $stripeAccountId = $user->stripe_account_id ?? null;
            } catch (\Throwable $e) {
                // Column may not exist
            }

            if (!$stripeAccountId) {
                return redirect()->route('photographer.stripe-connect')
                    ->with('warning', 'ไม่พบบัญชี Stripe กรุณาเริ่มต้นใหม่');
            }

            \Stripe\Stripe::setApiKey(\App\Services\Payment\StripeGateway::secretKey());
            $account = \Stripe\Account::retrieve($stripeAccountId);

            $updates = [
                'stripe_charges_enabled'   => $account->charges_enabled   ? 1 : 0,
                'stripe_payouts_enabled'   => $account->payouts_enabled    ? 1 : 0,
                'stripe_details_submitted' => $account->details_submitted  ? 1 : 0,
            ];

            try {
                $user->update($updates);
            } catch (\Throwable $e) {
                \Log::warning('Could not update stripe status columns: ' . $e->getMessage());
            }

            $message = $account->charges_enabled && $account->payouts_enabled
                ? 'เชื่อมต่อ Stripe สำเร็จ! คุณพร้อมรับชำระเงินแล้ว'
                : 'อัปเดตข้อมูล Stripe แล้ว กรุณาตรวจสอบสถานะ';

            return redirect()->route('photographer.stripe-connect')->with('success', $message);

        } catch (\Throwable $e) {
            \Log::error('Stripe return error: ' . $e->getMessage());
            return redirect()->route('photographer.stripe-connect')
                ->with('error', 'เกิดข้อผิดพลาดในการดึงข้อมูล Stripe: ' . $e->getMessage());
        }
    }

    /**
     * Refresh — regenerate onboarding link.
     */
    public function refresh(Request $request)
    {
        return redirect()->route('photographer.stripe-connect.onboard');
    }

    /**
     * Open Stripe Express Dashboard.
     */
    public function dashboard(Request $request)
    {
        if (!class_exists(\Stripe\Stripe::class)) {
            return redirect()->route('photographer.stripe-connect')
                ->with('error', 'Stripe SDK ยังไม่ได้ติดตั้ง');
        }

        try {
            $user = Auth::user();
            $stripeAccountId = null;

            try {
                $stripeAccountId = $user->stripe_account_id ?? null;
            } catch (\Throwable $e) {
                // Column may not exist
            }

            if (!$stripeAccountId) {
                return redirect()->route('photographer.stripe-connect')
                    ->with('error', 'ยังไม่ได้เชื่อมต่อบัญชี Stripe');
            }

            \Stripe\Stripe::setApiKey(\App\Services\Payment\StripeGateway::secretKey());
            $loginLink = \Stripe\Account::createLoginLink($stripeAccountId);

            return redirect($loginLink->url);

        } catch (\Throwable $e) {
            \Log::error('Stripe dashboard error: ' . $e->getMessage());
            return redirect()->route('photographer.stripe-connect')
                ->with('error', 'ไม่สามารถเปิด Stripe Dashboard ได้: ' . $e->getMessage());
        }
    }
}
