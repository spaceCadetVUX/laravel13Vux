# CLAUDE.md
> This file is read automatically by Claude Code CLI at the start of every session.
> It provides persistent project context, conventions, and rules.
> Last Updated: April 2026

---

## Project Overview

**Name:** B2C E-commerce + Blog
**Stack:** Laravel 13 (backend) + Nuxt 3 (frontend) — monorepo
**PHP:** 8.3 minimum (Laravel 13 requirement — released March 17, 2026)
**Database:** PostgreSQL
**Cache / Queue:** Redis + Laravel Horizon
**Search:** Meilisearch + Laravel Scout
**Admin Panel:** Filament v3
**Auth:** Laravel Sanctum + Google OAuth (Socialite)
**Repo structure:**
```
/backend    ← Laravel 13
/frontend   ← Nuxt 3
```

---

## Reference Documents

Always read the relevant files below before writing any code.
All paths are relative to the project root.

| Document | Path | Purpose |
|---|---|---|
| Requirements | `doc/requrement.md` | Project requirements and decisions |
| ERD | `doc/dataase.md` | All tables, columns, types, indexes, FKs |
| Folder Structure | `doc/folderstruct.md` | Where every file lives |
| API Route Map | `doc/API_ROUTE_MAP.md` | All endpoints, methods, auth, request/response |
| Frontend Architecture | `doc/Frontend Architecture — Nuxt 3 Storefront.md` | Nuxt 3 structure, SSR strategy, SEO pipeline |
| Backend Build Plan | `doc/Backend Build Plan.md` | Sprint-by-sprint backend build instructions |

### Which files to read per task

| Task type | Read these files |
|---|---|
| Migrations | `doc/dataase.md` + `doc/Backend Build Plan.md` |
| Models | `doc/dataase.md` + `doc/folderstruct.md` + `doc/Backend Build Plan.md` |
| API Controllers | `doc/API_ROUTE_MAP.md` + `doc/folderstruct.md` + `doc/Backend Build Plan.md` |
| Filament resources | `doc/folderstruct.md` + `doc/Backend Build Plan.md` |
| SEO / GEO | `doc/dataase.md` + `doc/Backend Build Plan.md` |
| Frontend | `doc/Frontend Architecture — Nuxt 3 Storefront.md` + `doc/API_ROUTE_MAP.md` |
| Any task | Always check `doc/Backend Build Plan.md` for the exact sprint instructions |

---

## Backend — Laravel 13

### Architecture pattern
```
Request → FormRequest (validate) → Controller (thin) → Service (logic) → Repository (query) → Resource (transform) → Response
```
- **Controllers** — call one service method, return one resource. Nothing else.
- **Services** — own all business logic. No direct HTTP or response concerns.
- **Repositories** — own all Eloquent queries. No business logic.
- **Resources** — transform models to API response shape. Always use envelope.

### Critical rules — NEVER do this
- ❌ Eloquent queries inside controllers
- ❌ Business logic inside controllers
- ❌ Raw SQL unless performance-critical (document why)
- ❌ Hard delete on soft-deletable models (`users`, `products`, `categories`, `orders`, `blog_posts`)
- ❌ `bigint` or `int` for UUID primary key columns
- ❌ `uuid` type for polymorphic `model_id` — always use `varchar(36)`
- ❌ Direct column access on encrypted fields — always use accessor
- ❌ Queries in Blade templates or API Resources
- ❌ `dd()`, `dump()`, `var_dump()` left in committed code

### Always do this
- ✅ Use `FormRequest` for ALL validation — never `$request->validate()` in controller
- ✅ Return `ApiResponse` trait envelope on every API response
- ✅ Use `slug` not `id` in all public-facing routes
- ✅ Add `HasSeoMeta`, `HasGeoProfile`, `HasJsonldSchemas`, `HasSitemapEntry`, `HasLlmsEntry` traits to any new public model
- ✅ Register new models in `morphMap` inside `AppServiceProvider`
- ✅ Use PHP Enums for all fixed value sets — never raw strings (backed enums, fully supported in PHP 8.3)
- ✅ Use native Laravel 13 attribute syntax where cleaner — `#[Middleware('auth')]`, `#[Authorize]`, `#[Tries(3)]`
- ✅ Dispatch SEO sync jobs on the `seo` queue — never `default`
- ✅ Dispatch order jobs on the `orders` queue
- ✅ Write feature tests for every new API endpoint

