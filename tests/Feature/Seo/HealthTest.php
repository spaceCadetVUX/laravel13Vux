<?php

namespace Tests\Feature\Seo;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_200(): void
    {
        Storage::fake('public');
        Http::fake(['*/health' => Http::response(['status' => 'available'], 200)]);
        Cache::put('horizon:status', 'running');
        Redis::shouldReceive('ping')->andReturn(true);

        $this->get('/health')
            ->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }

    public function test_health_endpoint_structure(): void
    {
        Storage::fake('public');
        Http::fake();

        $this->get('/health')
            ->assertJsonStructure([
                'status',
                'timestamp',
                'services' => ['database', 'redis', 'meilisearch', 'horizon', 'storage'],
            ]);
    }
}
