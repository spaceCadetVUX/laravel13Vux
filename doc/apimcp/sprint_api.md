# MCP API — Sprint Plan (Full Site Automation)

> API dành riêng cho Claude MCP tự động hóa content toàn site.
> Prefix: `/api/v1/mcp/`
> Auth: `Authorization: Bearer {token}` — Personal Access Token, long-lived.
> Observer chain tự xử lý JSON-LD / Sitemap / LLMs sau mỗi save — MCP không cần gọi thêm.

---

## Safety Guardrails — Đọc trước khi implement

### MCP tuyệt đối KHÔNG được phép

| Cấm | Lý do |
|---|---|
| DELETE bất kỳ record nào | Không reversible — MCP chỉ deactivate, không xóa |
| Thay đổi `slug` của entity đang active | Gây broken link nếu redirect chưa được test |
| Batch activate > 10 entity một lúc | Phải review từng cái trước khi public |
| Ghi đè content khi `is_protected = true` | Content do người dùng viết tay — MCP không được đụng |
| Publish blog post trực tiếp | Bắt buộc qua `status: draft` → human review → publish |
| Truy cập bảng users, orders, payments | Ngoài phạm vi content — không expose endpoint |
| Chạy raw SQL hoặc migration | Không có endpoint này |
| Deactivate entity đang active | `PATCH /activate` chỉ nhận `is_active: true`, không nhận `false` |

---

### Token Scopes — 3 cấp độ

```
mcp:read          → GET context, GET audit, GET list — chỉ đọc
mcp:write         → PUT upsert draft, PATCH SEO, batch translate — viết draft
mcp:publish       → PATCH activate/publish — đặc quyền riêng, cấp thủ công
```

**Nguyên tắc vận hành:**
- Claude thường ngày chỉ có `mcp:read + mcp:write` — không thể publish.
- `mcp:publish` chỉ cấp khi Tùng muốn Claude tự publish một batch cụ thể, sau đó thu hồi.
- Token tạo qua Filament admin panel, không qua API.

**Implement:**
```php
// Trong McpTokenPolicy hoặc middleware
$token->can('mcp:publish') // Laravel Sanctum token abilities
```

---

### Content Protection Flag

**Quyết định: thêm vào cả translation tables + seo_meta — bảo vệ description/body lẫn SEO.**

Migration thêm column `is_mcp_protected boolean default false` vào:

| Bảng | Bảo vệ gì |
|---|---|
| `product_translations` | name, description, short_description |
| `category_translations` | name, description |
| `blog_post_translations` | title, excerpt, body |
| `blog_category_translations` | name, description |
| `seo_meta` | meta_title, meta_description, og_title, og_description |

```
is_mcp_protected = true  →  MCP bị block, trả về 403
is_mcp_protected = false →  MCP có thể viết (default)
```

Khi Tùng viết tay content trong Filament → toggle `is_mcp_protected = true` trên row đó → MCP không bao giờ ghi đè.

**Filament:** thêm Toggle `is_mcp_protected` vào mỗi translation tab và SEO tab — nhỏ, ở cuối section.

---

### Batch Operation Limits

| Operation | Max items/request | Lý do |
|---|---|---|
| `POST /batch/seo-meta` | 50 | Đủ để xử lý, không quá rủi ro |
| `POST /batch/translate` | 20 | Translation nặng, cần review kỹ hơn |
| `GET /audit` | 100/page | Pagination bắt buộc |
| `PATCH /activate` | 1 | Từng cái một, không batch activate |

---

### `overwrite_existing` mặc định `false`

Mọi write operation mặc định **không ghi đè** content đã có:

```json
{
  "overwrite_existing": false  // default — bỏ qua field đã có giá trị
}
```

Phải explicit `"overwrite_existing": true` mới ghi đè — tránh Claude lỡ tay xóa content tốt.

---

### Audit log bắt buộc

Mọi write operation ghi vào `activity_log`:

```json
{
  "causer_type": "mcp_token",
  "causer_id": "{token_id}",
  "event": "mcp.product.upserted",
  "subject_type": "product",
  "subject_id": "{product_slug}",
  "properties": {
    "fields_written": ["translations.vi.description", "seo.vi.meta_title"],
    "dry_run": false,
    "idempotency_key": "..."
  }
}
```

Dùng để rollback thủ công nếu MCP ghi sai — xem log → biết field nào bị thay đổi → restore.

---

### Rollback thủ công

Không có auto-rollback endpoint (quá phức tạp và nguy hiểm). Thay vào đó:

