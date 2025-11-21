# User Guide ? Import Quota Management

This guide explains how to use the system from an administrator/super-administrator perspective. For technical details, see `docs/panduan-dev.md`.

## Key Concepts

- Products: master items/models. Important columns: `code`, `sap_model`, `hs_code`, `pk_capacity`.
- HS ? PK: reference mapping HS code to PK capacity (number or range). Stored in `hs_code_pk_mappings` by year (`period_key`) or legacy (empty).
- Quota: allocation amount for a PK range within a period (start?end dates). Government categories are parsed into PK ranges.
- Auto-Mapping: automatic pairing of Products to Quotas in a period based on product PK and quota PK range.

## New Period Workflow (Yearly)

1) Import HS ? PK
- Open: Data Preparation ? Import HS ? PK.
- Upload a file with required headers `HS_CODE`, `DESC` (CSV/XLSX).

2) Manual HS ? PK (optional)
- Open: Data Preparation ? HS-PK Manual.
- Enter HS Code, PK/Range, Period (YYYY, optional for legacy), then Save.

3) Import Quota
- Open: Data Preparation ? Import Quota.
- Upload quota file for the period (start?end dates). After Publish, the system normalizes PK categories and can run Auto-Mapping.

4) Check Unmapped Products
- Open: Data Preparation ? Unmapped Products (`/admin/mapping/unmapped/view`).
- Reasons:
  - `missing_hs`: product has no HS code.
  - `missing_pk`: HS exists but PK cannot be resolved yet (add HS?PK master).
  - `no_matching_quota`: PK exists but no quota contains the PK.
- Complete HS/PK via Import/Manual modules or add quotas so all products are mapped.

## Importing Open PO

- Menu: PO ? Import Open PO.
- Upload PO file (CSV/XLSX). Preview will highlight errors before publish.
- Publish modes:
  - `Insert add`: add new rows only.
  - `Replace`: replace all rows for POs in the file (delete then recreate).
- Forecast allocation runs per line based on product PK and quota periods.

## Importing Invoice/GR

- Menu: Import ? Invoice / GR.
- Upload ? Preview ? Publish. GR publish reduces the actual of related quotas according to GR date and model/PK.

## Dashboard & Analytics

- Dashboard shows: Unmapped Model, Open PO, In-Transit, GR (30 days), and Quota summary.
- Analytics: actual/forecast consumption per quota and CSV/XLSX/PDF export.

## File Formats & Templates

- HS?PK (CSV example):
  - Headers: `HS_CODE`, `DESC` (e.g., `8-10`, `<8`, `>10`, or a number).
- Quota (CSV example):
  - Headers: `QUOTA_NO`, `HS_CODE`, `GOV_CATEGORY`/`PK_RANGE`, `PERIOD_START`, `PERIOD_END`, `QUANTITY`.
- Open PO (CSV/XLSX): ensure key columns `PO_DOC`, `LINE_NO`, `ITEM_CODE`, `QTY`, `DELIV_DATE` (dd-mm-yyyy), etc. Missing HS will attempt resolution via model mapping/master.

## Troubleshooting

- Unmapped products still appear:
  - `missing_hs`: add HS in product master or model?HS mapping (if available); or fill HS during Open PO import.
  - `missing_pk`: add HS?PK mapping via Import/Manual.
  - `no_matching_quota`: add/adjust quotas so the PK range covers the product.
- Forecast not allocated when publishing Open PO:
  - Add quotas in the current or upcoming period for the related PK range.
- Dashboard vs Unmapped numbers differ:
  - They are now aligned; if different, check year setting (period) and HS?PK completeness.
- After publishing Open PO, watch for ?Partially unallocated? warnings and add quotas if needed.

If you need more help or want to add import formats, contact the development team and include a sample file plus the intended period.
