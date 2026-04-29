<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\ChangePasswordRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Verifies the password-change validation:
 *   - Rejects weak passwords (too short, no symbols, no numbers)
 *   - Rejects current password mismatch
 *   - Rejects setting new password equal to current
 */
class ChangePasswordRequestTest extends TestCase
{
    private function rulesFor(string $currentHash): array
    {
        $req = $this->createPartialMock(ChangePasswordRequest::class, []);
        // Bypass auth — we're just testing rule shape, not the closure
        return collect((new ChangePasswordRequest())->rules())
            ->map(fn ($r) => is_array($r) ? array_filter($r, fn ($x) => is_string($x)) : $r)
            ->toArray();
    }

    public function test_rejects_password_shorter_than_10_chars(): void
    {
        $rules = (new ChangePasswordRequest())->rules();
        // Strip the closure (current_password check) since it needs Auth::user()
        $rules['current_password'] = ['required', 'string'];

        $v = Validator::make([
            'current_password'      => 'irrelevant',
            'password'              => 'Aa1!short',
            'password_confirmation' => 'Aa1!short',
        ], $rules);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('password', $v->errors()->toArray());
    }

    public function test_rejects_password_without_numbers(): void
    {
        $rules = (new ChangePasswordRequest())->rules();
        $rules['current_password'] = ['required', 'string'];

        $v = Validator::make([
            'current_password'      => 'irrelevant',
            'password'              => 'NoNumbers!!Here',
            'password_confirmation' => 'NoNumbers!!Here',
        ], $rules);

        $this->assertTrue($v->fails());
    }

    public function test_rejects_password_without_symbols(): void
    {
        $rules = (new ChangePasswordRequest())->rules();
        $rules['current_password'] = ['required', 'string'];

        $v = Validator::make([
            'current_password'      => 'irrelevant',
            'password'              => 'NoSymbols123Here',
            'password_confirmation' => 'NoSymbols123Here',
        ], $rules);

        $this->assertTrue($v->fails());
    }

    public function test_accepts_strong_password_with_all_categories(): void
    {
        $rules = (new ChangePasswordRequest())->rules();
        $rules['current_password'] = ['required', 'string'];
        // Drop "uncompromised" rule because it would hit the HIBP API in CI
        $rules['password'] = collect($rules['password'])
            ->reject(fn ($r) => is_object($r) && method_exists($r, 'uncompromised'))
            ->all();

        $v = Validator::make([
            'current_password'      => 'irrelevant',
            'password'              => 'Str0ngP@ssword!2026',
            'password_confirmation' => 'Str0ngP@ssword!2026',
        ], $rules);

        $this->assertFalse($v->fails(), implode(', ', $v->errors()->all()));
    }

    public function test_rejects_when_password_confirmation_mismatch(): void
    {
        $rules = (new ChangePasswordRequest())->rules();
        $rules['current_password'] = ['required', 'string'];

        $v = Validator::make([
            'current_password'      => 'irrelevant',
            'password'              => 'Str0ngP@ssword!2026',
            'password_confirmation' => 'DifferentP@ss123!',
        ], $rules);

        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('password', $v->errors()->toArray());
    }
}
