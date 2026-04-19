<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\Order\SendOrderConfirmationEmail;
use App\Models\Order;
use App\Models\User;
use App\Repositories\Eloquent\CartRepository;
use App\Repositories\Eloquent\OrderRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly CartRepository  $cartRepository,
    ) {}

    // ── Place order ───────────────────────────────────────────────────────────

    public function placeOrder(User $user, array $data): Order
    {
        $cart = $this->orderRepository->getCartWithItems($user);

        if (! $cart || $cart->items->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => ['Your cart is empty.'],
            ]);
        }

        $address = $user->addresses()->findOrFail($data['address_id']);

        return DB::transaction(function () use ($user, $cart, $address, $data) {
            // ── Stock check ───────────────────────────────────────────────────
            foreach ($cart->items as $item) {
                if ($item->product->stock_quantity < $item->quantity) {
                    throw ValidationException::withMessages([
                        'cart' => ["\"{$item->product->name}\" only has {$item->product->stock_quantity} unit(s) in stock."],
                    ]);
                }
            }

            // ── Shipping address snapshot ─────────────────────────────────────
            $shippingSnapshot = [
                'full_name'    => $address->full_name,
                'phone'        => $address->phone,
                'address_line' => $address->address_line,
                'city'         => $address->city,
                'district'     => $address->district,
                'ward'         => $address->ward,
            ];

            // ── Total ─────────────────────────────────────────────────────────
            $total = $cart->items->sum(
                fn ($item) => $item->quantity * (float) ($item->product->sale_price ?? $item->product->price)
            );

            // ── Create order + items + deduct stock ───────────────────────────
            $order = $this->orderRepository->createOrder([
                'user_id'          => $user->id,
                'status'           => OrderStatus::Pending,
                'total_amount'     => $total,
                'shipping_address' => $shippingSnapshot,
                'payment_status'   => PaymentStatus::Unpaid,
                'note'             => $data['note'] ?? null,
            ]);

            $this->orderRepository->createOrderItems($order, $cart);

            // ── Clear cart ────────────────────────────────────────────────────
            $this->cartRepository->clearItems($cart);
            $cart->touch();

            // ── Dispatch confirmation email ───────────────────────────────────
            dispatch(new SendOrderConfirmationEmail($order->load('items')))->onQueue('orders');

            return $order->load('items');
        });
    }

    // ── Cancel ────────────────────────────────────────────────────────────────

    public function cancel(Order $order): Order
    {
        if ($order->status !== OrderStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => ['Only pending orders can be cancelled.'],
            ]);
        }

        DB::transaction(function () use ($order) {
            $this->orderRepository->restoreStock($order);
            $order->update(['status' => OrderStatus::Cancelled]);
        });

        return $order->fresh();
    }

    // ── List / Detail ─────────────────────────────────────────────────────────

    public function listForUser(User $user, ?string $status, int $perPage = 15): LengthAwarePaginator
    {
        return $this->orderRepository->paginateForUser($user, $status, $perPage);
    }

    public function getForUser(User $user, string $orderId): Order
    {
        $order = $this->orderRepository->findForUser($user, $orderId);

        abort_if(! $order, 404, 'Order not found.');

        return $order;
    }
}
