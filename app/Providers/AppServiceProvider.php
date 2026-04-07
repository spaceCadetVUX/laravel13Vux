<?php

namespace App\Providers;

use App\Auth\EncryptedUserProvider;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerMorphMap();
        $this->registerEncryptedUserProvider();
    }

    // ── Encrypted user provider ───────────────────────────────────────────────
    // Handles email_hash-based lookups for users with encrypted email columns.

    private function registerEncryptedUserProvider(): void
    {
        Auth::provider('encrypted', function ($app, array $config) {
            return new EncryptedUserProvider(
                $app['hash'],
                $config['model'] ?? User::class,
            );
        });
    }

    // ── Polymorphic alias map ─────────────────────────────────────────────────
    // CLAUDE.md rule: always use aliases in morphMap, never full class names.
    // Stored as model_type in all polymorphic tables (varchar(36) model_id).

    private function registerMorphMap(): void
    {
        Relation::morphMap([
            'product'       => Product::class,
            'blog_post'     => BlogPost::class,
            'category'      => Category::class,
            'blog_category' => BlogCategory::class,
            'blog_tag'      => BlogTag::class,
        ]);
    }
}
