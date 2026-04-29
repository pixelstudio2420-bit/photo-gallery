<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\PhotographerProfile;
use App\Services\AiTaskService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Photographer-facing AI tools dashboard + per-event run endpoints.
 *
 * Each runX endpoint is gated by the corresponding `subscription.feature`
 * middleware (registered in routes/web.php) so the request never reaches
 * the controller if the photographer's plan doesn't grant the feature.
 * As a defence-in-depth measure, AiTaskService also re-checks the gate.
 */
class AiToolsController extends Controller
{
    public function __construct(
        private AiTaskService $tasks,
        private SubscriptionService $subs,
    ) {}

    public function index(Request $request): View
    {
        $profile = $this->profile();
        $plan    = $this->subs->currentPlan($profile);
        // Filter out features whose global flag is OFF — keeps deprecated
        // tiles (color_enhance / smart_captions / video_thumbnails) hidden
        // even when the plan still grants them in JSON. Single source of
        // truth: SubscriptionService::featureGloballyEnabled().
        $features = collect($plan->ai_features ?? [])
            ->filter(fn($f) => $this->subs->featureGloballyEnabled($f))
            ->values()
            ->all();

        $events = Event::where('photographer_id', $profile->user_id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id','name','slug','status']);

        $recent = $this->tasks->recentTasks($profile, 15);

        return view('photographer.ai.index', [
            'profile'            => $profile,
            'plan'               => $plan,
            'features'           => $features,
            'events'             => $events,
            'recentTasks'        => $recent,
            'creditsRemaining'   => $this->subs->aiCreditsRemaining($profile),
            'creditsCap'         => $this->subs->monthlyAiCredits($profile),
            'creditsUsed'        => $this->subs->aiCreditsUsed($profile),
        ]);
    }

    public function runDuplicateDetection(Request $request, int $event): RedirectResponse
    {
        return $this->dispatch(fn ($p, $ev) => $this->tasks->runDuplicateDetection($p, $ev), $event,
            fn ($r) => 'พบรูปซ้ำ '.($r['result']['total_duplicates'] ?? 0).' รูป จาก '.($r['result']['processed'] ?? 0).' รูป');
    }

    public function runQualityFilter(Request $request, int $event): RedirectResponse
    {
        return $this->dispatch(fn ($p, $ev) => $this->tasks->runQualityFilter($p, $ev), $event,
            fn ($r) => 'พบรูปเบลอ '.($r['result']['blurry'] ?? 0).' รูป จาก '.($r['result']['processed'] ?? 0).' รูป');
    }

    public function runBestShot(Request $request, int $event): RedirectResponse
    {
        return $this->dispatch(fn ($p, $ev) => $this->tasks->runBestShot($p, $ev), $event,
            fn ($r) => 'จัดอันดับ '.count($r['result']['top_shots'] ?? []).' รูปแนะนำเรียบร้อย');
    }

    public function runColorEnhance(Request $request, int $event): RedirectResponse
    {
        return $this->dispatch(fn ($p, $ev) => $this->tasks->runColorEnhance($p, $ev), $event,
            fn ($r) => 'ปรับสี '.($r['result']['enhanced'] ?? 0).' รูปเรียบร้อย');
    }

    public function runAutoTagging(Request $request, int $event): RedirectResponse
    {
        return $this->dispatch(fn ($p, $ev) => $this->tasks->runAutoTagging($p, $ev), $event,
            fn ($r) => 'ติดแท็ก '.($r['result']['tagged'] ?? 0).' รูป (โหมด: '.($r['result']['mode'] ?? '?').')');
    }

    public function runFaceIndex(Request $request, int $event): RedirectResponse
    {
        return $this->dispatch(fn ($p, $ev) => $this->tasks->runFaceIndex($p, $ev), $event,
            fn ($r) => 'index ใบหน้า '.($r['result']['total_faces'] ?? 0).' หน้า'
                .(empty($r['result']['configure_hint']) ? '' : ' — '.$r['result']['configure_hint']));
    }

    public function runSmartCaptions(Request $request, int $event): RedirectResponse
    {
        return $this->dispatch(fn ($p, $ev) => $this->tasks->runSmartCaptions($p, $ev), $event,
            fn ($r) => 'สร้าง caption '.($r['result']['captioned'] ?? 0).' รูป (โหมด: '.($r['result']['mode'] ?? '?').')');
    }

    public function runVideoThumbnails(Request $request, int $event): RedirectResponse
    {
        return $this->dispatch(fn ($p, $ev) => $this->tasks->runVideoThumbnails($p, $ev), $event,
            fn ($r) => 'สร้าง thumbnail '.($r['result']['thumbnails'] ?? 0).' วิดีโอ'
                .(empty($r['result']['configure_hint']) ? '' : ' — '.$r['result']['configure_hint']));
    }

    /**
     * Common dispatch + flash wrapper for every AI run endpoint.
     */
    private function dispatch(\Closure $runner, int $eventId, \Closure $messageBuilder): RedirectResponse
    {
        $profile = $this->profile();
        $event   = Event::where('id', $eventId)
            ->where('photographer_id', $profile->user_id)
            ->first();
        if (!$event) {
            return back()->with('error', 'ไม่พบอีเวนต์');
        }

        $result = $runner($profile, $event);
        if (!$result['ok']) {
            return back()->with('error', $result['message'] ?? 'ทำไม่สำเร็จ');
        }

        return back()->with('success', $messageBuilder($result));
    }

    private function profile(): PhotographerProfile
    {
        $profile = Auth::user()?->photographerProfile;
        abort_unless($profile instanceof PhotographerProfile, 403, 'ไม่พบโปรไฟล์ช่างภาพ');
        return $profile;
    }
}
