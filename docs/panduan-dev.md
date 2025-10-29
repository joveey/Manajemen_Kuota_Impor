# Panduan Pengembang – Struktur File & Fungsi

Dokumen ini memetakan file penting, tanggung jawab modul, serta cara fungsi‑fungsi utamanya bekerja. Fokus pada domain HS→PK, Kuota, Open PO, Invoice/GR, dan Dashboard.

## Arsitektur Singkat

- Laravel (Controllers → Services → Models → Views), dengan route grup admin di `routes/web.php`.
- Data impor melalui `ImportController` (API) lalu UI per modul import.*PageController.
- Automasi pemetaan Produk→Kuota dikelola oleh `ProductQuotaAutoMapper` dan Listener terkait.

## Rute Utama

- `routes/web.php` – grup admin, prefix `admin.`
  - HS→PK page: `admin.imports.hs_pk.*` (`resources/views/admin/imports/hs_pk/...`).
  - HS→PK manual: `admin.hs_pk.manual.*`.
  - Mapping diagnostic: `admin.mapping.unmapped`, `admin.mapping.unmapped.page`.
  - Open PO: `admin.openpo.*`.
  - Invoice: `admin.imports.invoices.*`, GR: `admin.imports.gr.*`.
  - Dashboard: `admin.dashboard`.

## Modul HS→PK

- UI: `resources/views/admin/imports/hs_pk/index.blade.php` – form upload, validasi client‑side, tombol CSV contoh.
- Page controller: `app/Http/Controllers/Admin/HsPkImportPageController.php`
  - `index():13` – list 25 import terbaru.
  - `uploadForm(Request):24` – validasi file, delegasi ke API `ImportController::uploadHsPk`, redirect preview.
  - `preview(Import):49` – tampilkan ringkasan import.
  - `publishForm(Request, Import):55` – delegasi ke API `ImportController::publish` untuk tipe HS/PK.
- API: `app/Http/Controllers/Admin/ImportController.php`
  - `uploadHsPk(Request):506` –
    - Membaca sheet “HS code master” (fallback first sheet). Header wajib `HS_CODE`, `DESC`.
    - Parse `DESC` via `PkCategoryParser` menjadi rentang PK dan anchor.
    - Simpan baris ke `imports` + `import_items` (status normalized/error).
    - Response JSON: `import_id`, `status`, ringkasan total/valid/error.
  - `publish(Request, Import):1089` – upsert ke `hs_code_pk_mappings` (mendukung kolom `period_key` jika ada), tandai import published dan opsional memicu automapping.
- Manual: `app/Http/Controllers/Admin/HsPkManualController.php`
  - `index(Request):15` – paginate daftar mapping; filter `period_key`.
  - `store(Request):45` – validasi input; parse `pk_value`; hitung anchor; upsert unique (hs_code[, period_key]).
- Resolver: `app/Services/HsCodeResolver.php`
  - `resolveForProduct(Product, ?string $period):14` –
    - Ambil mapping di `hs_code_pk_mappings` untuk `hs_code` produk; prefer `period_key` (tahun) yang cocok, fallback legacy.
    - Jika tidak ada, fallback ke `product.pk_capacity`.
    - Return `float|null` PK anchor.
- Parser: `app/Support/PkCategoryParser.php`
  - `parse(string $label):9` – dukung `8-10`, `<8`, `>10`, angka tunggal. Kembalikan rentang dan inklusivitas.

## Produk Unmapped (Diagnostik)

- Page: `resources/views/admin/mapping/unmapped.blade.php` – filter Period/Reason, fetch ke API JSON, render tabel.
- API: `app/Http/Controllers/Admin/MappingController.php`
  - `unmapped(Request):28` –
    - Derive rentang tanggal dari Period (YYYY / YYYY‑MM / YYYY‑MM‑DD).
    - Untuk setiap `products`, tentukan alasan:
      - `missing_hs`: kolom `hs_code` kosong.
      - `missing_pk`: `hs_code` ada tapi resolver gagal temukan PK.
      - `no_matching_quota`: PK ada, namun tidak ada kuota yang mencakup PK untuk periode terkait.
    - Paginasi in‑memory; kembalikan JSON (`total`, `current_page`, `last_page`, `data`).

## Kuota

- UI Import: `resources/views/admin/imports/quotas/*` (index/preview).
- Page controller: `app/Http/Controllers/Admin/QuotaImportPageController.php`
  - `index()` – daftar import.
  - `uploadForm(Request)` – delegasi ke API `ImportController::uploadQuotas`.
  - `preview(Import)` – ringkasan.
  - `publishForm(Request, Import)` – delegasi ke API `ImportController::publishQuotas`.
- Automapping Produk→Kuota: `app/Services/ProductQuotaAutoMapper.php`
  - `runForPeriod(string|int $periodKey):22` –
    - Normalisasi rentang PK kuota (parse `government_category`).
    - Bersihkan mapping `source='auto'` untuk `period_key` terkait.
    - Resolve PK produk via `HsCodeResolver`, cari kuota kandidat yang mencakup PK.
    - Tentukan primary (rentang tersempit), sisanya backup; upsert ke `product_quota_mappings`.
  - `queryQuotasByPeriodKey($key):163` – pilih kuota yang overlap periode (tahun/bulan/hari).

## Open PO & Alokasi Forecast