### Polymorphic model_id rule
```php
// ALL polymorphic tables use varchar(36) — handles both uuid and bigint as string
$table->string('model_id', 36);        // ✅ correct
$table->uuid('model_id');              // ❌ wrong — breaks bigint PKs
$table->unsignedBigInteger('model_id'); // ❌ wrong — breaks uuid PKs
```

### morphMap — always use aliases, never full class names
```php
// AppServiceProvider::boot()
Relation::morphMap([
    'product'       => \App\Models\Product::class,
    'blog_post'     => \App\Models\BlogPost::class,
    'category'      => \App\Models\Category::class,
    'blog_category' => \App\Models\BlogCategory::class,
    'blog_tag'      => \App\Models\BlogTag::class,
]);
```

### API response envelope — always use this shape
```php
// Success
return $this->success(data: new ProductResource($product), message: 'OK');

// Paginated
return $this->success(
    data: ProductResource::collection($products),
    meta: $this->paginationMeta($products)
);

// Error
return $this->error(message: 'Not found', code: 404);
```

### Queue names
| Queue | Used for |
|---|---|
| `default` | General fallback |
| `orders` | Order emails, stock updates |
| `seo` | JSON-LD sync, sitemap sync, llms sync |
| `notifications` | Future: push, SMS |

### Soft delete behavior
These models use soft deletes — never call `->forceDelete()` unless explicitly requested:
`User`, `Product`, `Category`, `Order`, `BlogPost`

### Encrypted fields
These fields are encrypted at rest — always access via model accessor, never raw DB value:
- `users.email`, `users.phone`
- `addresses.phone`, `addresses.address_line`
- `orders.shipping_address`

### Key packages and their purpose
| Package | Purpose |
|---|---|
| `laravel/sanctum` | API token auth + session auth |
| `laravel/socialite` | Google OAuth |
| `spatie/laravel-permission` | Role-based access control |
| `spatie/laravel-activitylog` | Audit logging |
| `spatie/laravel-responsecache` | Full-page response cache |
| `filament/filament` | Admin panel (v3) |
| `laravel/scout` | Search integration |
| `laravel/horizon` | Queue monitoring |
| `knuckleswtf/scribe` | Auto API docs |
| `laravel/pint` | Code formatter |
| `nunomaduro/larastan` | Static analysis |

---

## Backend — File Locations

```
Controllers      → app/Http/Controllers/Api/V1/{Domain}/
FormRequests     → app/Http/Requests/{Domain}/
Resources        → app/Http/Resources/Api/{Domain}/
Services         → app/Services/{Domain}/
Repositories     → app/Repositories/Eloquent/
Models           → app/Models/ (SEO models → app/Models/Seo/)
Observers        → app/Observers/
Jobs             → app/Jobs/{Domain}/
Events           → app/Events/{Domain}/
Listeners        → app/Listeners/{Domain}/
Enums            → app/Enums/
Traits           → app/Traits/
Policies         → app/Policies/
Commands         → app/Console/Commands/
Migrations       → database/migrations/
Seeders          → database/seeders/
Tests            → tests/Feature/{Domain}/ and tests/Unit/
```

---

## Backend — Naming Conventions

| Type | Pattern | Example |
|---|---|---|
| Controller | `{Model}Controller` | `ProductController` |
| FormRequest | `{Store\|Update}{Model}Request` | `StoreProductRequest` |
| Resource | `{Model}Resource` | `ProductResource` |
| Collection | `{Model}Collection` | `ProductCollection` |
| Service | `{Model}Service` | `CartService` |
| Repository | `{Model}Repository` | `ProductRepository` |
| Observer | `{Model}Observer` | `ProductObserver` |
| Policy | `{Model}Policy` | `OrderPolicy` |
| Job | descriptive verb phrase | `SendOrderConfirmationEmail` |
| Event | noun + past verb | `OrderPlaced` |
| Listener | descriptive action | `SendOrderConfirmationListener` |
| Command | `{Action}Command` | `CartPruneCommand` |
| Enum | PascalCase | `OrderStatus`, `UserRole` |
| Trait | `Has{Capability}` | `HasSeoMeta`, `HasMedia` |
| Migration | `{nnnn}_create_{table}_table` | `0007_create_products_table` |

