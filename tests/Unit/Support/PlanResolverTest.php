<?php

namespace Tests\Unit\Support;

use App\Models\PhotographerProfile;
use App\Models\User;
use App\Support\PlanResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Tests\TestCase;

class PlanResolverTest extends TestCase
{
    public function test_returns_free_for_null_user(): void
    {
        $this->assertSame('free', PlanResolver::photographerCode(null));
        $this->assertSame('free', PlanResolver::resolveCode(null));
    }

    public function test_returns_free_for_non_user_authenticatable(): void
    {
        // Some auth implementations return a custom Authenticatable that
        // isn't an Eloquent User (e.g. token guard during impersonation).
        $stub = new class implements Authenticatable {
            public function getAuthIdentifierName() { return 'id'; }
            public function getAuthIdentifier()     { return 99; }
            public function getAuthPasswordName()   { return 'password'; }
            public function getAuthPassword()       { return ''; }
            public function getRememberToken()      { return ''; }
            public function setRememberToken($v): void {}
            public function getRememberTokenName()  { return ''; }
        };
        $this->assertSame('free', PlanResolver::photographerCode($stub));
        $this->assertSame('free', PlanResolver::resolveCode($stub));
    }

    public function test_returns_free_when_user_has_no_photographer_profile(): void
    {
        $u = new User();
        $u->forceFill(['id' => 1])->setRelation('photographerProfile', null);
        $this->assertSame('free', PlanResolver::photographerCode($u));
    }

    public function test_returns_free_when_profile_has_no_plan_code(): void
    {
        $u = new User();
        $u->forceFill(['id' => 1]);
        $u->setRelation('photographerProfile', new PhotographerProfile());
        $this->assertSame('free', PlanResolver::photographerCode($u));
    }

    public function test_returns_photographer_plan_when_present(): void
    {
        $u = new User();
        $u->forceFill(['id' => 1]);
        $profile = new PhotographerProfile();
        $profile->forceFill(['subscription_plan_code' => 'pro']);
        $u->setRelation('photographerProfile', $profile);

        $this->assertSame('pro', PlanResolver::photographerCode($u));
        $this->assertSame('pro', PlanResolver::resolveCode($u));
    }

    public function test_resolve_falls_through_to_storage_plan_when_photographer_is_free(): void
    {
        $u = new User();
        $u->forceFill(['id' => 1, 'storage_plan_code' => 'plus']);
        $u->setRelation('photographerProfile', null);

        $this->assertSame('free', PlanResolver::photographerCode($u));
        $this->assertSame('plus', PlanResolver::resolveCode($u));
    }

    public function test_resolve_prefers_photographer_over_storage_when_both_set(): void
    {
        $u = new User();
        $u->forceFill(['id' => 1, 'storage_plan_code' => 'plus']);
        $profile = new PhotographerProfile();
        $profile->forceFill(['subscription_plan_code' => 'business']);
        $u->setRelation('photographerProfile', $profile);

        $this->assertSame('business', PlanResolver::resolveCode($u));
    }

    public function test_resolve_returns_free_when_storage_plan_is_null(): void
    {
        $u = new User();
        $u->forceFill(['id' => 1, 'storage_plan_code' => null]);
        $u->setRelation('photographerProfile', null);

        $this->assertSame('free', PlanResolver::resolveCode($u));
    }

    public function test_storage_code_reads_storage_plan_only(): void
    {
        $u = new User();
        $u->forceFill(['id' => 1, 'storage_plan_code' => 'pro']);
        // Even when a photographer plan is set, storageCode() ignores it
        // — the consumer storage product is billed separately.
        $profile = new PhotographerProfile();
        $profile->forceFill(['subscription_plan_code' => 'business']);
        $u->setRelation('photographerProfile', $profile);

        $this->assertSame('pro', PlanResolver::storageCode($u));
    }

    public function test_storage_code_falls_back_to_free(): void
    {
        $u = new User();
        $u->forceFill(['id' => 1, 'storage_plan_code' => null]);
        $this->assertSame('free', PlanResolver::storageCode($u));
        $this->assertSame('free', PlanResolver::storageCode(null));
    }
}
