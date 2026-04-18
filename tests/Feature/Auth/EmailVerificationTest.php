<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    // ── 1. Verification email sent on register ────────────────────────────────

    public function test_verification_email_sent_on_register(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'name'                  => 'New User',
            'email'                 => 'newuser@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(201);

        $user = User::where('email_hash', hash('sha256', 'newuser@example.com'))->first();
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    // ── 2. Valid signed URL verifies email ────────────────────────────────────

    public function test_user_can_verify_email_with_signed_url(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'api.auth.email.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => $user->email_hash]
        );

        $this->get($url)
            ->assertStatus(200)
            ->assertJsonPath('message', 'Email verified successfully.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    // ── 3. Already verified returns 200 idempotently ──────────────────────────

    public function test_already_verified_returns_200(): void
    {
        $user = User::factory()->create(); // email_verified_at = now() by default

        $url = URL::temporarySignedRoute(
            'api.auth.email.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => $user->email_hash]
        );

        $this->get($url)
            ->assertStatus(200)
            ->assertJsonPath('message', 'Email already verified.');
    }

    // ── 4. Wrong hash returns 403 ─────────────────────────────────────────────

    public function test_wrong_hash_returns_403(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'api.auth.email.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => 'completely-wrong-hash']
        );

        $this->get($url)->assertStatus(403);
    }

    // ── 5. Expired link returns 403 ───────────────────────────────────────────

    public function test_expired_link_returns_403(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'api.auth.email.verify',
            now()->subMinutes(1), // already expired
            ['id' => $user->id, 'hash' => $user->email_hash]
        );

        $this->get($url)->assertStatus(403);
    }

    // ── 6. Authenticated user can resend ─────────────────────────────────────

    public function test_authenticated_user_can_resend_verification_email(): void
    {
        Notification::fake();

        $user  = User::factory()->unverified()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/email/resend')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Verification link sent.');

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    // ── 7. Already verified user cannot resend ────────────────────────────────

    public function test_already_verified_user_cannot_resend(): void
    {
        $user  = User::factory()->create(); // verified
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/email/resend')
            ->assertStatus(422);
    }
}
