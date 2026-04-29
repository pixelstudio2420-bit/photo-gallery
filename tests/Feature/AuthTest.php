<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ─── Page Load Tests ───

    public function test_login_page_loads(): void
    {
        $response = $this->get(route('auth.login'));

        $response->assertStatus(200);
    }

    public function test_register_page_loads(): void
    {
        $response = $this->get(route('auth.register'));

        $response->assertStatus(200);
    }

    // ─── Registration ───

    public function test_user_can_register(): void
    {
        $response = $this->post(route('auth.register.post'), [
            'first_name'            => 'Test',
            'last_name'             => 'User',
            'email'                 => 'testuser@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('home'));

        $this->assertDatabaseHas('auth_users', [
            'email'      => 'testuser@example.com',
            'first_name' => 'Test',
            'last_name'  => 'User',
        ]);

        $this->assertAuthenticated('web');
    }

    // ─── Login ───

    public function test_user_can_login(): void
    {
        $user = User::create([
            'first_name'    => 'Login',
            'last_name'     => 'Test',
            'email'         => 'login@example.com',
            'password_hash' => Hash::make('secret123'),
            'auth_provider' => 'local',
        ]);

        $response = $this->post(route('auth.login.post'), [
            'email'    => 'login@example.com',
            'password' => 'secret123',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        User::create([
            'first_name'    => 'Wrong',
            'last_name'     => 'Pass',
            'email'         => 'wrong@example.com',
            'password_hash' => Hash::make('correct-password'),
            'auth_provider' => 'local',
        ]);

        $response = $this->post(route('auth.login.post'), [
            'email'    => 'wrong@example.com',
            'password' => 'incorrect-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    // ─── Profile Access ───

    public function test_authenticated_user_can_access_profile(): void
    {
        $user = User::create([
            'first_name'    => 'Profile',
            'last_name'     => 'User',
            'email'         => 'profile@example.com',
            'password_hash' => Hash::make('password'),
            'auth_provider' => 'local',
        ]);

        $response = $this->actingAs($user)->get(route('profile'));

        $response->assertStatus(200);
    }

    public function test_guest_cannot_access_profile(): void
    {
        $response = $this->get(route('profile'));

        $response->assertRedirect(route('login'));
    }
}
