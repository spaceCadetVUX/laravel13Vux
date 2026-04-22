<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;

class VariantGeneratorService
{
    /**
     * Generate variants from all cartesian combinations of a product's option types.
     *
     * Rules:
     *  - Only CREATES new combinations — never modifies or deletes existing ones.
     *  - Skips combinations that already have a variant.
     *  - Auto-generates SKU from product SKU + option value slugs.
     *  - New variants inherit price/sale_price from the product; stock defaults to 0.
     *
     * @return array{created: int, skipped: int, error: string|null}
     */
    public function generate(Product $product): array
    {
        $product->loadMissing('optionTypes.values');

        $optionTypes = $product->optionTypes;

        // ── Guard: must have at least 1 option type with values ───────────────

        if ($optionTypes->isEmpty()) {
            return ['created' => 0, 'skipped' => 0, 'error' => 'No option types defined. Add at least one option (e.g. Color) with values first.'];
        }

        foreach ($optionTypes as $type) {
            if ($type->values->isEmpty()) {
                return ['created' => 0, 'skipped' => 0, 'error' => "Option \"{$type->name}\" has no values. Add at least one value before generating."];
            }
        }

        // ── Build cartesian product ───────────────────────────────────────────

        $valueSets    = $optionTypes->map(fn ($t) => $t->values)->values()->all();
        $combinations = $this->cartesian($valueSets);

        // ── Map existing variants to their option-value-set key ───────────────
        // Key = sorted option_value_ids joined by comma e.g. "3,7"

        $existingKeys = $product->variants()
            ->with('optionValues')
            ->get()
            ->mapWithKeys(fn ($v) => [
                $v->optionValues->pluck('id')->sort()->values()->join(',') => true,
            ])
            ->all();

        // ── Create missing combinations ───────────────────────────────────────

        $created       = 0;
        $skipped       = 0;
        $existingCount = count($existingKeys);

        foreach ($combinations as $combination) {
            $valueIds = collect($combination)->pluck('id')->sort()->values();
            $key      = $valueIds->join(',');

            if (isset($existingKeys[$key])) {
                $skipped++;
                continue;
            }

            // Auto SKU: PRODUCT-SKU-RED-M  (4-char slugs, uppercased)
            $sku = $this->buildSku($product->sku ?? 'VAR', $combination);

            // Create the variant row
            $variant = $product->variants()->create([
                'sku'            => $sku,
                'price'          => $product->price ?? 0,
                'sale_price'     => $product->sale_price,
                'stock_quantity' => 0,
                'is_active'      => true,
                'sort_order'     => $existingCount + $created,
            ]);

            // Link to option values via pivot
            $variant->optionValues()->attach($valueIds->all());

            // Add to seen keys to avoid duplication within this run
            $existingKeys[$key] = true;
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped, 'error' => null];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Cartesian product of N collections.
     *
     * Input:  [[Red, Blue], [S, M, L]]
     * Output: [[Red,S], [Red,M], [Red,L], [Blue,S], [Blue,M], [Blue,L]]
     */
    private function cartesian(array $sets): array
    {
        if (empty($sets)) {
            return [[]];
        }

        $first = array_shift($sets);
        $rest  = $this->cartesian($sets);
        $result = [];

        foreach ($first as $item) {
            foreach ($rest as $combo) {
                $result[] = array_merge([$item], $combo);
            }
        }

        return $result;
    }

    /**
     * Build a unique SKU string.
     *
     * e.g. SKU="SHIRT-001", combination=[Red, M] → "SHIRT-001-RED-M"
     * Appends a numeric suffix if the generated SKU is already taken.
     */
    private function buildSku(string $baseSku, array $combination): string
    {
        $suffix = collect($combination)
            ->map(fn ($v) => Str::upper(
                Str::substr(
                    Str::slug($v->value, '-'),
                    0, 5
                )
            ))
            ->filter()
            ->join('-');

        $sku     = $baseSku . ($suffix ? '-' . $suffix : '');
        $attempt = $sku;
        $counter = 1;

        while (ProductVariant::where('sku', $attempt)->exists()) {
            $attempt = $sku . '-' . $counter++;
        }

        return $attempt;
    }
}
