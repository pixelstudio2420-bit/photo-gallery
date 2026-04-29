<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Models\PhotographerProfile;
use App\Models\PhotographerTeamMember;
use App\Services\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Team-management UI for photographers on Business+ plans.
 *
 * Routes (registered in routes/web.php):
 *   GET    /photographer/team                  → list seats + invite form
 *   POST   /photographer/team/invite           → create pending invite
 *   POST   /photographer/team/{member}/role    → change role
 *   DELETE /photographer/team/{member}         → revoke seat
 *   GET    /team/accept/{token}                → public-ish accept link
 */
class TeamController extends Controller
{
    public function __construct(private TeamService $team) {}

    public function index(): View
    {
        $profile = $this->profile();
        $members = PhotographerTeamMember::forOwner($profile->user_id)
            ->whereIn('status', [PhotographerTeamMember::STATUS_PENDING, PhotographerTeamMember::STATUS_ACTIVE])
            ->with('user')
            ->orderBy('id', 'desc')
            ->get();

        return view('photographer.team.index', [
            'profile'      => $profile,
            'members'      => $members,
            'seatsAvail'   => $this->team->seatsAvailable($profile),
            'maxAdditional' => $this->team->maxAdditionalSeats($profile),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $profile = $this->profile();
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'role'  => 'required|in:admin,editor,viewer',
        ]);

        try {
            $invite = $this->team->invite($profile, $data['email'], $data['role']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $acceptUrl = route('team.accept', ['token' => $invite->invite_token]);

        // We don't ship transactional email yet — surface the link inline so
        // the photographer can copy/paste it. When email is wired up, swap
        // for a notification dispatch.
        return back()->with('success',
            "ส่งคำเชิญถึง {$data['email']} แล้ว — ส่งลิงก์นี้ให้สมาชิก: {$acceptUrl}"
        );
    }

    public function changeRole(Request $request, int $member): RedirectResponse
    {
        $profile = $this->profile();
        $data = $request->validate(['role' => 'required|in:admin,editor,viewer']);

        $ok = $this->team->changeRole($profile, $member, $data['role']);

        return back()->with($ok ? 'success' : 'error',
            $ok ? 'อัปเดตสิทธิ์เรียบร้อย' : 'ไม่สามารถอัปเดตสิทธิ์ได้'
        );
    }

    public function revoke(int $member): RedirectResponse
    {
        $profile = $this->profile();
        $ok = $this->team->revoke($profile, $member);

        return back()->with($ok ? 'success' : 'error',
            $ok ? 'ยกเลิกสิทธิ์เรียบร้อย' : 'ไม่พบสมาชิก'
        );
    }

    /**
     * Public-ish accept endpoint — requires user to be logged in. If not,
     * redirect to login with intended URL stored.
     */
    public function accept(string $token): RedirectResponse
    {
        if (!Auth::check()) {
            session(['url.intended' => url()->current()]);
            return redirect()->route('login')
                ->with('info', 'กรุณาเข้าสู่ระบบเพื่อยอมรับคำเชิญทีม');
        }

        try {
            $invite = $this->team->accept($token, Auth::user());
        } catch (\DomainException $e) {
            return redirect()->route('home')->with('error', $e->getMessage());
        }

        return redirect()->route('photographer.events.index')
            ->with('success', 'เข้าร่วมทีมเรียบร้อย');
    }

    private function profile(): PhotographerProfile
    {
        $profile = Auth::user()?->photographerProfile;
        abort_unless($profile instanceof PhotographerProfile, 403, 'ไม่พบโปรไฟล์ช่างภาพ');
        return $profile;
    }
}
