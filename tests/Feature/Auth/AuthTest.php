<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    // ── Register ──────────────────────────────────────────────────────────────

    public function test_user_can_register(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['token', 'user' => ['id', 'name', 'email']],
            ]);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dupe@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Another User',
            'email'                 => 'dupe@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'The email has already been taken.');
    }

    public function test_register_fails_with_weak_password(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'short',
            'password_confirmation' => 'short',
        ])
            ->assertStatus(422);
    }

    // ── Login ─────────────────────────────────────────────────────────────────

    public function test_user_can_login(): void
    {
        User::factory()->create(['email' => 'login@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'login@example.com',
            'password' => 'password',
        ])
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['token', 'user' => ['id', 'name', 'email']],
            ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'wrong@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'wrong@example.com',
            'password' => 'badpassword',
        ])
            ->assertStatus(422);
    }

    // ── Profile ───────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);

        $token = $user->createToken('api-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.email', 'me@example.com');
    }

    public function test_unauthenticated_user_cannot_get_profile(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function test_user_can_logout(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200);

        // Sanctum caches the resolved user in-process, so we verify via DB instead of HTTP.
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // ── Update profile ────────────────────────────────────────────────────────

    public function test_user_can_update_profile(): void
    {
        $user  = User::factory()->create(['name' => 'Old Name']);
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/v1/auth/me', ['name' => 'New Name'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    }
}
