<?php

namespace Tests\Feature\Seo;

use App\Enums\RedirectType;
use App\Models\Seo\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RedirectTest extends TestCase
{
    use RefreshDatabase;

    // Use existing web routes as from_path — HandleRedirects is in the web
    // middleware group and only runs when a route is matched.

    public function test_active_redirect_returns_301(): void
    {
        Redirect::create([
            'from_path'     => '/sitemap.xml',
            'to_path'       => '/new-sitemap-location',
            'type'          => RedirectType::Permanent,
            'hits'          => 0,
            'cache_version' => 1,
            'is_active'     => true,
        ]);

        $this->get('/sitemap.xml')
            ->assertStatus(301)
            ->assertRedirect('/new-sitemap-location');
    }

    public function test_302_redirect_returns_302(): void
    {
        Redirect::create([
            'from_path'     => '/llms.txt',
            'to_path'       => '/new-llms-location',
            'type'          => RedirectType::Temporary,
            'hits'          => 0,
            'cache_version' => 1,
            'is_active'     => true,
        ]);

        $this->get('/llms.txt')
            ->assertStatus(302)
            ->assertRedirect('/new-llms-location');
    }

    public function test_inactive_redirect_not_followed(): void
    {
        Redirect::create([
            'from_path'     => '/sitemap.xml',
            'to_path'       => '/somewhere-else',
            'type'          => RedirectType::Permanent,
            'hits'          => 0,
            'cache_version' => 1,
            'is_active'     => false,
        ]);

        // Inactive redirect is ignored — request proceeds to the real route
        $this->get('/sitemap.xml')
            ->assertStatus(200);
    }

    public function test_redirect_hits_incremented(): void
    {
        Storage::fake('public');

        $redirect = Redirect::create([
            'from_path'     => '/sitemap.xml',
            'to_path'       => '/new-sitemap-location',
            'type'          => RedirectType::Permanent,
            'hits'          => 0,
            'cache_version' => 1,
            'is_active'     => true,
        ]);

        // QUEUE_CONNECTION=sync → IncrementRedirectHits runs immediately
        $this->get('/sitemap.xml');

        $this->assertSame(1, $redirect->fresh()->hits);
    }
}
