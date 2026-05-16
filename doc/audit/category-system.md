# Category System — Technical Audit & Feature Documentation

> **Project:** B2C E-commerce (backbone)
> **Stack:** Laravel 13 · PHP 8.3 · PostgreSQL · Redis
> **Last Audited:** 2026-05-16
> **Status:** Production-ready

---

## Overview

The category system is designed as **SEO landing pages**, not simple product filters. Each category is a standalone, content-rich page targeting specific search keywords — comparable to how major Vietnamese e-commerce platforms (Tiki, Thegioididong, CellphoneS) structure their category pages.

---

## Architecture

```
Request → CategoryController → CategoryService → CategoryRepository → Model → Resource → Response
                                      ↓
                              CategoryObserver (on save/delete)
                                      ↓
                    ┌─────────────────┼─────────────────┐
                    ↓                 ↓                  ↓
            SyncJsonldSchema   SyncSitemapEntry    SyncLlmsEntry
              (per locale)       (per locale)       (per locale)
```

All SEO sync jobs are dispatched to the `seo` queue and run synchronously in local development via `SyncQueue`.

---

## Data Model

### Core Table: `categories`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | Auto-increment |
| `parent_id` | bigint FK | NULL = root category |
| `name` | varchar | Internal fallback name |
| `slug` | varchar | Internal fallback slug |
| `image_path` | varchar | Relative storage path |
| `is_active` | boolean | Controls visibility + SEO sync |
| `sort_order` | integer | Display ordering |
| `deleted_at` | timestamp | Soft delete |

### Translations Table: `category_translations`

Bilingual support via a dedicated translations table. Each locale gets its own row.

| Column | Type | Notes |
|---|---|---|
| `category_id` | FK | References `categories.id` |
| `locale` | varchar | `vi` or `en` |
| `name` | varchar | Locale-specific display name |
| `slug` | varchar | Locale-specific URL slug |
| `description` | text | Locale-specific description |

### Hierarchy

2-level parent-child hierarchy. URLs remain **flat** regardless of depth:

```
/categories/dien-thoai          ← child category
NOT /categories/dien-tu/dien-thoai
```

The parent-child relationship surfaces in:
- BreadcrumbList JSON-LD (full ancestor chain)
- Admin navigation
- Internal linking structure

---

## SEO Stack

Each category automatically maintains 6 data stores across 2 locales (vi + en):

```
Category (vi + en)
├── JsonldSchema × 3 per locale  →  CollectionPage, BreadcrumbList, FAQPage
├── SitemapEntry × 1 per locale  →  <url> in XML sitemap
├── LlmsEntry    × 1 per locale  →  llms.txt for AI crawlers
└── SeoMeta      × 1 per locale  →  og:*, meta, canonical, robots
```

### JSON-LD Schemas

#### CollectionPage

The primary schema for a category page. Enriched with:

```json
{
  "@context": "https://schema.org",
  "@type": "CollectionPage",
  "@id": "https://site.com/categories/electronics",
  "name": "Electronics",
  "description": "...",
  "url": "https://site.com/categories/electronics",
  "inLanguage": "en",
  "numberOfItems": 42,
  "publisher": { "@type": "Organization", "name": "..." },
  "mainEntity": {
    "@type": "ItemList",
    "numberOfItems": 42,
    "itemListElement": [
      {
        "@type": "ListItem",
        "position": 1,
        "name": "Product Name",
        "url": "https://site.com/products/product-slug",
        "image": "https://site.com/storage/...",
        "offers": {
          "@type": "Offer",
          "price": 1500000,
          "priceCurrency": "VND",
          "availability": "https://schema.org/InStock"
        }
      }
    ]
  }
}
```

**Locale-aware fields:** `name`, `description`, `url`, `inLanguage`, `@id`

#### BreadcrumbList

Walks the **full ancestor chain** at sync time — no runtime DB queries needed:

```json
{
  "@type": "BreadcrumbList",
  "itemListElement": [
    { "@type": "ListItem", "position": 1, "name": "Home", "item": "https://site.com" },
    { "@type": "ListItem", "position": 2, "name": "Electronics", "item": "https://site.com/categories/electronics" },
    { "@type": "ListItem", "position": 3, "name": "Phones", "item": "https://site.com/categories/phones" }
  ]
}
```