- UI: `resources/views/admin/openpo/*` (import, preview).
- Controller: `app/Http/Controllers/Admin/OpenPoImportController.php`
  - `form():24` – halaman unggah.
  - `preview():29` – baca file via `OpenPoReader`, validasi via `OpenPoValidator`, simpan preview ke session.
  - `previewPage():57` – memuat ulang preview dari session.
  - `publish(Request):73` –
    - Menulis `po_headers`/`po_lines` (mode `insert`/`replace`).
    - Membuat/menambah `purchase_orders` untuk driving forecast.
    - Per line: alokasikan forecast via service; tandai `forecast_allocated_at` pada `po_lines` agar tidak dobel.
- Validasi: `app/Services/OpenPoValidator.php`
  - `validate(array $rows, array $modelMap):18` –
    - Normalisasi kolom; cek HS master (`hs_codes` atau `hs_code_pk_mappings`).
    - Resolve HS dari `modelMap` atau `products.hs_code` bila kosong.
    - Kelompokkan per `PO_DOC`, hitung error, dan susun payload preview.
- Alokasi: `app/Services/QuotaAllocationService.php`
  - `allocateForecast(int $productId, int $poQty, $poDate, PurchaseOrder):18` –
    - Pilih kuota kandidat yang match PK produk (via `Quota::matchesProduct`), prioritaskan periode yang memuat tanggal PO lalu periode mendatang, urutkan rentang tersempit.
    - Kurangi `forecast_remaining`, log `quota_histories`, tulis pivot `purchase_order_quota`.

## Invoice & GR

- API di `ImportController`:
  - Invoice
    - `uploadInvoices(Request):27` – validasi & parse, simpan import/items.
    - `publishInvoices(Request, Import):147` – upsert ke `invoices` (kunci `po_no,line_no,invoice_no,qty`).
  - GR
    - `uploadGr(Request):187` – deteksi sheet bertema GR, normalisasi header.
    - `publishGr(Request, Import):399` – upsert ke `gr_receipts`, dan kurangi actual kuota untuk baris relevan (lihat akhir fungsi publish GR untuk pengurangan actual).
- Konsumsi: `app/Services/QuotaConsumptionService.php`
  - `computeForQuotas($quotas):18` – agregasi invoice+GR per line; terapkan filter periode dan rentang PK; hitung consumed dan remaining.

## Dashboard & Metrik

- Controller: `app/Http/Controllers/Admin/DashboardController.php`
  - `index():17` – hitung KPI ringan (Open PO outstanding, In‑Transit, GR 30 hari, kartu kuota, aktivitas). “Unmapped Model” memakai `HsCodeResolver` untuk tahun berjalan.
- View: `resources/views/admin/dashboard.blade.php` + partial `resources/views/admin/partials/*`.

## Models & Tabel Inti

- `app/Models/Product.php` – master produk (`products`).
- `app/Models/Quota.php` – kuota (`quotas`), kolom periode dan alokasi; menyediakan helper kecocokan PK.
- `app/Models/ProductQuotaMapping.php` – relasi produk↔kuota per periode (`product_quota_mappings`).
- `app/Models/PurchaseOrder.php` – agregat per nomor PO (`purchase_orders`).
- `app/Models/PoHeader.php`, `app/Models/PoLine.php` – detail dokumen PO.
- Tabel operasional: `invoices`, `gr_receipts`, `quota_histories`.

## Middleware & Permissions

- `app/Http/Middleware/*` – Role/Permission gating untuk rute admin.
- Konfigurasi laravel‑permission: `config/permission.php`.

## Listener & Commands

- `app/Listeners/RunProductQuotaAutoMapping.php` – memicu automapping pasca publish tertentu.
- Commands: `app/Console/Commands/*` seperti `RebuildForecast`, `RebuildActual`, `AllocBackfill*` untuk rekalkulasi batch.

## Contoh Penggunaan Fungsi Kunci

- Resolve PK produk untuk periode berjalan:

```php
$pk = app(\App\Services\HsCodeResolver::class)->resolveForProduct($product, now()->format('Y'));
```

- Jalankan Auto‑Mapping per tahun:

```php
$summary = app(\App\Services\ProductQuotaAutoMapper::class)->runForPeriod('2025');
// ['mapped'=>..,'unmapped'=>..,'total_products'=>..]
```

- Alokasikan forecast saat publish Open PO (per line):

```php
[$allocs, $left] = app(\App\Services\QuotaAllocationService::class)
    ->allocateForecast($productId, $qty, $poDate, $purchaseOrder);
```

## Testing & Verifikasi

- Jalankan test: `php artisan test`.
- Untuk modul impor, uji flow Upload → Preview → Publish, lalu cek tabel target dan dashboard/analytics.

## Catatan Teknis

- Kolom opsional `hs_code_pk_mappings.period_key` diperiksa dengan `Schema::hasColumn` dan fallback ke legacy.
- Periode (tahun/bulan/hari) dinormalisasi oleh utilitas di controller terkait (mis. `MappingController::derivePeriodRange`).
- Kinerja: agregasi menggunakan query SUM/LEFT JOIN/LEFT JOIN SUB untuk menghindari N+1.

---

Butuh penjelasan tambahan untuk file lain (mis. laporan final/shipments)? Tambahkan isu dan sebutkan path file.

