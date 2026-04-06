# Laravel 13 Shop — Infrastructure Blueprint

> **Stack:** Laravel 13 · PHP 8.5 · Designed for horizontal scale & easy future upgrades

---

## 1. High-Level Architecture Overview

```
Internet
   │
   ▼
[CDN / Edge Cache]          ← Cloudflare / AWS CloudFront
   │
   ▼
[Load Balancer]             ← Nginx / AWS ALB / HAProxy
   │        │
   ▼        ▼
[App 1]  [App 2]  ...       ← Laravel 13 + PHP 8.5 + FrankenPHP/Octane
   │
   ├──► [Cache Layer]       ← Redis Cluster (sessions, cache, queues)
   ├──► [DB Primary]        ← MySQL 8+ or PostgreSQL 16
   │       └──► [DB Read Replica(s)]
   ├──► [Queue Workers]     ← Laravel Horizon + Redis
   ├──► [Search Engine]     ← Meilisearch or Typesense
   ├──► [Object Storage]    ← AWS S3 / Cloudflare R2
   └──► [Monitoring Stack]  ← Prometheus + Grafana / Telescope
```

---

## 2. Application Layer

| Component | Choice | Notes |
|-----------|--------|-------|
| Framework | Laravel 13 | API + Web, Blade or Inertia.js |
| Runtime | PHP 8.5 + FrankenPHP | Use Octane for persistent worker mode |
| Web Server | Caddy (via FrankenPHP) or Nginx | FrankenPHP recommended for perf |
| Process Manager | Supervisord | Manage Octane + Horizon workers |

### Laravel Key Modules

```
app/
├── Modules/
│   ├── Catalog/        (products, categories, variants)
│   ├── Orders/         (cart, checkout, order lifecycle)
│   ├── Payments/       (gateway abstraction layer)
│   ├── Customers/      (auth, profiles, addresses)
│   ├── Inventory/      (stock management)
│   ├── Promotions/     (coupons, discounts, flash sales)
│   ├── Notifications/  (email, SMS, push)
│   └── Admin/          (dashboard, reporting)
├── Infrastructure/
│   ├── Cache/
│   ├── Search/
│   └── Storage/
```

---

## 3. Database Layer

### Primary Database
- **Engine:** MySQL 8.0+ or PostgreSQL 16
- **Setup:** Single Primary + 1–2 Read Replicas
- **Laravel config:** `DB_READ` / `DB_WRITE` connection separation (built-in)

### Database Strategy

| Concern | Approach |
|---------|----------|
| Writes | Always go to Primary |
| Reads | Distributed across replicas via sticky sessions |
| Migrations | Backward-compatible (add columns, never remove immediately) |
| Indexing | Index foreign keys, search fields, status + created_at combos |
| Soft Deletes | Use on all major models (orders, products, users) |

### Cache Database (Redis)
- **Version:** Redis 7+ (Cluster mode for scale)
- **Responsibilities:**
  - Session storage
  - Application cache (query cache, full-page snippets)
  - Queue driver (Laravel Horizon)
  - Rate limiting counters
  - Flash sale inventory locks (atomic operations)

---

## 4. Caching Strategy

```
Request → L1: Route/Response Cache (Nginx)
        → L2: Full-Page Cache (Redis / Varnish)
        → L3: Query Cache (Laravel Cache facade → Redis)
        → L4: Database
```

| Cache Type | TTL | Invalidation Trigger |
|------------|-----|----------------------|
| Product listings | 5–15 min | Product update event |
| Product detail | 30 min | Product update event |
| Homepage / banners | 10 min | Content update |
| Cart | Session lifetime | Item add/remove |
| User session | 2 hours (sliding) | Logout / expiry |

---

## 5. Queue & Background Jobs

**Driver:** Redis via **Laravel Horizon**

| Queue | Priority | Jobs |
|-------|----------|------|
| `critical` | Highest | Payment processing, order confirmation |
| `default` | Normal | Email notifications, webhooks |
| `search-sync` | Normal | Re-index product on update |
| `reporting` | Low | Analytics aggregation |
| `cleanup` | Lowest | Purge old data, temp files |

---

## 6. Search

