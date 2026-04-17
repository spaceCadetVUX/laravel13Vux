<?php

namespace App\Http\Controllers\Api\V1\Address;

use App\Http\Controllers\Controller;
use App\Http\Requests\Address\StoreAddressRequest;
use App\Http\Requests\Address\UpdateAddressRequest;
use App\Http\Resources\Api\Address\AddressResource;
use App\Http\Resources\Traits\ApiResponse;
use App\Models\Address;
use App\Services\Address\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AddressService $addressService) {}

    /**
     * GET /api/v1/addresses
     */
    public function index(Request $request): JsonResponse
    {
        $addresses = $this->addressService->list($request->user());

        return $this->success(data: AddressResource::collection($addresses));
    }

    /**
     * POST /api/v1/addresses
     */
    public function store(StoreAddressRequest $request): JsonResponse
    {
        $address = $this->addressService->create($request->user(), $request->validated());

        return $this->success(
            data: new AddressResource($address),
            message: 'Address created successfully',
            status: 201,
        );
    }

    /**
     * PUT/PATCH /api/v1/addresses/{address}
     */
    public function update(UpdateAddressRequest $request, Address $address): JsonResponse
    {
        $this->authorize('modify', $address);

        $address = $this->addressService->update($address, $request->validated());

        return $this->success(data: new AddressResource($address));
    }

    /**
     * DELETE /api/v1/addresses/{address}
     */
    public function destroy(Request $request, Address $address): JsonResponse
    {
        $this->authorize('modify', $address);

        $this->addressService->delete($address);

        return $this->success(message: 'Address deleted');
    }

    /**
     * PATCH /api/v1/addresses/{address}/default
     */
    public function setDefault(Request $request, Address $address): JsonResponse
    {
        $this->authorize('modify', $address);

        $address = $this->addressService->setDefault($request->user(), $address);

        return $this->success(
            data: new AddressResource($address),
            message: 'Default address updated',
        );
    }
}
