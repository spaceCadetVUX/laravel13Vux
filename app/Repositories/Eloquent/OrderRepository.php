<?php

namespace App\Repositories\Eloquent;

use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderRepository extends BaseRepository
{
    protected function model(): string
    {
        return Order::class;
    }

    // ── Read ──────────────────────────────────────────────────────────────────

    public function getCartWithItems(User $user): ?Cart
    {
        return Cart::where('user_id', $user->id)
            ->with('items.product')
            ->first();
    }

    public function paginateForUser(User $user, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->query()
            ->where('user_id', $user->id)
            ->latest();

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    public function findForUser(User $user, string $orderId): ?Order
    {
        /** @var Order|null */
        return $this->query()
            ->where('id', $orderId)
            ->where('user_id', $user->id)
            ->with('items')
            ->first();
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    public function createOrder(array $data): Order
    {
        return Order::create($data);
    }

    public function createOrderItems(Order $order, Cart $cart): void
    {
        foreach ($cart->items as $item) {
            $order->items()->create([
                'product_id'   => $item->product_id,
                'product_name' => $item->product->name,
                'product_sku'  => $item->product->sku,
                'quantity'     => $item->quantity,
                'unit_price'   => $item->product->sale_price ?? $item->product->price,
            ]);

            $item->product->decrement('stock_quantity', $item->quantity);
        }
    }

    public function restoreStock(Order $order): void
    {
        foreach ($order->items()->with('product')->get() as $item) {
            if ($item->product) {
                $item->product->increment('stock_quantity', $item->quantity);
            }
        }
    }
}
