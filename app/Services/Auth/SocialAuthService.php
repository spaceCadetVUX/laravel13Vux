<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthService
{
    /**
     * Handle Google OAuth login / registration.
     *
     * Flow:
     * 1. Verify the Google ID token with Socialite → get Google user payload.
     * 2. Lookup existing user by google_id (fastest — indexed unique column).
     * 3. If not found, try matching by decrypted email (encrypted column scan).
     * 4. If still not found, create a new customer account.
     * 5. If found via email but missing google_id, backfill it.
     * 6. Issue a Sanctum token and return.
     *
     * NOTE: email is encrypted at rest, so we cannot do a DB WHERE on it.
     * We load all users and compare decrypted values in PHP — same pattern
     * as AuthService::login(). Acceptable at this user scale.
     *
     * @return array{token: string, user: User, is_new_user: bool}
     *
     * @throws \Laravel\Socialite\Two\InvalidStateException|\Exception
     */
    public function handleGoogle(string $idToken): array
    {
        // ── 1. Verify token with Google and get user details ─────────────────
        /** @var \Laravel\Socialite\Two\User $googleUser */
        $googleUser = Socialite::driver('google')->userFromToken($idToken);

        $isNewUser = false;

        // ── 2. Try to find by google_id (stored as plaintext, indexed) ───────
        $user = User::where('google_id', $googleUser->getId())->first();

        // ── 3. Try to find by decrypted email ────────────────────────────────
        if (! $user && $googleUser->getEmail()) {
            $user = User::all()->first(
                fn (User $u) => strtolower($u->email) === strtolower($googleUser->getEmail())
            );

            // Backfill google_id so future lookups use the fast index path
            if ($user && ! $user->google_id) {
                $user->update(['google_id' => $googleUser->getId()]);
            }
        }

        // ── 4. Create new customer if not found ───────────────────────────────
        if (! $user) {
            $isNewUser = true;

            $user = DB::transaction(function () use ($googleUser): User {
                $newUser = User::create([
                    'name'              => $googleUser->getName() ?? $googleUser->getNickname() ?? 'Google User',
                    'email'             => $googleUser->getEmail(),
                    'google_id'         => $googleUser->getId(),
                    'password'          => null,             // Google-only account — no password
                    'email_verified_at' => now(),            // Google has already verified the email
                    'role'              => UserRole::Customer,
                ]);

                $newUser->assignRole('customer');

                return $newUser;
            });
        }

        // ── 5. Issue Sanctum token ────────────────────────────────────────────
        $token = $user->createToken('google-token')->plainTextToken;

        return [
            'token'       => $token,
            'user'        => $user->fresh(),
            'is_new_user' => $isNewUser,
        ];
    }
}