1. `GET /api/v1/mcp/audit/log?token_id={id}&from=2026-06-01` → xem MCP đã làm gì
2. Filament admin → vào record → sửa thủ công field bị ghi sai
3. Toggle `is_mcp_protected = true` để ngăn MCP ghi lại

---

## Entity coverage

| Entity | Translations | MCP fills | Observer tự làm |
|---|---|---|---|
| `product` | vi + en | description, SEO, FAQ | JSON-LD, Sitemap, LLMs |
| `category` | vi + en | description, SEO, FAQ | JSON-LD, Sitemap, LLMs |
| `blog_post` | vi + en | title, body, SEO, FAQ | JSON-LD, Sitemap, LLMs |
| `blog_category` | vi + en | description, SEO | JSON-LD, Sitemap, LLMs |
| `brand` | — (no translation) | description, SEO | Sitemap, LLMs |
| `manufacturer` | — (no translation) | description, SEO | Sitemap, LLMs |

---

## Nguyên tắc thiết kế

| Nguyên tắc | Chi tiết |
|---|---|
| **Compound write** | 1 request = 1 entity hoàn chỉnh (content + translations + SEO + FAQ) |
| **Upsert by slug** | `PUT /mcp/{resource}/{slug}` — tạo nếu chưa có, update nếu đã có |
| **Full resource response** | Mọi write trả về full resource để Claude xác nhận data đã persist |
| **Draft-first** | Content vào `draft` / `is_active: false` — activate/publish là bước riêng |
| **Idempotency key** | Header `X-Idempotency-Key: {uuid}` — Redis cache 24h, retry an toàn |
| **Structured errors** | Lỗi keyed by field path — Claude tự đọc, sửa, retry |
| **Slug-based address** | Không dùng numeric ID — Claude làm việc với slug |
| **Dry-run mode** | `?dry_run=true` — validate và preview payload, không ghi DB |
| **Partial update** | `PATCH` chỉ cập nhật fields được gửi — `PUT` replace toàn bộ |

---

## Những gì MCP KHÔNG cần làm

Observer chain tự xử lý sau `model->save()`:

- `SyncJsonldSchema` → JSON-LD schemas
- `SyncSitemapEntry` → Sitemap entries
- `SyncLlmsEntry` → LLMs entries
- `RedirectObserver` → 301 redirects khi slug thay đổi

MCP chỉ cần fill: **content + translations + SEO meta + FAQ items**.

---

## Sprint 0 — Discovery Layer (Foundation)

> Claude cần biết site có gì và thiếu gì trước khi viết content. Sprint này là nền tảng cho tất cả sprint sau.

### `GET /api/v1/mcp/audit`

Site-wide content audit — trả về tất cả entity đang thiếu content hoặc SEO.

**Query params:**
```
?model_type=product,category,blog_post,blog_category,brand,manufacturer
&locale=vi,en
&missing=description,meta_title,meta_description,faq
&is_active=false          # chỉ lấy inactive (chưa publish)
&per_page=50
&page=1
```

**Response:**
```json
{
  "data": [
    {
      "model_type": "product",
      "slug": "knx-push-button-4-fold",
      "name": "KNX Push Button 4-fold",
      "is_active": false,
      "missing": {
        "vi": ["description", "meta_title", "meta_description"],
        "en": ["description", "meta_title", "meta_description", "faq"]
      },
      "context_url": "/api/v1/mcp/products/knx-push-button-4-fold/context"
    }
  ],
  "summary": {
    "total_missing": 87,
    "by_type": {
      "product": 42,
      "category": 8,
      "blog_post": 15,
      "blog_category": 4,
      "brand": 10,
      "manufacturer": 8
    }
  },
  "meta": { "total": 87, "per_page": 50, "current_page": 1 }
}
```

---

### `GET /api/v1/mcp/{model_type}`

List tất cả entity của 1 loại, kèm trạng thái content.

`model_type`: `products` | `categories` | `blog-posts` | `blog-categories` | `brands` | `manufacturers`

**Query params:**
```
?has_description=false   # chỉ lấy entity chưa có description
&has_seo=false           # chỉ lấy entity chưa có SEO meta
&locale=vi               # check theo locale cụ thể
&per_page=20
```

**Response:**
```json
{
  "data": [
    {
      "slug": "knx-push-button-4-fold",
      "name": "KNX Push Button 4-fold",
      "is_active": false,
      "content_status": {
        "vi": { "has_description": false, "has_seo": false, "has_faq": false },
        "en": { "has_description": false, "has_seo": false, "has_faq": false }
      }
    }
  ]
}
```

---

