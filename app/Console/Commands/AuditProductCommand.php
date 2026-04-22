<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Audit\ProductAuditService;
use Illuminate\Console\Command;

class AuditProductCommand extends Command
{
    protected $signature = 'audit:product
                            {product?   : Product slug or UUID (omit when using --all)}
                            {--all      : Audit every active product}
                            {--inactive : Include inactive products when using --all}';

    protected $description = 'Generate a Markdown SEO/GEO/LLMs/JSON-LD audit report for a product';

    public function handle(ProductAuditService $service): int
    {
        if ($this->option('all')) {
            return $this->auditAll($service);
        }

        $input = $this->argument('product');

        if ($input === null) {
            $this->error('Provide a product slug / UUID, or use --all to audit every product.');

            return self::FAILURE;
        }

        return $this->auditOne($service, $input);
    }

    // ── Handlers ─────────────────────────────────────────────────────────────

    private function auditOne(ProductAuditService $service, string $input): int  // @phpstan-ignore-line (null already guarded in handle)
    {
        // Try slug first; only attempt UUID lookup if input looks like a UUID
        // (PostgreSQL throws on non-UUID strings cast to uuid column)
        $isUuid = (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $input
        );

        $product = Product::where('slug', $input)
            ->when($isUuid, fn ($q) => $q->orWhere('id', $input))
            ->first();

        if (! $product) {
            $this->error("Product not found: \"{$input}\" (tried slug and UUID)");

            return self::FAILURE;
        }

        $path = $service->generate($product);

        $this->info('✅ Audit saved → ' . $path);

        return self::SUCCESS;
    }

    private function auditAll(ProductAuditService $service): int
    {
        $query = Product::query();

        if (! $this->option('inactive')) {
            $query->where('is_active', true);
        }

        $count = $query->count();

        if ($count === 0) {
            $this->warn('No products found.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->chunkById(50, function ($products) use ($service, $bar): void {
            foreach ($products as $product) {
                $service->generate($product);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("✅ {$count} audit report(s) saved to audit/products/");

        return self::SUCCESS;
    }
}
