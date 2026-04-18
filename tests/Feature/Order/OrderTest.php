<?php

namespace Tests\Feature\Order;

use App\Enums\AddressLabel;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('scout.driver', 'collection');
        Queue::fake();
    }

    // ── Auth guards ───────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_place_order(): void
    {
        $this->postJson('/api/v1/orders', [])
            ->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_list_orders(): void
    {
        $this->getJson('/api/v1/orders')
            ->assertStatus(401);
    }

    // ── Place order ───────────────────────────────────────────────────────────

    public function test_user_can_place_order(): void
    {
        ['user' => $user, 'product' => $product, 'address' => $address] = $this->setupOrderData();
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/orders', ['address_id' => $address->id])
            ->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'status', 'total_amount', 'items'],
            ])
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('cart_items', 0);           // cart is cleared
        $this->assertEquals(8, $product->fresh()->stock_quantity); // 10 - 2
    }

    public function test_place_order_fails_with_empty_cart(): void
    {
        $user    = User::factory()->create();
        $address = $this->createAddress($user);
        $token   = $user->createToken('api-token')->plainTextToken;

        Cart::create(['user_id' => $user->id]); // cart exists but has no items

        $this->withToken($token)
            ->postJson('/api/v1/orders', ['address_id' => $address->id])
            ->assertStatus(422);
    }

    public function test_place_order_fails_when_stock_is_insufficient(): void
    {
        $user    = User::factory()->create();
        $address = $this->createAddress($user);
        $product = Product::factory()->create(['stock_quantity' => 1]);
        $token   = $user->createToken('api-token')->plainTextToken;

        $cart = Cart::create(['user_id' => $user->id]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 5]);

        $this->withToken($token)
            ->postJson('/api/v1/orders', ['address_id' => $address->id])
            ->assertStatus(422);
    }

    // ── Order history ─────────────────────────────────────────────────────────

    public function test_user_can_list_orders(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $this->createOrder($user);
        $this->createOrder($user);

        $this->withToken($token)
            ->getJson('/api/v1/orders')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_user_can_view_order(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;
        $order = $this->createOrder($user);

        $this->withToken($token)
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_user_cannot_view_other_users_order(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = $this->createOrder($owner);
        $token = $other->createToken('api-token')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertStatus(403);
    }

    // ── Cancel ────────────────────────────────────────────────────────────────

    public function test_user_can_cancel_pending_order(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;
        $order = $this->createOrder($user, OrderStatus::Pending);

        $this->withToken($token)
            ->patchJson("/api/v1/orders/{$order->id}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cancel_fails_when_order_is_not_pending(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;
        $order = $this->createOrder($user, OrderStatus::Shipped);

        $this->withToken($token)
            ->patchJson("/api/v1/orders/{$order->id}/cancel")
            ->assertStatus(422);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function setupOrderData(): array
    {
        $user    = User::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 10, 'price' => 100.00]);
        $address = $this->createAddress($user);

        $cart = Cart::create(['user_id' => $user->id]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 2]);

        return compact('user', 'product', 'address', 'cart');
    }

    private function createAddress(User $user): Address
    {
        return Address::create([
            'user_id'      => $user->id,
            'label'        => AddressLabel::Home,
            'full_name'    => 'Test User',
            'phone'        => '0123456789',
            'address_line' => '123 Test Street',
            'city'         => 'Hanoi',
            'district'     => 'Hoan Kiem',
            'ward'         => 'Hang Bong',
            'is_default'   => true,
        ]);
    }

    private function createOrder(User $user, OrderStatus $status = OrderStatus::Pending): Order
    {
        $product = Product::factory()->create();

        $order = Order::create([
            'user_id'          => $user->id,
            'status'           => $status,
            'total_amount'     => 200.00,
            'shipping_address' => [
                'full_name'    => 'Test User',
                'phone'        => '0123456789',
                'address_line' => '123 Test Street',
                'city'         => 'Hanoi',
                'district'     => 'Hoan Kiem',
                'ward'         => 'Hang Bong',
            ],
            'payment_status' => PaymentStatus::Unpaid,
        ]);

        $order->items()->create([
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'product_sku'  => $product->sku,
            'quantity'     => 2,
            'unit_price'   => 100.00,
        ]);

        return $order;
    }
}
