# Folder Structure
> **Project:** Laravel 13 B2C E-commerce + Blog
> **Version:** 1.0
> **Last Updated:** April 2026

---

## Table of Contents
1. [Root Structure](#1-root-structure)
2. [app/ — Application Core](#2-app--application-core)
3. [config/](#3-config)
4. [database/](#4-database)
5. [routes/](#5-routes)
6. [resources/](#6-resources)
7. [storage/](#7-storage)
8. [tests/](#8-tests)
9. [docker/](#9-docker)
10. [Naming Conventions](#10-naming-conventions)
11. [Key Architecture Rules](#11-key-architecture-rules)

---

## 1. Root Structure

```
/
├── app/
├── bootstrap/
├── config/
├── database/
├── docker/
├── public/
├── resources/
├── routes/
├── storage/
├── tests/
├── .env
├── .env.example
├── .gitignore
├── artisan
├── composer.json
├── composer.lock
├── docker-compose.yml
├── pint.json                        ← Laravel Pint config
├── phpstan.neon                     ← Larastan config
├── ARCHITECTURE.md
├── ERD.md
├── FOLDER_STRUCTURE.md
└── README.md
```

---

## 2. app/ — Application Core

```
app/
├── Console/
│   └── Commands/
│       ├── CartPruneCommand.php           ← php artisan cart:prune
│       ├── SitemapGenerateCommand.php     ← php artisan sitemap:generate
│       ├── LlmsGenerateCommand.php        ← php artisan llms:generate
│       └── JsonldSyncCommand.php          ← php artisan jsonld:sync
│
├── Enums/
│   ├── UserRole.php                       ← admin | customer
│   ├── OrderStatus.php                    ← pending | processing | shipped | delivered | cancelled
│   ├── PaymentStatus.php                  ← unpaid | paid | refunded
│   ├── AddressLabel.php                   ← home | office | other
│   ├── BlogPostStatus.php                 ← draft | published | archived
│   ├── RedirectType.php                   ← 301 | 302
│   ├── OgType.php                         ← website | article | product
│   ├── JsonldSchemaType.php               ← Product | Article | BreadcrumbList | ...
│   └── SitemapChangefreq.php              ← always | hourly | daily | weekly | ...
│
├── Events/
│   ├── Order/
│   │   ├── OrderPlaced.php
│   │   ├── OrderStatusChanged.php
│   │   └── OrderCancelled.php
│   ├── Product/
│   │   └── ProductStockLow.php
│   └── Blog/
│       └── BlogPostPublished.php
│
├── Exceptions/
│   ├── Handler.php
│   ├── Api/
│   │   ├── ResourceNotFoundException.php
│   │   └── ValidationException.php
│   └── Business/
│       ├── InsufficientStockException.php
│       └── CartExpiredException.php
│
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   └── V1/
│   │   │       ├── Auth/
│   │   │       │   ├── AuthController.php         ← register, login, logout, me
│   │   │       │   └── SocialAuthController.php   ← Google OAuth
│   │   │       ├── Product/
│   │   │       │   ├── ProductController.php
│   │   │       │   └── ProductSearchController.php
│   │   │       ├── Category/
│   │   │       │   └── CategoryController.php
│   │   │       ├── Cart/
│   │   │       │   ├── CartController.php
│   │   │       │   └── CartItemController.php
│   │   │       ├── Order/
│   │   │       │   └── OrderController.php
│   │   │       ├── Address/
│   │   │       │   └── AddressController.php
│   │   │       └── Blog/
│   │   │           ├── BlogPostController.php
│   │   │           ├── BlogCategoryController.php
│   │   │           └── BlogCommentController.php
│   │   └── Web/
│   │       ├── SitemapController.php              ← serves sitemap.xml + children
│   │       ├── LlmsController.php                 ← serves /llms.txt /llms-*.txt
│   │       └── HealthController.php               ← /health endpoint
│   │
│   ├── Middleware/
│   │   ├── HandleRedirects.php                    ← resolves redirects table via Redis
│   │   ├── ForceJsonResponse.php                  ← API always returns JSON
│   │   └── SetLocale.php
│   │
│   ├── Requests/
│   │   ├── Auth/
│   │   │   ├── LoginRequest.php
│   │   │   └── RegisterRequest.php
│   │   ├── Product/
│   │   │   ├── StoreProductRequest.php
│   │   │   └── UpdateProductRequest.php
│   │   ├── Category/
│   │   │   ├── StoreCategoryRequest.php
│   │   │   └── UpdateCategoryRequest.php
│   │   ├── Cart/
│   │   │   ├── AddCartItemRequest.php
│   │   │   └── UpdateCartItemRequest.php
│   │   ├── Order/
│   │   │   └── PlaceOrderRequest.php
│   │   ├── Address/
│   │   │   ├── StoreAddressRequest.php
│   │   │   └── UpdateAddressRequest.php
│   │   └── Blog/
│   │       ├── StoreBlogPostRequest.php
│   │       ├── UpdateBlogPostRequest.php
│   │       └── StoreBlogCommentRequest.php
│   │
│   └── Resources/
│       ├── Api/
│       │   ├── UserResource.php
│       │   ├── AddressResource.php
│       │   ├── AddressCollection.php
│       │   ├── Product/
│       │   │   ├── ProductResource.php
│       │   │   ├── ProductCollection.php
│       │   │   └── ProductDetailResource.php      ← includes images, videos, seo
│       │   ├── Category/
│       │   │   ├── CategoryResource.php
│       │   │   └── CategoryTreeResource.php       ← nested children
│       │   ├── Cart/
│       │   │   ├── CartResource.php
│       │   │   └── CartItemResource.php
│       │   ├── Order/
│       │   │   ├── OrderResource.php
│       │   │   ├── OrderCollection.php
│       │   │   └── OrderItemResource.php
│       │   └── Blog/
│       │       ├── BlogPostResource.php
│       │       ├── BlogPostCollection.php
│       │       ├── BlogCategoryResource.php
│       │       └── BlogCommentResource.php
│       └── Traits/
│           └── ApiResponse.php                    ← success/error envelope helper
│
├── Jobs/
│   ├── Order/
│   │   └── SendOrderConfirmationEmail.php
│   ├── Seo/
│   │   ├── SyncJsonldSchema.php                   ← queued after model save
│   │   ├── SyncSitemapEntry.php
│   │   └── SyncLlmsEntry.php
│   └── Cart/
│       └── MergeGuestCartOnLogin.php
│
├── Listeners/
│   ├── Order/
│   │   ├── SendOrderConfirmationListener.php
│   │   └── UpdateStockOnOrderPlaced.php
│   ├── Blog/
│   │   └── TriggerSeoSyncOnPublish.php
│   └── Product/
│       └── TriggerSeoSyncOnSave.php
│
├── Models/
│   ├── User.php
│   ├── Address.php
│   ├── Category.php
│   ├── Product.php
│   ├── ProductImage.php
│   ├── ProductVideo.php
│   ├── Cart.php
│   ├── CartItem.php
│   ├── Order.php
│   ├── OrderItem.php
│   ├── BlogCategory.php
│   ├── BlogPost.php
│   ├── BlogTag.php
│   ├── BlogComment.php
│   ├── Seo/
│   │   ├── SeoMeta.php
│   │   ├── GeoEntityProfile.php
│   │   ├── JsonldTemplate.php
│   │   ├── JsonldSchema.php
│   │   ├── LlmsDocument.php
│   │   ├── LlmsEntry.php
│   │   ├── Redirect.php
│   │   ├── SitemapIndex.php
│   │   └── SitemapEntry.php
│   ├── Media.php
│   └── ActivityLog.php
│
├── Observers/
│   ├── ProductObserver.php                        ← triggers SEO/JSON-LD/sitemap/llms sync
│   ├── BlogPostObserver.php                       ← same as above for blog
│   ├── CategoryObserver.php
│   ├── RedirectObserver.php                       ← increments cache_version, busts Redis
│   └── CartObserver.php                           ← sets/extends expires_at
│
├── Policies/
│   ├── ProductPolicy.php
│   ├── OrderPolicy.php
│   ├── AddressPolicy.php
│   ├── BlogPostPolicy.php
│   └── BlogCommentPolicy.php
│
├── Providers/
│   ├── AppServiceProvider.php                     ← morphMap registration
│   ├── AuthServiceProvider.php                    ← policy bindings
│   ├── EventServiceProvider.php                   ← event → listener map
│   ├── ObserverServiceProvider.php                ← model observer registration
│   └── RouteServiceProvider.php
│
├── Repositories/                                  ← optional DB query layer
│   ├── Contracts/
│   │   ├── ProductRepositoryInterface.php
│   │   ├── OrderRepositoryInterface.php
│   │   └── BlogPostRepositoryInterface.php
│   └── Eloquent/
│       ├── ProductRepository.php
│       ├── OrderRepository.php
│       └── BlogPostRepository.php
│
├── Services/
│   ├── Auth/
│   │   ├── AuthService.php
│   │   └── SocialAuthService.php
│   ├── Cart/
│   │   ├── CartService.php                        ← add, update, remove, merge guest→auth
│   │   └── CartPruneService.php
│   ├── Order/
│   │   └── OrderService.php                       ← place order, stock deduction
│   ├── Product/
│   │   └── ProductService.php
│   ├── Blog/
│   │   └── BlogPostService.php
│   └── Seo/
│       ├── SeoMetaService.php                     ← read/write seo_meta per model
│       ├── JsonldService.php                      ← template resolution + schema upsert
│       ├── GeoProfileService.php                  ← read/write geo_entity_profiles
│       ├── LlmsGeneratorService.php               ← flatten + write llms_entries
│       ├── SitemapService.php                     ← build + write sitemap XML files
│       └── RedirectCacheService.php               ← Redis load/invalidate for redirects
│
└── Traits/
    ├── HasSeoMeta.php                             ← morphOne SeoMeta — use on any model
    ├── HasGeoProfile.php                          ← morphOne GeoEntityProfile
    ├── HasJsonldSchemas.php                       ← morphMany JsonldSchema
    ├── HasSitemapEntry.php                        ← morphOne SitemapEntry
    ├── HasLlmsEntry.php                           ← morphOne LlmsEntry
    ├── HasMedia.php                               ← morphMany Media
    └── HasActivityLog.php                         ← morphMany ActivityLog
```

---

## 3. config/

```
config/
├── app.php
├── auth.php
├── cache.php
├── cors.php                                       ← CORS for API
├── database.php                                   ← PostgreSQL + Redis
├── filesystems.php                                ← local disk config
├── horizon.php                                    ← Laravel Horizon queues
├── logging.php
├── mail.php
├── permission.php                                 ← Spatie permission config
├── queue.php                                      ← Redis driver
├── sanctum.php
├── scout.php                                      ← Meilisearch driver
├── session.php                                    ← Redis driver
└── seo.php                                        ← custom: default meta, OG image, site name
```

---

## 4. database/

```
database/
├── factories/
│   ├── UserFactory.php
│   ├── CategoryFactory.php
│   ├── ProductFactory.php
│   ├── OrderFactory.php
│   ├── BlogPostFactory.php
│   └── Seo/
│       ├── SeoMetaFactory.php
│       └── GeoEntityProfileFactory.php
│
├── migrations/
│   ├── 0001_create_users_table.php
│   ├── 0002_create_password_reset_tokens_table.php
│   ├── 0003_create_personal_access_tokens_table.php
│   ├── 0004_create_permission_tables.php
│   ├── 0005_create_addresses_table.php
│   ├── 0006_create_categories_table.php
│   ├── 0007_create_products_table.php
│   ├── 0008_create_product_images_table.php
│   ├── 0009_create_product_videos_table.php
│   ├── 0010_create_carts_table.php
│   ├── 0011_create_cart_items_table.php
│   ├── 0012_create_orders_table.php
│   ├── 0013_create_order_items_table.php
│   ├── 0014_create_blog_categories_table.php
│   ├── 0015_create_blog_posts_table.php
│   ├── 0016_create_blog_tags_table.php
│   ├── 0017_create_blog_post_tag_table.php
│   ├── 0018_create_blog_comments_table.php
│   ├── 0019_create_seo_meta_table.php
│   ├── 0020_create_geo_entity_profiles_table.php
│   ├── 0021_create_jsonld_templates_table.php
│   ├── 0022_create_jsonld_schemas_table.php
│   ├── 0023_create_llms_documents_table.php
│   ├── 0024_create_llms_entries_table.php
│   ├── 0025_create_redirects_table.php
│   ├── 0026_create_sitemap_indexes_table.php
│   ├── 0027_create_sitemap_entries_table.php
│   ├── 0028_create_media_table.php
│   ├── 0029_create_activity_logs_table.php
│   ├── 0030_create_cache_table.php
│   └── 0031_create_sessions_table.php
│
└── seeders/
    ├── DatabaseSeeder.php
    ├── RoleSeeder.php                             ← seeds admin + customer roles
    ├── AdminUserSeeder.php                        ← seeds first admin account
    ├── CategorySeeder.php
    ├── ProductSeeder.php
    ├── BlogCategorySeeder.php
    ├── JsonldTemplateSeeder.php                   ← seeds base JSON-LD templates
    ├── SitemapIndexSeeder.php                     ← seeds products/blog/category indexes
    └── LlmsDocumentSeeder.php                     ← seeds llms.txt document registry
```

---

## 5. routes/

```
routes/
├── api.php                                        ← /api/v1/* REST endpoints
├── web.php                                        ← sitemap, llms.txt, health
├── channels.php
└── console.php                                    ← artisan schedule definitions
```

### routes/api.php structure
```php
Route::prefix('v1')->group(function () {

    // Public
    Route::get('products', [...]);
    Route::get('products/{slug}', [...]);
    Route::get('categories', [...]);
    Route::get('categories/{slug}', [...]);
    Route::get('search', [...]);
    Route::get('blog', [...]);
    Route::get('blog/{slug}', [...]);
    Route::get('blog/categories', [...]);

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('register', [...]);
        Route::post('login', [...]);
        Route::post('google', [...]);
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [...]);
            Route::get('me', [...]);
        });
    });

    // Customer (auth required)
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('addresses', AddressController::class);
        Route::get('cart', [...]);
        Route::post('cart/items', [...]);
        Route::put('cart/items/{id}', [...]);
        Route::delete('cart/items/{id}', [...]);
        Route::apiResource('orders', OrderController::class)->only(['index','show','store']);
        Route::post('blog/{slug}/comments', [...]);
    });

});
```

### routes/web.php structure
```php
// Sitemap
Route::get('sitemap.xml', [SitemapController::class, 'index']);
Route::get('sitemap-{name}.xml', [SitemapController::class, 'child']);

// LLMs
Route::get('llms.txt', [LlmsController::class, 'index']);
Route::get('llms-full.txt', [LlmsController::class, 'full']);
Route::get('llms-{slug}.txt', [LlmsController::class, 'scoped']);

// Health
Route::get('health', HealthController::class);
```

---

## 6. resources/

```
resources/
├── views/
│   ├── layouts/
│   │   └── app.blade.php                          ← minimal layout (admin fallback, health)
│   └── emails/
│       └── order/
│           └── confirmation.blade.php
└── lang/
    ├── en/
    │   ├── auth.php
    │   ├── validation.php
    │   └── messages.php
    └── vi/
        ├── auth.php
        ├── validation.php
        └── messages.php
```

---

## 7. storage/

```
storage/
├── app/
│   └── public/
│       ├── products/
│       │   └── {year}/{month}/                    ← uploaded product images/videos
│       └── blog/
│           └── {year}/{month}/                    ← uploaded blog featured images
├── framework/
│   ├── cache/
│   ├── sessions/
│   └── views/
└── logs/
    └── laravel.log
```

---

## 8. tests/

```
tests/
├── Feature/
│   ├── Auth/
│   │   ├── LoginTest.php
│   │   ├── RegisterTest.php
│   │   └── GoogleAuthTest.php
│   ├── Product/
│   │   ├── ProductListTest.php
│   │   └── ProductDetailTest.php
│   ├── Cart/
│   │   ├── CartTest.php
│   │   └── CartPruneTest.php
│   ├── Order/
│   │   └── PlaceOrderTest.php
│   ├── Blog/
│   │   └── BlogPostTest.php
│   └── Seo/
│       ├── SitemapTest.php
│       ├── LlmsTest.php
│       ├── JsonldTest.php
│       └── RedirectTest.php
├── Unit/
│   ├── Services/
│   │   ├── CartServiceTest.php
│   │   ├── OrderServiceTest.php
│   │   └── JsonldServiceTest.php
│   └── Observers/
│       ├── ProductObserverTest.php
│       └── RedirectObserverTest.php
└── TestCase.php
```

---

## 9. docker/

```
docker/
├── nginx/
│   ├── nginx.conf
│   └── default.conf                               ← server block, storage symlink, CORS headers
├── php/
│   ├── Dockerfile
│   └── php.ini                                    ← upload_max_filesize=10M, memory_limit=256M
└── scripts/
    ├── entrypoint.sh                              ← migrate, cache, storage:link on boot
    └── worker.sh                                  ← horizon start script
```

### docker-compose.yml services
```yaml
services:
  nginx:          # port 80/443
  php-fpm:        # Laravel app (PHP 8.3)
  postgres:       # port 5432
  redis:          # port 6379
  meilisearch:    # port 7700
  horizon:        # queue worker (shares php image)
  scheduler:      # cron: * * * * * php artisan schedule:run
```

---

## 10. Naming Conventions

| Type | Convention | Example |
|---|---|---|
| Models | PascalCase singular | `BlogPost`, `OrderItem` |
| Controllers | PascalCase + Controller | `ProductController` |
| Services | PascalCase + Service | `CartService`, `JsonldService` |
| Repositories | PascalCase + Repository | `ProductRepository` |
| Observers | PascalCase + Observer | `ProductObserver` |
| Policies | PascalCase + Policy | `OrderPolicy` |
| Jobs | Descriptive verb phrase | `SendOrderConfirmationEmail` |
| Events | Noun + past verb | `OrderPlaced`, `BlogPostPublished` |
| Listeners | Descriptive action | `SendOrderConfirmationListener` |
| Commands | Descriptive + Command | `CartPruneCommand` |
| Requests | Store/Update + Model + Request | `StoreProductRequest` |
| Resources | Model + Resource/Collection | `ProductResource`, `OrderCollection` |
| Traits | Has + Capability | `HasSeoMeta`, `HasMedia` |
| Enums | PascalCase | `OrderStatus`, `UserRole` |
| Migrations | sequential prefix + action | `0007_create_products_table` |
| Routes | kebab-case | `/blog-categories`, `/cart/items` |
| Config keys | snake_case | `seo.default_og_image` |

---

## 11. Key Architecture Rules

### Service Layer owns business logic
Controllers are thin — they validate input via `FormRequest`, call a `Service`, and return a `Resource`. No Eloquent queries in controllers.

```
Request → Controller → Service → Repository/Model → Resource → Response
```

### Traits attach SEO & GEO capabilities to any Model
Every model that needs SEO/GEO just uses the trait:
```php
class Product extends Model {
    use HasSeoMeta, HasGeoProfile, HasJsonldSchemas, HasSitemapEntry, HasLlmsEntry, HasMedia;
}
```
No schema changes needed to add discoverability to a new model.

### Observers trigger SEO pipeline automatically
When a `Product` or `BlogPost` is saved/updated, the Observer dispatches queued jobs:
```
ProductObserver::saved()
  → dispatch SyncJsonldSchema    (queue: seo)
  → dispatch SyncSitemapEntry    (queue: seo)
  → dispatch SyncLlmsEntry       (queue: seo)
```
All on a dedicated `seo` queue — isolated from order processing and email queues.

### morphMap registered in AppServiceProvider
```php
Relation::morphMap([
    'product'       => Product::class,
    'blog_post'     => BlogPost::class,
    'category'      => Category::class,
    'blog_category' => BlogCategory::class,
    'blog_tag'      => BlogTag::class,
]);
```

### API response envelope enforced via Trait
```php
// App\Http\Resources\Traits\ApiResponse.php
return $this->success(data: new ProductResource($product));
return $this->error(message: 'Not found', code: 404);
```

### Queue separation
| Queue | Jobs |
|---|---|
| `default` | General |
| `orders` | Order confirmation emails, stock updates |
| `seo` | JSON-LD sync, sitemap sync, llms sync |
| `notifications` | Future: push, SMS |

---

*This document is the single source of truth for project structure. Update it when adding new modules.*