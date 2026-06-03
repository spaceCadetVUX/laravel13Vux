# claudemcp.md — MCP Server Wrapper cho KNXStore API

> File này là CLAUDE.md cho project `mcp-server/` — đọc trước khi code bất kỳ thứ gì.
> MCP Server là lớp TypeScript dịch MCP tool call → HTTP request vào Laravel REST API.
> Nguồn spec: `doc/apimcp/sprint_api.md`

---

## Kiến trúc tổng thể

```
Claude Desktop / Claude Code
    ↓  MCP protocol (stdio transport)
mcp-server/src/index.ts          ← entry point, đăng ký tất cả tools
    ↓  HTTP (fetch)
Laravel /api/v1/mcp/*            ← REST API đã build (Sprint 0–6)
    ↓
PostgreSQL / Redis / Meilisearch
```

---

## Cấu trúc thư mục

```
mcp-server/
├── package.json
├── tsconfig.json
├── .env.example
├── src/
│   ├── index.ts          ← entry: khởi tạo McpServer, register tất cả tools
│   ├── client.ts         ← HTTP client wrapper (fetch + auth + error handling)
│   ├── tools/
│   │   ├── sprint0.ts    ← Discovery: audit, search, review-queue, list
│   │   ├── sprint1.ts    ← Products: context, readiness, save, activate
│   │   ├── sprint2.ts    ← Categories: context, readiness, save, activate
│   │   ├── sprint3.ts    ← Blog Posts + Blog Categories
│   │   ├── sprint4.ts    ← Brands + Manufacturers
│   │   ├── sprint5.ts    ← Batch: seo-meta, translate
│   │   └── sprint6.ts    ← Import from specs
│   └── types.ts          ← shared TypeScript types
```

---

## Environment variables

```env
# .env (hoặc truyền qua claude_desktop_config.json env block)
KNXSTORE_API_BASE=http://127.0.0.1:8000/api/v1
KNXSTORE_API_TOKEN=your_sanctum_personal_access_token_here
```

---

## Claude Desktop config

```json
// ~/Library/Application Support/Claude/claude_desktop_config.json (Mac)
// %APPDATA%\Claude\claude_desktop_config.json (Windows)
{
  "mcpServers": {
    "knxstore": {
      "command": "node",
      "args": ["C:/path/to/mcp-server/dist/index.js"],
      "env": {
        "KNXSTORE_API_BASE": "http://127.0.0.1:8000/api/v1",
        "KNXSTORE_API_TOKEN": "your_token_here"
      }
    }
  }
}
```

---

## Dependencies (package.json)

```json
{
  "name": "knxstore-mcp-server",
  "version": "1.0.0",
  "type": "module",
  "scripts": {
    "build": "tsc",
    "dev":   "tsx src/index.ts",
    "start": "node dist/index.js"
  },
  "dependencies": {
    "@modelcontextprotocol/sdk": "^1.0.0",
    "zod": "^3.22.0"
  },
  "devDependencies": {
    "typescript": "^5.0.0",
    "tsx": "^4.0.0",
    "@types/node": "^20.0.0"
  }
}
```

> **Lưu ý `dev` script:** dùng `tsx src/index.ts` (không phải `tsx watch`). Vì đây là stdio transport — nếu watch restart process thì connection Claude Desktop bị đứt. Để develop, test bằng MCP Inspector thay vì hot-reload.

---

## `tsconfig.json`

```json
{
  "compilerOptions": {
    "target": "ES2022",
    "module": "NodeNext",
    "moduleResolution": "NodeNext",
    "outDir": "./dist",
    "rootDir": "./src",
    "strict": true,
    "esModuleInterop": true,
    "skipLibCheck": true
  },
  "include": ["src"]
}
```

> **Bắt buộc dùng `module: "NodeNext"`** vì project là ESM (`"type": "module"`) và import paths dùng `.js` extension. Thiếu setting này `tsc` build fail.

---

## `src/client.ts` — HTTP client (viết 1 lần, dùng mọi sprint)

