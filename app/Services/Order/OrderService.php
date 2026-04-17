<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Jobs\Order\SendOrderConfirmationEmail;
use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * Place a new order from the user's active cart.
     *
     * Runs in a DB transaction:
     *   1. Validate cart is not empty
     *   2. Check stock per item
     *   3. Snapshot shipping address
     *   4. Create Order + OrderItems (price snapshot at checkout time)
     *   5. Deduct stock_quantity per product
     *   6. Clear cart
     *   7. Dispatch confirmation email (queue: orders)
     */
    public function placeOrder(User $user, array $data): Order
    {
        $cart = Cart::where('user_id', $user->id)
            ->with('items.product')
            ->first();

        if (! $cart || $cart->items->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => ['Your cart is empty.'],
            ]);
        }

        // Verify address belongs to this user
        $address = $user->addresses()->findOrFail($data['address_id']);

        return DB::transaction(function () use ($user, $cart, $address, $data) {
            // ── Stock check ───────────────────────────────────────────────────
            foreach ($cart->items as $item) {
                if ($item->product->stock_quantity < $item->quantity) {
                    throw ValidationException::withMessages([
                        'cart' => [
                            "\"{$item->product->name}\" only has {$item->product->stock_quantity} unit(s) in stock.",
                        ],
                    ]);
                }
            }

            // ── Address snapshot (decrypt → plain array) ──────────────────────
            $shippingSnapshot = [
                'full_name'    => $address->full_name,
                'phone'        => $address->phone,        // decrypted by accessor
                'address_line' => $address->address_line, // decrypted by accessor
                'city'         => $address->city,
                'district'     => $address->district,
                'ward'         => $address->ward,
            ];

            // ── Calculate total ───────────────────────────────────────────────
            $total = $cart->items->sum(fn ($item) =>
                $item->quantity * (float) ($item->product->sale_price ?? $item->product->price)
            );

            // ── Create order ──────────────────────────────────────────────────
            $order = Order::create([
                'user_id'          => $user->id,
                'status'           => OrderStatus::Pending,
                'total_amount'     => $total,
                'shipping_address' => $shippingSnapshot, // encrypted:array cast handles encryption
                'payment_status'   => \App\Enums\PaymentStatus::Unpaid,
                'note'             => $data['note'] ?? null,
            ]);

            // ── Create order items (price snapshot) ───────────────────────────
            foreach ($cart->items as $item) {
                $order->items()->create([
                    'product_id'   => $item->product_id,
                    'product_name' => $item->product->name,
                    'product_sku'  => $item->product->sku,
                    'quantity'     => $item->quantity,
                    'unit_price'   => $item->product->sale_price ?? $item->product->price,
                ]);

                // ── Deduct stock ──────────────────────────────────────────────
                $item->product->decrement('stock_quantity', $item->quantity);
            }

            // ── Clear cart ────────────────────────────────────────────────────
            $cart->items()->delete();
            $cart->touch();

            // ── Dispatch confirmation email ───────────────────────────────────
            dispatch(new SendOrderConfirmationEmail($order->load('items')))->onQueue('orders');

            return $order->load('items');
        });
    }

    /**
     * Cancel a pending order.
     * Only allowed when status = pending.
     * Restores stock for each item.
     */
    public function cancel(Order $order): Order
    {
        if ($order->status !== OrderStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => ['Only pending orders can be cancelled.'],
            ]);
        }

        DB::transaction(function () use ($order) {
            // Restore stock
            foreach ($order->items()->with('product')->get() as $item) {
                if ($item->product) {
                    $item->product->increment('stock_quantity', $item->quantity);
                }
            }

            $order->update(['status' => OrderStatus::Cancelled]);
        });

        return $order->fresh();
    }
}
