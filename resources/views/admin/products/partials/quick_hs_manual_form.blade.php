@php
    $modelValue = old('model', $model ?? '');
    $hsValue = old('hs_code');
    $pkValue = old('pk_capacity');
    $categoryValue = old('category');
    $periodValue = old('period_key', $periodKey ?? '');
    $backDestination = $backUrl ?? route('admin.master.quick_hs.index');
    $showCancel = $showCancel ?? true;
@endphp

<div class="alert alert-info mb-4" role="alert">
    <strong>Tips:</strong> Isi minimal Model/SKU dan HS Code. Jika model sudah ada, HS Code akan diperbarui.
    Tambahkan kapasitas PK atau kategori bila tersedia untuk meningkatkan akurasi pemetaan.
</div>

<form method="POST" action="{{ route('admin.master.quick_hs.store') }}" class="row g-3">
    @csrf
    <div class="col-md-6">
        <label class="form-label">Model/SKU</label>
        <input type="text" name="model" value="{{ $modelValue }}" class="form-control @error('model') is-invalid @enderror" maxlength="100" required>
        @error('model')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">HS Code</label>
        <select id="hs_code_select" name="hs_code" class="form-select @error('hs_code') is-invalid @enderror" required>
            <option value="" disabled {{ $hsValue ? '' : 'selected' }} hidden>Pilih HS</option>
            @if(!empty($hsSeedOptions ?? []))
                @foreach(($hsSeedOptions ?? []) as $opt)
                    <option value="{{ $opt['id'] }}" data-desc="{{ $opt['desc'] ?? '' }}" data-pk="{{ $opt['pk'] ?? '' }}" @selected($hsValue === ($opt['id'] ?? null))>{{ $opt['text'] }}</option>
                @endforeach
            @endif
        </select>
        <div class="form-text" id="hs_desc_help">Deskripsi otomatis berdasarkan HS.</div>
        @error('hs_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Deskripsi PK</label>
        <input type="text" id="hs_desc_text" class="form-control" value="" readonly>
        <input type="hidden" name="pk_capacity" id="pk_capacity_hidden" value="{{ $pkValue }}">
        @error('pk_capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Deskripsi Produk (Optional)</label>
        <input type="text" name="category" value="{{ $categoryValue }}" class="form-control @error('category') is-invalid @enderror" maxlength="100">
        @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <input type="hidden" name="period_key" value="{{ $periodValue }}">
    <input type="hidden" name="return" value="{{ $backDestination }}">

    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Simpan
        </button>
        @if($showCancel)
            <a href="{{ $backDestination }}" class="btn btn-outline-secondary">
                <i class="fas fa-rotate-left me-1"></i> Batal
            </a>
        @endif
    </div>
</form>

<script>
(function(){
    const hsSel = document.getElementById('hs_code_select');
    const help = document.getElementById('hs_desc_help');
    const descInput = document.getElementById('hs_desc_text');
    const pkHidden = document.getElementById('pk_capacity_hidden');

    async function loadHsOptions(search=''){
        const url = new URL(@json(route('admin.imports.quotas.hs-options')));
        if (search) url.searchParams.set('q', search);
        try{
            const resp = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
            const data = await resp.json();
            const val = @json($hsValue ?? '');
            hsSel.innerHTML = '<option value="" disabled hidden>Pilih HS</option>';
            (data || []).forEach(function(opt){
                const o = document.createElement('option');
                o.value = opt.id; o.textContent = opt.text; o.setAttribute('data-desc', opt.desc || ''); o.setAttribute('data-pk', opt.pk ?? '');
                if (val && val === String(opt.id)) { o.selected = true; }
                hsSel.appendChild(o);
            });
            if (hsSel.value) { applyHsMeta(); }
        }catch(e){ console.error('HS options failed', e); }
    }

    function applyHsMeta(){
        const opt = hsSel.selectedOptions[0];
        if (!opt) return;
        const desc = opt.getAttribute('data-desc') || '';
        const pk = opt.getAttribute('data-pk') || '';
        if (help) help.textContent = desc !== '' ? desc : '—';
        if (descInput) descInput.value = desc || '';
        if (pkHidden) pkHidden.value = pk || '';
    }

    hsSel.addEventListener('change', applyHsMeta);
    // If server-side options are not provided, fallback to client fetch
    if (!hsSel.options || hsSel.options.length <= 1) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function(){ loadHsOptions(''); });
        } else { loadHsOptions(''); }
    } else {
        // Apply description/PK from selected server-side option
        applyHsMeta();
    }
})();
</script>
