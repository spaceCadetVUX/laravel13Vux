<?php

namespace App\Http\Controllers\Api\V1\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\Traits\ApiResponse;
use App\Models\BusinessProfile;
use App\Services\Seo\BusinessJsonldService;
use Illuminate\Http\JsonResponse;

class SiteConfigController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly BusinessJsonldService $jsonldService) {}

    /**
     * GET /api/v1/site/config
     *
     * Returns business profile data and global JSON-LD schemas.
     * Nuxt layout calls this once (SSR, cached) to render Organization/WebSite
     * schemas and populate footer contact info.
     */
    public function __invoke(): JsonResponse
    {
        $profile = BusinessProfile::instance();

        return $this->success(data: [
            'name'         => $profile->name,
            'legal_name'   => $profile->legal_name,
            'tagline'      => $profile->tagline,
            'email'        => $profile->email,
            'phone'        => $profile->phone,
            'address'      => [
                'line'        => $profile->address_line,
                'city'        => $profile->city,
                'state'       => $profile->state,
                'country'     => $profile->country,
                'postal_code' => $profile->postal_code,
                'latitude'    => $profile->latitude,
                'longitude'   => $profile->longitude,
            ],
            'business_hours' => $profile->business_hours,
            'social_links'   => $profile->social_links,
            'currency'       => $profile->currency,
            'logo_path'      => $profile->logo_path,
            'jsonld_schemas' => $this->jsonldService->getSchemas(),
        ]);
    }
}
