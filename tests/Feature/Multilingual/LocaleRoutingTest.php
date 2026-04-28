<?php

namespace Tests\Feature\Multilingual;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_to_locale(): void
    {
        $this->get('/')->assertStatus(302);
    }

    public function test_valid_locale_returns_200(): void
    {
        $this->get('/vi/')->assertStatus(200);
        $this->get('/en/')->assertStatus(200);
    }

    public function test_invalid_locale_returns_404(): void
    {
        // /xx/ hits the fallback (301 → /vi/xx); the locale group routes it to
        // PageController which finds no matching PageTranslation → 404.
        $this->followingRedirects()
            ->get('/xx/')
            ->assertStatus(404);
    }

    public function test_no_locale_path_redirects_301(): void
    {
        $this->get('/products/test')
            ->assertStatus(301)
            ->assertRedirect('/vi/products/test');
    }
}
