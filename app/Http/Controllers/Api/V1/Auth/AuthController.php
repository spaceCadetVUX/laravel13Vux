<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Api\UserResource;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuthService $authService) {}

    /**
     * POST /api/v1/auth/register
     * Register a new customer account.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return $this->success(
            data: [
                'token' => $result['token'],
                'user'  => new UserResource($result['user']),
            ],
            message: 'Account created successfully',
            status: 201,
        );
    }

    /**
     * POST /api/v1/auth/login
     * Login with email + password.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return $this->success(
            data: [
                'token' => $result['token'],
                'user'  => new UserResource($result['user']),
            ],
            message: 'Logged in successfully',
        );
    }

    /**
     * POST /api/v1/auth/logout  [auth:sanctum]
     * Revoke current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->success(data: null, message: 'Logged out successfully');
    }

    /**
     * GET /api/v1/auth/me  [auth:sanctum]
     * Return the authenticated user's profile.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success(
            data: new UserResource($request->user()),
        );
    }

    /**
     * PUT /api/v1/auth/me  [auth:sanctum]
     * Update the authenticated user's profile.
     * (S22 — stub ready, filled in next sprint)
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'  => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $user = $this->authService->updateProfile($request->user(), $data);

        return $this->success(
            data: new UserResource($user),
            message: 'Profile updated successfully',
        );
    }
}