### `GET /api/v1/mcp/search`

Tìm entity theo keyword — tránh tạo duplicate, tìm sản phẩm liên quan để link trong blog.

**Query params:**
```
?q=KNX push button
&types=product,category,brand,manufacturer   # lọc theo loại
&locale=vi
&per_page=10
```

**Response:**
```json
{
  "data": [
    {
      "model_type": "product",
      "slug": "knx-push-button-4-fold",
      "name": "KNX Push Button 4-fold",
      "is_active": true,
      "score": 0.95
    },
    {
      "model_type": "manufacturer",
      "slug": "jung",
      "name": "JUNG",
      "is_active": true,
      "score": 0.72
    }
  ]
}
```

**Quyết định: hybrid search — Scout cho entity có full-text index, DB LIKE cho entity ít record.**

| Entity | Search engine | Lý do |
|---|---|---|
| `product`, `category` | Meilisearch Scout | Đã có `Searchable` trait + index, cần fuzzy full-text |
| `brand`, `manufacturer` | DB `LIKE '%q%'` trên name + slug | < 100 records, tên thường exact (ABB, JUNG) — Scout là over-engineering |

Implement trong `McpSearchService`: tách query theo type → merge results → sort by score.

---

### `GET /api/v1/mcp/review-queue`

Danh sách entity MCP đã draft nhưng chưa được activate/publish — để Tùng biết cần review gì.

**Quyết định: dùng column `mcp_drafted_at` + `mcp_token_id` trực tiếp trên entity tables — query O(1), không cần join activity_log.**

Migration thêm 2 columns vào các bảng:

| Bảng | Columns thêm |
|---|---|
| `products` | `mcp_drafted_at timestamp nullable`, `mcp_token_id bigint nullable` |
| `categories` | `mcp_drafted_at timestamp nullable`, `mcp_token_id bigint nullable` |
| `blog_posts` | `mcp_drafted_at timestamp nullable`, `mcp_token_id bigint nullable` |
| `blog_categories` | `mcp_drafted_at timestamp nullable`, `mcp_token_id bigint nullable` |
| `brands` | `mcp_drafted_at timestamp nullable`, `mcp_token_id bigint nullable` |
| `manufacturers` | `mcp_drafted_at timestamp nullable`, `mcp_token_id bigint nullable` |

Set khi MCP gọi PUT upsert. Reset về `null` khi entity được activate/publish (xem là đã reviewed).

**Query params:**
```
?model_type=product,blog_post
&drafted_after=2026-06-01
&per_page=20
```

**Response:**
```json
{
  "data": [
    {
      "model_type": "product",
      "slug": "knx-push-button-4-fold",
      "name": "KNX Push Button 4-fold",
      "drafted_by": "mcp_token:abc123",
      "drafted_at": "2026-06-01T10:30:00+07:00",
      "readiness_score": 85,
      "readiness_issues": ["faq_en missing"],
      "review_url": "/admin/products/knx-push-button-4-fold/edit",
      "activate_url": "PATCH /api/v1/mcp/products/knx-push-button-4-fold/activate"
    }
  ],
  "summary": {
    "total_pending": 12,
    "by_type": { "product": 8, "blog_post": 4 }
  }
}
```

---

## Auto-create Stubs — Tạo nhánh con khi chưa tồn tại

> Khi tạo product, nếu `manufacturer_slug` / `category_slug` chưa có trong DB,
> API tự động tạo stub `is_active: false` thay vì trả lỗi 422.
> Stub chỉ có name + slug — content fill sau qua endpoint riêng.

### Vấn đề với design hiện tại (4 request)

```
PUT /mcp/manufacturers/jung      ← phải tạo trước
PUT /mcp/brands/abb              ← phải tạo trước
PUT /mcp/categories/knx-input    ← phải tạo trước
PUT /mcp/products/knx-button     ← mới tạo được
```

### Giải pháp: `_stubs` block trong product request (1 request)

```json
PUT /api/v1/mcp/products/knx-push-button-4-fold

{
  "name": "KNX Push Button 4-fold",
  "slug": "knx-push-button-4-fold",
  "manufacturer_slug": "jung",
  "category_slug": "knx-input-devices",
  "is_active": false,

  "_stubs": {
    "manufacturer": {
      "slug": "jung",
      "name": "JUNG",
      "country": "DE",
      "website": "https://jung.de"
    },
    "category": {
      "slug": "knx-input-devices",
      "translations": {
        "vi": { "name": "Thiết bị đầu vào KNX", "slug": "thiet-bi-dau-vao-knx" },
        "en": { "name": "KNX Input Devices",     "slug": "knx-input-devices" }
      }
    }
  },

  "translations": { ... },
  "seo": { ... },
  "faq_items_vi": [...],
  "faq_items_en": [...]
}
```

