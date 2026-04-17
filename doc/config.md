# Config & Infrastructure Notes

---

## S53 — Laravel Horizon (Queue Monitor)

### Đã làm

| Bước | File / Lệnh |
|---|---|
| Cài package | `composer require laravel/horizon --ignore-platform-reqs` |
| Publish assets | `php artisan horizon:install` |
| Cấu hình workers | `config/horizon.php` |
| Restrict dashboard | `app/Providers/HorizonServiceProvider.php` |

> **Tại sao `--ignore-platform-reqs`?**
> `ext-pcntl` và `ext-posix` là Linux-only — không có trên Windows.
> Package vẫn install bình thường, Horizon thực sự chạy trong Docker (Linux).

---

### Queue workers — `config/horizon.php`

#### Local (development)

1 supervisor xử lý tất cả 4 queues:

```php
'local' => [
    'supervisor-1' => [
        'connection'   => 'redis',
        'queue'        => ['default', 'orders', 'seo', 'notifications'],
        'balance'      => 'auto',
        'minProcesses' => 1,
        'maxProcesses' => 4,
        'tries'        => 3,
    ],
],
```

#### Production

3 supervisors riêng biệt để cô lập từng loại queue:

```php
'production' => [
    'supervisor-1' => [          // general
        'queue'        => ['default'],
        'minProcesses' => 1,
        'maxProcesses' => 3,
        'tries'        => 3,
    ],
    'supervisor-2' => [          // order emails + stock
        'queue'        => ['orders'],
        'minProcesses' => 1,
        'maxProcesses' => 5,
        'tries'        => 3,
    ],
    'supervisor-3' => [          // JSON-LD, sitemap, llms
        'queue'        => ['seo'],
        'minProcesses' => 1,
        'maxProcesses' => 3,
        'tries'        => 3,
    ],
],
```

---

### Dashboard access — `HorizonServiceProvider`

```php
Gate::define('viewHorizon', function ($user) {
    return $user->role === UserRole::Admin;
});
```

- **Local env**: Horizon tự bypass gate → tất cả ai cũng vào được (mặc định của package)
- **Production / staging**: chỉ user có `role = admin` mới vào được

---

### Cách test ngày mai

#### 1. Khởi động Horizon trong Docker

```bash
docker compose restart horizon

# Hoặc nếu chưa có service horizon trong docker-compose:
php artisan horizon   # chạy thẳng trong terminal (local)
```

#### 2. Truy cập dashboard

```
http://localhost:8000/horizon
```

Phải thấy dashboard với 4 queues: `default`, `orders`, `seo`, `notifications`

#### 3. Dispatch test job thủ công (tinker)

```bash
php artisan tinker --no-interaction <<'PHP'
# Dispatch job đơn giản để kiểm tra queue chạy được
dispatch(function () {
    \Illuminate\Support\Facades\Log::info('Horizon test job ran successfully');
})->onQueue('default');

echo "Job dispatched to default queue\n";
PHP
```

#### 4. Kiểm tra job xuất hiện và complete

- Vào `/horizon` → tab **Pending** → thấy job mới
- Sau khi Horizon xử lý → tab **Completed** → job chuyển sang đây
- Kiểm tra log: `storage/logs/laravel.log` → phải có dòng "Horizon test job ran successfully"

#### 5. Test queue `orders` (dispatch SendOrderConfirmationEmail)

```bash
php artisan tinker --no-interaction <<'PHP'
$order = App\Models\Order::latest()->first();
if ($order) {
    dispatch(new App\Jobs\Order\SendOrderConfirmationEmail($order));
    echo "Dispatched to orders queue: " . $order->id . "\n";
} else {
    echo "No orders found — place an order first\n";
}
PHP
```

Vào `/horizon` → tab **Recent** → filter queue `orders` → thấy job

#### 6. Kiểm tra gate hoạt động (non-local)

```bash
# Đổi APP_ENV=staging tạm thời để test gate
# Sau đó thử truy cập /horizon bằng tài khoản customer → phải bị 403
# Truy cập bằng tài khoản admin → vào được
```

---

### Queue names — quy ước

| Queue | Dùng cho |
|---|---|
| `default` | Fallback chung |
| `orders` | Order emails, cập nhật tồn kho |
| `seo` | JSON-LD sync, sitemap sync, llms sync |
| `notifications` | Push, SMS (tương lai) |

---

### Lệnh Horizon thường dùng

```bash
php artisan horizon              # chạy Horizon (giữ terminal mở)
php artisan horizon:status       # xem trạng thái
php artisan horizon:pause        # tạm dừng xử lý
php artisan horizon:continue     # tiếp tục sau khi pause
php artisan horizon:terminate    # dừng gracefully (reload code mới)
php artisan horizon:snapshot     # chụp metrics (chạy qua scheduler mỗi 5 phút)
php artisan queue:failed         # xem failed jobs
php artisan queue:retry all      # retry tất cả failed jobs
```
