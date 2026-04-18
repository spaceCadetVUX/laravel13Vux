<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Register a new customer account.
     *
     * Email uniqueness is checked here (not in FormRequest) because the email
     * column is encrypted at rest — a DB unique index compares ciphertexts,
     * so we must decrypt every row to find a real duplicate.
     *
     * @return array{token: string, user: User}
     *
     * @throws ValidationException
     */
    public function register(array $data): array
    {
        // Plaintext duplicate check — iterate encrypted rows
        $emailExists = User::all()->first(
            fn (User $u) => strtolower($u->email) === strtolower($data['email'])
        );

        if ($emailExists) {
            throw ValidationException::withMessages([
                'email' => ['The email has already been taken.'],
            ]);
        }

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'], // casted to 'hashed' in model
            'role'     => UserRole::Customer,
        ]);

        // Assign Spatie 'customer' role
        $user->assignRole('customer');

        $user->sendEmailVerificationNotification();

        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'token' => $token,
            'user'  => $user,
        ];
    }

    /**
     * Login with email + password.
     *
     * Cannot use Auth::attempt() because the email column is encrypted —
     * Laravel's attempt() does a raw DB WHERE which compares ciphertext.
     * We load all users and compare decrypted email + bcrypt hash instead.
     *
     * @return array{token: string, user: User}
     *
     * @throws ValidationException
     */
    public function login(array $credentials): array
    {
        // Find user by decrypted email
        $user = User::all()->first(
            fn (User $u) => strtolower($u->email) === strtolower($credentials['email'])
        );

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->trashed()) {
            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated.'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'token' => $token,
            'user'  => $user,
        ];
    }

    /**
     * Revoke the user's current access token.
     */
    public function logout(User $user): void
    {
        // currentAccessToken() is set by Sanctum after auth:sanctum middleware resolves
        $user->currentAccessToken()->delete();
    }

    /**
     * Update a user's profile fields.
     */
    public function updateProfile(User $user, array $data): User
    {
        $user->fill($data)->save();

        return $user->fresh();
    }
}