---

## Backend — Database Rules

### Primary keys
- **uuid** — `users`, `products`, `carts`, `orders`, `blog_posts`, `addresses`
- **bigint auto-increment** — all other tables

### Foreign keys — correct `onDelete` behavior
| Scenario | Behavior |
|---|---|
| Child must die with parent | `CASCADE` |
| Child survives, FK becomes null | `SET NULL` (column must be nullable) |
| Parent cannot be deleted if children exist | `RESTRICT` |

### Index every FK column
Every foreign key column must have an index. Composite indexes for polymorphic pairs:
```php
$table->index(['model_type', 'model_id']); // every polymorphic table
```

### PostgreSQL-specific
- Use `jsonb` not `json` for JSON columns (faster queries, indexable)
- Use `decimal(12, 2)` for all monetary values
- Use `text` for encrypted fields (encrypted values are longer than varchar limits)

---

## Frontend — Nuxt 3

### Architecture rules
- **Pages** — fetch data via composables, pass to components as props. No API calls.
- **Components** — receive props, emit events. No direct API calls. No business logic.
- **Composables** — own all API calls and data transformation.

### SSR rules
- Public pages (products, blog, categories) → Full SSR via `useAsyncData()`
- User-specific pages (cart, account, checkout) → CSR via `useFetch()` client-side
- Never use `client-only` wrapper on SEO-critical content

### SEO rules — apply on every SSR page
```ts
// Every SSR page must have both of these:
useSeo(data.value.seo)                              // meta tags
// and in template:
// <JsonldRenderer :schemas="data.jsonld_schemas" />
```

### Never do this in Nuxt
- ❌ Raw `<img>` tags — always use `<NuxtImg>`
- ❌ Raw `<a href>` for internal links — always use `<NuxtLink>`
- ❌ API calls inside `<script setup>` without composable wrapper
- ❌ Hardcoded API URLs — always use `useRuntimeConfig().public.apiBase`
- ❌ `console.log` left in committed code

### Always do this in Nuxt
- ✅ `definePageMeta({ middleware: 'auth' })` on all account/checkout pages
- ✅ `definePageMeta({ middleware: 'guest' })` on login/register pages
- ✅ `formatCurrency()` from `utils/currency.ts` for all price display
- ✅ `formatDate()` from `utils/date.ts` for all date display
- ✅ Explicit `width` + `height` on every `<NuxtImg>` to prevent CLS

### File locations
```
Pages          → frontend/pages/
Components     → frontend/components/{Domain}/
Composables    → frontend/composables/
Layouts        → frontend/layouts/
Middleware     → frontend/middleware/
Stores         → frontend/stores/
Types          → frontend/types/
Utils          → frontend/utils/
Assets         → frontend/assets/css/
Public         → frontend/public/
```

### Component naming
| Prefix | Usage | Example |
|---|---|---|
| `App` | Global layout | `AppHeader`, `AppFooter` |
| `Product` | Product domain | `ProductCard`, `ProductGrid` |
| `Blog` | Blog domain | `BlogCard`, `BlogDetail` |
| `Cart` | Cart domain | `CartDrawer`, `CartItem` |
| `Order` | Order domain | `OrderCard`, `OrderDetail` |
| `Address` | Address domain | `AddressForm`, `AddressCard` |
| `Search` | Search domain | `SearchBar`, `SearchResults` |
| `Seo` | SEO components | `JsonldRenderer` |
| `Ui` | Generic primitives | `UiPagination`, `UiBadge` |

---

## SEO & GEO — Critical Rules

### Any new public model must have
```php
class NewModel extends Model {
    use HasSeoMeta;
    use HasGeoProfile;
    use HasJsonldSchemas;
    use HasSitemapEntry;
    use HasLlmsEntry;
    use HasMedia;
}
```

### Any new public model must have an Observer that dispatches
```php
class NewModelObserver {
    public function saved(NewModel $model): void {
        dispatch(new SyncJsonldSchema($model))->onQueue('seo');
        dispatch(new SyncSitemapEntry($model))->onQueue('seo');
        dispatch(new SyncLlmsEntry($model))->onQueue('seo');
    }
}
```

### Any new public model must be added to
1. `morphMap` in `AppServiceProvider`
2. `sitemap_indexes` seeder (new child sitemap)
3. `llms_documents` seeder (new llms document)
4. Meilisearch Scout index config

