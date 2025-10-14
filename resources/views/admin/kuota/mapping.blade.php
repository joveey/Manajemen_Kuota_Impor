{{-- resources/views/admin/kuota/mapping.blade.php --}}
@extends('layouts.admin')

@section('title', 'Mapping Produk-Kuota')

@push('styles')
<style>
    .mapping-page { display:flex; flex-direction:column; gap:24px; }
    .mapping-metrics { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; }
    .mapping-metric {
        background:#ffffff;
        border:1px solid #e2e8f0;
        border-radius:16px;
        padding:16px;
        display:flex;
        flex-direction:column;
        gap:4px;
        box-shadow:0 24px 48px -45px rgba(15,23,42,0.4);
    }
    .mapping-metric__label { font-size:12px; text-transform:uppercase; letter-spacing:.08em; color:#64748b; }
    .mapping-metric__value { font-size:26px; font-weight:700; color:#0f172a; }
    .mapping-card { border:1px solid #e2e8f0; border-radius:18px; box-shadow:0 28px 54px -48px rgba(15,23,42,0.46); }
    .table-mapping tbody tr.primary-row { background:rgba(37,99,235,0.06); }
    .table-mapping td { vertical-align:middle; }
    .status-box {
        display:none;
        margin-bottom:16px;
        border-radius:12px;
        padding:12px 16px;
        font-size:13px;
    }
    .status-box.is-visible { display:block; }
    .status-box--success { border:1px solid rgba(34,197,94,0.4); background:rgba(34,197,94,0.12); color:#15803d; }
    .status-box--error { border:1px solid rgba(248,113,113,0.4); background:rgba(248,113,113,0.12); color:#b91c1c; }
    @media (max-width: 992px) {
        .mapping-page { gap:18px; }
        .mapping-metric__value { font-size:22px; }
    }
</style>
@endpush

@section('content')
@php
    $initialPayload = $initialMappings->map(function ($item) {
        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_code' => $item->product?->code,
            'product_name' => $item->product?->name,
            'quota_id' => $item->quota_id,
            'quota_number' => $item->quota?->quota_number,
            'quota_name' => $item->quota?->name,
            'government_category' => $item->quota?->government_category,
            'priority' => $item->priority,
            'is_primary' => (bool) $item->is_primary,
            'notes' => $item->notes,
            'updated_at' => optional($item->updated_at)->toDateTimeString(),
        ];
    });
@endphp

<div class="mapping-page">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <h1 class="h3 mb-1">Mapping Produk &amp; Kuota</h1>
            <p class="text-muted mb-0">Atur pasangan produk-ke-kuota beserta prioritas dan status primary secara real time.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.quotas.index') }}" class="btn btn-light border">
                <i class="fas fa-arrow-left me-1"></i> Data Kuota
            </a>
        </div>
    </div>

    <div class="mapping-metrics">
        <div class="mapping-metric">
            <span class="mapping-metric__label">Total Mapping</span>
            <span class="mapping-metric__value" data-summary-total>0</span>
            <span class="small text-muted">Relasi aktif produk <-> kuota</span>
        </div>
        <div class="mapping-metric">
            <span class="mapping-metric__label">Produk Dilayani</span>
            <span class="mapping-metric__value" data-summary-products>0</span>
            <span class="small text-muted">Produk yang sudah punya kuota</span>
        </div>
        <div class="mapping-metric">
            <span class="mapping-metric__label">Kuota Digunakan</span>
            <span class="mapping-metric__value" data-summary-quotas>0</span>
            <span class="small text-muted">Kuota aktif yang digunakan</span>
        </div>
        <div class="mapping-metric">
            <span class="mapping-metric__label">Primary Mapping</span>
            <span class="mapping-metric__value" data-summary-primary>0</span>
            <span class="small text-muted">Digunakan sebagai prioritas utama</span>
        </div>
    </div>

    <div class="alert alert-info border-0">
        <i class="fas fa-info-circle me-2"></i>
        Sistem otomatis menjaga agar setiap produk memiliki paling banyak satu mapping primary. Gunakan tombol <em>set primary</em> atau ubah prioritas untuk mengatur cadangan.
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mapping-card h-100">
                <div class="card-header border-0 bg-white py-3">
                    <h5 class="mb-1">Tambah Mapping Baru</h5>
                    <p class="text-muted small mb-0">Pilih produk dan kuota yang kompatibel lalu tentukan prioritasnya.</p>
                </div>
                <div class="card-body">
                    <form data-create-form>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Produk</label>
                            <select class="form-select" name="product_id" required data-product-select>
                                <option value="">-- Pilih Produk --</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->code }} &ndash; {{ $product->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Kuota</label>
                            <select class="form-select" name="quota_id" required data-quota-select>
                                <option value="">-- Pilih Kuota --</option>
                                @foreach($quotas as $quota)
                                    <option value="{{ $quota->id }}">{{ $quota->quota_number }} &ndash; {{ $quota->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Hanya kuota dengan rentang PK sesuai yang disarankan.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Prioritas</label>
                            <input type="number" class="form-control" name="priority" min="1" value="1" required data-priority-input>
                            <div class="form-text">Semakin kecil angka, semakin tinggi prioritas.</div>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="is_primary" name="is_primary" value="1">
                            <label class="form-check-label" for="is_primary">Tetapkan sebagai primary</label>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Catatan (opsional)</label>
                            <textarea class="form-control" name="notes" rows="3" maxlength="1000" placeholder="Contoh: Kuota utama untuk proyek pemerintah"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-1"></i> Simpan Mapping
                        </button>
                    </form>
                </div>
                <div class="card-footer bg-white border-0">
                    <div class="small text-muted">
                        Prioritas dipakai oleh alokasi PO otomatis. Mapping baru otomatis menjadi primary bila belum ada primary lain untuk produk tersebut.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mapping-card">
                <div class="card-header border-0 bg-white">
                    <div class="d-flex flex-wrap gap-3 align-items-end">
                        <div>
                            <h5 class="mb-1">Daftar Mapping Aktif</h5>
                            <p class="text-muted small mb-0">Atur ulang prioritas dan primary tanpa perlu memuat ulang halaman.</p>
                        </div>
                        <form class="row g-2 align-items-end ms-auto" data-filter-form>
                            <div class="col-md-4">
                                <label class="form-label small text-muted mb-1">Produk</label>
                                <select class="form-select form-select-sm" name="product_id" data-filter-input>
                                    <option value="">Semua Produk</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->code }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted mb-1">Kuota</label>
                                <select class="form-select form-select-sm" name="quota_id" data-filter-input>
                                    <option value="">Semua Kuota</option>
                                    @foreach($quotas as $quota)
                                        <option value="{{ $quota->id }}">{{ $quota->quota_number }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 col-lg-3">
                                <label class="form-label small text-muted mb-1">Cari</label>
                                <input type="search" class="form-control form-control-sm" name="search" placeholder="Kode / nama" data-filter-input>
                            </div>
                            <div class="col-auto">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-filter-reset>
                                    <i class="fas fa-rotate-left me-1"></i> Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <div class="status-box" data-status></div>
                    <div class="alert alert-light border d-none align-items-center gap-2 py-2 px-3" data-loading>
                        <span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>
                        <span>Memuat data terbaru...</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-mapping align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:22%">Produk</th>
                                    <th style="width:24%">Kuota</th>
                                    <th class="text-center" style="width:10%">Prioritas</th>
                                    <th class="text-center" style="width:12%">Primary</th>
                                    <th style="width:24%">Catatan</th>
                                    <th class="text-end" style="width:18%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody data-table-body>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Memuat data awal...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const routes = {
        index: '{{ route('admin.product-quotas.index') }}',
        store: '{{ route('admin.product-quotas.store') }}',
        reorder: '{{ route('admin.product-quotas.reorder') }}',
        update: '{{ route('admin.product-quotas.update', ['productQuotaMapping' => '__ID__']) }}',
        destroy: '{{ route('admin.product-quotas.destroy', ['productQuotaMapping' => '__ID__']) }}'
    };

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const tableBody = document.querySelector('[data-table-body]');
    const statusBox = document.querySelector('[data-status]');
    const loader = document.querySelector('[data-loading]');
    const summaryEls = {
        total: document.querySelector('[data-summary-total]'),
        products: document.querySelector('[data-summary-products]'),
        quotas: document.querySelector('[data-summary-quotas]'),
        primary: document.querySelector('[data-summary-primary]')
    };
    const createForm = document.querySelector('[data-create-form]');
    const filterForm = document.querySelector('[data-filter-form]');
    const filterReset = document.querySelector('[data-filter-reset]');
    const productSelect = document.querySelector('[data-product-select]');
    const priorityInput = document.querySelector('[data-priority-input]');
    const primaryCheckbox = document.getElementById('is_primary');

    let filters = { product_id: '', quota_id: '', search: '' };
    let records = (@json($initialPayload ?? []))?.map(normalize) ?? [];

    render();
    fetchData();

    if (createForm) {
        createForm.addEventListener('submit', function (event) {
            event.preventDefault();
            setStatus(null);

            const formData = new FormData(createForm);
            request(routes.store, { method: 'POST', formData })
                .then(function (payload) {
                    if (payload) {
                        records.push(normalize(payload));
                        render();
                        setStatus('success', 'Mapping berhasil ditambahkan.');
                    }
                    createForm.reset();
                    if (priorityInput) {
                        priorityInput.value = '1';
                    }
                })
                .catch(handleError);
        });
    }

    if (productSelect && priorityInput) {
        productSelect.addEventListener('change', function () {
            const productId = Number(productSelect.value || 0);
            if (!productId) {
                priorityInput.value = '1';
                if (primaryCheckbox) {
                    primaryCheckbox.checked = false;
                }
                return;
            }

            const related = records.filter(function (item) {
                return item.product_id === productId;
            });

            if (!related.length) {
                priorityInput.value = '1';
                if (primaryCheckbox) {
                    primaryCheckbox.checked = true;
                }
            } else {
                const maxPriority = Math.max.apply(null, related.map(function (item) {
                    return item.priority;
                }));
                priorityInput.value = String(maxPriority + 1);
                if (primaryCheckbox) {
                    primaryCheckbox.checked = false;
                }
            }
        });
    }

    if (filterForm) {
        filterForm.addEventListener('change', function (event) {
            if (!event.target.matches('[data-filter-input]')) {
                return;
            }
            applyFilters();
            fetchData();
        });

        const searchInput = filterForm.querySelector('input[name="search"]');
        if (searchInput) {
            const debouncedSearch = debounce(function () {
                filters.search = searchInput.value.trim();
                fetchData();
            }, 350);
            searchInput.addEventListener('input', debouncedSearch);
        }
    }

    if (filterReset) {
        filterReset.addEventListener('click', function () {
            if (filterForm) {
                filterForm.reset();
            }
            filters = { product_id: '', quota_id: '', search: '' };
            fetchData();
        });
    }

    if (tableBody) {
        tableBody.addEventListener('click', function (event) {
            const button = event.target.closest('[data-action]');
            if (!button) {
                return;
            }

            const id = Number(button.getAttribute('data-id'));
            if (!id) {
                return;
            }

            const action = button.getAttribute('data-action');
            if (action === 'promote') {
                reorder(id, button.getAttribute('data-direction'));
            } else if (action === 'set-primary') {
                setPrimary(id);
            } else if (action === 'edit-notes') {
                editNotes(id);
            } else if (action === 'delete') {
                destroyMapping(id);
            }
        });
    }

    function normalize(raw) {
        if (!raw) {
            return null;
        }

        return {
            id: Number(raw.id),
            product_id: Number(raw.product_id),
            product_code: raw.product?.code ?? raw.product_code ?? '',
            product_name: raw.product?.name ?? raw.product_name ?? '',
            quota_id: Number(raw.quota_id),
            quota_number: raw.quota?.quota_number ?? raw.quota_number ?? '',
            quota_name: raw.quota?.name ?? raw.quota_name ?? '',
            government_category: raw.quota?.government_category ?? raw.government_category ?? '',
            priority: Number(raw.priority),
            is_primary: Boolean(raw.is_primary),
            notes: raw.notes ?? '',
            updated_at: raw.updated_at ?? null
        };
    }

    function render() {
        if (!tableBody) {
            return;
        }

        const groups = new Map();
        records.forEach(function (item) {
            if (!item) {
                return;
            }
            if (!groups.has(item.product_id)) {
                groups.set(item.product_id, []);
            }
            groups.get(item.product_id).push(item);
        });

        const orderIndex = new Map();
        groups.forEach(function (list) {
            list.sort(function (a, b) {
                if (a.priority !== b.priority) {
                    return a.priority - b.priority;
                }
                return a.id - b.id;
            });
            list.forEach(function (item, index) {
                orderIndex.set(item.id, { index: index, total: list.length });
            });
        });

        const sorted = records.slice().sort(function (a, b) {
            const byName = a.product_name.localeCompare(b.product_name);
            if (byName !== 0) {
                return byName;
            }
            const posA = orderIndex.get(a.id)?.index ?? 0;
            const posB = orderIndex.get(b.id)?.index ?? 0;
            if (posA !== posB) {
                return posA - posB;
            }
            return a.quota_number.localeCompare(b.quota_number);
        });

        updateSummary(sorted);

        if (!sorted.length) {
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Belum ada mapping untuk filter ini.</td></tr>';
            return;
        }

        tableBody.innerHTML = sorted.map(function (item) {
            const order = orderIndex.get(item.id) ?? { index: 0, total: 1 };
            const disableUp = order.index === 0 ? 'disabled' : '';
            const disableDown = order.index === order.total - 1 ? 'disabled' : '';

            return [
                '<tr class="' + (item.is_primary ? 'primary-row' : '') + '">',
                '<td><div class="fw-semibold">' + escapeHtml(item.product_code) + '</div>',
                '<div class="text-muted small">' + escapeHtml(item.product_name) + '</div></td>',
                '<td><div class="fw-semibold">' + escapeHtml(item.quota_number) + '</div>',
                '<div class="text-muted small">' + escapeHtml(item.quota_name) + '</div>',
                item.government_category ? '<div class="text-muted small">' + escapeHtml(item.government_category) + '</div>' : '',
                '</td>',
                '<td class="text-center"><span class="badge text-bg-primary">#' + item.priority + '</span></td>',
                '<td class="text-center">' + (item.is_primary
                    ? '<span class="badge text-bg-success">Primary</span>'
                    : '<span class="badge text-bg-secondary">Cadangan</span>') + '</td>',
                '<td>' + (item.notes ? '<div class="small">' + escapeHtml(item.notes) + '</div>' : '<span class="text-muted">-</span>') + '</td>',
                '<td class="text-end"><div class="btn-group btn-group-sm">',
                '<button class="btn btn-outline-secondary" data-action="promote" data-direction="up" data-id="' + item.id + '" ' + disableUp + ' title="Naikkan prioritas"><i class="fas fa-arrow-up"></i></button>',
                '<button class="btn btn-outline-secondary" data-action="promote" data-direction="down" data-id="' + item.id + '" ' + disableDown + ' title="Turunkan prioritas"><i class="fas fa-arrow-down"></i></button>',
                '<button class="btn btn-outline-primary" data-action="set-primary" data-id="' + item.id + '" title="Tetapkan sebagai primary"><i class="fas fa-star"></i></button>',
                '<button class="btn btn-outline-info" data-action="edit-notes" data-id="' + item.id + '" title="Ubah catatan"><i class="fas fa-pen"></i></button>',
                '<button class="btn btn-outline-danger" data-action="delete" data-id="' + item.id + '" title="Hapus mapping"><i class="fas fa-trash"></i></button>',
                '</div></td>',
                '</tr>'
            ].join('');
        }).join('');
    }

    function updateSummary(list) {
        const data = Array.isArray(list) ? list : [];

        if (summaryEls.total) {
            summaryEls.total.textContent = formatNumber(data.length);
        }
        if (summaryEls.products) {
            summaryEls.products.textContent = formatNumber(new Set(data.map(function (item) {
                return item.product_id;
            })).size);
        }
        if (summaryEls.quotas) {
            summaryEls.quotas.textContent = formatNumber(new Set(data.map(function (item) {
                return item.quota_id;
            })).size);
        }
        if (summaryEls.primary) {
            summaryEls.primary.textContent = formatNumber(data.filter(function (item) {
                return item.is_primary;
            }).length);
        }
    }

    function fetchData() {
        setLoading(true);

        const params = new URLSearchParams({ per_page: '500' });
        if (filters.product_id) {
            params.set('product_id', filters.product_id);
        }
        if (filters.quota_id) {
            params.set('quota_id', filters.quota_id);
        }
        if (filters.search) {
            params.set('search', filters.search);
        }

        request(routes.index + '?' + params.toString(), { method: 'GET' })
            .then(function (payload) {
                const rows = payload?.data ?? payload ?? [];
                records = Array.isArray(rows) ? rows.map(normalize).filter(Boolean) : [];
                render();
            })
            .catch(handleError)
            .finally(function () {
                setLoading(false);
            });
    }

    function reorder(id, direction) {
        const current = records.find(function (item) {
            return item.id === id;
        });
        if (!current) {
            return;
        }

        const list = records.filter(function (item) {
            return item.product_id === current.product_id;
        }).sort(function (a, b) {
            return a.priority - b.priority;
        });

        const index = list.findIndex(function (item) {
            return item.id === id;
        });
        if (index === -1) {
            return;
        }

        if (direction === 'up' && index === 0) {
            return;
        }

        if (direction === 'down' && index === list.length - 1) {
            return;
        }

        const swapIndex = direction === 'up' ? index - 1 : index + 1;
        const reordered = list.slice();
        reordered[index] = list[swapIndex];
        reordered[swapIndex] = list[index];

        const formData = new FormData();
        formData.append('product_id', String(current.product_id));
        reordered.forEach(function (item) {
            formData.append('order[]', String(item.id));
        });

        request(routes.reorder, { method: 'POST', formData })
            .then(function () {
                setStatus('success', 'Prioritas mapping diperbarui.');
                fetchData();
            })
            .catch(handleError);
    }

    function setPrimary(id) {
        request(routes.update.replace('__ID__', String(id)), {
            method: 'PATCH',
            json: { is_primary: 1 }
        })
            .then(function () {
                setStatus('success', 'Mapping berhasil dijadikan primary.');
                fetchData();
            })
            .catch(handleError);
    }

    function editNotes(id) {
        const current = records.find(function (item) {
            return item.id === id;
        });
        if (!current) {
            return;
        }

        const save = function (value) {
            request(routes.update.replace('__ID__', String(id)), {
                method: 'PATCH',
                json: { notes: value ?? '' }
            })
                .then(function () {
                    setStatus('success', 'Catatan mapping diperbarui.');
                    fetchData();
                })
                .catch(handleError);
        };

        if (window.Swal) {
            window.Swal.fire({
                title: 'Ubah Catatan',
                input: 'textarea',
                inputValue: current.notes ?? '',
                inputAttributes: { maxlength: 1000 },
                confirmButtonText: 'Simpan',
                cancelButtonText: 'Batal',
                showCancelButton: true,
                allowOutsideClick: false
            }).then(function (result) {
                if (result.isConfirmed) {
                    save(result.value ?? '');
                }
            });
        } else {
            const value = window.prompt('Ubah catatan mapping:', current.notes ?? '');
            if (value !== null) {
                save(value);
            }
        }
    }

    function destroyMapping(id) {
        const remove = function () {
            request(routes.destroy.replace('__ID__', String(id)), { method: 'DELETE' })
                .then(function () {
                    setStatus('success', 'Mapping berhasil dihapus.');
                    fetchData();
                })
                .catch(handleError);
        };

        if (window.Swal) {
            window.Swal.fire({
                title: 'Hapus mapping?',
                text: 'Relasi produk dan kuota akan dihapus.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                confirmButtonText: 'Hapus',
                cancelButtonText: 'Batal'
            }).then(function (result) {
                if (result.isConfirmed) {
                    remove();
                }
            });
        } else if (window.confirm('Hapus mapping ini?')) {
            remove();
        }
    }

    function applyFilters() {
        if (!filterForm) {
            return;
        }
        const formData = new FormData(filterForm);
        filters.product_id = String(formData.get('product_id') ?? '');
        filters.quota_id = String(formData.get('quota_id') ?? '');
        const search = formData.get('search');
        filters.search = typeof search === 'string' ? search.trim() : '';
    }

    function setLoading(active) {
        if (!loader) {
            return;
        }
        loader.classList.toggle('d-none', !active);
    }

    function setStatus(type, message) {
        if (!statusBox) {
            return;
        }

        statusBox.classList.remove('is-visible', 'status-box--success', 'status-box--error');

        if (!message) {
            statusBox.textContent = '';
            return;
        }

        statusBox.textContent = message;
        statusBox.classList.add('is-visible');
        statusBox.classList.add(type === 'success' ? 'status-box--success' : 'status-box--error');
    }

    function handleError(error) {
        console.error(error);
        setStatus('error', extractMessage(error));
    }

    function extractMessage(error) {
        if (error?.data?.message) {
            return error.data.message;
        }
        if (error?.data?.errors) {
            const first = Object.values(error.data.errors)[0];
            if (Array.isArray(first) && first.length) {
                return first[0];
            }
        }
        if (error?.status === 422) {
            return 'Data tidak valid. Periksa kembali input Anda.';
        }
        if (error?.status === 404) {
            return 'Data tidak ditemukan atau sudah dihapus.';
        }
        return 'Terjadi kesalahan pada server.';
    }

    function request(url, options) {
        const config = {
            method: options?.method ?? 'GET',
            headers: { Accept: 'application/json' }
        };

        if (config.method !== 'GET') {
            config.headers['X-CSRF-TOKEN'] = csrfToken;
        }

        if (options?.formData) {
            config.body = options.formData;
        } else if (options?.json) {
            config.headers['Content-Type'] = 'application/json';
            config.body = JSON.stringify(options.json);
        }

        return fetch(url, config).then(function (response) {
            const contentType = response.headers.get('content-type') ?? '';
            const expectsJson = contentType.includes('application/json');

            return (expectsJson ? response.json().catch(function () { return null; }) : Promise.resolve(null))
                .then(function (data) {
                    if (!response.ok) {
                        const error = new Error('Request failed');
                        error.status = response.status;
                        error.data = data;
                        throw error;
                    }
                    return data;
                });
        });
    }

    function escapeHtml(value) {
        if (typeof value !== 'string') {
            return value ?? '';
        }
        return value
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatNumber(value) {
        return new Intl.NumberFormat('id-ID').format(value ?? 0);
    }

    function debounce(fn, delay) {
        let timer = null;
        return function () {
            const args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(null, args);
            }, delay);
        };
    }
})();
</script>
@endpush
