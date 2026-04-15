# Production Deployment Notes

> Ghi lại các vấn đề phát sinh trong dev và cần chú ý khi deploy lên production.
> Cập nhật file này mỗi khi phát hiện thêm vấn đề mới.

---

## 1. Meilisearch — Scout phải chạy qua queue

**Vấn đề:** Mặc định `SCOUT_QUEUE=false`, Scout sync trực tiếp trong request.
Nếu Meilisearch chậm hoặc down → request bị block, user thấy lỗi.

**Fix đã áp dụng** trong `config/scout.php`:
```php
'queue' => [
    'connection' => env('SCOUT_QUEUE_CONNECTION', 'redis'),
    'queue'      => env('SCOUT_QUEUE_NAME', 'seo'),
],
```

**`.env` production phải có:**
```bash
SCOUT_DRIVER=meilisearch
SCOUT_QUEUE_CONNECTION=redis
SCOUT_QUEUE_NAME=seo
MEILISEARCH_HOST=http://<host>:7700
MEILISEARCH_KEY=<master_key>
```

**Chạy worker:**
```bash
php artisan horizon
```

---

## 2. Redis — PHP extension phải được cài

**Vấn đề:** `REDIS_CLIENT=phpredis` yêu cầu PHP extension `redis` (phpredis).
Nếu thiếu extension → `Class "Redis" not found` khi có bất kỳ thao tác nào dùng Redis (queue, cache, session).

**Kiểm tra trước khi deploy:**
```bash
php -m | grep redis
```

Phải thấy `redis` trong output. Nếu không:
```bash
# Ubuntu/Debian
sudo apt install php8.3-redis

# hoặc dùng PECL
pecl install redis
```

Hoặc đổi client sang `predis` (pure PHP, không cần extension):
```bash
composer require predis/predis
```
```bash
REDIS_CLIENT=predis
```

---

## 3. Queue & Cache — không dùng `sync` / `file` trên production

**Vấn đề hiện tại trong `.env` local:**
```bash
CACHE_STORE=file       # chậm, không scale
QUEUE_CONNECTION=sync  # chạy đồng bộ trong request, block user
SESSION_DRIVER=file    # không hoạt động trên multi-server
```

**`.env` production phải đổi sang:**
```bash
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

---

## 4. APP_DEBUG phải tắt

**Vấn đề:** `APP_DEBUG=true` trả về full stack trace cho user, lộ cấu trúc code và credentials.

```bash
APP_DEBUG=false
APP_ENV=production
LOG_LEVEL=error
```

---

## 5. Storage link phải chạy sau deploy

Ảnh sản phẩm upload lên `storage/app/public/`, được serve qua `public/storage/`.
Symlink này không tự tạo — phải chạy:

**Linux/macOS (production):**
```bash
php artisan storage:link
```

**Windows (local dev) — `php artisan storage:link` tạo object hỏng, dùng PowerShell:**
```powershell
Remove-Item -Force -Path 'public/storage'
New-Item -ItemType Junction -Path 'public/storage' -Target (Resolve-Path 'storage/app/public')
```

Nếu không chạy → ảnh upload thành công nhưng URL trả về 404.

---

## 6. Sau deploy — checklist artisan

```bash
php artisan config:cache      # cache config
php artisan route:cache       # cache routes
php artisan view:cache        # cache Blade views
php artisan storage:link      # symlink storage
php artisan migrate --force   # chạy migration
php artisan db:seed --class=JsonldTemplateSeeder  # nếu cần seed
```

---

## 7. Horizon — phải chạy như daemon

```bash
# Supervisor config (ví dụ)
[program:horizon]
command=php /var/www/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/storage/logs/horizon.log
```

Các queue cần worker: `default`, `seo`, `orders`, `notifications`.

---

## 8. Sanctum — cấu hình domain

**Vấn đề:** `SANCTUM_STATEFUL_DOMAINS=localhost:3000` chỉ cho phép localhost.

**Production:**
```bash
SANCTUM_STATEFUL_DOMAINS=yourdomain.com,www.yourdomain.com
SESSION_DOMAIN=.yourdomain.com
```

---

## 9. Google OAuth — redirect URI

**Vấn đề:** `GOOGLE_REDIRECT_URI` đang trỏ về localhost.

**Production:**
```bash
GOOGLE_REDIRECT_URI=https://yourdomain.com/api/v1/auth/google/callback
```

Đồng thời cập nhật URI này trong Google Cloud Console → OAuth 2.0 Credentials.

---

## 10. Filesystem — dùng S3 cho ảnh/video

**Hiện tại:** upload vào `public` disk (local storage).
**Production nên dùng S3** để không mất file khi redeploy:

```bash
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=
```

Nếu giữ local disk thì đảm bảo `storage/app/public/` được mount vào persistent volume (quan trọng nếu dùng Docker/container).
