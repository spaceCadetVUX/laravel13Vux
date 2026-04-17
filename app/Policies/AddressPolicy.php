<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\User;

class AddressPolicy
{
    /**
     * Only the address owner can modify or delete.
     */
    public function modify(User $user, Address $address): bool
    {
        return (string) $address->user_id === (string) $user->id;
    }
}
