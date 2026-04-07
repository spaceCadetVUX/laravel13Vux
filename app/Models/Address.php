<?php

namespace App\Models;

use App\Enums\AddressLabel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class Address extends Model
{
    use HasFactory;
    use HasUuids;

    // ── PK config (explicit — HasUuids handles this, stated for clarity) ──────

    protected $keyType    = 'string';
    public    $incrementing = false;

    // ── Mass assignment ───────────────────────────────────────────────────────

    protected $fillable = [
        'user_id',
        'label',
        'full_name',
        'phone',
        'address_line',
        'city',
        'district',
        'ward',
        'is_default',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'label'      => AddressLabel::class,
            'is_default' => 'boolean',
        ];
    }

    // ── Encrypted attributes ──────────────────────────────────────────────────

    protected function phone(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => Crypt::decryptString($value),
            set: fn (string $value) => Crypt::encryptString($value),
        );
    }

    protected function addressLine(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => Crypt::decryptString($value),
            set: fn (string $value) => Crypt::encryptString($value),
        );
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
