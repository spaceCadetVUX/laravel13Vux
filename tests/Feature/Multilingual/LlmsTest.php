<?php

namespace Tests\Feature\Multilingual;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LlmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_vi_llms_returns_vietnamese_content(): void
    {
        $this->get('/vi/llms.txt')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function test_en_llms_returns_english_content(): void
    {
        $this->get('/en/llms.txt')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function test_root_llms_redirects_to_vi(): void
    {
        $this->get('/llms.txt')
            ->assertStatus(302)
            ->assertRedirect('/vi/llms.txt');
    }
}
