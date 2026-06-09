<?php

namespace App\Models;

/**
 * Compatibility shim — maps Setting::get($key) calls from Blade views
 * to the actual data store (BusinessProfile + config).
 *
 * This lets us migrate views from the old Setting-based front-end without
 * rewriting every @include that calls \App\Models\Setting::get().
 */
class Setting
{
    private static ?BusinessProfile $profile = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $p = static::profile();
        } catch (\Throwable) {
            return $default;
        }

        return match ($key) {
            // Brand / identity
            'site_name'            => $p->name ?? config('app.name'),
            'site_tagline'         => $p->tagline,
            'site_tagline_en'      => $p->extra['site_tagline_en'] ?? null,
            'site_logo'            => $p->logo_path,

            // Contact
            'contact_email'           => $p->email,
            'contact_phone'           => $p->phone,
            'contact_phone_display'   => $p->extra['contact_phone_display'] ?? $p->phone,
            'contact_working_hours'   => $p->extra['contact_working_hours'] ?? null,
            'contact_maps_url'        => $p->extra['contact_maps_url'] ?? null,
            'contact_address'         => $p->address_line,
            'contact_city'         => $p->city,
            'contact_state'        => $p->state,
            'contact_country'      => $p->country,
            'contact_postal_code'  => $p->postal_code,

            // Social links (stored as JSON array on BusinessProfile)
            'social_facebook'      => $p->social_links['facebook']  ?? null,
            'social_instagram'     => $p->social_links['instagram'] ?? null,
            'social_youtube'       => $p->social_links['youtube']   ?? null,
            'social_tiktok'        => $p->social_links['tiktok']    ?? null,
            'social_linkedin'      => $p->social_links['linkedin']  ?? null,
            'social_twitter'       => $p->social_links['twitter']   ?? null,
            'social_zalo'          => $p->social_links['zalo']      ?? null,

            // About / company
            'about_description'     => $p->description,
            'company_founded_year'  => $p->founded_year,
            'company_certifications'=> $p->extra['certifications'] ?? null,
            'about_og_image'        => $p->extra['about_og_image'] ?? null,

            // SEO / OG defaults
            'default_og_image'     => $p->extra['default_og_image'] ?? null,
            'meta_description'     => $p->extra['meta_description'] ?? null,

            // Product page trust badges — configurable from Filament BusinessProfile
            'return_days'          => $p->extra['return_days']    ?? 7,
            'support_hours'        => $p->extra['support_hours']  ?? '24/7',
            'warranty_info_vi'     => $p->extra['warranty_info_vi'] ?? null,
            'warranty_info_en'     => $p->extra['warranty_info_en'] ?? null,

            default                => $p->extra[$key] ?? $default,
        };
    }

    private static function profile(): BusinessProfile
    {
        if (! static::$profile) {
            static::$profile = BusinessProfile::instance();
        }

        return static::$profile;
    }
}
