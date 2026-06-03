<?php

namespace App\Services\Mcp;

use App\Models\Manufacturer;
use App\Models\Seo\GeoEntityProfile;
use App\Models\Seo\SeoMeta;
use Illuminate\Support\Facades\DB;

class McpManufacturerService
{
    // ── Public API ─────────────────────────────────────────────────────────────

    public function context(string $slug): array
    {
        $mfr = $this->loadManufacturer($slug);

        return $this->buildContextResponse($mfr);
    }

    public function upsert(string $slug, array $data, int $tokenId, bool $dryRun): array
    {
        $preview = null;

        try {
            DB::transaction(function () use ($slug, $data, $tokenId, $dryRun, &$preview) {
                $overwrite = (bool) ($data['overwrite_existing'] ?? false);

                // ── Find or create ────────────────────────────────────────────
                $mfr = Manufacturer::where('slug', $slug)->first();

                if (! $mfr) {
                    $mfr = new Manufacturer([
                        'slug'      => $slug,
                        'name'      => $data['name'] ?? $slug,
                        'is_active' => false,
                    ]);
                    $mfr->save();
                }

                // ── Scalar fields ─────────────────────────────────────────────
                $writeable = ['name', 'logo', 'description', 'website', 'country', 'sort_order'];
                foreach ($writeable as $field) {
                    if (! array_key_exists($field, $data)) {
                        continue;
                    }
                    if (! $overwrite && $field !== 'name' && filled($mfr->$field)) {
                        continue;
                    }
                    $mfr->$field = $data[$field];
                }

                // Never auto-activate via upsert — use /activate endpoint
                if (isset($data['is_active']) && $data['is_active'] === false) {
                    $mfr->is_active = false;
                }

                $mfr->mcp_drafted_at = now();
                $mfr->mcp_token_id   = $tokenId;
                $mfr->save();

                // ── SEO meta ──────────────────────────────────────────────────
                if (! empty($data['seo'])) {
                    $this->writeSeoMeta($mfr, $data['seo'], $overwrite);
                }

                // ── Geo profiles (AI Context + Key Facts) ─────────────────────
                if (! empty($data['geo'])) {
                    $this->writeGeoProfiles($mfr, $data['geo'], $overwrite);
                }

                $mfr->refresh()->load(['seoMetas', 'geoProfiles']);
                $preview = $this->buildContextResponse($mfr);

                if ($dryRun) {
                    throw new \RuntimeException('__mcp_dry_run__');
                }
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== '__mcp_dry_run__') {
                throw $e;
            }
        }

        return ['data' => $preview];
    }

    public function readiness(string $slug): array
    {
        return $this->computeReadiness($this->loadManufacturer($slug));
    }

    public function activate(string $slug): array
    {
        $mfr      = $this->loadManufacturer($slug);
        $readiness = $this->computeReadiness($mfr);

        if (! empty($readiness['blocking_issues'])) {
            abort(422, 'Manufacturer chưa sẵn sàng để activate: ' . implode('; ', $readiness['blocking_issues']));
        }

        $mfr->update([
            'is_active'      => true,
            'mcp_drafted_at' => null,
            'mcp_token_id'   => null,
        ]);

        $mfr->refresh()->load(['seoMetas', 'geoProfiles']);

        return ['data' => $this->buildContextResponse($mfr)];
    }

    // ── Private: load ──────────────────────────────────────────────────────────

    private function loadManufacturer(string $slug): Manufacturer
    {
        $mfr = Manufacturer::with(['seoMetas', 'geoProfiles'])
            ->withCount('products')
            ->where('slug', $slug)
            ->first();

        if (! $mfr) {
            abort(404, "Manufacturer '{$slug}' not found.");
        }

        return $mfr;
    }

    // ── Private: readiness ─────────────────────────────────────────────────────

    private function computeReadiness(Manufacturer $mfr): array
    {
        $checks   = [];
        $blocking = [];
        $warnings = [];
        $score    = 0;
        $total    = 0;

        // has_description (blocking)
        $hasDesc = filled($mfr->description);
        $checks['has_description'] = ['pass' => $hasDesc];
        $total++; if ($hasDesc) { $score++; }
        if (! $hasDesc) { $blocking[] = 'description missing'; }

        // has_logo (warning)
        $hasLogo = filled($mfr->logo);
        $checks['has_logo'] = ['pass' => $hasLogo];
        $total++; if ($hasLogo) { $score++; }
        if (! $hasLogo) { $warnings[] = 'logo chưa có'; }

        // has_geo vi (warning)
        $geoVi    = $mfr->geoProfiles->firstWhere('locale', 'vi');
        $hasGeoVi = filled($geoVi?->ai_summary);
        $checks['has_geo_vi'] = ['pass' => $hasGeoVi];
        $total++; if ($hasGeoVi) { $score++; }
        if (! $hasGeoVi) { $warnings[] = 'geo.vi.ai_summary chưa có'; }

        // SEO per locale
        foreach (['vi', 'en'] as $locale) {
            $seoMeta = $mfr->seoMetas->firstWhere('locale', $locale);

            $hasMetaTitle = filled($seoMeta?->meta_title);
            $checks["seo_{$locale}"]['has_meta_title'] = ['pass' => $hasMetaTitle];
            $total++; if ($hasMetaTitle) { $score++; }
            if (! $hasMetaTitle) {
                $locale === 'vi'
                    ? $blocking[] = "seo_vi.meta_title missing"
                    : $warnings[] = "seo_en.meta_title missing";
            }

            $hasMetaDesc = filled($seoMeta?->meta_description);
            $checks["seo_{$locale}"]['has_meta_description'] = ['pass' => $hasMetaDesc];
            $total++; if ($hasMetaDesc) { $score++; }
            if (! $hasMetaDesc) {
                $locale === 'vi'
                    ? $blocking[] = "seo_vi.meta_description missing"
                    : $warnings[] = "seo_en.meta_description missing";
            }
        }

        $scorePercent = $total > 0 ? (int) round(($score / $total) * 100) : 0;

        return [
            'slug'            => $mfr->slug,
            'score'           => $scorePercent,
            'ready'           => empty($blocking),
            'checks'          => $checks,
            'blocking_issues' => $blocking,
            'warnings'        => $warnings,
        ];
    }

    // ── Private: write helpers ─────────────────────────────────────────────────

    private function writeSeoMeta(Manufacturer $mfr, array $seo, bool $overwrite): void
    {
        $writeable = ['meta_title', 'meta_description', 'meta_keywords', 'canonical_url', 'og_title', 'og_description', 'og_image', 'robots'];

        foreach ($seo as $locale => $data) {
            if (! in_array($locale, ['vi', 'en'], true)) {
                continue;
            }

            $seoMeta = SeoMeta::firstOrNew([
                'model_type' => 'manufacturer',
                'model_id'   => (string) $mfr->id,
                'locale'     => $locale,
            ]);

            if ($seoMeta->exists && $seoMeta->is_mcp_protected) {
                continue;
            }

            foreach ($writeable as $field) {
                if (! array_key_exists($field, $data)) {
                    continue;
                }
                if (! $overwrite && $seoMeta->exists && filled($seoMeta->$field)) {
                    continue;
                }
                $seoMeta->$field = $data[$field];
            }

            if (blank($seoMeta->robots)) {
                $seoMeta->robots = 'index, follow';
            }

            $seoMeta->model_type = 'manufacturer';
            $seoMeta->model_id   = (string) $mfr->id;
            $seoMeta->locale     = $locale;
            $seoMeta->save();
        }
    }

    private function writeGeoProfiles(Manufacturer $mfr, array $geo, bool $overwrite): void
    {
        $writeable     = ['ai_summary', 'use_cases', 'target_audience', 'llm_context_hint'];
        $writeableJson = ['key_facts', 'faq'];

        foreach ($geo as $locale => $data) {
            if (! in_array($locale, ['vi', 'en'], true)) {
                continue;
            }

            $profile = GeoEntityProfile::firstOrNew([
                'model_type' => 'manufacturer',
                'model_id'   => (string) $mfr->id,
                'locale'     => $locale,
            ]);

            foreach ($writeable as $field) {
                if (! isset($data[$field])) {
                    continue;
                }
                if (! $overwrite && $profile->exists && filled($profile->$field)) {
                    continue;
                }
                $profile->$field = $data[$field];
            }

            foreach ($writeableJson as $field) {
                if (! array_key_exists($field, $data)) {
                    continue;
                }
                if (! $overwrite && $profile->exists && ! empty($profile->$field)) {
                    continue;
                }
                $profile->$field = $data[$field];
            }

            $profile->model_type = 'manufacturer';
            $profile->model_id   = (string) $mfr->id;
            $profile->locale     = $locale;
            $profile->save();
        }
    }

    // ── Private: response builder ──────────────────────────────────────────────

    private function buildContextResponse(Manufacturer $mfr): array
    {
        $seoOut = [];
        foreach ($mfr->seoMetas as $meta) {
            $seoOut[$meta->locale] = [
                'meta_title'       => $meta->meta_title,
                'meta_description' => $meta->meta_description,
                'meta_keywords'    => $meta->meta_keywords,
                'canonical_url'    => $meta->canonical_url,
                'og_title'         => $meta->og_title,
                'og_description'   => $meta->og_description,
                'og_image'         => $meta->og_image,
                'robots'           => $meta->robots,
                'is_mcp_protected' => (bool) $meta->is_mcp_protected,
            ];
        }

        $geoOut = [];
        foreach ($mfr->geoProfiles as $g) {
            $geoOut[$g->locale] = [
                'ai_summary'       => $g->ai_summary,
                'use_cases'        => $g->use_cases,
                'target_audience'  => $g->target_audience,
                'llm_context_hint' => $g->llm_context_hint,
                'key_facts'        => $g->key_facts ?? [],
                'faq'              => $g->faq ?? [],
            ];
        }

        return [
            'slug'           => $mfr->slug,
            'name'           => $mfr->name,
            'logo'           => $mfr->logo,
            'description'    => $mfr->description,
            'website'        => $mfr->website,
            'country'        => $mfr->country,
            'is_active'      => (bool) $mfr->is_active,
            'sort_order'     => $mfr->sort_order,
            'product_count'  => $mfr->products_count ?? $mfr->loadCount('products')->products_count,
            'geo'            => $geoOut,
            'seo'            => $seoOut,
            'mcp_drafted_at' => $mfr->mcp_drafted_at?->toIso8601String(),
        ];
    }
}
