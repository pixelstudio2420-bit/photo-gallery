<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PhotographerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PhotographerOnboardingController extends Controller
{
    public function index(Request $request)
    {
        $stage = $request->string('stage')->toString() ?: null;

        $q = PhotographerProfile::with('user:id,email,first_name,last_name,username')
            ->orderByDesc('updated_at');
        if ($stage) $q->where('onboarding_stage', $stage);

        $profiles = $q->paginate(25)->withQueryString();

        $counts = PhotographerProfile::query()
            ->selectRaw('onboarding_stage, COUNT(*) as c')
            ->groupBy('onboarding_stage')
            ->pluck('c', 'onboarding_stage')
            ->toArray();

        $stages = PhotographerProfile::onboardingStages();

        return view('admin.photographers.onboarding', compact('profiles', 'counts', 'stages', 'stage'));
    }

    public function review(PhotographerProfile $profile)
    {
        $profile->load('user');
        $stages = PhotographerProfile::onboardingStages();
        return view('admin.photographers.review', compact('profile', 'stages'));
    }

    public function approve(PhotographerProfile $profile)
    {
        // Admin approval now flows straight to 'active' — the interim
        // 'approved → contract_signed' steps were dropped when we removed
        // the ID-card + contract-signing requirement (2026-04-25).
        // Tier is synced so the photographer is promoted to Pro immediately
        // if they also have a PromptPay number on file.
        $profile->onboarding_stage = 'active';
        $profile->status = 'approved';
        $profile->approved_by = Auth::guard('admin')->id();
        $profile->approved_at = now();
        $profile->rejection_reason = null;
        $profile->save();
        $profile->syncTier();
        return back()->with('success', 'อนุมัติช่างภาพแล้ว — พร้อมรับงานได้ทันที');
    }

    public function reject(Request $request, PhotographerProfile $profile)
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $profile->onboarding_stage = 'rejected';
        $profile->status = 'suspended';
        $profile->rejection_reason = $data['reason'];
        $profile->save();
        return back()->with('success', 'ปฏิเสธใบสมัครแล้ว');
    }

    public function markReviewing(PhotographerProfile $profile)
    {
        if ($profile->onboarding_stage === 'submitted') {
            $profile->onboarding_stage = 'under_review';
            $profile->save();
        }
        return back()->with('success', 'เริ่มตรวจสอบแล้ว');
    }
}
