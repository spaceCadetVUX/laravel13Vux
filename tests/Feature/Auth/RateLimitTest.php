<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rate_limited_after_5_attempts(): void
    {
        Cache::flush(); // reset any residual throttle state

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email'    => 'brute@example.com',
                'password' => 'wrong-password-' . $i,
            ]);
        }

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'brute@example.com',
            'password' => 'final-attempt',
        ])->assertStatus(429);
    }

    public function test_register_is_rate_limited_after_5_attempts(): void
    {
        Cache::flush();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/register', [
                'name'                  => 'User ' . $i,
                'email'                 => 'user' . $i . '@example.com',
                'password'              => 'password123',
                'password_confirmation' => 'password123',
            ]);
        }

        $this->postJson('/api/v1/auth/register', [
            'name'                  => 'User 6',
            'email'                 => 'user6@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(429);
    }
}
