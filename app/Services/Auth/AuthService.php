<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}

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
        if ($this->userRepository->findByEmail($data['email'])) {
            throw ValidationException::withMessages([
                'email' => ['The email has already been taken.'],
            ]);
        }

        $user = $this->userRepository->createUser([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'],
            'role'     => UserRole::Customer,
        ]);

        $user->assignRole('customer');
        $user->sendEmailVerificationNotification();

        $token = $user->createToken('api-token')->plainTextToken;

        return ['token' => $token, 'user' => $user];
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
        $user = $this->userRepository->findByEmailWithTrashed($credentials['email']);

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

        return ['token' => $token, 'user' => $user];
    }

    /**
     * Revoke the user's current access token.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    /**
     * Update a user's profile fields.
     */
    public function updateProfile(User $user, array $data): User
    {
        return $this->userRepository->updateProfile($user, $data);
    }
}