### JSON-LD — two modes, never confuse them
- `is_auto_generated = true` → Observer fills from template. Never manually edit payload.
- `is_auto_generated = false` → Admin manually edited. Observer never overwrites.

### Redirects cache invalidation
The `RedirectObserver` handles this automatically — never manually flush the redirects Redis key.

---

## Artisan Commands Reference

```bash
# Development
php artisan serve
php artisan migrate
php artisan migrate:fresh --seed
php artisan db:seed --class=JsonldTemplateSeeder

# SEO / GEO
php artisan sitemap:generate
php artisan llms:generate
php artisan jsonld:sync

# Maintenance
php artisan cart:prune
php artisan horizon

# Code quality
./vendor/bin/pint
./vendor/bin/phpstan analyse
php artisan test

# Scribe API docs
php artisan scribe:generate
```

---

## Git Commit Convention

```
feat: add product detail API endpoint
fix: correct model_id type in seo_meta migration
refactor: extract cart logic into CartService
chore: update composer dependencies
docs: update ERD with jsonld_schemas table
test: add feature test for place order endpoint
seo: add BreadcrumbList schema to ProductObserver
```

---

## Environment — Key Variables

```bash
# Backend (backend/.env)
APP_ENV=local
APP_URL=http://localhost:8000
DB_CONNECTION=pgsql
REDIS_HOST=redis
MEILISEARCH_HOST=http://meilisearch:7700
FRONTEND_URL=http://localhost:3000
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost:8000/api/v1/auth/google/callback
MAIL_MAILER=log

# Frontend (frontend/.env)
NUXT_PUBLIC_API_BASE=http://localhost:8000/api/v1
NUXT_PUBLIC_APP_URL=http://localhost:3000
NUXT_PUBLIC_GOOGLE_CLIENT_ID=
```

---

## How to Start a Claude Code Session

```
# Paste this at the start of every session:

Read these files before doing anything:
- doc/dataase.md
- doc/folderstruct.md
- doc/API_ROUTE_MAP.md
- doc/Backend Build Plan.md

Then execute [SPRINT NAME e.g. S03] exactly as written in doc/Backend Build Plan.md.
Do not skip steps.
Do not add files or packages not mentioned in the sprint.
Ask me before making any decision not covered in the docs.
```

---

## What to Check Before Every Commit

```
□ No Eloquent queries in controllers
□ No raw $request->validate() — use FormRequest
□ No hardcoded strings where Enums exist
□ No uuid type on polymorphic model_id columns
□ New public model has all 6 SEO/GEO traits
□ New model registered in morphMap
□ New Observer dispatches to correct queue
□ php artisan test passes
□ ./vendor/bin/pint passes (no formatting errors)
□ No dd() / dump() / console.log in committed code
□ API_ROUTE_MAP.md updated if new route added
□ doc/dataase.md updated if new migration added
```

---

*This file is the single most important file for Claude Code CLI sessions.
Place it at the project root — not inside /doc.
Keep it updated as the project evolves — outdated CLAUDE.md causes outdated code generation.*

<!-- gitnexus:start -->
# GitNexus — Code Intelligence

This project is indexed by GitNexus as **backbone** (8412 symbols, 25121 relationships, 300 execution flows). Use the GitNexus MCP tools to understand code, assess impact, and navigate safely.

> If any GitNexus tool warns the index is stale, run `npx gitnexus analyze` in terminal first.

## Always Do

- **MUST run impact analysis before editing any symbol.** Before modifying a function, class, or method, run `gitnexus_impact({target: "symbolName", direction: "upstream"})` and report the blast radius (direct callers, affected processes, risk level) to the user.
- **MUST run `gitnexus_detect_changes()` before committing** to verify your changes only affect expected symbols and execution flows.
- **MUST warn the user** if impact analysis returns HIGH or CRITICAL risk before proceeding with edits.
- When exploring unfamiliar code, use `gitnexus_query({query: "concept"})` to find execution flows instead of grepping. It returns process-grouped results ranked by relevance.
- When you need full context on a specific symbol — callers, callees, which execution flows it participates in — use `gitnexus_context({name: "symbolName"})`.

## When Debugging

