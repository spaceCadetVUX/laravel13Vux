<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GoogleAuthRequest;
use App\Http\Resources\Api\UserResource;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Auth\SocialAuthService;
use Illuminate\Http\JsonResponse;

class SocialAuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SocialAuthService $socialAuthService) {}

    /**
     * POST /api/v1/auth/google
     *
     * Authenticate via Google ID token (sent from the Nuxt frontend after
     * the user completes the Google consent flow on the client side).
     */
    public function google(GoogleAuthRequest $request): JsonResponse
    {
        $result = $this->socialAuthService->handleGoogle(
            $request->validated('id_token')
        );

        return $this->success(
            data: [
                'token'       => $result['token'],
                'user'        => new UserResource($result['user']),
                'is_new_user' => $result['is_new_user'],
            ],
            message: $result['is_new_user'] ? 'Account created via Google' : 'Logged in via Google',
        );
    }
}
