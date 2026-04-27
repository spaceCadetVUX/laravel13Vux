<?php

namespace App\Providers;

use App\Auth\EncryptedUserProvider;
use App\Models\Author;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Brand;
use App\Models\BusinessProfile;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\Review;
use App\Models\Seo\Redirect;
use App\Models\User;
use App\Models\BlogCategoryTranslation;
use App\Models\BlogPostTranslation;
use App\Models\CategoryTranslation;
use App\Models\ProductTranslation;
use App\Observers\BlogCategoryObserver;
use App\Observers\BlogCategoryTranslationObserver;
use App\Observers\BlogPostObserver;
use App\Observers\BlogPostTranslationObserver;
use App\Observers\BrandObserver;
use App\Observers\BusinessProfileObserver;
use App\Observers\CategoryObserver;
use App\Observers\CategoryTranslationObserver;
use App\Observers\ManufacturerObserver;
use App\Observers\ProductObserver;
use App\Observers\ProductTranslationObserver;
use App\Observers\RedirectObserver;
use App\Observers\ReviewObserver;
use Illuminate\Database\Eloquent\Model;
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
        // ── N+1 Detection safety net ──────────────────────────────────────────
        // Throws LazyLoadingViolationException for any lazy-loaded relationship
        // in non-production environments. This forces eager loading discipline
        // and makes N+1 problems surface immediately in dev/test.
        Model::preventLazyLoading(! app()->isProduction());

        $this->registerMorphMap();
        $this->registerEncryptedUserProvider();
        $this->registerObservers();
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

    private function registerObservers(): void
    {
        Category::observe(CategoryObserver::class);
        Product::observe(ProductObserver::class);
        Brand::observe(BrandObserver::class);
        Manufacturer::observe(ManufacturerObserver::class);
        Review::observe(ReviewObserver::class);
        BlogPost::observe(BlogPostObserver::class);
        BlogCategory::observe(BlogCategoryObserver::class);
        Redirect::observe(RedirectObserver::class);
        BusinessProfile::observe(BusinessProfileObserver::class);

        ProductTranslation::observe(ProductTranslationObserver::class);
        CategoryTranslation::observe(CategoryTranslationObserver::class);
        BlogPostTranslation::observe(BlogPostTranslationObserver::class);
        BlogCategoryTranslation::observe(BlogCategoryTranslationObserver::class);
    }

    private function registerMorphMap(): void
    {
        Relation::morphMap([
            'product'       => Product::class,
            'blog_post'     => BlogPost::class,
            'category'      => Category::class,
            'blog_category' => BlogCategory::class,
            'blog_tag'      => BlogTag::class,
            'brand'         => Brand::class,
            'manufacturer'  => Manufacturer::class,
            'author'        => Author::class,
        ]);
    }
}