1. `gitnexus_query({query: "<error or symptom>"})` — find execution flows related to the issue
2. `gitnexus_context({name: "<suspect function>"})` — see all callers, callees, and process participation
3. `READ gitnexus://repo/backbone/process/{processName}` — trace the full execution flow step by step
4. For regressions: `gitnexus_detect_changes({scope: "compare", base_ref: "main"})` — see what your branch changed

## When Refactoring

- **Renaming**: MUST use `gitnexus_rename({symbol_name: "old", new_name: "new", dry_run: true})` first. Review the preview — graph edits are safe, text_search edits need manual review. Then run with `dry_run: false`.
- **Extracting/Splitting**: MUST run `gitnexus_context({name: "target"})` to see all incoming/outgoing refs, then `gitnexus_impact({target: "target", direction: "upstream"})` to find all external callers before moving code.
- After any refactor: run `gitnexus_detect_changes({scope: "all"})` to verify only expected files changed.

## Never Do

- NEVER edit a function, class, or method without first running `gitnexus_impact` on it.
- NEVER ignore HIGH or CRITICAL risk warnings from impact analysis.
- NEVER rename symbols with find-and-replace — use `gitnexus_rename` which understands the call graph.
- NEVER commit changes without running `gitnexus_detect_changes()` to check affected scope.

## Tools Quick Reference

| Tool | When to use | Command |
|------|-------------|---------|
| `query` | Find code by concept | `gitnexus_query({query: "auth validation"})` |
| `context` | 360-degree view of one symbol | `gitnexus_context({name: "validateUser"})` |
| `impact` | Blast radius before editing | `gitnexus_impact({target: "X", direction: "upstream"})` |
| `detect_changes` | Pre-commit scope check | `gitnexus_detect_changes({scope: "staged"})` |
| `rename` | Safe multi-file rename | `gitnexus_rename({symbol_name: "old", new_name: "new", dry_run: true})` |
| `cypher` | Custom graph queries | `gitnexus_cypher({query: "MATCH ..."})` |

## Impact Risk Levels

| Depth | Meaning | Action |
|-------|---------|--------|
| d=1 | WILL BREAK — direct callers/importers | MUST update these |
| d=2 | LIKELY AFFECTED — indirect deps | Should test |
| d=3 | MAY NEED TESTING — transitive | Test if critical path |

## Resources

| Resource | Use for |
|----------|---------|
| `gitnexus://repo/backbone/context` | Codebase overview, check index freshness |
| `gitnexus://repo/backbone/clusters` | All functional areas |
| `gitnexus://repo/backbone/processes` | All execution flows |
| `gitnexus://repo/backbone/process/{name}` | Step-by-step execution trace |

## Self-Check Before Finishing

Before completing any code modification task, verify:
1. `gitnexus_impact` was run for all modified symbols
2. No HIGH/CRITICAL risk warnings were ignored
3. `gitnexus_detect_changes()` confirms changes match expected scope
4. All d=1 (WILL BREAK) dependents were updated

## Keeping the Index Fresh

After committing code changes, the GitNexus index becomes stale. Re-run analyze to update it:

```bash
npx gitnexus analyze
```

If the index previously included embeddings, preserve them by adding `--embeddings`:

```bash
npx gitnexus analyze --embeddings
```

To check whether embeddings exist, inspect `.gitnexus/meta.json` — the `stats.embeddings` field shows the count (0 means no embeddings). **Running analyze without `--embeddings` will delete any previously generated embeddings.**

> Claude Code users: A PostToolUse hook handles this automatically after `git commit` and `git merge`.

## CLI

| Task | Read this skill file |
|------|---------------------|
| Understand architecture / "How does X work?" | `.claude/skills/gitnexus/gitnexus-exploring/SKILL.md` |
| Blast radius / "What breaks if I change X?" | `.claude/skills/gitnexus/gitnexus-impact-analysis/SKILL.md` |
| Trace bugs / "Why is X failing?" | `.claude/skills/gitnexus/gitnexus-debugging/SKILL.md` |
| Rename / extract / split / refactor | `.claude/skills/gitnexus/gitnexus-refactoring/SKILL.md` |
| Tools, resources, schema reference | `.claude/skills/gitnexus/gitnexus-guide/SKILL.md` |
| Index, status, clean, wiki CLI commands | `.claude/skills/gitnexus/gitnexus-cli/SKILL.md` |

<!-- gitnexus:end -->
