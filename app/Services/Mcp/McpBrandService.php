<?php

namespace App\Services\Mcp;

use App\Models\Brand;
use App\Models\Seo\SeoMeta;
use Illuminate\Support\Facades\DB;

class McpBrandService
{
    // ── Public API ─────────────────────────────────────────────────────────────

    public function context(string $slug): array
    {
        $brand = Brand::with('seoMetas')->where('slug', $slug)->firstOrFail();

        return $this->buildContextResponse($brand);
    }

    public function upsert(string $slug, array $data, int $tokenId, bool $dryRun): array
    {
        $preview = null;

        try {
            DB::transaction(function () use ($slug, $data, $tokenId, $dryRun, &$preview) {
                $overwrite = (bool) ($data['overwrite_existing'] ?? false);

                // ── Find or create (no SoftDeletes) ───────────────────────────
                $brand = Brand::where('slug', $slug)->first();

                if (!$brand) {
                    $brand = new Brand([
                        'slug'      => $slug,
                        'name'      => $data['name'] ?? $slug,
                        'is_active' => false,
                    ]);
                    $brand->save();
                }

                // ── Scalar fields ─────────────────────────────────────────────
                $writeable = ['name', 'description', 'website', 'sort_order'];
                foreach ($writeable as $field) {
                    if (!array_key_exists($field, $data)) continue;
                    if (!$overwrite && $field !== 'name' && filled($brand->$field)) continue;
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
                $this->writeSeoMeta($brand, $data['seo'] ?? [], $overwrite);

                $brand->load('seoMetas');
                $preview = $this->buildContextResponse($brand);

                if ($dryRun) throw new \RuntimeException('__mcp_dry_run__');
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== '__mcp_dry_run__') throw $e;
        }

        return ['data' => $preview];
    }

    public function activate(string $slug): array
    {
        $brand = Brand::with('seoMetas')->where('slug', $slug)->firstOrFail();

        $readiness = $this->computeReadiness($brand);

        if (!empty($readiness['blocking_issues'])) {
            abort(422, 'Brand chưa sẵn sàng để activate: ' . implode('; ', $readiness['blocking_issues']));
        }

        $brand->update([
            'is_active'      => true,
            'mcp_drafted_at' => null,
            'mcp_token_id'   => null,
        ]);

        return [
            'data' => $this->buildContextResponse($brand->fresh('seoMetas')),
        ];
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function computeReadiness(Brand $brand): array
    {
        $checks   = [];
        $blocking = [];
        $warnings = [];
        $score    = 0;
        $total    = 0;

        // has_description (blocking)
        $hasDesc = !empty($brand->description);
        $checks['has_description'] = ['pass' => $hasDesc];
        $total++; if ($hasDesc) $score++;
        if (!$hasDesc) $blocking[] = 'description missing';

        // SEO per locale
        foreach (['vi', 'en'] as $locale) {
            $seoMeta = $brand->seoMetas->firstWhere('locale', $locale);

            $hasMetaTitle = !empty($seoMeta?->meta_title);
            $checks["seo_{$locale}"]['has_meta_title'] = ['pass' => $hasMetaTitle];
            $total++; if ($hasMetaTitle) $score++;
            if (!$hasMetaTitle) {
                $locale === 'vi'
                    ? $blocking[] = "seo_vi.meta_title missing"
                    : $warnings[] = "seo_en.meta_title missing";
            }

            $hasMetaDesc = !empty($seoMeta?->meta_description);
            $checks["seo_{$locale}"]['has_meta_description'] = ['pass' => $hasMetaDesc];
            $total++; if ($hasMetaDesc) $score++;
            if (!$hasMetaDesc) {
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

    private function writeSeoMeta(Brand $brand, array $seo, bool $overwrite): void
    {
        foreach ($seo as $locale => $data) {
            if (!in_array($locale, ['vi', 'en'], true)) continue;

            $seoMeta = SeoMeta::firstOrNew([
                'model_type' => 'brand',
                'model_id'   => (string) $brand->id,
                'locale'     => $locale,
            ]);

            if ($seoMeta->exists && $seoMeta->is_mcp_protected) continue;

            foreach (['meta_title', 'meta_description', 'og_title', 'og_description', 'robots'] as $field) {
                if (!array_key_exists($field, $data)) continue;
                if (!$overwrite && $seoMeta->exists && filled($seoMeta->$field)) continue;
                $seoMeta->$field = $data[$field];
            }

            if (blank($seoMeta->robots)) $seoMeta->robots = 'index, follow';

            $seoMeta->model_type = 'brand';
            $seoMeta->model_id   = (string) $brand->id;
            $seoMeta->locale     = $locale;
            $seoMeta->save();
        }
    }

    private function buildContextResponse(Brand $brand): array
    {
        $seo = [];
        foreach ($brand->seoMetas as $meta) {
            $seo[$meta->locale] = [
                'meta_title'       => $meta->meta_title,
                'meta_description' => $meta->meta_description,
                'og_title'         => $meta->og_title,
                'og_description'   => $meta->og_description,
                'robots'           => $meta->robots,
                'is_mcp_protected' => $meta->is_mcp_protected,
            ];
        }

        return [
            'slug'           => $brand->slug,
            'name'           => $brand->name,
            'description'    => $brand->description,
            'website'        => $brand->website,
            'is_active'      => $brand->is_active,
            'sort_order'     => $brand->sort_order,
            'product_count'  => $brand->products()->count(),
            'seo'            => $seo,
            'mcp_drafted_at' => $brand->mcp_drafted_at?->toIso8601String(),
        ];
    }
}
