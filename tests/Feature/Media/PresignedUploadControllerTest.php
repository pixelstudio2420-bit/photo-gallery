<?php

namespace Tests\Feature\Media;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature tests for the direct-browser-upload presigned URL API.
 *
 *   POST /api/uploads/sign       → mints a presigned PUT URL
 *   POST /api/uploads/confirm    → verifies the object landed
 *
 * The full RefreshDatabase migration set is currently broken on sqlite
 * (Postgres-specific check constraint on payment_methods.method_type),
 * so this test runs against a minimal hand-built schema rather than the
 * project's full migrations. Only the auth_users table is needed.
 */
class PresignedUploadControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bootMinimalSchema();
        Storage::fake('r2');
        config(['media.disk' => 'r2']);
        config(['media.r2_only' => false]);
        config([
            'filesystems.disks.r2.bucket'   => 'test-bucket',
            'filesystems.disks.r2.endpoint' => 'https://account.r2.cloudflarestorage.com',
            'filesystems.disks.r2.key'      => 'test-key',
            'filesystems.disks.r2.secret'   => 'test-secret',
        ]);
    }

    private function bootMinimalSchema(): void
    {
        $schema = \Illuminate\Support\Facades\Schema::connection('sqlite');
        if (!$schema->hasTable('auth_users')) {
            $schema->create('auth_users', function ($t) {
                $t->id();
                $t->string('email')->unique();
                $t->string('username')->nullable();
                $t->string('first_name')->nullable();
                $t->string('last_name')->nullable();
                $t->string('password_hash')->nullable();
                $t->string('avatar')->nullable();
                $t->timestamps();
            });
        }
    }
    private function makeUser(): User
    {
        $u = new User();
        $u->setRawAttributes([
            'id'         => random_int(1, 100_000),
            'email'      => 'u'.uniqid().'@test.local',
            'first_name' => 'Test',
            'last_name'  => 'User',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $u->save();
        return $u->refresh();
    }

    public function test_sign_requires_authentication(): void
    {
        $response = $this->postJson('/api/uploads/sign', [
            'category' => 'auth.avatar',
            'filename' => 'me.jpg',
            'mime'     => 'image/jpeg',
            'size'     => 1024,
        ]);

        $response->assertStatus(401);
    }

    public function test_sign_avatar_returns_presigned_url(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)
            ->postJson('/api/uploads/sign', [
                'category' => 'auth.avatar',
                'filename' => 'me.png',
                'mime'     => 'image/png',
                'size'     => 500 * 1024,
            ]);

        $response->assertOk()
            ->assertJsonStructure(['url', 'key', 'expected_mime', 'expires_at', 'max_bytes'])
            ->assertJson([
                'expected_mime' => 'image/png',
            ]);

        $key = $response->json('key');
        $this->assertStringStartsWith("auth/avatar/user_{$user->id}/", $key);
        $this->assertStringEndsWith('.png', $key);
    }

    public function test_sign_rejects_resource_id_for_user_scoped_category(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)
            ->postJson('/api/uploads/sign', [
                'category'    => 'auth.avatar',
                'resource_id' => 999,
                'filename'    => 'me.png',
                'mime'        => 'image/png',
                'size'        => 1024,
            ]);

        $response->assertStatus(403);
    }

    public function test_sign_rejects_unowned_event(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)
            ->postJson('/api/uploads/sign', [
                'category'    => 'events.photos',
                'resource_id' => 12345, // doesn't exist + not owned
                'filename'    => 'photo.jpg',
                'mime'        => 'image/jpeg',
                'size'        => 1024,
            ]);

        $response->assertStatus(403);
    }

    public function test_sign_rejects_oversize_file(): void
    {
        $user = $this->makeUser();

        // auth.avatar max = 2 MB
        $response = $this->actingAs($user)
            ->postJson('/api/uploads/sign', [
                'category' => 'auth.avatar',
                'filename' => 'huge.png',
                'mime'     => 'image/png',
                'size'     => 5 * 1024 * 1024,
            ]);

        $response->assertStatus(413)
            ->assertJsonStructure(['error', 'max_bytes']);
    }

    public function test_sign_rejects_disallowed_mime(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)
            ->postJson('/api/uploads/sign', [
                'category' => 'auth.avatar',
                'filename' => 'shell.exe',
                'mime'     => 'application/x-msdownload',
                'size'     => 1024,
            ]);

        $response->assertStatus(422);
    }

    public function test_confirm_requires_object_to_exist(): void
    {
        $user = $this->makeUser();
        $key  = "auth/avatar/user_{$user->id}/never_uploaded.png";

        $response = $this->actingAs($user)
            ->postJson('/api/uploads/confirm', [
                'key'           => $key,
                'category'      => 'auth.avatar',
                'original_name' => 'never_uploaded.png',
                'byte_size'     => 1024,
            ]);

        $response->assertStatus(404);
    }

    public function test_confirm_succeeds_when_object_exists(): void
    {
        $user = $this->makeUser();
        $key  = "auth/avatar/user_{$user->id}/abc_real.png";

        // Simulate a successful PUT to R2 by writing to the fake disk.
        Storage::disk('r2')->put($key, 'fake-bytes');

        $response = $this->actingAs($user)
            ->postJson('/api/uploads/confirm', [
                'key'           => $key,
                'category'      => 'auth.avatar',
                'original_name' => 'abc_real.png',
                'byte_size'     => 10,
            ]);

        $response->assertOk()
            ->assertJson(['ok' => true, 'key' => $key]);
    }

    public function test_confirm_rejects_key_outside_user_ownership_prefix(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();

        // Create a real object under ANOTHER user
        $foreignKey = "auth/avatar/user_{$other->id}/sneaky.png";
        Storage::disk('r2')->put($foreignKey, 'fake-bytes');

        $response = $this->actingAs($user)
            ->postJson('/api/uploads/confirm', [
                'key'           => $foreignKey,
                'category'      => 'auth.avatar',
                'original_name' => 'sneaky.png',
                'byte_size'     => 10,
            ]);

        $response->assertStatus(403)
            ->assertJsonFragment(['expected' => "auth/avatar/user_{$user->id}"]);
    }
}
