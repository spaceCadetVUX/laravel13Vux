<?php

namespace App\Repositories\Eloquent;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class AddressRepository extends BaseRepository
{
    protected function model(): string
    {
        return Address::class;
    }

    // ── Read ──────────────────────────────────────────────────────────────────

    /**
     * All addresses for a user — default first, then by creation date.
     */
    public function getForUser(User $user): Collection
    {
        return $user->addresses()
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    /**
     * Create a new address for a user.
     */
    public function createForUser(User $user, array $data): Address
    {
        return $user->addresses()->create($data);
    }

    /**
     * Clear the default flag on all of a user's addresses.
     * Used before setting a new default — called inside DB::transaction in Service.
     */
    public function clearDefault(User $user, ?string $exceptId = null): void
    {
        $query = $user->addresses();

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update(['is_default' => false]);
    }

    /**
     * Set a single address as the default.
     */
    public function setDefault(Address $address): Address
    {
        $address->update(['is_default' => true]);

        return $address->fresh();
    }
}
