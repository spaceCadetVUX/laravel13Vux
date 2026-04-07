<?php

namespace App\Http\Resources\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Standardised API response envelope.
 * Used by all API controllers — never return raw arrays or JsonResponse directly.
 *
 * Success:    { "status": "success", "message": "...", "data": {...}, "meta": {...} }
 * Error:      { "status": "error",   "message": "...", "errors": {...}, "code": 422 }
 */
trait ApiResponse
{
    /**
     * Return a successful JSON response.
     *
     * @param  mixed       $data     Resource, Collection, or plain array
     * @param  string      $message  Human-readable message
     * @param  array       $meta     Pagination meta or extra envelope fields
     * @param  int         $status   HTTP status code (default 200)
     */
    protected function success(
        mixed  $data    = null,
        string $message = 'OK',
        array  $meta    = [],
        int    $status  = 200,
    ): JsonResponse {
        $payload = [
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
        ];

        if (! empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * Return an error JSON response.
     *
     * @param  string  $message  Human-readable error message
     * @param  int     $code     HTTP status code
     * @param  array   $errors   Validation errors keyed by field name
     */
    protected function error(
        string $message = 'An error occurred',
        int    $code    = 400,
        array  $errors  = [],
    ): JsonResponse {
        $payload = [
            'status'  => 'error',
            'message' => $message,
            'code'    => $code,
        ];

        if (! empty($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $code);
    }

    /**
     * Build the standard pagination meta block from a LengthAwarePaginator.
     * Pass to success() as the $meta argument.
     */
    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
            'from'         => $paginator->firstItem(),
            'to'           => $paginator->lastItem(),
        ];
    }
}
