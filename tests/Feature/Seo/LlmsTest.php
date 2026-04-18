<?php

namespace Tests\Feature\Seo;

use App\Enums\LlmsScope;
use App\Models\Seo\LlmsDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LlmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_llms_txt_returns_plain_text(): void
    {
        // No root document — controller returns a minimal stub at 200
        $this->get('/llms.txt')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function test_llms_full_txt_returns_plain_text(): void
    {
        // No full-scope documents — controller returns a stub at 200
        $this->get('/llms-full.txt')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function test_llms_products_returns_plain_text(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('llms/products.txt', '# Products LLMs');

        LlmsDocument::create([
            'name'      => 'products',
            'slug'      => 'products',
            'title'     => 'Products',
            'scope'     => LlmsScope::Full,
            'is_active' => true,
        ]);

        $this->get('/llms-products.txt')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function test_llms_unknown_slug_returns_404(): void
    {
        $this->get('/llms-unknown-document.txt')
            ->assertStatus(404);
    }
}
