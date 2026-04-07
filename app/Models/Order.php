<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    // ── PK config ─────────────────────────────────────────────────────────────

    protected $keyType    = 'string';
    public    $incrementing = false;

    // ── Mass assignment ───────────────────────────────────────────────────────

    protected $fillable = [
        'user_id',
        'status',
        'total_amount',
        'shipping_address',
        'payment_method',
        'payment_status',
        'note',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'status'           => OrderStatus::class,
            'payment_status'   => PaymentStatus::class,
            // Encrypted JSONB snapshot — decrypts to PHP array automatically
            'shipping_address' => 'encrypted:array',
            'total_amount'     => 'decimal:2',
            'deleted_at'       => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    /** Nullable — order is preserved if user account is deleted (SET NULL). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
