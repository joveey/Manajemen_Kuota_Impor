# Developer Guide ? File Structure & Functions

This document maps the key files, module responsibilities, and how the core flows behave. Focus areas: HS->PK, Quotas, Open PO, Invoice/GR, and Dashboard.

## Quick Architecture

- Laravel stack (Controllers + Services + Models + Views) with admin routes grouped in `routes/web.php`.
- Import data flows through `ImportController` (API) then per-module UI controllers (import.*PageController).
- Automatic Product->Quota mapping is handled by `ProductQuotaAutoMapper` and related listeners.

## Primary Routes

- `routes/web.php` ? admin group, prefix `admin.`
  - HS->PK page: `admin.imports.hs_pk.*` (`resources/views/admin/imports/hs_pk/...`).
  - HS->PK manual: `admin.hs_pk.manual.*`.
  - Mapping diagnostics: `admin.mapping.unmapped`, `admin.mapping.unmapped.page`.
  - Open PO: `admin.openpo.*`.
  - Invoice: `admin.imports.invoices.*`, GR: `admin.imports.gr.*`.
  - Dashboard: `admin.dashboard`.

## HS->PK Module

- UI: `resources/views/admin/imports/hs_pk/index.blade.php` ? upload form, client-side validation, sample CSV button.
- Page controller: `app/Http/Controllers/Admin/HsPkImportPageController.php`
  - `index():13` ? list 25 latest imports.
  - `uploadForm(Request):24` ? validate file, delegate to API `ImportController::uploadHsPk`, redirect to preview.
  - `preview(Import):49` ? show import summary.
  - `publishForm(Request, Import):55` ? delegate to API `ImportController::publish` for HS/PK type.
- API: `app/Http/Controllers/Admin/ImportController.php`
  - `uploadHsPk(Request):506` ? read ?HS code master? sheet (fallback first sheet). Required headers: `HS_CODE`, `DESC`. Parse `DESC` via `PkCategoryParser` into PK range/anchor. Save rows to `imports` + `import_items` (status normalized/error). Responds with JSON `import_id`, `status`, totals.
  - `publish(Request, Import):1089` ? upsert into `hs_code_pk_mappings` (supports `period_key` if present), mark import published, optionally trigger automapping.
- Manual: `app/Http/Controllers/Admin/HsPkManualController.php`
  - `index(Request):15` ? paginate mappings; filter by `period_key`.
  - `store(Request):45` ? validate input; parse `pk_value`; compute anchor; upsert unique (hs_code[, period_key]).
- Resolver: `app/Services/HsCodeResolver.php`
  - `resolveForProduct(Product, ?string $period):14` ? fetch mapping for product `hs_code` (prefer matching `period_key` year, fallback legacy). If missing, fallback to `product.pk_capacity`. Returns `float|null` PK anchor.
- Parser: `app/Support/PkCategoryParser.php`
  - `parse(string $label):9` ? supports `8-10`, `<8`, `>10`, single numbers; returns range and inclusivity flags.

## Unmapped Products (Diagnostics)

- Page: `resources/views/admin/mapping/unmapped.blade.php` ? filter by Period/Reason, fetch JSON API, render table.
- API: `app/Http/Controllers/Admin/MappingController.php`
  - `unmapped(Request):28` ? derive date range from Period (YYYY / YYYY-MM / YYYY-MM-DD). For each `products` row, determine reason:
    - `missing_hs`: column `hs_code` empty.
    - `missing_pk`: `hs_code` present but resolver cannot find PK.
    - `no_matching_quota`: PK exists but no quota covers the period.
    - In-memory pagination; returns JSON (`total`, `current_page`, `last_page`, `data`).

## Quotas

- Import UI: `resources/views/admin/imports/quotas/*` (index/preview).
- Page controller: `app/Http/Controllers/Admin/QuotaImportPageController.php`
  - `index()` ? list imports.
  - `uploadForm(Request)` ? delegate to API `ImportController::uploadQuotas`.
  - `preview(Import)` ? show summary.
  - `publishForm(Request, Import)` ? delegate to API `ImportController::publishQuotas`.
- Automapping Product->Quota: `app/Services/ProductQuotaAutoMapper.php`
  - `runForPeriod(string|int $periodKey):22` ? normalize quota PK ranges (`government_category`), remove `source='auto'` mappings for the period, resolve product PK via `HsCodeResolver`, find candidate quotas, mark primary (narrowest range) + backups, upsert into `product_quota_mappings`.
  - `queryQuotasByPeriodKey($key):163` ? select quotas overlapping the period (year/month/day).

## Open PO & Forecast Allocation