- **Engine:** Meilisearch (self-hosted) or Typesense
- **Laravel integration:** Laravel Scout
- **Indexed:** Products (name, description, tags, category, attributes)
- **Sync:** Via queue job on product create/update/delete

---

## 7. File / Media Storage

| Asset Type | Storage | Delivery |
|------------|---------|----------|
| Product images | S3 / Cloudflare R2 | CDN URL |
| User uploads | S3 / Cloudflare R2 | CDN URL |
| Invoices (PDF) | S3 private bucket | Signed URL |
| App assets (CSS/JS) | CDN + versioned | Cache-busted on deploy |

**Image processing:** Laravel with Spatie Media Library + on-the-fly transforms

---

## 8. CDN & Edge

- **Provider:** Cloudflare (recommended) or AWS CloudFront
- **Cached at edge:** Static assets, product images, public pages
- **Bypassed:** Cart, checkout, authenticated pages
- **DDoS protection:** Cloudflare WAF + Rate Limiting rules

---

## 9. Containerization & Deployment

```
docker-compose (dev)
│
├── app        (PHP 8.5 + FrankenPHP/Octane)
├── nginx      (optional reverse proxy)
├── mysql      (or postgres)
├── redis
├── meilisearch
└── mailpit    (local mail dev)

Kubernetes (production)
│
├── Deployment: app (auto-scale 2–10 pods)
├── Deployment: horizon-worker (queue)
├── StatefulSet: redis
├── External: RDS / managed MySQL
└── External: S3 / managed storage
```

**CI/CD Pipeline:**
```
Git Push → GitHub Actions / GitLab CI
  → Run tests (Pest)
  → Build Docker image
  → Push to registry
  → Rolling deploy to K8s / ECS
  → Run migrations (zero-downtime)
  → Cache warm-up
```

---

## 10. Monitoring & Observability

| Tool | Purpose |
|------|---------|
| Laravel Telescope | Local/staging debug (queries, jobs, requests) |
| Laravel Pulse | Production real-time dashboard |
| Prometheus + Grafana | Metrics (RPS, queue depth, cache hit rate) |
| Sentry | Error tracking + performance tracing |
| Uptime Robot / BetterUptime | External uptime monitoring |
| Cloudflare Analytics | Edge traffic insights |

---

## 11. Security Layers

- WAF at CDN edge (Cloudflare)
- Rate limiting: API routes (Laravel `throttle` middleware)
- HTTPS enforced everywhere
- Laravel Sanctum (SPA/API auth) or Jetstream
- CSRF protection on all forms
- SQL injection: Eloquent ORM (parameterized queries only)
- Secrets: `.env` + AWS Secrets Manager / HashiCorp Vault (prod)
- Dependency audits: `composer audit` in CI pipeline

---

## 12. Scaling Playbook

| Traffic Level | Action |
|---------------|--------|
| ~1K req/min | Single server + Redis + read replica |
| ~10K req/min | 2–3 app servers + Horizon workers scaled out |
| ~100K req/min | K8s auto-scaling + ElastiCache + Aurora RDS |
| ~1M+ req/min | Multi-region, edge caching, read-heavy DB sharding |

---

## 13. Tech Stack Summary

```
Language:       PHP 8.5
Framework:      Laravel 13
Runtime:        FrankenPHP + Octane
Web Server:     Caddy (via FrankenPHP)
Database:       MySQL 8 / PostgreSQL 16
Cache/Queue:    Redis 7
Search:         Meilisearch / Typesense
Storage:        AWS S3 / Cloudflare R2
CDN:            Cloudflare
Containers:     Docker + Kubernetes
CI/CD:          GitHub Actions
Monitoring:     Pulse + Telescope + Sentry
Testing:        Pest
```

---

## 14. Development Stages

```
Stage 1 — MVP
  └── Single server, SQLite→MySQL, Redis, basic queues

Stage 2 — Growth
  └── Read replica, Horizon, CDN for assets, S3 storage

Stage 3 — Scale
  └── Multi-server (load balanced), Redis Cluster, Meilisearch

Stage 4 — Enterprise
  └── Kubernetes, multi-region, managed DB, full observability
```

---

*Last updated: 2026-04-06*