```typescript
const BASE  = process.env.KNXSTORE_API_BASE!;
const TOKEN = process.env.KNXSTORE_API_TOKEN!;

export async function api(
  method: "GET" | "PUT" | "PATCH" | "POST",
  path: string,
  bodyOrParams?: unknown,
): Promise<unknown> {
  // GET: bodyOrParams là object params → append as query string
  // PUT/PATCH/POST: bodyOrParams là request body → JSON stringify
  let url  = `${BASE}${path}`;
  let body: string | undefined;

  if (method === "GET" && bodyOrParams && typeof bodyOrParams === "object") {
    const qs = new URLSearchParams(
      Object.entries(bodyOrParams as Record<string, unknown>)
        .filter(([, v]) => v !== undefined && v !== null)
        .map(([k, v]) => [k, String(v)])
    ).toString();
    if (qs) url += `?${qs}`;
  } else if (bodyOrParams !== undefined) {
    body = JSON.stringify(bodyOrParams);
  }

  const res = await fetch(url, {
    method,
    headers: {
      "Authorization": `Bearer ${TOKEN}`,
      "Content-Type":  "application/json",
      "Accept":        "application/json",
    },
    body,
  });

  const json = await res.json();

  if (!res.ok) {
    const msg  = json?.message ?? `HTTP ${res.status}`;
    const errs = json?.errors ? JSON.stringify(json.errors) : "";
    throw new Error(`${msg}${errs ? " — " + errs : ""}`);
  }

  return json;
}

/** Wrap bất kỳ data nào thành MCP tool response. Export và dùng trong mọi sprint. */
export function ok(data: unknown) {
  return { content: [{ type: "text" as const, text: JSON.stringify(data, null, 2) }] };
}
```

**Cách dùng trong tools:**

```typescript
// GET với query params — pass object, client tự build query string
await api("GET", "/mcp/audit", { model_type: "product", locale: "vi", per_page: 50 });
// → GET /api/v1/mcp/audit?model_type=product&locale=vi&per_page=50

// PUT/POST với body — pass object, client JSON stringify
await api("PUT", "/mcp/products/my-slug", { name: "...", translations: {...} });

// PATCH không có body
await api("PATCH", "/mcp/products/my-slug/activate", {});
```

---

## `src/index.ts` — Entry point

```typescript
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { registerSprint0Tools } from "./tools/sprint0.js";
import { registerSprint1Tools } from "./tools/sprint1.js";
import { registerSprint2Tools } from "./tools/sprint2.js";
import { registerSprint3Tools } from "./tools/sprint3.js";
import { registerSprint4Tools } from "./tools/sprint4.js";
import { registerSprint5Tools } from "./tools/sprint5.js";
import { registerSprint6Tools } from "./tools/sprint6.js";

const server = new McpServer({
  name:    "knxstore-mcp",
  version: "1.0.0",
});

registerSprint0Tools(server);
registerSprint1Tools(server);
registerSprint2Tools(server);
registerSprint3Tools(server);
registerSprint4Tools(server);
registerSprint5Tools(server);
registerSprint6Tools(server);

const transport = new StdioServerTransport();
await server.connect(transport);
```

---

---

## Sprint 0 — Discovery Tools

**File:** `src/tools/sprint0.ts`
**Scope token:** `mcp:read`

### Tools

| Tool name | Endpoint | Mô tả |
|---|---|---|
| `audit_site_content` | `GET /mcp/audit` | Site-wide audit — thiếu content ở đâu |
| `list_entities` | `GET /mcp/{model_type}` | List entities theo loại + filter |
| `search_entities` | `GET /mcp/search` | Tìm entity theo keyword |
| `get_review_queue` | `GET /mcp/review-queue` | Draft MCP chưa được review |

### Input schemas

```typescript
// audit_site_content
z.object({
  model_type:  z.string().optional(),  // "product,category,blog_post,..."
  locale:      z.string().optional(),  // "vi,en"
  missing:     z.string().optional(),  // "description,meta_title,..."
  is_active:   z.boolean().optional(),
  per_page:    z.number().default(50),
  page:        z.number().default(1),
})

// list_entities
z.object({
  model_type:      z.enum(["products","categories","blog-posts","blog-categories","brands","manufacturers"]),
  has_description: z.boolean().optional(),
  has_seo:         z.boolean().optional(),
  locale:          z.string().optional(),
  per_page:        z.number().default(20),
})

// search_entities
z.object({
  q:        z.string(),
  types:    z.string().optional(),  // "product,category,brand,manufacturer"
  locale:   z.string().default("vi"),
  per_page: z.number().default(10),
})

// get_review_queue
z.object({
  model_type:     z.string().optional(),
  drafted_after:  z.string().optional(),  // ISO date
  per_page:       z.number().default(20),
})
```

