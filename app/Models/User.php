<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasUuids;
    use Notifiable;
    use HasRoles;
    use SoftDeletes;

    // ── Mass assignment ───────────────────────────────────────────────────────

    protected $fillable = [
        'name',
        'email',
        'email_hash',
        'phone',
        'password',
        'role',
        'google_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'password'         => 'hashed',
            'email_verified_at' => 'datetime',
            'deleted_at'       => 'datetime',
            'role'             => UserRole::class,
        ];
    }

    // ── Encrypted attributes ──────────────────────────────────────────────────
    // email and phone are stored encrypted at rest (text columns).
    // NOTE: the DB unique constraint on email checks ciphertext, not plaintext.
    // Duplicate detection at query time is handled in AuthService (S20).

    protected function email(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => Crypt::decryptString($value),
            set: function (string $value): array {
                return [
                    'email'      => Crypt::encryptString($value),
                    'email_hash' => hash('sha256', strtolower($value)),
                ];
            },
        );
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Crypt::decryptString($value) : null,
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Blog posts authored by this user (FK: author_id). */
    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'author_id');
    }

    public function blogComments(): HasMany
    {
        return $this->hasMany(BlogComment::class);
    }

    // ── Filament access control ───────────────────────────────────────────────

    /**
     * Restrict Filament admin panel access to users with role=admin.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === UserRole::Admin;
    }
}
