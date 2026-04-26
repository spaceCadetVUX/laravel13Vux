# Audit — BusinessProfile SEO/GEO Logic
**Date:** 2026-04-26
**Scope:** Edit Business Profile — SEO & GEO output consistency
**Files inspected:**
- `app/Models/BusinessProfile.php`
- `app/Observers/BusinessProfileObserver.php`
- `app/Filament/Resources/BusinessProfileResource.php`
- `app/Filament/Resources/BusinessProfileResource/Pages/EditBusinessProfile.php`
- `app/Services/Seo/BusinessJsonldService.php`
- `app/Jobs/Seo/GenerateBusinessDocument.php`
- `app/Services/Seo/LlmsGeneratorService.php`
- `app/Providers/AppServiceProvider.php`

---

## Summary

| Severity | Count |
|---|---|
| Logical conflict | 1 |
| Minor issue | 2 |
| By design (no fix needed) | 4 |

---

## [CONFLICT] Description vs Tagline field priority mismatch

**Severity:** Logical conflict
**Files:**
- `app/Services/Seo/BusinessJsonldService.php:97`
- `app/Services/Seo/LlmsGeneratorService.php:315`

### Problem

The two SEO outputs consume different model fields for the same semantic concept ("what this business is about"), with opposite priority:

| Output | Field used | Code location |
|---|---|---|
| JSON-LD `Organization` → `schema:description` | `tagline` only | `BusinessJsonldService::organization()` line 97 |
| LLMs `business.txt` intro paragraph | `description` first, `tagline` fallback | `LlmsGeneratorService::generateBusinessDocument()` line 315 |

The model's `description` column is never exposed in JSON-LD. If an admin fills both fields:
- Google/structured-data crawlers see **tagline** as the organization description
- AI crawlers (LLMs.txt) see **description** as the intro

This is a semantic inconsistency across two SEO surfaces.

### Fix

Align `BusinessJsonldService::organization()` to match the LLMs priority (`description` → `tagline`):

```php
// BusinessJsonldService.php — organization() method

// BEFORE (line 97):
if (filled($p->tagline)) $schema['description'] = $p->tagline;

// AFTER:
$desc = $p->description ?? $p->tagline ?? null;
if (filled($desc)) $schema['description'] = $desc;
```

---

## [MINOR] Unused import in LlmsGeneratorService

**Severity:** Minor (dead code)
**File:** `app/Services/Seo/LlmsGeneratorService.php:7`

```php
use App\Models\Seo\GeoEntityProfile; // imported but never referenced directly
```

`GeoEntityProfile` is accessed only indirectly via the dynamic `$model->geoProfile` relationship, never by class name. The import can be removed.

### Fix

Delete line 7 in `LlmsGeneratorService.php`.

---

## [MINOR] Type fragility in localBusiness openingHours mapping

**Severity:** Minor (potential TypeError in edge case)
**File:** `app/Services/Seo/BusinessJsonldService.php:181-183`

```php
$hours = collect((array) ($p->business_hours ?? []))
    ->map(fn (string $h, string $d): string => ($dayMap[$d] ?? $d) . ' ' . trim($h))
    ->values()
    ->all();
```

The strict `string` type hint on `$h` throws `TypeError` if any `business_hours` value is `null`. Filament's `KeyValue` component normally produces strings, but submitting an empty value field can produce `null` in the JSON.

### Fix

Cast inside the callback instead of relying on the type hint:

```php
->map(fn ($h, string $d): string => ($dayMap[$d] ?? $d) . ' ' . trim((string) ($h ?? '')))
```

---

## By Design — No Fix Required

### BusinessProfile has no SEO/GEO traits
`BusinessProfile` does not use `HasSeoMeta`, `HasGeoProfile`, `HasJsonldSchemas`, `HasSitemapEntry`, or `HasLlmsEntry`. This is intentional — it is a singleton admin-only model, not a public slug-based URL model. Its JSON-LD and LLMs outputs are handled by dedicated services (`BusinessJsonldService`, `LlmsGeneratorService::generateBusinessDocument()`).

### BusinessProfile not registered in morphMap
`BusinessProfile` has no polymorphic relationships, so morphMap registration is not applicable.

### No SyncSitemapEntry dispatched from BusinessProfileObserver
`BusinessProfile` has no public URL to index in a sitemap. Observer correctly dispatches only `GenerateBusinessDocument`.

### Observer dispatches to correct queue
`BusinessProfileObserver::saved()` dispatches `GenerateBusinessDocument` on the `seo` queue — consistent with CLAUDE.md queue naming rules.