**Behavior:**
- Nếu `manufacturer_slug: "jung"` đã tồn tại → dùng existing, **bỏ qua** `_stubs.manufacturer`.
- Nếu chưa tồn tại → tạo stub từ `_stubs.manufacturer` với `is_active: false`.
- Tương tự với `category`.
- Nếu slug chưa tồn tại nhưng `_stubs` không cung cấp data → trả lỗi 422 rõ ràng.
- Stub không bao giờ được `is_active: true` dù request có chỉ định.

**Response bao gồm `auto_created`:**

```json
{
  "data": { ...full product resource... },
  "auto_created": [
    {
      "type": "manufacturer",
      "slug": "jung",
      "name": "JUNG",
      "is_active": false,
      "fill_url": "PUT /api/v1/mcp/manufacturers/jung"
    },
    {
      "type": "category",
      "slug": "knx-input-devices",
      "is_active": false,
      "fill_url": "PUT /api/v1/mcp/categories/knx-input-devices"
    }
  ]
}
```

Claude đọc `auto_created` → biết cần fill content cho những entity nào tiếp theo.

---

### Cascade khi tạo hàng loạt

Scenario: import 50 sản phẩm JUNG từ catalog PDF — manufacturer chỉ cần tạo 1 lần.

```
Request 1: PUT /mcp/products/jung-push-button-2fold
  → auto_created: [manufacturer:jung, category:knx-input]

Request 2: PUT /mcp/products/jung-push-button-4fold
  → manufacturer:jung đã tồn tại → dùng lại
  → category:knx-input đã tồn tại → dùng lại
  → auto_created: []

Request 3..50: tương tự
```

Kết quả: 50 sản phẩm + 1 manufacturer stub + N category stubs được tạo từ N request song song.

---

### `_stubs` hỗ trợ cho các entity nào

| Field trong product | Entity | `_stubs` key | Fields tối thiểu |
|---|---|---|---|
| `manufacturer_slug` | Manufacturer | `_stubs.manufacturer` | `slug`, `name` |
| `category_slug` | Category | `_stubs.category` | `slug`, `translations.vi.name`, `translations.vi.slug`, `translations.en.name`, `translations.en.slug` |

Brand không phải foreign key của product — không cần stub ở đây.

---

### Safety rules cho auto-create stubs

- Stub luôn `is_active: false` — không bao giờ hiện trên site cho đến khi được activate thủ công.
- `auto_created` list ghi vào `activity_log` — biết MCP đã tạo stub nào.
- Stub không được ghi đè entity đang active — nếu slug đã tồn tại dù `is_active` là gì, dùng existing.
- `_stubs` bị bỏ qua hoàn toàn khi `dry_run: true` — chỉ validate, không tạo stub.

---

## Sprint 1 — Products

> Ưu tiên cao nhất: catalog nhiều SKU, mỗi sản phẩm cần mô tả kỹ thuật vi + en.

### `GET /api/v1/mcp/products/{slug}/context`

Đọc đủ context để Claude viết content. Gọi trước khi write.

**Response bao gồm:**
```json
{
  "slug": "knx-push-button-4-fold",
  "name": "KNX Push Button 4-fold",
  "sku": "2094 TSM",
  "is_active": false,
  "manufacturer": { "slug": "jung", "name": "JUNG" },
  "category": {
    "slug": "knx-input-devices",
    "translations": {
      "vi": { "name": "Thiết bị đầu vào KNX" },
      "en": { "name": "KNX Input Devices" }
    }
  },
  "attributes": [
    { "name": "Số kênh", "value": "4" },
    { "name": "Giao thức", "value": "KNX TP" }
  ],
  "translations": {
    "vi": { "name": "...", "description": "...", "short_description": "..." },
    "en": { "name": "...", "description": "...", "short_description": "..." }
  },
  "seo": {
    "vi": { "meta_title": "...", "meta_description": "...", "robots": "..." },
    "en": { "meta_title": "...", "meta_description": "...", "robots": "..." }
  },
  "geo": {
    "vi": { "ai_summary": "...", "use_cases": "...", "key_facts": [{ "label": "...", "value": "..." }], "faq": [] },
    "en": { "ai_summary": "...", "use_cases": "...", "key_facts": [{ "label": "...", "value": "..." }], "faq": [] }
  },
  "faq_items_vi": [],
  "faq_items_en": [],
  "related_products": [
    { "slug": "...", "name": "..." }
  ]
}
```

