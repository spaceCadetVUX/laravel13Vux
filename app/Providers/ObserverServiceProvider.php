<?php

namespace App\Providers;

use App\Models\BlogPost;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seo\Redirect;
use App\Observers\BlogPostObserver;
use App\Observers\CartObserver;
use App\Observers\CategoryObserver;
use App\Observers\ProductObserver;
use App\Observers\RedirectObserver;
use Illuminate\Support\ServiceProvider;

/**
 * Centralised observer registration.
 * Registered in bootstrap/providers.php alongside AppServiceProvider.
 */
class ObserverServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Product::observe(ProductObserver::class);
        Category::observe(CategoryObserver::class);
        BlogPost::observe(BlogPostObserver::class);
        Redirect::observe(RedirectObserver::class);
        Cart::observe(CartObserver::class);
    }
}
