<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Only the order owner can view or modify the order.
     */
    public function view(User $user, Order $order): bool
    {
        return (string) $order->user_id === (string) $user->id;
    }

    public function cancel(User $user, Order $order): bool
    {
        return (string) $order->user_id === (string) $user->id;
    }
}