---

### `PUT /api/v1/mcp/products/{slug}`

Upsert product content. Tạo mới nếu slug chưa tồn tại.

**Request body:**
```json
{
  "name": "KNX Push Button 4-fold",
  "slug": "knx-push-button-4-fold",
  "manufacturer_slug": "jung",
  "category_slug": "knx-input-devices",
  "is_active": false,
  "translations": {
    "vi": {
      "name": "Nút nhấn KNX 4 kênh",
      "slug": "nut-nhan-knx-4-kenh",
      "description": "...",
      "short_description": "..."
    },
    "en": {
      "name": "KNX Push Button 4-fold",
      "slug": "knx-push-button-4-fold",
      "description": "...",
      "short_description": "..."
    }
  },
  "seo": {
    "vi": { "meta_title": "...", "meta_description": "...", "robots": "index, follow" },
    "en": { "meta_title": "...", "meta_description": "...", "robots": "index, follow" }
  },
  "geo": {
    "vi": {
      "ai_summary": "...",
      "use_cases": "...",
      "target_audience": "...",
      "key_facts": [
        { "label": "Số kênh", "value": "4" },
        { "label": "Điện áp bus", "value": "21–31 VDC" }
      ],
      "faq": [
        { "question": "KNX Push Button 4-fold dùng cho gì?", "answer": "..." }
      ]
    },
    "en": {
      "ai_summary": "...",
      "use_cases": "...",
      "target_audience": "...",
      "key_facts": [
        { "label": "Channels", "value": "4" },
        { "label": "Bus voltage", "value": "21–31 VDC" }
      ],
      "faq": [
        { "question": "What is KNX Push Button 4-fold used for?", "answer": "..." }
      ]
    }
  },
  "faq_items_vi": [{ "question": "...", "answer": "..." }],
  "faq_items_en": [{ "question": "...", "answer": "..." }],

  "attributes": [
    { "name": "Số kênh",      "value": "4",        "unit": null },
    { "name": "Giao thức",    "value": "KNX TP",   "unit": null },
    { "name": "Nguồn cấp",    "value": "Bus",      "unit": null },
    { "name": "Điện áp",      "value": "21–31",    "unit": "VDC" },
    { "name": "Nhiệt độ",     "value": "-5 – +45", "unit": "°C" }
  ]
}
```

`geo.vi.faq` / `geo.en.faq` là path chính thức — inject vào JSON-LD FAQPage. `faq_items_vi/en` (deprecated) vẫn nhận được nhưng tự động promote lên `geo.faq` và sync-back.

`attributes` là structured specs — hiển thị trong bảng thông số kỹ thuật, Claude điền từ datasheet.

**Response:** `200 OK` — full product resource (translations, SEO, status).

---

### `PATCH /api/v1/mcp/products/{slug}/activate`

Activate sản phẩm sau khi review. Observer tự trigger JSON-LD + Sitemap + LLMs.

```json
{ "is_active": true }
```

---

### `GET /api/v1/mcp/products/{slug}/readiness`

Kiểm tra product đã đủ điều kiện activate chưa. Gọi trước `PATCH /activate`.

**Response:**
```json
{
  "slug": "knx-push-button-4-fold",
  "score": 72,
  "ready": false,
  "checks": {
    "vi": {
      "has_description":       { "pass": true,  "value": 320 },
      "description_min_length":{ "pass": true,  "min": 100 },
      "has_short_description": { "pass": true  },
      "has_meta_title":        { "pass": true  },
      "meta_title_length":     { "pass": false, "value": 72, "max": 70 },
      "has_meta_description":  { "pass": true  },
      "has_faq":               { "pass": false, "count": 0 }
    },
    "en": {
      "has_description":       { "pass": false },
      "has_meta_title":        { "pass": false },
      "has_meta_description":  { "pass": false }
    },
    "general": {
      "has_category":          { "pass": true  },
      "has_manufacturer":      { "pass": true  },
      "category_is_active":    { "pass": false }
    }
  },
  "blocking_issues": [
    "en.description missing",
    "general.category_is_active — category 'knx-input-devices' chưa active"
  ],
  "warnings": [
    "vi.meta_title quá dài (72/70 ký tự)",
    "vi.faq chưa có — nên thêm ít nhất 3 câu hỏi"
  ]
}
```

`score` = 0–100% (giống tất cả entity khác). `ready: false` + `blocking_issues` không empty → server từ chối `PATCH /activate`.

---

## Sprint 2 — Categories