- UI: `resources/views/admin/openpo/*` (import, preview).
- Controller: `app/Http/Controllers/Admin/OpenPoImportController.php`
  - `form():24` ? upload page.
  - `preview():29` ? read file via `OpenPoReader`, validate via `OpenPoValidator`, store preview in session.
  - `previewPage():57` ? reload preview from session.
  - `publish(Request):73` ? write `po_headers`/`po_lines` (modes `insert`/`replace`); create/update `purchase_orders` for forecast; allocate forecast per line via service; stamp `forecast_allocated_at` on `po_lines` to avoid double-counting.
- Validation: `app/Services/OpenPoValidator.php`
  - `validate(array $rows, array $modelMap):18` ? normalize columns; check HS master (`hs_codes` or `hs_code_pk_mappings`); resolve HS from `modelMap` or `products.hs_code` when empty; group by `PO_DOC`, count errors, build preview payload.
- Allocation: `app/Services/QuotaAllocationService.php`
  - `allocateForecast(int $productId, int $poQty, $poDate, PurchaseOrder):18` ? choose quotas matching product PK (`Quota::matchesProduct`), prioritizing periods covering PO date then future periods, sorted by narrowest range. Reduce `forecast_remaining`, log `quota_histories`, write pivot `purchase_order_quota`.

## Invoice & GR

- API in `ImportController`:
  - Invoice
    - `uploadInvoices(Request):27` ? validate/parse, save import/items.
    - `publishInvoices(Request, Import):147` ? upsert into `invoices` (keyed by `po_no,line_no,invoice_no,qty`).
  - GR
    - `uploadGr(Request):187` ? detect GR-themed sheet, normalize headers.
    - `publishGr(Request, Import):399` ? upsert into `gr_receipts` and reduce actual quota for relevant rows.
- Consumption: `app/Services/QuotaConsumptionService.php`
  - `computeForQuotas($quotas):18` ? aggregate invoice+GR per line; apply period and PK-range filters; compute consumed vs remaining.

## Dashboard & Metrics

- Controller: `app/Http/Controllers/Admin/DashboardController.php`
  - `index():17` ? light KPIs (Open PO outstanding, In-Transit, GR 30 days, quota cards, activity). ?Unmapped Model? uses `HsCodeResolver` for the current year.
- View: `resources/views/admin/dashboard.blade.php` plus partials in `resources/views/admin/partials/*`.

## Core Models & Tables

- `app/Models/Product.php` ? products (`products`).
- `app/Models/Quota.php` ? quotas (`quotas`), period/allocation columns; helper to match PK.
- `app/Models/ProductQuotaMapping.php` ? product<->quota per period (`product_quota_mappings`).
- `app/Models/PurchaseOrder.php` ? aggregates per PO number (`purchase_orders`).
- `app/Models/PoHeader.php`, `app/Models/PoLine.php` ? PO document details.
- Operational tables: `invoices`, `gr_receipts`, `quota_histories`.

## Middleware & Permissions

- `app/Http/Middleware/*` ? Role/Permission gating for admin routes.
- Permission config: `config/permission.php`.

## Listeners & Commands

- `app/Listeners/RunProductQuotaAutoMapping.php` ? triggers automapping after certain publishes.
- Commands: `app/Console/Commands/*` such as `RebuildForecast`, `RebuildActual`, `AllocBackfill*` for batch recalculation.

## Key Usage Examples

- Resolve product PK for current period:

```php
$pk = app(\App\Services\HsCodeResolver::class)->resolveForProduct($product, now()->format('Y'));
```

- Run Auto-Mapping per year:

```php
$summary = app(\App\Services\ProductQuotaAutoMapper::class)->runForPeriod('2025');
// ['mapped'=>..,'unmapped'=>..,'total_products'=>..]
```

- Allocate forecast when publishing Open PO (per line):

```php
[$allocs, $left] = app(\App\Services\QuotaAllocationService::class)
    ->allocateForecast($productId, $qty, $poDate, $purchaseOrder);
```

## Testing & Verification

- Run tests: `php artisan test`.
- For import modules, exercise Upload + Preview + Publish and review target tables plus dashboard/analytics.

## Technical Notes

- Optional column `hs_code_pk_mappings.period_key` is checked via `Schema::hasColumn`, with legacy fallback when absent.
- Periods (year/month/day) are normalized by helpers in related controllers (e.g., `MappingController::derivePeriodRange`).
- Performance: aggregates use SUM/LEFT JOIN (and filtered subqueries) to avoid N+1.

---

Need more detail for other files (final reports/shipments)? Open an issue and mention the file path.
