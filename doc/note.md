# php artisan migrate:fresh --seed


  S48 — Cart (Giỏ hàng)

  Cho phép người dùng thêm/sửa/xoá sản phẩm vào giỏ hàng.

  GET    /api/v1/cart                    ← xem giỏ hàng
  POST   /api/v1/cart/items              ← thêm sản phẩm
  PUT    /api/v1/cart/items/{id}         ← đổi số lượng
  DELETE /api/v1/cart/items/{id}         ← xoá sản phẩm
  DELETE /api/v1/cart                    ← xoá toàn bộ giỏ
  POST   /api/v1/cart/merge              ← gộp giỏ guest vào tài khoản sau khi login

  Hai loại giỏ hàng:
  - Guest (chưa đăng nhập) → dùng header X-Session-ID: <uuid> — frontend tự tạo UUID và lưu vào localStorage
  - Auth (đã đăng nhập) → dùng Bearer token như bình thường

  Tự động hết hạn:
  - Guest cart: 7 ngày
  - Auth cart: 30 ngày
  - Mỗi lần dùng → tự gia hạn thêm



  # Dev Notes

---

## S49 — Order API

### Tổng quan

| Endpoint | Auth | Làm gì |
|---|---|---|
| `POST /api/v1/orders` | Bearer token | Đặt hàng từ giỏ hàng hiện tại |
| `GET /api/v1/orders` | Bearer token | Lịch sử đơn hàng (paginated) |
| `GET /api/v1/orders/{id}` | Bearer token | Chi tiết 1 đơn (403 nếu không phải chủ đơn) |
| `PATCH /api/v1/orders/{id}/cancel` | Bearer token | Huỷ đơn đang pending, hoàn tồn kho |

### Flow đặt hàng (`POST /orders`)

```
Giỏ hàng không rỗng?
  → Kiểm tra tồn kho từng item
  → Xác nhận address_id thuộc về user hiện tại
  → Snapshot địa chỉ (decrypt → plain array → encrypt lại qua cast)
  → DB::transaction:
      - Tạo Order (status=pending, payment_status=unpaid)
      - Tạo OrderItems (snapshot giá tại thời điểm mua)
      - Trừ stock_quantity từng sản phẩm
      - Xoá giỏ hàng (cart->items()->delete())
      - Dispatch SendOrderConfirmationEmail (queue: orders)
```

### Các file liên quan

```
app/Services/Order/OrderService.php          ← logic chính
app/Http/Controllers/Api/V1/Order/OrderController.php
app/Http/Requests/Order/PlaceOrderRequest.php
app/Http/Resources/Api/Order/OrderResource.php
app/Http/Resources/Api/Order/OrderItemResource.php
app/Http/Resources/Api/Order/OrderCollection.php
app/Policies/OrderPolicy.php                 ← chặn user xem đơn người khác
app/Jobs/Order/SendOrderConfirmationEmail.php
```

### Bugs đã fix trong S49

| Bug | Nguyên nhân | Fix |
|---|---|---|
| `invalid input syntax for type json` khi POST /orders | `orders.shipping_address` là cột `jsonb` nhưng cast `encrypted:array` lưu chuỗi base64 — PostgreSQL reject | Migration đổi cột sang `text` (`2026_04_17_180000_change_shipping_address_to_text_in_orders_table.php`) |
| Cart route trả 400 "X-Session-ID required" dù gửi Bearer token | Cart routes không có middleware `auth:sanctum` nên `$request->user()` luôn null | Dùng `auth('sanctum')->user()` trong `CartService::resolveCart()` thay vì `$request->user()` |
| `Call to undefined method authorize()` trong OrderController | Base `Controller` của Laravel 11 không có trait `AuthorizesRequests` | Thêm `use AuthorizesRequests` vào `app/Http/Controllers/Controller.php` |

---

### Test case thủ công (curl)

#### 1. Đăng ký / Đăng nhập

```bash
BASE="http://localhost:8000/api/v1"

# Đăng ký
curl -s -X POST "$BASE/auth/register" \
  -H "Content-Type: application/json" \
  -d '{"name":"Test User","email":"test@example.com","password":"password","password_confirmation":"password"}'

# Đăng nhập → lấy token
TOKEN=$(curl -s -X POST "$BASE/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}' \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['token'])")
```

#### 2. Tạo địa chỉ giao hàng (qua tinker — chưa có API)

```bash
php artisan tinker --no-interaction <<'PHP'
$emailHash = hash('sha256', strtolower('test@example.com'));
$user = App\Models\User::where('email_hash', $emailHash)->first();
$addr = $user->addresses()->create([
    'full_name'    => 'Test User',
    'phone'        => '0912345678',
    'address_line' => '123 Test Street',
    'city'         => 'Ho Chi Minh',
    'district'     => 'District 1',
    'ward'         => 'Ward 1',
    'is_default'   => true,
]);
echo $addr->id;
PHP
```

#### 3. Thêm sản phẩm vào giỏ

```bash
PRODUCT_ID="<uuid của product có stock > 0>"

curl -s -X POST "$BASE/cart/items" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"product_id\":\"$PRODUCT_ID\",\"quantity\":2}"
```

#### 4. Đặt hàng

```bash
ADDR_ID="<uuid address vừa tạo>"

curl -s -X POST "$BASE/orders" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"address_id\":\"$ADDR_ID\",\"note\":\"Please leave at door\"}"

# Expected: 201, status=pending, items có snapshot giá, cart bị xoá
```

#### 5. Xem lịch sử đơn hàng