### HTTP mapping

```typescript
audit_site_content  → GET /mcp/audit?{params}
list_entities       → GET /mcp/{model_type}?{params}
search_entities     → GET /mcp/search?{params}
get_review_queue    → GET /mcp/review-queue?{params}
```

---

## Sprint 1 — Products

**File:** `src/tools/sprint1.ts`
**Scope token:** `mcp:read` (GET) + `mcp:write` (PUT) + `mcp:publish` (PATCH activate)

### Tools

| Tool name | Endpoint | Mô tả |
|---|---|---|
| `get_product_context` | `GET /mcp/products/{slug}/context` | Load full context trước khi viết |
| `check_product_readiness` | `GET /mcp/products/{slug}/readiness` | Kiểm tra đủ điều kiện activate chưa |
| `save_product` | `PUT /mcp/products/{slug}` | Upsert content + SEO + FAQ + attributes |
| `activate_product` | `PATCH /mcp/products/{slug}/activate` | Activate sau khi readiness = pass |

### Input schemas

```typescript
// get_product_context / check_product_readiness
z.object({ slug: z.string() })

// save_product
z.object({
  slug:              z.string(),
  name:              z.string().optional(),
  sku:               z.string().optional(),
  price:             z.number().optional(),
  manufacturer_slug: z.string().optional(),
  category_slug:     z.string().optional(),
  overwrite_existing: z.boolean().default(false),
  dry_run:           z.boolean().default(false),
  _stubs: z.object({
    manufacturer: z.object({
      slug: z.string(), name: z.string(), country: z.string().optional(), website: z.string().optional(),
    }).optional(),
    category: z.object({
      slug: z.string(),
      translations: z.record(z.object({ name: z.string(), slug: z.string() })),
    }).optional(),
  }).optional(),
  translations: z.record(z.object({
    name:              z.string().optional(),
    slug:              z.string().optional(),
    description:       z.string().optional(),
    short_description: z.string().optional(),
  })).optional(),
  seo: z.record(z.object({
    meta_title:       z.string().optional(),
    meta_description: z.string().optional(),
    robots:           z.string().optional(),
  })).optional(),
  geo: z.record(z.object({
    ai_summary:       z.string().optional(),
    use_cases:        z.string().optional(),
    target_audience:  z.string().optional(),
    llm_context_hint: z.string().optional(),
    key_facts:        z.array(z.object({ label: z.string(), value: z.string() })).optional(),
    faq: z.array(z.object({
      question: z.string(),
      answer:   z.string(),
    })).optional(),  // Ưu tiên hơn faq_items_vi/en
  })).optional(),
  faq_items_vi: z.array(z.object({ question: z.string(), answer: z.string() })).optional(),  // [Deprecated — dùng geo.vi.faq]
  faq_items_en: z.array(z.object({ question: z.string(), answer: z.string() })).optional(),  // [Deprecated — dùng geo.en.faq]
  attributes: z.array(z.object({
    name: z.string(), value: z.string(), unit: z.string().nullable().optional(),
  })).optional(),
})

// activate_product
z.object({ slug: z.string() })
```

### HTTP mapping

```typescript
get_product_context     → GET  /mcp/products/{slug}/context
check_product_readiness → GET  /mcp/products/{slug}/readiness
save_product            → PUT  /mcp/products/{slug}           body: {...}
activate_product        → PATCH /mcp/products/{slug}/activate body: {}
```

---

## Sprint 2 — Categories

**File:** `src/tools/sprint2.ts`

### Tools

| Tool name | Endpoint |
|---|---|
| `get_category_context` | `GET /mcp/categories/{slug}/context` |
| `check_category_readiness` | `GET /mcp/categories/{slug}/readiness` |
| `save_category` | `PUT /mcp/categories/{slug}` |
| `activate_category` | `PATCH /mcp/categories/{slug}/activate` |

