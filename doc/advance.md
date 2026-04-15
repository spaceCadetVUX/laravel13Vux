# Advance Features — Backlog

Các tính năng phức tạp chưa build, cần thời gian riêng.

---

## 1. Product Variant System

### Tại sao cần

Hiện tại ảnh sản phẩm được tag với nhiều attributes riêng lẻ (Đỏ, Size M).
Cách này gây xung đột khi filter — ảnh tag `[Đỏ, Size M]` sẽ hiện khi user chọn Đỏ
lẫn khi chọn Size M, không phân biệt được tổ hợp chính xác.

Variant system đúng sẽ gắn ảnh với đúng **1 tổ hợp** `{Màu: Đỏ, Size: M}`.

### Schema đề xuất

```
product_attributes          product_attribute_values
──────────────────          ────────────────────────
id (bigint)                 id (bigint)
product_id (uuid, FK)       attribute_id (bigint, FK)
name (string)               value (string)
sort_order (int)            sort_order (int)

product_variants            variant_attribute_values (pivot)
────────────────            ────────────────────────────────
id (bigint)                 variant_id (bigint, FK)
product_id (uuid, FK)       attribute_value_id (bigint, FK)
sku (string, unique)
price (decimal 12,2)        product_variant_images (pivot)
sale_price (decimal)        ──────────────────────────────
stock_quantity (int)        variant_id (bigint, FK)
is_active (bool)            product_image_id (bigint, FK)
```

### Những việc cần làm

| Việc | Ghi chú |
|---|---|
| 4 migration mới | `product_attributes`, `product_attribute_values`, `product_variants`, `variant_attribute_values` |
| 4 model mới | + relationships vào `Product` |
| Xóa `category_product_image` pivot | Không còn cần tag ảnh theo category |
| Xóa `price` khỏi `product_images` | Giá nằm ở variant, không ở ảnh |
| Filament form — phần khó nhất | Repeater attributes → values → auto-generate variants → điền giá/stock |
| Auto-generate variant combinations | Cartesian product của attribute values |
| Gắn ảnh vào variant | Pivot `product_variant_images` |
| Cập nhật API product detail | Trả về `variants[]` với tổ hợp attributes + giá + stock |
| Cập nhật `toSearchableArray` | Index min/max price thay vì 1 giá |
| Cập nhật `ProductObserver::forceDeleting` | Xóa variants + attribute values |

### UX flow trong Filament

```
Tab General
  └── Attributes (Repeater)
        ├── Attribute name: "Màu"
        │     └── Values: Đỏ | Xanh | Vàng
        └── Attribute name: "Size"
              └── Values: S | M | L | XL

Tab Variants (auto-generated)
  └── Bảng variants (Đỏ-S, Đỏ-M, Đỏ-L, Xanh-S, ...)
        ├── SKU
        ├── Price
        ├── Sale price
        ├── Stock
        └── Image (chọn từ ảnh đã upload)
```

### Độ ưu tiên

Implement sau khi MVP storefront hoàn thiện.
Không nên build song song với frontend — cần API product detail ổn định trước.

---
