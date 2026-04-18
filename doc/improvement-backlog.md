# Backend Improvement Backlog
> Tạo sau khi hoàn thành 60 sprints — 2026-04-18
> Mọi thứ ở đây là **ngoài scope** của build plan ban đầu.
> Sắp xếp theo mức độ ưu tiên: 🔴 Critical → 🟡 Important → 🟢 Nice-to-have

---

## 🔴 P1 — Bug / Incomplete (phải làm trước khi production)

### 1. Implement `SendOrderConfirmationEmail` job
**File:** `app/Jobs/Order/SendOrderConfirmationEmail.php`
**Vấn đề:** Job được dispatch khi đặt hàng thành công nhưng `handle()` chỉ có comment TODO. Không có email nào được gửi.

**Việc cần làm:**
- [ ] Tạo Mailable: `app/Mail/Order/OrderConfirmationMail.php`
- [ ] Tạo Blade template: `resources/views/emails/order/confirmation.blade.php`
- [ ] Uncomment dòng `Mail::to(...)` trong job
- [ ] Test với `MAIL_MAILER=log` (đã config trong `.env`)

```php
// app/Jobs/Order/SendOrderConfirmationEmail.php
public function handle(): void
{
    Mail::to($this->order->user->email)
        ->send(new OrderConfirmationMail($this->order));
}
```

---

### 2. Thiếu Feature Tests cho Address API
**Vấn đề:** S50 implement xong nhưng không có test file. Đây là endpoint duy nhất không có coverage.

**File cần tạo:** `tests/Feature/Address/AddressTest.php`

**Test cases cần viết:**
- [ ] `test_unauthenticated_user_cannot_access_addresses()` → 401
- [ ] `test_user_can_list_own_addresses()` → 200, paginated
- [ ] `test_user_can_create_address()` → 201
- [ ] `test_create_address_with_is_default_clears_others()` → chỉ 1 địa chỉ is_default=true
- [ ] `test_user_can_update_address()` → 200
- [ ] `test_user_can_delete_address()` → 200
- [ ] `test_user_cannot_modify_other_users_address()` → 403
- [ ] `test_user_can_set_default_address()` → 200, is_default swapped

---

## 🟡 P2 — Security (cần trước khi public)

### 3. Rate limiting cho Auth endpoints
**Vấn đề:** Register/Login không có rate limit riêng — dễ bị brute force.

**Fix trong** `routes/api.php`:
```php
Route::prefix('auth')->middleware('throttle:5,1')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
});
```

- [ ] Thêm throttle middleware cho register + login
- [ ] Thêm test: `test_login_is_rate_limited_after_5_attempts()`

---

### 4. Password Reset flow
**Vấn đề:** Không có "Quên mật khẩu". User mắc kẹt nếu quên password.

**Việc cần làm:**
- [ ] `POST /api/v1/auth/forgot-password` → gửi link reset
- [ ] `POST /api/v1/auth/reset-password` → đổi password bằng token
- [ ] Dùng `Password::broker()` của Laravel (built-in, không cần package)
- [ ] Tạo Mailable: `app/Mail/Auth/ResetPasswordMail.php`
- [ ] Thêm FormRequests: `ForgotPasswordRequest`, `ResetPasswordRequest`

---

### 5. Email Verification
**Vấn đề:** User có thể đăng ký với email giả — không có bước xác thực.

**Việc cần làm:**
- [ ] Implement `MustVerifyEmail` interface trên `User` model
- [ ] `POST /api/v1/auth/email/verify/{id}/{hash}` → verify link
- [ ] `POST /api/v1/auth/email/resend` → gửi lại email xác thực
- [ ] Bảo vệ các route nhạy cảm (orders, addresses) bằng `verified` middleware

---

### 6. Sanctum Token Expiry
**Vấn đề:** Token hiện tại không có expiry — một lần tạo dùng mãi mãi.

**Fix trong** `config/sanctum.php`:

```php
'expiration' => 60 * 24 * 30, // 30 ngày (minutes)
```

- [ ] Set expiration cho token
- [ ] Handle 401 khi token hết hạn ở frontend

---

## 🟡 P3 — Code Quality

### 7. PHPStan Level 5 scan
**Vấn đề:** Chưa chạy static analysis. Có thể có type errors tiềm ẩn.

```bash
./vendor/bin/phpstan analyse --level=5
```

- [ ] Chạy PHPStan level 5
- [ ] Fix tất cả errors trước khi production
- [ ] Tạo `phpstan.neon` baseline nếu có quá nhiều warnings từ third-party

---

### 8. N+1 Query Detection
**Vấn đề:** Chưa check N+1 queries trong các resource collections.

**Việc cần làm:**
- [ ] Enable `DB::enableQueryLog()` và check trong development
- [ ] Review `ProductResource`, `OrderResource`, `BlogPostResource` — đảm bảo eager loading đủ
- [ ] Thêm `preventLazyLoading()` trong `AppServiceProvider` cho môi trường local:
```php
Model::preventLazyLoading(! app()->isProduction());
```

---

### 9. Unit Tests cho Services và Repositories
**Vấn đề:** Hiện tại chỉ có Feature tests (integration level). Services/Repositories thiếu unit test.

**Files nên có unit tests:**
- [ ] `tests/Unit/Services/CartServiceTest.php`
- [ ] `tests/Unit/Services/OrderServiceTest.php`
- [ ] `tests/Unit/Services/RedirectCacheServiceTest.php`
- [ ] `tests/Unit/Services/SitemapServiceTest.php`