> Categories ảnh hưởng SEO của toàn bộ product listing — ưu tiên trước blog.

### `GET /api/v1/mcp/categories/{slug}/context`

**Response bao gồm:** translations hiện tại + SEO + parent category + product count + child categories.

---

### `PUT /api/v1/mcp/categories/{slug}`

```json
{
  "translations": {
    "vi": {
      "name": "Đèn LED",
      "slug": "den-led",
      "description": "...",
      "short_description": "..."
    },
    "en": {
      "name": "LED Lighting",
      "slug": "led-lighting",
      "description": "...",
      "short_description": "..."
    }
  },
  "seo": {
    "vi": { "meta_title": "...", "meta_description": "..." },
    "en": { "meta_title": "...", "meta_description": "..." }
  },
  "faq_items_vi": [...],
  "faq_items_en": [...]
}
```

---

### `PATCH /api/v1/mcp/categories/{slug}/activate`

```json
{ "is_active": true }
```

---

## Sprint 3 — Blog Posts + Blog Categories

> Technical content KNX, DALI-2, Casambi, Matter — MCP draft, Tùng review, publish.

### `GET /api/v1/mcp/blog-posts/{slug}/context`

**Response bao gồm:** translations + SEO + FAQ hiện tại + blog category + related posts + JSON-LD payload đang active.

---

### `PUT /api/v1/mcp/blog-posts/{slug}`

Upsert — luôn save ở `status: draft` nếu không chỉ định.

```json
{
  "blog_category_slug": "kien-thuc-knx",
  "author_slug": "tung-vu",
  "status": "draft",
  "tags": ["knx", "building-automation", "smart-home"],
  "translations": {
    "vi": {
      "title": "KNX là gì? Tổng quan hệ thống tự động hóa tòa nhà",
      "slug": "knx-la-gi",
      "excerpt": "...",
      "body": "..."
    },
    "en": {
      "title": "What is KNX? Building Automation Overview",
      "slug": "what-is-knx",
      "excerpt": "...",
      "body": "..."
    }
  },
  "seo": {
    "vi": { "meta_title": "...", "meta_description": "..." },
    "en": { "meta_title": "...", "meta_description": "..." }
  },
  "geo": {
    "vi": {
      "ai_summary": "...",
      "use_cases": "...",
      "target_audience": "...",
      "llm_context_hint": "...",
      "faq": [
        { "question": "KNX là gì?", "answer": "..." }
      ]
    },
    "en": {
      "ai_summary": "...",
      "use_cases": "...",
      "target_audience": "...",
      "llm_context_hint": "...",
      "faq": [
        { "question": "What is KNX?", "answer": "..." }
      ]
    }
  },
  "faq_items_vi": [...],
  "faq_items_en": [...]
}
```

> `geo.vi.faq` / `geo.en.faq` là path chính thức — inject vào JSON-LD FAQPage.
> `faq_items_vi/en` (deprecated) vẫn nhận được nhưng sẽ tự động promote lên `geo.faq` và sync-back.

---

### `PATCH /api/v1/mcp/blog-posts/{slug}/publish`

Publish sau khi review. Observer dispatch JSON-LD + Sitemap + LLMs.

```json
{
  "published_at": "2026-06-01T08:00:00+07:00"
}
```

---

### `GET /api/v1/mcp/blog-categories/{slug}/context`

---

### `PUT /api/v1/mcp/blog-categories/{slug}`

```json
{
  "translations": {
    "vi": {
      "name": "Kiến thức KNX",
      "slug": "kien-thuc-knx",
      "description": "..."
    },
    "en": {
      "name": "KNX Knowledge Base",
      "slug": "knx-knowledge",
      "description": "..."
    }
  },
  "seo": {
    "vi": { "meta_title": "...", "meta_description": "..." },
    "en": { "meta_title": "...", "meta_description": "..." }
  }
}
```

### `PATCH /api/v1/mcp/blog-categories/{slug}/activate`

```json
{ "is_active": true }
```

---

## Sprint 4 — Brands + Manufacturers

> Không có translations — dùng chung 1 slug cho vi + en. SEO tối giản: canonical + robots đủ để không vi phạm duplicate.

### `GET /api/v1/mcp/brands/{slug}/context`

**Response bao gồm:** description hiện tại + SEO vi + SEO en + product count.

---

### `PUT /api/v1/mcp/brands/{slug}`

