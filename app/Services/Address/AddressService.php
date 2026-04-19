<?php

namespace App\Services\Address;

use App\Models\Address;
use App\Models\User;
use App\Repositories\Eloquent\AddressRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AddressService
{
    public function __construct(
        private readonly AddressRepository $addressRepository,
    ) {}

    public function list(User $user): Collection
    {
        return $this->addressRepository->getForUser($user);
    }

    public function create(User $user, array $data): Address
    {
        return DB::transaction(function () use ($user, $data) {
            if (! empty($data['is_default'])) {
                $this->addressRepository->clearDefault($user);
            }

            return $this->addressRepository->createForUser($user, $data);
        });
    }

    public function update(Address $address, array $data): Address
    {
        return DB::transaction(function () use ($address, $data) {
            if (! empty($data['is_default'])) {
                $this->addressRepository->clearDefault($address->user, $address->id);
            }

            return $this->addressRepository->update($address, $data);
        });
    }

    public function delete(Address $address): void
    {
        $this->addressRepository->delete($address);
    }

    public function setDefault(User $user, Address $address): Address
    {
        return DB::transaction(function () use ($user, $address) {
            $this->addressRepository->clearDefault($user);

            return $this->addressRepository->setDefault($address);
        });
    }
}