---

### 10. Laravel Pint + PHPStan trong CI
**Vấn đề:** Code formatting và static analysis chạy thủ công. Dễ bị quên.

- [ ] Tạo GitHub Actions workflow: `.github/workflows/ci.yml`
- [ ] Bước 1: `./vendor/bin/pint --test`
- [ ] Bước 2: `./vendor/bin/phpstan analyse`
- [ ] Bước 3: `php artisan test`

---

## 🟡 P4 — Missing Features (business logic)

### 11. Product Reviews & Ratings
**Vấn đề:** Không có hệ thống đánh giá sản phẩm — quan trọng với B2C.

**Việc cần làm:**
- [ ] Migration: `product_reviews` table (user_id, product_id, rating 1-5, body, is_approved)
- [ ] `ProductReview` model + factory
- [ ] `GET  /api/v1/products/{slug}/reviews` (public, paginated)
- [ ] `POST /api/v1/products/{slug}/reviews` (auth, 1 review/product/user)
- [ ] Filament resource để moderate
- [ ] Thêm `average_rating` aggregate vào `ProductResource`

---

### 12. Wishlist
**Vấn đề:** User không thể lưu sản phẩm yêu thích.

**Việc cần làm:**
- [ ] Migration: `wishlists` table (user_id, product_id) — unique constraint
- [ ] `GET  /api/v1/wishlist` (auth)
- [ ] `POST /api/v1/wishlist/{product}` (auth, toggle)
- [ ] `DELETE /api/v1/wishlist/{product}` (auth)

---

### 13. Coupon / Discount System
**Vấn đề:** Không có hệ thống mã giảm giá.

**Việc cần làm:**
- [ ] Migration: `coupons` table (code, type: percent/fixed, value, min_order, max_uses, expires_at)
- [ ] `POST /api/v1/cart/coupon` (apply coupon, trả về discount amount)
- [ ] `DELETE /api/v1/cart/coupon` (xoá coupon)
- [ ] Áp dụng discount khi `POST /api/v1/orders`
- [ ] Filament resource để quản lý coupons

---

### 14. Order Status Webhooks / Notifications
**Vấn đề:** Admin thay đổi order status nhưng user không được thông báo.

**Việc cần làm:**
- [ ] Laravel Notifications cho các event: `OrderShipped`, `OrderDelivered`, `OrderCancelled`
- [ ] Channel: `mail` (và sau này: `database`, push notification)
- [ ] Trigger từ Filament OrderResource khi admin cập nhật status

---

### 15. Image Upload cho Products và Blog Posts
**Vấn đề:** Filament có file upload nhưng chưa có API endpoint để frontend upload ảnh.

**Việc cần làm:**
- [ ] `POST /api/v1/media/upload` (auth, admin only) → trả về URL
- [ ] Dùng Spatie Media Library nếu cần (đã có trong tech stack plan)
- [ ] Validate: mime type, max size, virus scan (tùy)

---

## 🟢 P5 — Production Readiness

### 16. Error Monitoring (Sentry)
```bash
composer require sentry/sentry-laravel
```
- [ ] Tích hợp Sentry
- [ ] Config `SENTRY_LARAVEL_DSN` trong `.env`
- [ ] Alert khi có 5xx errors

---

### 17. Response Caching cho public endpoints
**Vấn đề:** `GET /products`, `GET /blog`, `GET /sitemap.xml` được gọi nhiều nhưng chưa cache ở response level.

`spatie/laravel-responsecache` đã có trong tech stack:
- [ ] Enable cho: `GET /api/v1/products`, `GET /api/v1/blog`, `GET /api/v1/categories`
- [ ] TTL: 5 phút
- [ ] Invalidate cache khi admin cập nhật qua Filament

---

### 18. Database Indexes Review
- [ ] Chạy `EXPLAIN ANALYZE` trên các query nặng (product list với filter, order history)
- [ ] Đảm bảo `products.is_active`, `products.category_id`, `blog_posts.status`, `blog_posts.published_at` đều có index

---

### 19. Scribe API Docs Update
**Vấn đề:** Docs cuối cùng được generate ở S55, trước khi có Address tests và SEO routes tests.

```bash
php artisan scribe:generate
```
- [ ] Regenerate docs
- [ ] Đảm bảo tất cả endpoints có `@bodyParam` và `@response` annotations
- [ ] Publish docs lên subdomain: `api-docs.yourdomain.com`

---

### 20. Meilisearch Index Seeding
**Vấn đề:** Products trong DB nhưng Meilisearch index có thể trống nếu chưa sync.

```bash
php artisan scout:import "App\Models\Product"
php artisan scout:import "App\Models\BlogPost"
```
- [ ] Thêm lệnh này vào deploy script
- [ ] Test search trả kết quả đúng trên data thật

---

## Tổng kết

| Priority | # Items | Estimated effort |
|---|---|---|
| 🔴 P1 — Bug/Incomplete | 2 | ~3h |
| 🟡 P2 — Security | 4 | ~1 ngày |
| 🟡 P3 — Code Quality | 4 | ~1 ngày |
| 🟡 P4 — Missing Features | 5 | ~1 tuần |
| 🟢 P5 — Production | 5 | ~1 ngày |

**Thứ tự làm đề xuất:** P1 → P2 → P3 → P5 → P4

> P4 (features) nên làm sau khi frontend sẵn sàng — implement xong mà không có UI để test thì khó verify.
