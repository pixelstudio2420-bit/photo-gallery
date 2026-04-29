<?php

namespace App\Services;

use App\Models\PhotographerProfile;
use App\Models\PhotographerTeamMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * TeamService
 * ───────────
 * Owns invite / accept / revoke flows for the Team Members feature
 * (gated by SubscriptionPlan.max_team_seats — Free/Starter/Pro = 1
 * meaning owner only, Business = 3, Studio = 10).
 *
 * The "owner" is always the photographer's User row — they implicitly
 * occupy seat #1 and aren't stored in photographer_team_members.
 *
 * Cap math: max_team_seats includes the owner. So Business = 3 means
 * "owner + 2 invited members". seatsAvailable() returns the remainder.
 */
class TeamService
{
    public function __construct(private SubscriptionService $subs) {}

    /**
     * How many ADDITIONAL seats the photographer can fill beyond themselves.
     * Returns 0 when at-cap, the slot count when there's room.
     */
    public function seatsAvailable(PhotographerProfile $profile): int
    {
        // Global admin kill switch — if Team feature is disabled at
        // /admin/features, no one can invite regardless of plan.
        if (!$this->subs->featureGloballyEnabled('team_seats')) return 0;

        $cap = (int) ($this->subs->currentPlan($profile)->max_team_seats ?? 1);
        // Cap of 1 (or less) = owner only. No invites permitted.
        if ($cap <= 1) return 0;

        $used = PhotographerTeamMember::forOwner($profile->user_id)
            ->whereIn('status', [PhotographerTeamMember::STATUS_PENDING, PhotographerTeamMember::STATUS_ACTIVE])
            ->count();

        return max(0, ($cap - 1) - $used);
    }

    public function maxAdditionalSeats(PhotographerProfile $profile): int
    {
        if (!$this->subs->featureGloballyEnabled('team_seats')) return 0;
        $cap = (int) ($this->subs->currentPlan($profile)->max_team_seats ?? 1);
        return max(0, $cap - 1);
    }

    /**
     * Create a pending invite. Returns the new TeamMember row.
     * Caller is responsible for emailing the invite_token URL.
     */
    public function invite(PhotographerProfile $profile, string $email, string $role = PhotographerTeamMember::ROLE_EDITOR): PhotographerTeamMember
    {
        if ($this->seatsAvailable($profile) <= 0) {
            throw new \DomainException('ทีมเต็มโควต้าแล้ว — อัปเกรดแผนเพื่อเพิ่มที่นั่ง');
        }

        // Prevent duplicate pending invites to the same email.
        $existing = PhotographerTeamMember::forOwner($profile->user_id)
            ->where('invite_email', $email)
            ->whereIn('status', [PhotographerTeamMember::STATUS_PENDING, PhotographerTeamMember::STATUS_ACTIVE])
            ->first();
        if ($existing) {
            throw new \DomainException('อีเมลนี้ได้รับเชิญแล้ว');
        }

        return PhotographerTeamMember::create([
            'owner_user_id' => $profile->user_id,
            'invite_email'  => $email,
            'invite_token'  => bin2hex(random_bytes(24)),
            'invited_at'    => now(),
            'role'          => $role,
            'status'        => PhotographerTeamMember::STATUS_PENDING,
        ]);
    }

    /**
     * Accept an invite. The user must be authenticated; we attach
     * their user_id and flip status → active.
     */
    public function accept(string $token, User $user): PhotographerTeamMember
    {
        return DB::transaction(function () use ($token, $user) {
            $invite = PhotographerTeamMember::where('invite_token', $token)
                ->where('status', PhotographerTeamMember::STATUS_PENDING)
                ->lockForUpdate()
                ->first();

            if (!$invite) {
                throw new \DomainException('คำเชิญไม่ถูกต้องหรือหมดอายุแล้ว');
            }

            // The accepting user's email should match the invited email.
            if (strcasecmp($invite->invite_email, $user->email) !== 0) {
                throw new \DomainException('คำเชิญนี้สำหรับอีเมลอื่น กรุณาเข้าสู่ระบบด้วยอีเมลที่ได้รับ');
            }

            $invite->forceFill([
                'user_id'       => $user->id,
                'status'        => PhotographerTeamMember::STATUS_ACTIVE,
                'accepted_at'   => now(),
                'invite_token'  => null, // single-use
            ])->save();

            return $invite;
        });
    }

    public function revoke(PhotographerProfile $profile, int $memberId): bool
    {
        $member = PhotographerTeamMember::forOwner($profile->user_id)
            ->where('id', $memberId)
            ->first();
        if (!$member) return false;

        $member->forceFill(['status' => PhotographerTeamMember::STATUS_REVOKED])->save();
        return true;
    }

    public function changeRole(PhotographerProfile $profile, int $memberId, string $role): bool
    {
        if (!in_array($role, [
            PhotographerTeamMember::ROLE_ADMIN,
            PhotographerTeamMember::ROLE_EDITOR,
            PhotographerTeamMember::ROLE_VIEWER,
        ], true)) {
            return false;
        }

        $member = PhotographerTeamMember::forOwner($profile->user_id)
            ->where('id', $memberId)
            ->first();
        if (!$member) return false;

        $member->forceFill(['role' => $role])->save();
        return true;
    }

    /**
     * Resolve the photographer "owner" the current user can act on behalf of.
     * - If they ARE a photographer → returns their own user_id
     * - If they're an active team member of someone else → returns the owner's user_id
     * - Otherwise null
     *
     * Used by middleware/controllers that need to know "which photographer's
     * data is this request operating on?" when a team member uploads photos
     * to their team owner's events.
     */
    public function resolveOwnerFor(?User $user): ?int
    {
        if (!$user) return null;
        if ($user->is_photographer ?? false) return $user->id;

        $seat = PhotographerTeamMember::where('user_id', $user->id)
            ->where('status', PhotographerTeamMember::STATUS_ACTIVE)
            ->latest('id')
            ->first();
        return $seat?->owner_user_id;
    }
}