```json
{
  "name": "ABB",
  "slug": "abb",
  "website": "https://abb.com",
  "country": "SE",
  "description": "ABB là tập đoàn...",
  "is_active": false,
  "seo": {
    "vi": {
      "meta_title": "ABB — Giải pháp tự động hóa | KNXStore.vn",
      "meta_description": "...",
      "robots": "index, follow"
    },
    "en": {
      "meta_title": "ABB — Building Automation Solutions | KNXStore.vn",
      "meta_description": "...",
      "robots": "index, follow"
    }
  }
}
```

### `PATCH /api/v1/mcp/brands/{slug}/activate`

```json
{ "is_active": true }
```

---

### `GET /api/v1/mcp/manufacturers/{slug}/context`

### `PUT /api/v1/mcp/manufacturers/{slug}`

Cùng structure với brand — thay `brands` bằng `manufacturers`.

### `PATCH /api/v1/mcp/manufacturers/{slug}/activate`

---

## Sprint 5 — SEO Bulk

> Fill meta_title + meta_description cho toàn bộ entity đang trống. Chạy 1 lần sau khi catalog đã có description.

### `POST /api/v1/mcp/batch/seo-meta`

```json
{
  "items": [
    { "model_type": "product",       "slug": "knx-push-button-4-fold" },
    { "model_type": "category",      "slug": "den-led" },
    { "model_type": "blog_post",     "slug": "knx-la-gi" },
    { "model_type": "blog_category", "slug": "kien-thuc-knx" },
    { "model_type": "brand",         "slug": "abb" },
    { "model_type": "manufacturer",  "slug": "jung" }
  ],
  "locales": ["vi", "en"],
  "overwrite_existing": false
}
```

**Behavior:**
- Load content hiện có → server-side generate meta từ description/name → upsert `seo_meta`.
- `overwrite_existing: false` → bỏ qua field đã có giá trị.
- Trả về: `{ filled: 42, skipped: 8, errors: [] }`.

---

### `POST /api/v1/mcp/batch/translate`

Dịch content từ locale nguồn sang locale đích cho danh sách entity.

```json
{
  "items": [
    { "model_type": "product",   "slug": "knx-push-button-4-fold" },
    { "model_type": "blog_post", "slug": "knx-la-gi" }
  ],
  "from_locale": "vi",
  "to_locale": "en",
  "fields": ["description", "short_description", "meta_title", "meta_description"],
  "overwrite_existing": false,
  "dry_run": false
}
```

**Behavior:**
- Đọc nội dung `from_locale` → trả về bản dịch đề xuất.
- Nếu `dry_run: true` → trả về preview, không lưu.
- Nếu `dry_run: false` → lưu trực tiếp vào translation + SEO meta của `to_locale`.

**Response (dry_run):**
```json
{
  "previews": [
    {
      "model_type": "product",
      "slug": "knx-push-button-4-fold",
      "translations": {
        "en": {
          "description": "...",
          "short_description": "..."
        }
      },
      "seo": {
        "en": { "meta_title": "...", "meta_description": "..." }
      }
    }
  ]
}
```

---

## Sprint 6 — Import từ Specs

> Paste datasheet text hoặc URL → server parse + MCP generate content → save draft.

### `POST /api/v1/mcp/import/product-from-specs`

```json
{
  "slug": "knx-push-button-4-fold",
  "manufacturer_slug": "jung",
  "category_slug": "knx-input-devices",
  "specs_text": "Model: 2094 TSM\nChannels: 4\nProtocol: KNX TP\nPower: Bus-powered\n...",
  "locales": ["vi", "en"],
  "auto_activate": false
}
```

**Behavior:**
- Server parse specs → chuẩn bị context → trả về content đề xuất.
- Claude review response → gọi `PUT /mcp/products/{slug}` để lưu chính thức.
- Không tự lưu — chỉ generate preview để Claude kiểm tra trước.

**Response:**
```json
{
  "suggested": {
    "translations": {
      "vi": { "name": "...", "description": "...", "short_description": "..." },
      "en": { "name": "...", "description": "...", "short_description": "..." }
    },
    "seo": {
      "vi": { "meta_title": "...", "meta_description": "..." },
      "en": { "meta_title": "...", "meta_description": "..." }
    },
    "faq_items_vi": [...],
    "faq_items_en": [...]
  },
  "save_url": "PUT /api/v1/mcp/products/knx-push-button-4-fold"
}
```

---

## Common patterns

### Error format

```json
{
  "message": "Validation failed",
  "errors": {
    "translations.vi.slug": ["Slug đã tồn tại"],
    "seo.vi.meta_title": ["Vượt quá 70 ký tự"],
    "translations.en.body": ["Bắt buộc"]
  }
}
```

### Idempotency

```
X-Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000
```

