<?php

namespace App\Services\Address;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AddressService
{
    public function list(User $user): Collection
    {
        return $user->addresses()->orderByDesc('is_default')->orderBy('created_at')->get();
    }

    public function create(User $user, array $data): Address
    {
        return DB::transaction(function () use ($user, $data) {
            if (! empty($data['is_default'])) {
                $user->addresses()->update(['is_default' => false]);
            }

            return $user->addresses()->create($data);
        });
    }

    public function update(Address $address, array $data): Address
    {
        return DB::transaction(function () use ($address, $data) {
            if (! empty($data['is_default'])) {
                $address->user->addresses()
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
            }

            $address->update($data);

            return $address->fresh();
        });
    }

    public function delete(Address $address): void
    {
        $address->delete();
    }

    public function setDefault(User $user, Address $address): Address
    {
        return DB::transaction(function () use ($user, $address) {
            $user->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);

            return $address->fresh();
        });
    }
}
