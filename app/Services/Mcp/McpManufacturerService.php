<?php

namespace App\Services\Mcp;

use App\Models\Manufacturer;
use App\Models\Seo\SeoMeta;
use Illuminate\Support\Facades\DB;

class McpManufacturerService
{
    // ── Public API ─────────────────────────────────────────────────────────────

    public function context(string $slug): array
    {
        $mfr = Manufacturer::with('seoMetas')->where('slug', $slug)->firstOrFail();

        return $this->buildContextResponse($mfr);
    }

    public function upsert(string $slug, array $data, int $tokenId, bool $dryRun): array
    {
        $preview = null;

        try {
            DB::transaction(function () use ($slug, $data, $tokenId, $dryRun, &$preview) {
                $overwrite = (bool) ($data['overwrite_existing'] ?? false);

                // ── Find or create (no SoftDeletes) ───────────────────────────
                $mfr = Manufacturer::where('slug', $slug)->first();

                if (!$mfr) {
                    $mfr = new Manufacturer([
                        'slug'      => $slug,
                        'name'      => $data['name'] ?? $slug,
                        'is_active' => false,
                    ]);
                    $mfr->save();
                }

                // ── Scalar fields ─────────────────────────────────────────────
                $writeable = ['name', 'description', 'website', 'country', 'sort_order'];
                foreach ($writeable as $field) {
                    if (!array_key_exists($field, $data)) continue;
                    if (!$overwrite && $field !== 'name' && filled($mfr->$field)) continue;
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
                $this->writeSeoMeta($mfr, $data['seo'] ?? [], $overwrite);

                $mfr->load('seoMetas');
                $preview = $this->buildContextResponse($mfr);

                if ($dryRun) throw new \RuntimeException('__mcp_dry_run__');
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== '__mcp_dry_run__') throw $e;
        }

        return ['data' => $preview];
    }

    public function activate(string $slug): array
    {
        $mfr = Manufacturer::with('seoMetas')->where('slug', $slug)->firstOrFail();

        $readiness = $this->computeReadiness($mfr);

        if (!empty($readiness['blocking_issues'])) {
            abort(422, 'Manufacturer chưa sẵn sàng để activate: ' . implode('; ', $readiness['blocking_issues']));
        }

        $mfr->update([
            'is_active'      => true,
            'mcp_drafted_at' => null,
            'mcp_token_id'   => null,
        ]);

        return [
            'data' => $this->buildContextResponse($mfr->fresh('seoMetas')),
        ];
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function computeReadiness(Manufacturer $mfr): array
    {
        $checks   = [];
        $blocking = [];
        $warnings = [];
        $score    = 0;
        $total    = 0;

        // has_description (blocking)
        $hasDesc = !empty($mfr->description);
        $checks['has_description'] = ['pass' => $hasDesc];
        $total++; if ($hasDesc) $score++;
        if (!$hasDesc) $blocking[] = 'description missing';

        // SEO per locale
        foreach (['vi', 'en'] as $locale) {
            $seoMeta = $mfr->seoMetas->firstWhere('locale', $locale);

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
            'slug'            => $mfr->slug,
            'score'           => $scorePercent,
            'ready'           => empty($blocking),
            'checks'          => $checks,
            'blocking_issues' => $blocking,
            'warnings'        => $warnings,
        ];
    }

    private function writeSeoMeta(Manufacturer $mfr, array $seo, bool $overwrite): void
    {
        foreach ($seo as $locale => $data) {
            if (!in_array($locale, ['vi', 'en'], true)) continue;

            $seoMeta = SeoMeta::firstOrNew([
                'model_type' => 'manufacturer',
                'model_id'   => (string) $mfr->id,
                'locale'     => $locale,
            ]);

            if ($seoMeta->exists && $seoMeta->is_mcp_protected) continue;

            foreach (['meta_title', 'meta_description', 'og_title', 'og_description', 'robots'] as $field) {
                if (!array_key_exists($field, $data)) continue;
                if (!$overwrite && $seoMeta->exists && filled($seoMeta->$field)) continue;
                $seoMeta->$field = $data[$field];
            }

            if (blank($seoMeta->robots)) $seoMeta->robots = 'index, follow';

            $seoMeta->model_type = 'manufacturer';
            $seoMeta->model_id   = (string) $mfr->id;
            $seoMeta->locale     = $locale;
            $seoMeta->save();
        }
    }

    private function buildContextResponse(Manufacturer $mfr): array
    {
        $seo = [];
        foreach ($mfr->seoMetas as $meta) {
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
            'slug'           => $mfr->slug,
            'name'           => $mfr->name,
            'description'    => $mfr->description,
            'website'        => $mfr->website,
            'country'        => $mfr->country,
            'is_active'      => $mfr->is_active,
            'sort_order'     => $mfr->sort_order,
            'product_count'  => $mfr->products()->count(),
            'seo'            => $seo,
            'mcp_drafted_at' => $mfr->mcp_drafted_at?->toIso8601String(),
        ];
    }
}
