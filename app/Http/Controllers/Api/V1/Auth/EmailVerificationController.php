<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/auth/email/verify/{id}/{hash}  [signed]
     *
     * Validates the signed URL, checks the hash, and marks the email as verified.
     * The `signed` middleware aborts with 403 before this runs if the URL is
     * invalid or expired.
     */
    public function verify(string $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals($user->email_hash, $hash)) {
            return $this->error(message: 'Invalid verification link.', code: 403);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->success(data: null, message: 'Email already verified.');
        }

        $user->markEmailAsVerified();

        return $this->success(data: null, message: 'Email verified successfully.');
    }

    /**
     * POST /api/v1/auth/email/resend  [auth:sanctum]
     *
     * Re-sends the verification email for the authenticated user.
     */
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->error(message: 'Email is already verified.', code: 422);
        }

        $user->sendEmailVerificationNotification();

        return $this->success(data: null, message: 'Verification link sent.');
    }
}
