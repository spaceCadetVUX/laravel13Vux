<?php

namespace App\Services\Mcp;

use App\Models\Brand;
use App\Models\Seo\GeoEntityProfile;
use App\Models\Seo\SeoMeta;
use Illuminate\Support\Facades\DB;

class McpBrandService
{
    // ── Public API ─────────────────────────────────────────────────────────────

    public function context(string $slug): array
    {
        $brand = $this->loadBrand($slug);

        return $this->buildContextResponse($brand);
    }

    public function upsert(string $slug, array $data, int $tokenId, bool $dryRun): array
    {
        $preview = null;

        try {
            DB::transaction(function () use ($slug, $data, $tokenId, $dryRun, &$preview) {
                $overwrite = (bool) ($data['overwrite_existing'] ?? false);

                // ── Find or create ────────────────────────────────────────────
                $brand = Brand::where('slug', $slug)->first();

                if (! $brand) {
                    $brand = new Brand([
                        'slug'      => $slug,
                        'name'      => $data['name'] ?? $slug,
                        'is_active' => false,
                    ]);
                    $brand->save();
                }

                // ── Scalar fields ─────────────────────────────────────────────
                $writeable = ['name', 'description', 'website', 'logo', 'sort_order'];
                foreach ($writeable as $field) {
                    if (! array_key_exists($field, $data)) {
                        continue;
                    }
                    if (! $overwrite && $field !== 'name' && filled($brand->$field)) {
                        continue;
                    }
                    $brand->$field = $data[$field];
                }

                // Never auto-activate via upsert — use /activate endpoint
                if (isset($data['is_active']) && $data['is_active'] === false) {
                    $brand->is_active = false;
                }

                $brand->mcp_drafted_at = now();
                $brand->mcp_token_id   = $tokenId;
                $brand->save();

                // ── SEO meta ──────────────────────────────────────────────────
                if (! empty($data['seo'])) {
                    $this->writeSeoMeta($brand, $data['seo'], $overwrite);
                }

                // ── Geo profiles (AI Context + Key Facts) ─────────────────────
                if (! empty($data['geo'])) {
                    $this->writeGeoProfiles($brand, $data['geo'], $overwrite);
                }

                $brand->refresh()->load(['seoMetas', 'geoProfiles']);
                $preview = $this->buildContextResponse($brand);

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
        return $this->computeReadiness($this->loadBrand($slug));
    }

    public function activate(string $slug): array
    {
        $brand    = $this->loadBrand($slug);
        $readiness = $this->computeReadiness($brand);

        if (! empty($readiness['blocking_issues'])) {
            abort(422, 'Brand chưa sẵn sàng để activate: ' . implode('; ', $readiness['blocking_issues']));
        }

        $brand->update([
            'is_active'      => true,
            'mcp_drafted_at' => null,
            'mcp_token_id'   => null,
        ]);

        $brand->refresh()->load(['seoMetas', 'geoProfiles']);

        return ['data' => $this->buildContextResponse($brand)];
    }

    // ── Private: load ──────────────────────────────────────────────────────────

    private function loadBrand(string $slug): Brand
    {
        $brand = Brand::with(['seoMetas', 'geoProfiles'])
            ->withCount('products')
            ->where('slug', $slug)
            ->first();

        if (! $brand) {
            abort(404, "Brand '{$slug}' not found.");
        }

        return $brand;
    }

    // ── Private: readiness ─────────────────────────────────────────────────────

    private function computeReadiness(Brand $brand): array
    {
        $checks   = [];
        $blocking = [];
        $warnings = [];
        $score    = 0;
        $total    = 0;

        // has_description (blocking)
        $hasDesc = filled($brand->description);
        $checks['has_description'] = ['pass' => $hasDesc];
        $total++; if ($hasDesc) { $score++; }
        if (! $hasDesc) { $blocking[] = 'description missing'; }

        // has_logo (warning)
        $hasLogo = filled($brand->logo);
        $checks['has_logo'] = ['pass' => $hasLogo];
        $total++; if ($hasLogo) { $score++; }
        if (! $hasLogo) { $warnings[] = 'logo chưa có'; }

        // has_geo vi (warning)
        $geoVi    = $brand->geoProfiles->firstWhere('locale', 'vi');
        $hasGeoVi = filled($geoVi?->ai_summary);
        $checks['has_geo_vi'] = ['pass' => $hasGeoVi];
        $total++; if ($hasGeoVi) { $score++; }
        if (! $hasGeoVi) { $warnings[] = 'geo.vi.ai_summary chưa có'; }

        // SEO per locale
        foreach (['vi', 'en'] as $locale) {
            $seoMeta = $brand->seoMetas->firstWhere('locale', $locale);

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
            'slug'            => $brand->slug,
            'score'           => $scorePercent,
            'ready'           => empty($blocking),
            'checks'          => $checks,
            'blocking_issues' => $blocking,
            'warnings'        => $warnings,
        ];
    }

    // ── Private: write helpers ─────────────────────────────────────────────────

    private function writeSeoMeta(Brand $brand, array $seo, bool $overwrite): void
    {
        $writeable = ['meta_title', 'meta_description', 'meta_keywords', 'canonical_url', 'og_title', 'og_description', 'og_image', 'robots'];

        foreach ($seo as $locale => $data) {
            if (! in_array($locale, ['vi', 'en'], true)) {
                continue;
            }

            $seoMeta = SeoMeta::firstOrNew([
                'model_type' => 'brand',
                'model_id'   => (string) $brand->id,
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

            $seoMeta->model_type = 'brand';
            $seoMeta->model_id   = (string) $brand->id;
            $seoMeta->locale     = $locale;
            $seoMeta->save();
        }
    }

    private function writeGeoProfiles(Brand $brand, array $geo, bool $overwrite): void
    {
        $writeable     = ['ai_summary', 'use_cases', 'target_audience', 'llm_context_hint'];
        $writeableJson = ['key_facts', 'faq'];

        foreach ($geo as $locale => $data) {
            if (! in_array($locale, ['vi', 'en'], true)) {
                continue;
            }

            $profile = GeoEntityProfile::firstOrNew([
                'model_type' => 'brand',
                'model_id'   => (string) $brand->id,
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

            $profile->model_type = 'brand';
            $profile->model_id   = (string) $brand->id;
            $profile->locale     = $locale;
            $profile->save();
        }
    }

    // ── Private: response builder ──────────────────────────────────────────────

    private function buildContextResponse(Brand $brand): array
    {
        $seoOut = [];
        foreach ($brand->seoMetas as $meta) {
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
        foreach ($brand->geoProfiles as $g) {
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
            'slug'           => $brand->slug,
            'name'           => $brand->name,
            'description'    => $brand->description,
            'logo'           => $brand->logo,
            'website'        => $brand->website,
            'is_active'      => (bool) $brand->is_active,
            'sort_order'     => $brand->sort_order,
            'product_count'  => $brand->products_count ?? $brand->loadCount('products')->products_count,
            'geo'            => $geoOut,
            'seo'            => $seoOut,
            'mcp_drafted_at' => $brand->mcp_drafted_at?->toIso8601String(),
        ];
    }
}