### Input schemas

```typescript
// get_category_context / check_category_readiness / activate_category
z.object({ slug: z.string() })

// save_category
z.object({
  slug:               z.string(),
  name:               z.string().optional(),
  parent_slug:        z.string().optional(),
  sort_order:         z.number().optional(),
  overwrite_existing: z.boolean().default(false),
  dry_run:            z.boolean().default(false),
  translations: z.record(z.object({
    name:         z.string().optional(),
    slug:         z.string().optional(),
    description:  z.string().optional(),
    rich_content: z.string().optional(),
  })).optional(),
  seo: z.record(z.object({
    meta_title:          z.string().optional(),
    meta_description:    z.string().optional(),
    og_title:            z.string().optional(),
    og_description:      z.string().optional(),
    twitter_title:       z.string().optional(),
    twitter_description: z.string().optional(),
  })).optional(),
  geo: z.record(z.object({
    ai_summary:       z.string().optional(),
    use_cases:        z.string().optional(),
    target_audience:  z.string().optional(),
    llm_context_hint: z.string().optional(),
    key_facts: z.array(z.object({ label: z.string(), value: z.string() })).optional(),
    faq: z.array(z.object({
      question: z.string(),
      answer:   z.string(),
    })).optional(),  // Ưu tiên hơn faq_items_vi/en
  })).optional(),
  faq_items_vi: z.array(z.object({ question: z.string(), answer: z.string() })).optional(),  // [Deprecated — dùng geo.vi.faq]
  faq_items_en: z.array(z.object({ question: z.string(), answer: z.string() })).optional(),  // [Deprecated — dùng geo.en.faq]
})
```

---

## Sprint 3 — Blog Posts + Blog Categories

**File:** `src/tools/sprint3.ts`

### Tools

| Tool name | Endpoint |
|---|---|
| `get_blog_post_context` | `GET /mcp/blog-posts/{slug}/context` |
| `save_blog_post` | `PUT /mcp/blog-posts/{slug}` |
| `publish_blog_post` | `PATCH /mcp/blog-posts/{slug}/publish` |
| `get_blog_category_context` | `GET /mcp/blog-categories/{slug}/context` |
| `save_blog_category` | `PUT /mcp/blog-categories/{slug}` |
| `activate_blog_category` | `PATCH /mcp/blog-categories/{slug}/activate` |

### Input schemas

```typescript
// get_blog_post_context / get_blog_category_context / activate_blog_category
z.object({ slug: z.string() })

// save_blog_post
z.object({
  slug:               z.string(),
  blog_category_slug: z.string().optional(),
  author_slug:        z.string().optional(),
  tags:               z.array(z.string()).optional(),
  overwrite_existing: z.boolean().default(false),
  dry_run:            z.boolean().default(false),
  translations: z.record(z.object({
    title:   z.string().optional(),
    slug:    z.string().optional(),
    excerpt: z.string().optional(),
    body:    z.string().optional(),
  })).optional(),
  seo: z.record(z.object({
    meta_title:       z.string().optional(),
    meta_description: z.string().optional(),
  })).optional(),
  geo: z.record(z.object({
    ai_summary:       z.string().optional(),
    use_cases:        z.string().optional(),
    target_audience:  z.string().optional(),
    llm_context_hint: z.string().optional(),
    faq: z.array(z.object({
      question: z.string(),
      answer:   z.string(),
    })).optional(),  // Ưu tiên hơn faq_items_vi/en
  })).optional(),
  faq_items_vi: z.array(z.object({ question: z.string(), answer: z.string() })).optional(),  // [Deprecated — dùng geo.vi.faq]
  faq_items_en: z.array(z.object({ question: z.string(), answer: z.string() })).optional(),  // [Deprecated — dùng geo.en.faq]
})

// publish_blog_post
z.object({
  slug:         z.string(),
  published_at: z.string().optional(),  // ISO 8601
})

// save_blog_category
z.object({
  slug:               z.string(),
  overwrite_existing: z.boolean().default(false),
  dry_run:            z.boolean().default(false),
  translations: z.record(z.object({
    name:        z.string().optional(),
    slug:        z.string().optional(),
    description: z.string().optional(),
  })).optional(),
  seo: z.record(z.object({
    meta_title:       z.string().optional(),
    meta_description: z.string().optional(),
  })).optional(),
})
```