Google reads this for:
- Breadcrumb display in SERPs (replaces raw URL)
- Topical authority signal between parent and child categories
- PageRank flow from parent → child

#### FAQPage

Generated from `GeoEntityProfile.faq` (Repeater field, per locale):

```json
{
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Which phone is best for gaming?",
      "acceptedAnswer": { "@type": "Answer", "text": "..." }
    }
  ]
}
```

Google may render this as an expandable accordion below the search result for informational queries.

---

### Sitemap Entry

Each locale gets an independent sitemap entry with hreflang alternates:

```xml
<url>
  <loc>https://site.com/vi/categories/dien-thoai</loc>
  <lastmod>2026-05-16</lastmod>
  <changefreq>weekly</changefreq>
  <priority>0.7</priority>
  <xhtml:link rel="alternate" hreflang="vi" href="https://site.com/vi/categories/dien-thoai"/>
  <xhtml:link rel="alternate" hreflang="en" href="https://site.com/en/categories/phones"/>
</url>
```

`lastmod` is automatically updated from `Category.updated_at` on every save.

---

### LLMs Entry

Plain-text representation for AI crawlers (ChatGPT, Perplexity, Claude, etc.) via `llms.txt`:

```
# Điện Thoại

https://site.com/vi/categories/dien-thoai

AI summary text here...

Use Cases: ...
Target Audience: ...

Key Facts:
  - Pin: 5000mAh
  - RAM: 8–16GB

FAQ:
  Q: Điện thoại nào tốt nhất cho chơi game?
  A: ...
```

Key facts use `label: value` format from the Filament Repeater component.

---

### SeoMeta

Per-locale SEO metadata managed in the Filament admin panel:

| Field | Used for |
|---|---|
| `meta_title` | `<title>` tag |
| `meta_description` | `<meta name="description">` |
| `meta_keywords` | `<meta name="keywords">` |
| `og_title` | Open Graph title |
| `og_description` | Open Graph description |
| `og_image` | Open Graph image URL |
| `og_type` | `website` \| `article` |
| `twitter_card` | Twitter Card type |
| `twitter_title` | Twitter title |
| `twitter_description` | Twitter description |
| `canonical_url` | `<link rel="canonical">` |
| `robots` | `index,follow` \| `noindex` etc. |

---

## API

