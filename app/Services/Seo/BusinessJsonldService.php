<?php

namespace App\Services\Seo;

use App\Models\BusinessProfile;
use Illuminate\Support\Facades\Cache;

class BusinessJsonldService
{
    private const CACHE_KEY = 'business_jsonld_schemas';

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Returns all global JSON-LD schemas (Organization, WebSite, LocalBusiness).
     * Cached in Redis for 24 hours per locale. Invalidated by BusinessProfileObserver on save.
     */
    public function getSchemas(?string $locale = null): array
    {
        $locale  = $locale ?? app()->getLocale();
        $cacheKey = self::CACHE_KEY . '_' . $locale;

        try {
            return Cache::store('redis')->remember(
                $cacheKey,
                now()->addHours(24),
                fn (): array => $this->buildSchemas($locale)
            );
        } catch (\Throwable) {
            return $this->buildSchemas($locale);
        }
    }

    /**
     * Returns a minimal Organization block for use as Article publisher.
     * Always reads live data — no cache (used in JSON-LD sync jobs, not hot path).
     */
    public function publisherBlock(): array
    {
        $profile = BusinessProfile::instance();
        $baseUrl = rtrim((string) config('app.url'), '/');

        $publisher = [
            '@type' => 'Organization',
            'name'  => $profile->name,
            'url'   => $baseUrl,
        ];

        if (filled($profile->logo_path)) {
            $publisher['logo'] = [
                '@type' => 'ImageObject',
                'url'   => str_starts_with((string) $profile->logo_path, 'http')
                    ? $profile->logo_path
                    : $baseUrl . '/storage/' . ltrim((string) $profile->logo_path, '/'),
            ];
        }

        return $publisher;
    }

    public function flushCache(): void
    {
        try {
            Cache::store('redis')->forget(self::CACHE_KEY . '_vi');
            Cache::store('redis')->forget(self::CACHE_KEY . '_en');
        } catch (\Throwable) {
        }
    }

    // ── Schema builders ───────────────────────────────────────────────────────

    private function buildSchemas(string $locale = 'vi'): array
    {
        $profile = BusinessProfile::instance();
        $schemas = [
            $this->organization($profile, $locale),
            $this->website($profile, $locale),
        ];

        if (filled($profile->address_line) || filled($profile->city)) {
            $schemas[] = $this->localBusiness($profile);
        }

        $faqKey = $locale === 'en' ? 'faq_en' : 'faq';
        $faq    = (array) ($profile->extra[$faqKey] ?? []);
        if (! empty($faq)) {
            $schemas[] = $this->faqPage($faq);
        }

        return $schemas;
    }

