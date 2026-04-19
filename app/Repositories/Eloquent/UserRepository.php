<?php

namespace App\Repositories\Eloquent;

use App\Models\User;

class UserRepository extends BaseRepository
{
    protected function model(): string
    {
        return User::class;
    }

    // ── Email lookup (encrypted column) ───────────────────────────────────────

    /**
     * Find a user by plaintext email.
     *
     * Cannot use WHERE email = ? because the column is encrypted at rest —
     * the DB stores ciphertext, not plaintext. We load all users and compare
     * decrypted values in PHP. Acceptable cost: user table stays small.
     */
    public function findByEmail(string $email): ?User
    {
        return User::all()->first(
            fn (User $u) => strtolower($u->email) === strtolower($email)
        );
    }

    /**
     * Same as findByEmail but includes soft-deleted users.
     * Used during login to detect deactivated accounts.
     */
    public function findByEmailWithTrashed(string $email): ?User
    {
        return User::withTrashed()->get()->first(
            fn (User $u) => strtolower($u->email) === strtolower($email)
        );
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    /**
     * Create a new user record.
     */
    public function createUser(array $data): User
    {
        return User::create($data);
    }

    /**
     * Update profile fields on an existing user.
     */
    public function updateProfile(User $user, array $data): User
    {
        $user->fill($data)->save();

        return $user->fresh();
    }
}
