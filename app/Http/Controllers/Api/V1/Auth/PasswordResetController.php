<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PasswordResetService $passwordResetService,
    ) {}

    /**
     * POST /api/v1/auth/forgot-password
     *
     * Always returns 200 regardless of whether the email exists — prevents
     * user enumeration.
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->sendResetLink($request->validated('email'));

        return $this->success(
            data: null,
            message: 'If that email is registered, a reset link has been sent.',
        );
    }

    /**
     * POST /api/v1/auth/reset-password
     *
     * Validates the token and updates the user's password.
     * Revokes all existing Sanctum tokens on success.
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->reset($request->validated());

        return $this->success(
            data: null,
            message: 'Password reset successfully. Please log in with your new password.',
        );
    }
}
