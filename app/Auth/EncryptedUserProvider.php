<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Custom UserProvider that handles encrypted email lookups.
 *
 * The users.email column is encrypted at rest with a random IV.
 * Direct WHERE email = ? queries fail because each encryption is unique.
 *
 * Instead we query by email_hash = sha256(lower(email)), which is
 * deterministic and indexed — then let Laravel verify the password normally.
 *
 * Registered in AppServiceProvider::boot() as the 'encrypted' driver.
 * Configured in config/auth.php as the provider driver for 'users'.
 */
class EncryptedUserProvider extends EloquentUserProvider
{
    /** Fields that are encrypted at rest and need hash-based lookup. */
    protected array $encryptedFields = ['email'];

    /**
     * Retrieve a user by their credentials.
     * For encrypted fields, swap the plaintext value for its sha256 hash
     * so we can do an indexed WHERE email_hash = ? query.
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $query = $this->newModelQuery();

        foreach ($credentials as $key => $value) {
            if (str_contains($key, 'password')) {
                continue;
            }

            if (in_array($key, $this->encryptedFields, true)) {
                // Query the hash column instead of the encrypted column
                $query->where("{$key}_hash", hash('sha256', strtolower($value)));
            } else {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }
}
