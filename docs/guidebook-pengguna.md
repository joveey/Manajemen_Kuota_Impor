# Guidebook Pengguna – Manajemen Kuota Impor

Dokumen ini menjelaskan cara menggunakan sistem dari sudut pandang pengguna (administrator/super administrator). Untuk penjelasan teknis file dan fungsi, lihat `docs/panduan-dev.md`.

## Konsep Utama

- Produk: master barang/model. Kolom penting: `code`, `sap_model`, `hs_code`, `pk_capacity`.
- HS → PK: referensi yang memetakan HS code ke kapasitas PK (angka atau rentang). Disimpan di tabel `hs_code_pk_mappings` per tahun (`period_key`) atau legacy (kosong).
- Kuota: alokasi jumlah untuk rentang PK dalam suatu periode (tanggal mulai–akhir). Kategori pemerintah akan diparse menjadi rentang PK.
- Auto‑Mapping: proses otomatis memasangkan Produk ke Kuota pada suatu periode berdasarkan PK produk dan rentang PK kuota.
- Open PO: sumber forecast. Invoice menambah forecast (in‑transit), GR mengurangi actual.

## Peran & Hak Akses

- Menu dilindungi permission. Umumnya Administrator dan Super Administrator dapat mengakses semua menu persiapan data dan operasional.

## Alur Kerja Periode Baru (Tahunan)

1) Import HS → PK

- Buka: Persiapan Data → Import HS → PK (`/admin/imports/hs-pk`).
- Isi Periode (YYYY). Boleh dikosongkan untuk mapping legacy.
- Upload berkas `.xlsx`, `.xls`, atau `.csv`.
  - Excel: gunakan sheet bernama “HS code master”.
  - Header minimal: `HS_CODE`, `DESC`.
  - Format `DESC` yang dikenali: `8-10`, `<8`, `>10`, atau angka tunggal (mis. `8`). Teks “PK” diabaikan.
- Submit → halaman Preview → klik Publish untuk menyimpan ke master.

2) Input Manual (opsional)

- Buka: Persiapan Data → Import HS → PK → “Input Manual HS → PK”.
- Masukkan HS Code, PK/Range, Periode (YYYY, boleh kosong untuk legacy), lalu Simpan.

3) Import Kuota

- Buka: Persiapan Data → Import Kuota.
- Upload berkas kuota untuk periode (tanggal mulai–akhir). Setelah Publish, sistem akan menormalisasi kategori PK dan dapat menjalankan Auto‑Mapping.

4) Cek Produk Unmapped

- Buka: Persiapan Data → Produk Unmapped (`/admin/mapping/unmapped/view`).
- Filter `Period` dan `Reason`:
  - `missing_hs`: Produk belum memiliki HS.
  - `missing_pk`: HS ada tetapi belum bisa di-resolve ke PK (tambahkan master HS→PK).
  - `no_matching_quota`: PK sudah ada tetapi tidak ada kuota yang menampung PK.
- Lengkapi HS/PK di modul Import/Manual atau tambah kuota agar semua produk termapping.

## Operasional Harian

1) Import Open PO

- Buka: Operasional → Open PO → Import.
- Upload file; Preview akan menampilkan grup per PO dan error per baris.
- Pilih mode Publish:
  - `Insert`: menambahkan baris baru; duplikat dilewati.
  - `Replace`: mengganti seluruh line pada PO yang sama (hapus dulu lalu buat ulang).
- Publish akan:
  - Membuat/memperbarui header `po_headers` dan `po_lines`.
  - Membuat/menambah `purchase_orders` per PO untuk alokasi forecast.
  - Mengalokasikan forecast ke kuota terdekat berdasarkan PK dan periode (yang memuat tanggal PO maupun periode mendatang).

2) Import Invoice

- Buka: Operasional → Import Invoice.
- Upload → Preview → Publish. Menambah forecast (in‑transit) dengan mengacu `po_no`/`line_no`.

3) Import GR (Good Receipt)

- Buka: Operasional → Import GR.
- Upload → Preview → Publish. Mengurangi actual dari kuota terkait sesuai tanggal GR dan model/PK.

## Monitoring & Laporan

- Dashboard (`/admin/dashboard`)
  - Unmapped Model: jumlah produk yang belum bisa di-resolve ke PK (atau tidak ada HS) untuk tahun berjalan. Angka ini konsisten dengan halaman “Produk Unmapped”.
  - Open PO, In‑Transit, GR (30 hari) dan ringkasan Kuota.
- PO Progress: ringkasan status line PO (ordered/partial/completed) dan statistik pengiriman.
- Analytics: konsumsi actual/forecast per kuota dan ekspor CSV/XLSX/PDF.

## Format Berkas & Template

- HS→PK (CSV contoh):

  ```csv
  HS_CODE,DESC
  0101.21.00,PK 8-10
  0101.29.10,<8
  0101.29.90,>10
  ```

- Open PO: kolom yang umum dipakai antara lain `PO_DOC`, `LINE_NO`, `ITEM_CODE`, `QTY`, `CREATED_DATE`, `HS_CODE` (opsional), dan beberapa kolom operasional (WH/SUBINV/kategori). Detail validasi ada di sistem saat Preview.
- Invoice: `PO_NO`, `LINE_NO`, `INVOICE_NO`, `INVOICE_DATE`, `QTY`.
- GR: kolom bervariasi; sistem akan mencari sheet bertajuk GR dan memetakan `PO_NO`, `LINE_NO`, `RECEIVE_DATE`, `QTY`.

## Troubleshooting

- “Produk Unmapped” masih muncul:
  - Reason `missing_hs`: tambah HS di master produk atau mapping model→HS (jika modul tersebut tersedia); atau isi HS saat import Open PO.
  - Reason `missing_pk`: tambahkan mapping HS→PK di Import/Manual.
  - Reason `no_matching_quota`: tambahkan/ubah kuota agar rentang PK mencakup produk.
- Forecast tidak teralokasi saat Publish Open PO:
  - Tambahkan kuota pada periode berjalan atau periode mendatang untuk rentang PK terkait.
- Perbedaan angka Dashboard vs Produk Unmapped:
  - Kini sudah disamakan; bila berbeda, cek setting tahun (periode) dan kelengkapan HS→PK.

## Tips Operasional

- Lakukan Import HS→PK sebelum Import Kuota agar Auto‑Mapping akurat.
- Setelah publish Open PO, cek peringatan “Sebagian tidak teralokasi” dan tambah kuota jika perlu.
- Upload Invoice/GR secara berkala agar metrik In‑Transit dan Actual selalu mutakhir.

---

Jika membutuhkan bantuan lebih lanjut atau ingin menambah format impor, hubungi tim pengembang dan sertakan contoh berkas serta periode yang dimaksud.

