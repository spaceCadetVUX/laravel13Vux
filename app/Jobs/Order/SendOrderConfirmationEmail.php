<?php

namespace App\Jobs\Order;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOrderConfirmationEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function handle(): void
    {
        // TODO: send order confirmation email via Mailable
        // Mail::to($this->order->user)->send(new OrderConfirmationMail($this->order));
    }
}
