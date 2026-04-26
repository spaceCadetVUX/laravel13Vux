# Laravel Horizon — Deploy Guide
**Last Updated:** 2026-04-26

---

## Tại sao cần Horizon

Project này dùng queue `seo` cho toàn bộ SEO pipeline:

| Job | Trigger | Queue |
|---|---|---|
| `SyncJsonldSchema` | Product / Category / BlogPost / BlogCategory saved | `seo` |
| `SyncSitemapEntry` | Product / Category / BlogPost / BlogCategory saved | `seo` |
| `SyncLlmsEntry` | Product / Category / BlogPost / BlogCategory saved | `seo` |
| `GenerateBusinessDocument` | BusinessProfile saved | `seo` |

Không có Horizon → jobs nằm chờ trong queue, sitemap / JSON-LD / LLMs **không bao giờ tự cập nhật**.

---

## Có Horizon vs Không có Horizon

| | Có Horizon | Không có Horizon |
|---|---|---|
| Sitemap tự cập nhật | ✅ Sau mỗi lần admin save | ❌ Phải chạy thủ công |
| JSON-LD tự cập nhật | ✅ | ❌ |
| LLMs tự cập nhật | ✅ | ❌ |
| HTTP response bị block | ❌ Không — jobs chạy nền | ❌ Không — nhưng không chạy gì cả |
| Retry khi job fail | ✅ Tự động (tries = 3) | ❌ |
| Cần Redis | ✅ Bắt buộc | ❌ |
| Cần Supervisor | ✅ Bắt buộc | ❌ |
| RAM thêm | ~50–100MB | 0 |

**Kết luận:** Horizon là bắt buộc trên production.

---

## Cài đặt Supervisor trên VPS

### 1. Cài Supervisor

```bash
sudo apt-get install supervisor
```

### 2. Tạo config file

```bash
sudo nano /etc/supervisor/conf.d/horizon.conf
```

Nội dung:

```ini
[program:horizon]
process_name=%(program_name)s
command=php /var/www/backbone/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/backbone/storage/logs/horizon.log
stopwaitsecs=3600
```

> **Lưu ý:** Đổi `/var/www/backbone` thành đường dẫn thực tế trên VPS.
> `stopwaitsecs=3600` — cho phép Horizon finish hết jobs đang xử lý trước khi Supervisor kill process (quan trọng khi deploy).

### 3. Khởi động

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon
```

### 4. Kiểm tra trạng thái

```bash
sudo supervisorctl status horizon
```

---

## Workflow deploy mới

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan horizon:terminate   # Horizon tự finish jobs rồi tắt
                                # Supervisor tự restart với code mới
```

> **Không dùng `php artisan horizon:terminate` khi Supervisor chưa setup** — sẽ tắt Horizon mà không có gì restart lại.

---

## Chạy thủ công khi cần (fallback)

Nếu vì lý do nào đó Horizon không chạy được, có thể regenerate thủ công:

```bash
php artisan sitemap:generate    # Regenerate tất cả sitemap XML
php artisan llms:generate       # Regenerate tất cả LLMs .txt
php artisan jsonld:sync         # Sync lại tất cả JSON-LD schemas
```

---

## Dashboard

Sau khi Horizon chạy, truy cập:

```
http://your-domain.com/horizon
```

Chỉ admin mới được vào — cấu hình gate tại `app/Providers/HorizonServiceProvider.php`.

---

## Queue names trong project

| Queue | Dùng cho |
|---|---|
| `seo` | JSON-LD sync, sitemap sync, LLMs sync |
| `orders` | Order emails, stock updates |
| `default` | General fallback |
| `notifications` | Push, SMS (future) |