```bash
curl -s "$BASE/orders" \
  -H "Authorization: Bearer $TOKEN"

# Expected: paginated list
```

#### 6. Xem chi tiết đơn

```bash
ORDER_ID="<uuid đơn vừa tạo>"

curl -s "$BASE/orders/$ORDER_ID" \
  -H "Authorization: Bearer $TOKEN"

# Expected: 200 với full order detail
```

#### 7. Kiểm tra 403 (user khác xem đơn)

```bash
# Đăng ký user khác
OTHER_TOKEN=$(curl -s -X POST "$BASE/auth/register" \
  -H "Content-Type: application/json" \
  -d '{"name":"Other","email":"other@example.com","password":"password","password_confirmation":"password"}' \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['token'])")

curl -s "$BASE/orders/$ORDER_ID" \
  -H "Authorization: Bearer $OTHER_TOKEN"

# Expected: 403 "This action is unauthorized."
```

#### 8. Huỷ đơn

```bash
curl -s -X PATCH "$BASE/orders/$ORDER_ID/cancel" \
  -H "Authorization: Bearer $TOKEN"

# Expected: status=cancelled, tồn kho được hoàn lại
```

#### 9. Xác nhận tồn kho đã hoàn (tinker)

```bash
php artisan tinker --no-interaction <<'PHP'
$p = App\Models\Product::find('<product_id>');
echo $p->stock_quantity; // phải bằng giá trị trước khi đặt hàng
PHP
```

---

## S50 — Address API

### Tổng quan

| Endpoint | Auth | Làm gì |
|---|---|---|
| `GET /api/v1/addresses` | Bearer token | Danh sách địa chỉ của user (default lên đầu) |
| `POST /api/v1/addresses` | Bearer token | Tạo địa chỉ mới |
| `PUT /api/v1/addresses/{id}` | Bearer token | Cập nhật địa chỉ |
| `DELETE /api/v1/addresses/{id}` | Bearer token | Xoá địa chỉ (403 nếu không phải chủ) |
| `PATCH /api/v1/addresses/{id}/default` | Bearer token | Đặt làm địa chỉ mặc định, clear các địa chỉ còn lại |

### Các file liên quan

```
app/Services/Address/AddressService.php
app/Http/Controllers/Api/V1/Address/AddressController.php
app/Http/Requests/Address/StoreAddressRequest.php
app/Http/Requests/Address/UpdateAddressRequest.php
app/Http/Resources/Api/Address/AddressResource.php
app/Policies/AddressPolicy.php                 ← policy: modify (view/update/delete)
```

### Test case thủ công (curl)

```bash
BASE="http://localhost:8000/api/v1"

TOKEN=$(curl -s -X POST "$BASE/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}' \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['token'])")
```

#### 1. Tạo địa chỉ

```bash
curl -s -X POST "$BASE/addresses" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "label": "home",
    "full_name": "Nguyen Van A",
    "phone": "0912345678",
    "address_line": "123 Le Loi",
    "city": "Ho Chi Minh",
    "district": "District 1",
    "ward": "Ben Nghe",
    "is_default": true
  }'

# Expected: 201, is_default=true
```

#### 2. Tạo địa chỉ thứ hai (office)

```bash
ADDR2=$(curl -s -X POST "$BASE/addresses" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "label": "office",
    "full_name": "Nguyen Van A",
    "phone": "0987654321",
    "address_line": "456 Nguyen Hue",
    "city": "Ho Chi Minh",
    "district": "District 1",
    "ward": "Ben Thanh",
    "is_default": false
  }')

ADDR2_ID=$(echo "$ADDR2" | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['id'])")
```

#### 3. Đặt địa chỉ 2 làm mặc định

```bash
curl -s -X PATCH "$BASE/addresses/$ADDR2_ID/default" \
  -H "Authorization: Bearer $TOKEN"

# Expected: is_default=true cho addr2, addr1 bị clear về false
```

#### 4. Xem danh sách — xác nhận chỉ 1 cái is_default

```bash
curl -s "$BASE/addresses" -H "Authorization: Bearer $TOKEN"

# Expected: addr2 lên đầu (is_default=true), addr1 is_default=false
```

#### 5. Cập nhật địa chỉ

```bash
curl -s -X PUT "$BASE/addresses/$ADDR2_ID" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"full_name": "Nguyen Van B"}'

# Expected: full_name đã đổi
```

#### 6. Xoá địa chỉ

```bash
ADDR1_ID="<id của địa chỉ đầu tiên>"

curl -s -X DELETE "$BASE/addresses/$ADDR1_ID" \
  -H "Authorization: Bearer $TOKEN"

# Expected: message "Address deleted"
```

#### 7. Kiểm tra 403 (user khác xoá địa chỉ)

```bash
OTHER_TOKEN=$(curl -s -X POST "$BASE/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"other@example.com","password":"password"}' \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['token'])")

curl -s -X DELETE "$BASE/addresses/$ADDR2_ID" \
  -H "Authorization: Bearer $OTHER_TOKEN"

# Expected: 403 "This action is unauthorized."
```

---

## Ghi chú kiến trúc

- `shipping_address` được lưu dạng `encrypted:array` → cột phải là `text`, không phải `jsonb`
- Cart routes không dùng middleware `auth:sanctum` (để guest dùng được) → phải dùng `auth('sanctum')->user()` để detect auth user trong CartService
- Policy auto-discovery hoạt động từ Laravel 11, không cần đăng ký thủ công trong AuthServiceProvider
