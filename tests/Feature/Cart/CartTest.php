<?php

namespace Tests\Feature\Cart;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('scout.driver', 'collection');
    }

    private function sessionId(): string
    {
        return (string) Str::uuid();
    }

    // ── Guest cart ────────────────────────────────────────────────────────────

    public function test_guest_can_view_cart(): void
    {
        $session = $this->sessionId();

        $this->withHeaders(['X-Session-ID' => $session])
            ->getJson('/api/v1/cart')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'expires_at', 'items', 'total', 'item_count'],
            ]);
    }

    public function test_cart_requires_session_id_for_guests(): void
    {
        $this->getJson('/api/v1/cart')
            ->assertStatus(400);
    }

    public function test_guest_can_add_item_to_cart(): void
    {
        $session = $this->sessionId();
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $this->withHeaders(['X-Session-ID' => $session])
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity'   => 2,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.item_count', 2);
    }

    public function test_add_item_increments_quantity_if_already_present(): void
    {
        $session = $this->sessionId();
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $this->withHeaders(['X-Session-ID' => $session])
            ->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 2]);

        $this->withHeaders(['X-Session-ID' => $session])
            ->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 3])
            ->assertStatus(201)
            ->assertJsonPath('data.item_count', 5);
    }

    public function test_add_item_fails_when_exceeding_stock(): void
    {
        $session = $this->sessionId();
        $product = Product::factory()->create(['stock_quantity' => 3]);

        $this->withHeaders(['X-Session-ID' => $session])
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'quantity'   => 5,
            ])
            ->assertStatus(422);
    }

    public function test_guest_can_update_cart_item(): void
    {
        $session = $this->sessionId();
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $cart = Cart::create(['session_id' => $session]);
        $item = $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $this->withHeaders(['X-Session-ID' => $session])
            ->putJson("/api/v1/cart/items/{$item->id}", ['quantity' => 4])
            ->assertStatus(200)
            ->assertJsonPath('data.item_count', 4);
    }

    public function test_guest_can_remove_cart_item(): void
    {
        $session = $this->sessionId();
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $cart = Cart::create(['session_id' => $session]);
        $item = $cart->items()->create(['product_id' => $product->id, 'quantity' => 2]);

        $this->withHeaders(['X-Session-ID' => $session])
            ->deleteJson("/api/v1/cart/items/{$item->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.item_count', 0);
    }

    public function test_guest_can_clear_cart(): void
    {
        $session = $this->sessionId();
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $cart = Cart::create(['session_id' => $session]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 2]);

        $this->withHeaders(['X-Session-ID' => $session])
            ->deleteJson('/api/v1/cart')
            ->assertStatus(200);

        $this->assertDatabaseCount('cart_items', 0);
    }

    // ── Authenticated cart ────────────────────────────────────────────────────

    public function test_authenticated_user_can_view_cart(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/cart')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'expires_at', 'items', 'total', 'item_count'],
            ]);
    }

    public function test_user_cannot_modify_another_users_cart_item(): void
    {
        $ownerSession = $this->sessionId();
        $otherSession = $this->sessionId();
        $product      = Product::factory()->create(['stock_quantity' => 10]);

        $ownerCart = Cart::create(['session_id' => $ownerSession]);
        $item      = $ownerCart->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $this->withHeaders(['X-Session-ID' => $otherSession])
            ->putJson("/api/v1/cart/items/{$item->id}", ['quantity' => 99])
            ->assertStatus(403);
    }
}