---

## Sprint 4 — Brands + Manufacturers

**File:** `src/tools/sprint4.ts`

### Tools

| Tool name | Endpoint |
|---|---|
| `get_brand_context` | `GET /mcp/brands/{slug}/context` |
| `save_brand` | `PUT /mcp/brands/{slug}` |
| `activate_brand` | `PATCH /mcp/brands/{slug}/activate` |
| `get_manufacturer_context` | `GET /mcp/manufacturers/{slug}/context` |
| `save_manufacturer` | `PUT /mcp/manufacturers/{slug}` |
| `activate_manufacturer` | `PATCH /mcp/manufacturers/{slug}/activate` |

### Input schemas

```typescript
// get_brand_context / activate_brand / get_manufacturer_context / activate_manufacturer
z.object({ slug: z.string() })

// save_brand
z.object({
  slug:               z.string(),
  name:               z.string().optional(),
  description:        z.string().optional(),
  website:            z.string().optional(),
  sort_order:         z.number().optional(),
  overwrite_existing: z.boolean().default(false),
  dry_run:            z.boolean().default(false),
  seo: z.record(z.object({
    meta_title:       z.string().optional(),
    meta_description: z.string().optional(),
    robots:           z.string().optional(),
  })).optional(),
})

// save_manufacturer — cùng save_brand + thêm country
z.object({
  slug:               z.string(),
  name:               z.string().optional(),
  description:        z.string().optional(),
  website:            z.string().optional(),
  country:            z.string().optional(),  // thêm field này
  overwrite_existing: z.boolean().default(false),
  dry_run:            z.boolean().default(false),
  seo: z.record(z.object({
    meta_title:       z.string().optional(),
    meta_description: z.string().optional(),
    robots:           z.string().optional(),
  })).optional(),
})
```

---

## Sprint 5 — Batch SEO + Translate

**File:** `src/tools/sprint5.ts`
**Scope token:** `mcp:write`

### Tools

| Tool name | Endpoint |
|---|---|
| `bulk_seo_fill` | `POST /mcp/batch/seo-meta` |
| `bulk_translate` | `POST /mcp/batch/translate` |

### Input schemas

```typescript
// bulk_seo_fill
z.object({
  items: z.array(z.object({
    model_type: z.enum(["product","category","blog_post","blog_category","brand","manufacturer"]),
    slug:       z.string(),
  })).max(50),
  locales:            z.array(z.string()).default(["vi","en"]),
  overwrite_existing: z.boolean().default(false),
})

// bulk_translate
z.object({
  items: z.array(z.object({
    model_type: z.enum(["product","category","blog_post","blog_category","brand","manufacturer"]),
    slug:       z.string(),
  })).max(20),
  from_locale:        z.string().default("vi"),
  to_locale:          z.string().default("en"),
  fields:             z.array(z.string()).optional(),
  overwrite_existing: z.boolean().default(false),
  dry_run:            z.boolean().default(false),
})
```

---

## Sprint 6 — Import từ Specs

**File:** `src/tools/sprint6.ts`
**Scope token:** `mcp:write`

### Tools

| Tool name | Endpoint |
|---|---|
| `import_from_specs` | `POST /mcp/import/product-from-specs` |

### Input schema

```typescript
z.object({
  slug:              z.string(),
  manufacturer_slug: z.string().optional(),
  category_slug:     z.string().optional(),
  specs_text:        z.string(),  // raw text từ datasheet
  locales:           z.array(z.string()).default(["vi","en"]),
  auto_activate:     z.boolean().default(false),
})
```

**Lưu ý:** Endpoint này trả về `suggested` content — không tự lưu. Claude đọc response rồi gọi `save_product` để lưu chính thức.

---

## Thứ tự build

```
Sprint 0  →  Setup project + client.ts + Discovery tools (cần ngay để Claude audit được)
Sprint 1  →  Products (ưu tiên cao nhất — nhiều SKU nhất)
Sprint 2  →  Categories
Sprint 3  →  Blog Posts + Blog Categories
Sprint 4  →  Brands + Manufacturers
Sprint 5  →  Batch tools
Sprint 6  →  Import from specs (cần Laravel endpoint Sprint 6 làm trước)
```