- Redis cache response theo key, TTL 24h.
- Retry cùng key → trả về response gốc, không tạo duplicate.
- Apply cho: mọi write operation (PUT, PATCH, POST batch).

### Dry-run

```
PUT /api/v1/mcp/products/knx-push-button-4-fold?dry_run=true
```

- Validate toàn bộ request body.
- Trả về resource như thể đã lưu, nhưng không ghi DB.
- Dùng để Claude kiểm tra trước khi commit.

---

## Thứ tự thực hiện

```
Sprint 0  →  Discovery (audit + list) ← Claude cần cái này để hoạt động được
Sprint 1  →  Products (upsert + context + activate)
Sprint 2  →  Categories
Sprint 3  →  Blog Posts + Blog Categories
Sprint 4  →  Brands + Manufacturers
Sprint 5  →  SEO Bulk + Translation batch
Sprint 6  →  Import từ specs (productivity multiplier)
```

---

## MCP Tool mapping

Khi expose qua MCP server, mỗi endpoint = 1 tool Claude có thể gọi:

| Tool name | Endpoint | Dùng khi |
|---|---|---|
| `audit_site_content` | `GET /mcp/audit` | Bắt đầu session — biết thiếu gì |
| `search_entities` | `GET /mcp/search` | Tìm entity trước khi tạo mới |
| `get_review_queue` | `GET /mcp/review-queue` | Xem draft chưa review |
| `list_entities` | `GET /mcp/{type}` | Browse entities theo loại |
| `get_product_context` | `GET /mcp/products/{slug}/context` | Trước khi viết content |
| `check_product_readiness` | `GET /mcp/products/{slug}/readiness` | Trước khi activate |
| `save_product` | `PUT /mcp/products/{slug}` | Sau khi có content |
| `activate_product` | `PATCH /mcp/products/{slug}/activate` | Sau khi readiness = pass |
| `get_category_context` | `GET /mcp/categories/{slug}/context` | |
| `save_category` | `PUT /mcp/categories/{slug}` | |
| `activate_category` | `PATCH /mcp/categories/{slug}/activate` | |
| `get_blog_post_context` | `GET /mcp/blog-posts/{slug}/context` | |
| `save_blog_post` | `PUT /mcp/blog-posts/{slug}` | |
| `publish_blog_post` | `PATCH /mcp/blog-posts/{slug}/publish` | |
| `save_brand` | `PUT /mcp/brands/{slug}` | |
| `save_manufacturer` | `PUT /mcp/manufacturers/{slug}` | |
| `bulk_seo_fill` | `POST /mcp/batch/seo-meta` | Fill SEO hàng loạt |
| `bulk_translate` | `POST /mcp/batch/translate` | Dịch vi→en hoặc en→vi |
| `import_from_specs` | `POST /mcp/import/product-from-specs` | Từ datasheet text |

---

## Migrations cần tạo trước khi code

Tất cả migration chạy trước Sprint 0 — các endpoint phụ thuộc vào columns này.

### Migration 1: `add_mcp_protection_to_translation_tables`

```php
// Thêm vào 4 translation tables + seo_meta
$table->boolean('is_mcp_protected')->default(false)->after('locale');
```

Tables: `product_translations`, `category_translations`, `blog_post_translations`, `blog_category_translations`, `seo_meta`

---

### Migration 2: `add_mcp_tracking_to_entity_tables`

```php
// Thêm vào 6 entity tables
$table->timestamp('mcp_drafted_at')->nullable()->after('updated_at');
$table->foreignId('mcp_token_id')->nullable()->constrained('personal_access_tokens')->nullOnDelete()->after('mcp_drafted_at');
```

Tables: `products`, `categories`, `blog_posts`, `blog_categories`, `brands`, `manufacturers`

**Reset về null khi activate/publish** — đánh dấu là đã reviewed.

---

## Ghi chú kỹ thuật

```
Controllers  → app/Http/Controllers/Mcp/
FormRequests → app/Http/Requests/Mcp/
Services     → dùng lại từ app/Services/{Domain}/ — không tạo mới
Resources    → dùng lại từ app/Http/Resources/Api/ — không tạo mới
```

- Auth: `auth:sanctum` middleware + `mcp-access` ability check trên token
- Rate limit: 120 req/min per token (cao hơn frontend API vì batch operations)
- Logging: mọi MCP write ghi vào `activity_log` với `causer_type = mcp_token`
- Queue: SEO jobs vẫn dispatch trên queue `seo` — không thay đổi
- `?dry_run=true` không dispatch bất kỳ job nào