    private function organization(BusinessProfile $p, string $locale = 'vi'): array
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            '@id'      => $baseUrl . '/#organization',
            'name'     => $p->name,
            'url'      => $baseUrl,
        ];

        $legalName = $locale === 'en'
            ? ($p->extra['legal_name_en'] ?? $p->legal_name ?? null)
            : ($p->legal_name ?? null);
        if (filled($legalName))        $schema['legalName']    = $legalName;
        $desc = $locale === 'en'
            ? ($p->extra['description_en'] ?? $p->extra['tagline_en'] ?? null)
            : ($p->description ?? $p->tagline ?? null);
        if (filled($desc))            $schema['description']  = $desc;
        if (filled($p->email))        $schema['email']        = $p->email;
        if (filled($p->phone))        $schema['telephone']    = $p->phone;
        if (filled($p->founded_year)) $schema['foundingDate'] = (string) $p->founded_year;
        if (filled($p->vat_number))   $schema['taxID']        = $p->vat_number;

        if (filled($p->logo_path)) {
            $schema['logo'] = [
                '@type' => 'ImageObject',
                'url'   => str_starts_with((string) $p->logo_path, 'http')
                    ? $p->logo_path
                    : $baseUrl . '/storage/' . ltrim((string) $p->logo_path, '/'),
            ];
        }

        if (filled($p->address_line) || filled($p->city)) {
            $schema['address'] = $this->postalAddress($p);
        }

        $socialLinks = array_values(array_filter((array) ($p->social_links ?? [])));
        if (! empty($socialLinks)) {
            $schema['sameAs'] = $socialLinks;
        }

        return $schema;
    }

    private function website(BusinessProfile $p, string $locale = 'vi'): array
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $tagline = $locale === 'en'
            ? ($p->extra['tagline_en'] ?? $p->tagline ?? null)
            : ($p->tagline ?? null);

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            '@id'      => $baseUrl . '/#website',
            'name'     => $p->name,
            'url'      => $baseUrl,
        ];

        if (filled($tagline)) $schema['description'] = $tagline;

        return array_merge($schema, [
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => $baseUrl . '/search?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ]);
    }

    private function localBusiness(BusinessProfile $p): array
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'LocalBusiness',
            '@id'      => $baseUrl . '/#localbusiness',
            'name'     => $p->name,
            'url'      => $baseUrl,
        ];

        if (filled($p->phone))   $schema['telephone'] = $p->phone;
        if (filled($p->email))   $schema['email']     = $p->email;

        if (filled($p->logo_path)) {
            $schema['image'] = str_starts_with((string) $p->logo_path, 'http')
                ? $p->logo_path
                : $baseUrl . '/storage/' . ltrim((string) $p->logo_path, '/');
        }

        $schema['address'] = $this->postalAddress($p);

        if ($p->latitude && $p->longitude) {
            $schema['geo'] = [
                '@type'     => 'GeoCoordinates',
                'latitude'  => $p->latitude,
                'longitude' => $p->longitude,
            ];
        }

        // openingHours format: "Mo 09:00-18:00"
        $dayMap = [
            'Monday' => 'Mo', 'Tuesday' => 'Tu', 'Wednesday' => 'We',
            'Thursday' => 'Th', 'Friday' => 'Fr', 'Saturday' => 'Sa', 'Sunday' => 'Su',
        ];

        $raw = (array) ($p->business_hours ?? []);

        // Support both array format [{day,open,close}] and legacy keyed format {Monday:{open,close}}
        if (array_is_list($raw)) {
            $hours = collect($raw)
                ->map(fn (array $h): string => ($dayMap[ucfirst(strtolower($h['day'] ?? ''))] ?? ($h['day'] ?? '')) . ' '
                    . trim(($h['open'] ?? '') . '-' . ($h['close'] ?? ''), '-'))
                ->filter(fn (string $entry): bool => filled(trim(explode(' ', $entry, 2)[1] ?? '')))
                ->values()
                ->all();
        } else {
            $hours = collect($raw)
                ->map(fn ($h, string $d): string => ($dayMap[ucfirst(strtolower($d))] ?? $d) . ' ' . (
                    is_array($h)
                        ? trim(($h['open'] ?? '') . '-' . ($h['close'] ?? ''), '-')
                        : trim((string) ($h ?? ''))
                ))
                ->filter(fn (string $entry): bool => filled(trim(explode(' ', $entry, 2)[1] ?? '')))
                ->values()
                ->all();
        }

        if (! empty($hours)) {
            $schema['openingHours'] = $hours;
        }

        return $schema;
    }

    private function postalAddress(BusinessProfile $p): array
    {
        $address = ['@type' => 'PostalAddress'];
        if (filled($p->address_line)) $address['streetAddress']   = $p->address_line;
        if (filled($p->city))         $address['addressLocality'] = $p->city;
        if (filled($p->state))        $address['addressRegion']   = $p->state;
        if (filled($p->country))      $address['addressCountry']  = $p->country;
        if (filled($p->postal_code))  $address['postalCode']      = $p->postal_code;

        return $address;
    }

    private function faqPage(array $faq): array
    {
        $entities = array_map(fn (array $item): array => [
            '@type'          => 'Question',
            'name'           => $item['question'] ?? '',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => $item['answer'] ?? '',
            ],
        ], $faq);

        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }
}
