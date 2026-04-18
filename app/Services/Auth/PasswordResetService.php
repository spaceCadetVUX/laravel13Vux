<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetService
{
    private const EXPIRE_MINUTES = 60;

    /**
     * Generate a reset token and send the reset email.
     *
     * Uses email_hash for DB lookup (email column is encrypted at rest).
     * Always returns silently even when the email doesn't exist — prevents
     * user enumeration attacks.
     */
    public function sendResetLink(string $email): void
    {
        $emailHash = hash('sha256', strtolower($email));
        $user      = User::where('email_hash', $emailHash)->first();

        if (! $user) {
            return;
        }

        $token = Str::random(64);

        // Store hashed token; use email_hash as the "email" key so we can
        // look it up later without decrypting the entire users table.
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email'      => $emailHash],
            ['token'      => Hash::make($token), 'created_at' => now()]
        );

        $user->notify(new ResetPasswordNotification($token, $email));
    }

    /**
     * Validate the reset token and update the user's password.
     *
     * @throws ValidationException
     */
    public function reset(array $data): void
    {
        $emailHash = hash('sha256', strtolower($data['email']));
        $record    = DB::table('password_reset_tokens')
            ->where('email', $emailHash)
            ->first();

        if (! $record || ! Hash::check($data['token'], $record->token)) {
            throw ValidationException::withMessages([
                'token' => ['Invalid or expired password reset token.'],
            ]);
        }

        if (Carbon::parse($record->created_at)->addMinutes(self::EXPIRE_MINUTES)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $emailHash)->delete();

            throw ValidationException::withMessages([
                'token' => ['The password reset token has expired.'],
            ]);
        }

        $user = User::where('email_hash', $emailHash)->firstOrFail();
        $user->update(['password' => $data['password']]);

        // Delete token + revoke all Sanctum tokens for security
        DB::table('password_reset_tokens')->where('email', $emailHash)->delete();
        $user->tokens()->delete();
    }
}