---

## Quy tắc implement

- Mỗi sprint = 1 file `sprint{N}.ts` export `register{N}Tools(server: McpServer): void`
- Tool name: snake_case, khớp đúng với bảng MCP Tool mapping trong `sprint_api.md`
- Input validation: Zod schema — luôn có `.describe()` trên mỗi field để Claude hiểu
- Error: throw `new Error(message)` — MCP SDK tự wrap thành tool error response
- Không log ra stdout (sẽ corrupt stdio transport) — dùng `process.stderr.write()` nếu cần debug
- Build trước khi test: `npm run build` → `node dist/index.js`
- Test nhanh với MCP Inspector: `npx @modelcontextprotocol/inspector node dist/index.js`

---

## Ví dụ tool đầy đủ (tham khảo pattern)

```typescript
// src/tools/sprint1.ts
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import { api, ok } from "../client.js";  // ok export từ client.ts — không define local

export function registerSprint1Tools(server: McpServer) {

  server.tool(
    "get_product_context",
    "Load full product context trước khi viết content. Gọi đầu tiên.",
    { slug: z.string().describe("Product slug, e.g. knx-push-button-4fold") },
    async ({ slug }) => ok(await api("GET", `/mcp/products/${slug}/context`)),
  );

  server.tool(
    "check_product_readiness",
    "Kiểm tra product đủ điều kiện activate chưa. Gọi trước activate_product.",
    { slug: z.string() },
    async ({ slug }) => ok(await api("GET", `/mcp/products/${slug}/readiness`)),
  );

  server.tool(
    "save_product",
    "Upsert product — tạo mới hoặc update content, translations, SEO, FAQ, attributes.",
    {
      slug:               z.string().describe("Product slug — dùng làm URL"),
      name:               z.string().optional(),
      sku:                z.string().optional(),
      manufacturer_slug:  z.string().optional(),
      category_slug:      z.string().optional(),
      overwrite_existing: z.boolean().default(false).describe("true = ghi đè content đã có"),
      dry_run:            z.boolean().default(false).describe("true = preview, không lưu DB"),
      translations: z.record(z.object({
        name:              z.string().optional(),
        slug:              z.string().optional(),
        description:       z.string().optional(),
        short_description: z.string().optional(),
      })).optional().describe('{"vi": {...}, "en": {...}}'),
      seo: z.record(z.object({
        meta_title:       z.string().optional(),
        meta_description: z.string().optional(),
      })).optional(),
      faq_items_vi: z.array(z.object({ question: z.string(), answer: z.string() })).optional(),
      faq_items_en: z.array(z.object({ question: z.string(), answer: z.string() })).optional(),
      attributes: z.array(z.object({ name: z.string(), value: z.string(), unit: z.string().nullable().optional() })).optional(),
      _stubs: z.object({
        manufacturer: z.object({ slug: z.string(), name: z.string(), country: z.string().optional(), website: z.string().optional() }).optional(),
        category: z.object({ slug: z.string(), translations: z.record(z.object({ name: z.string(), slug: z.string() })) }).optional(),
      }).optional(),
    },
    async ({ slug, ...body }) => ok(await api("PUT", `/mcp/products/${slug}`, body)),
  );

  server.tool(
    "activate_product",
    "Activate product sau khi readiness pass. Observer tự sync JSON-LD + Sitemap.",
    { slug: z.string() },
    async ({ slug }) => ok(await api("PATCH", `/mcp/products/${slug}/activate`, {})),
  );
}
```

---

## Checklist trước khi connect Claude Desktop

```
□ npm run build không có lỗi TypeScript
□ node dist/index.js không crash ngay (kiểm tra process.env)
□ npx @modelcontextprotocol/inspector → test từng tool thủ công
□ KNXSTORE_API_TOKEN có scope đúng (mcp:read + mcp:write tối thiểu)
□ Laravel server đang chạy (php artisan serve)
□ claude_desktop_config.json đúng path đến dist/index.js
□ Restart Claude Desktop sau khi sửa config
```