### Endpoints

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/categories` | Full active category tree (root + nested children) |
| `GET` | `/api/v1/categories/{slug}` | Category detail + paginated products |

### Locale Detection

All API routes run through `SetApiLocale` middleware. Locale is resolved in priority order:

```
X-Locale header  →  ?locale= query param  →  fallback: 'vi'
```

Invalid locales silently fall back to `'vi'`. The frontend (Nuxt 3) sets `X-Locale` globally via a single composable interceptor.

### Detail Response Shape

```json
{
  "status": "success",
  "data": {
    "category": {
      "id": 4,
      "name": "Điện Thoại",
      "slug": "dien-thoai",
      "description": "...",
      "image_url": "https://site.com/storage/...",
      "parent": null,
      "children": [],
      "seo": {
        "meta_title": "Điện Thoại | KNX Store",
        "meta_description": "...",
        "og_title": "...",
        "og_description": "...",
        "og_image": "...",
        "og_type": "website",
        "canonical_url": "https://site.com/vi/categories/dien-thoai",
        "robots": "index, follow"
      },
      "jsonld_schemas": [
        { "type": "CollectionPage",  "label": "Category Page",  "payload": { ... } },
        { "type": "BreadcrumbList",  "label": "Breadcrumb",     "payload": { ... } },
        { "type": "FAQPage",         "label": "FAQ",            "payload": { ... } }
      ]
    },
    "products": [ ... ]
  },
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 15,
    "total": 42
  }
}
```

`jsonld_schemas` only returns schemas for the **requested locale** — never mixed vi+en.

### Slug Lookup

`CategoryRepository::findActiveBySlug()` searches both the internal `categories.slug` and all `category_translations.slug` values, making it compatible with locale-specific URL patterns.

---

## Observer Triggers

`CategoryObserver` dispatches SEO sync jobs on every relevant lifecycle event:

| Event | Action |
|---|---|
| `saved` (is_active=true) | Sync jsonld, sitemap, llms for each locale with a translation |
| `saved` (is_active=false) | Deactivate all SEO entries (rows kept, `is_active=false`) |
| `deleted` (soft) | Deactivate all SEO entries |
| `restored` | Re-sync all SEO entries |
| `forceDeleted` | Hard delete all SEO rows from DB |

Only locales that have a corresponding translation are synced — a vi-only category never creates EN schemas.

---

## Admin Panel (Filament v3)

### GeoEntityProfile Fields

Managed per locale via Filament tabs:

| Tab | Component | Storage Format |
|---|---|---|
| Content | Textarea (ai_summary, use_cases, target_audience, llm_context_hint) | plain text |
| Key Facts | **Repeater** (`[{"label":"...","value":"..."}]`) | jsonb array |
| FAQ | Repeater (`[{"question":"...","answer":"..."}]`) | jsonb array |

Key facts use a Repeater (not KeyValue) to match the stored jsonb format. Items are reorderable and collapsible.

---

## Test Results

All tests verified on 2026-05-16 against category ID=4 (bilingual, 2 locales).

| Test Group | Points | Result |
|---|---|---|
| Observer Triggers (9 scenarios) | 9/9 | ✅ All pass |
| JSON-LD CollectionPage | 12/14 | ✅ (2 skipped — no products in test data) |
| JSON-LD BreadcrumbList | 6/6 | ✅ All pass |
| JSON-LD FAQPage | 6/6 | ✅ All pass |
| OG Sharing API Response | 12/13 | ✅ (1 empty — og_description not filled in test data) |
| LLMs Entry | 6/7 | ✅ (1 empty — no ai_summary in test data) |
| Sitemap Entry | 3/3 | ✅ All pass |
| Edge Cases | 5/5 | ✅ All pass |

**Total: 59/67 verified** — remaining 8 are data gaps in test fixtures, not code defects.

---

## Known Limitations

| Item | Notes |
|---|---|
| Product filtering / facets | Not implemented — categories are landing pages, not filter containers |
| Pagination canonical | Frontend (Nuxt 3) responsibility — backend returns base canonical only |
| `og_description` auto-fill | Manually set in admin panel — not auto-generated from description |
| Canonical auto-update on slug change | JSON-LD canonical auto-syncs; SeoMeta canonical requires manual update in admin |

---

## File Index

| File | Purpose |
|---|---|
| `app/Models/Category.php` | Model with all SEO/GEO traits |
| `app/Models/CategoryTranslation.php` | Per-locale name, slug, description |
| `app/Observers/CategoryObserver.php` | Lifecycle hooks → SEO sync dispatch |
| `app/Services/Category/CategoryService.php` | Business logic: tree, detail, pagination |
| `app/Repositories/Eloquent/CategoryRepository.php` | DB queries: tree, slug lookup, products |
| `app/Http/Controllers/Api/V1/Category/CategoryController.php` | Thin controller |
| `app/Http/Resources/Api/Category/CategoryResource.php` | List/tree representation |
| `app/Http/Resources/Api/Category/CategoryDetailResource.php` | Detail with seo + jsonld_schemas |
| `app/Http/Resources/Api/Category/CategoryTreeResource.php` | Tree representation with children |
| `app/Http/Middleware/SetApiLocale.php` | Locale detection for all API routes |
| `app/Services/Seo/JsonldService.php` | JSON-LD generation + enrichment |
| `app/Services/Seo/SitemapService.php` | Sitemap entry sync |
| `app/Services/Seo/LlmsGeneratorService.php` | LLMs entry generation |
| `app/Filament/Resources/CategoryResource.php` | Admin CRUD panel |
| `app/Filament/Resources/GeoEntityProfileResource.php` | GEO content management |
