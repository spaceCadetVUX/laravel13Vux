<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    // ── 1. Forgot password sends reset email ──────────────────────────────────

    public function test_forgot_password_sends_reset_email(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'user@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'user@example.com'])
            ->assertStatus(200);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    // ── 2. Unknown email returns 200 (no enumeration) ─────────────────────────

    public function test_forgot_password_with_unknown_email_returns_200(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])
            ->assertStatus(200);

        Notification::assertNothingSent();
    }

    // ── 3. Valid token resets password ────────────────────────────────────────

    public function test_can_reset_password_with_valid_token(): void
    {
        $user      = User::factory()->create(['email' => 'user@example.com']);
        $emailHash = hash('sha256', 'user@example.com');
        $token     = 'valid-reset-token-abc123';

        DB::table('password_reset_tokens')->insert([
            'email'      => $emailHash,
            'token'      => Hash::make($token),
            'created_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email'                 => 'user@example.com',
            'token'                 => $token,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(200);

        // Token is deleted after use
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $emailHash]);

        // New password works
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    // ── 4. Invalid token returns 422 ─────────────────────────────────────────

    public function test_cannot_reset_with_invalid_token(): void
    {
        User::factory()->create(['email' => 'user@example.com']);
        $emailHash = hash('sha256', 'user@example.com');

        DB::table('password_reset_tokens')->insert([
            'email'      => $emailHash,
            'token'      => Hash::make('correct-token'),
            'created_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email'                 => 'user@example.com',
            'token'                 => 'wrong-token',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(422);
    }

    // ── 5. Expired token returns 422 ─────────────────────────────────────────

    public function test_cannot_reset_with_expired_token(): void
    {
        User::factory()->create(['email' => 'user@example.com']);
        $emailHash = hash('sha256', 'user@example.com');
        $token     = 'expired-token';

        DB::table('password_reset_tokens')->insert([
            'email'      => $emailHash,
            'token'      => Hash::make($token),
            'created_at' => now()->subMinutes(61), // expired
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email'                 => 'user@example.com',
            'token'                 => $token,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(422);
    }

    // ── 6. Reset revokes all Sanctum tokens ──────────────────────────────────

    public function test_reset_password_revokes_all_tokens(): void
    {
        $user      = User::factory()->create(['email' => 'user@example.com']);
        $emailHash = hash('sha256', 'user@example.com');
        $token     = 'valid-token';

        $user->createToken('device-1');
        $user->createToken('device-2');

        DB::table('password_reset_tokens')->insert([
            'email'      => $emailHash,
            'token'      => Hash::make($token),
            'created_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email'                 => 'user@example.com',
            'token'                 => $token,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(200);

        $this->assertCount(0, $user->fresh()->tokens);
    }
}
