<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Import;
use App\Models\Quota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class QuotaImportPageController extends Controller
{
    protected bool $hsHasDesc;
    protected bool $hsHasPeriod;

    public function __construct()
    {
        $this->hsHasDesc = Schema::hasTable('hs_code_pk_mappings') && Schema::hasColumn('hs_code_pk_mappings', 'desc');
        $this->hsHasPeriod = Schema::hasTable('hs_code_pk_mappings') && Schema::hasColumn('hs_code_pk_mappings', 'period_key');
    }

    public function index(): View
    {
        $recent = Import::query()
            ->where('type', 'quota')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $preview = $this->refreshManualPreview(session('quotas.manual.preview', []));
        session(['quotas.manual.preview' => $preview]);
        $summary = $this->buildPreviewSummary($preview);

        $oldHs = session()->getOldInput('hs_code');
        $selectedHsOption = null;
        if ($oldHs) {
            $selectedHsOption = $this->fetchHsOptionByCode($oldHs);
        }

        $seedOptions = $this->fetchHsOptions();
        if ($selectedHsOption) {
            $exists = collect($seedOptions)->contains(fn ($opt) => $opt['id'] === $selectedHsOption['id']);
            if (!$exists) {
                array_unshift($seedOptions, $selectedHsOption);
            }
        }

        $manualQuotas = Quota::query()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('admin.imports.quotas.index', [
            'recent' => $recent,
            'manualPreview' => $preview,
            'manualSummary' => $summary,
            'hsSeedOptions' => $seedOptions,
            'selectedHsOption' => $selectedHsOption,
            'manualQuotas' => $manualQuotas,
        ]);
    }

    public function uploadForm(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'period_key' => ['required'],
        ]);

        $api = app(ImportController::class);
        $resp = $api->uploadQuotas($request);
        $payload = json_decode($resp->getContent(), true) ?: [];

        if (isset($payload['error'])) {
            return back()->withErrors(['file' => $payload['error']])->withInput();
        }

        $importId = $payload['import_id'] ?? null;
        if (!$importId) {
            return back()->withErrors(['file' => 'Upload failed.'])->withInput();
        }

        return redirect()
            ->route('admin.imports.quotas.preview', ['import' => $importId])
            ->with('status', 'Upload successful. Summary ready for review.');
    }

    public function preview(Import $import): View
    {
        abort_unless($import->type === 'quota', 404);
        return view('admin.imports.quotas.preview', compact('import'));
    }

    public function publishForm(Request $request, Import $import): RedirectResponse
    {
        abort_unless($import->type === 'quota', 404);

        $api = app(ImportController::class);
        $resp = $api->publishQuotas($request, $import);
        $payload = json_decode($resp->getContent(), true) ?: [];

        if ($resp->getStatusCode() >= 400) {
            $msg = $payload['error'] ?? 'Publish failed.';
            return back()->withErrors(['publish' => $msg]);
        }

        $applied = $payload['applied'] ?? null;
        $skipped = $payload['skipped'] ?? null;
        $version = $payload['version'] ?? null;
        $extra = !empty($payload['ran_automap']) ? ' + automap' : '';
        $msg = 'Publish successful'.($version !== null ? " (v$version)" : '').($applied !== null && $skipped !== null ? ": applied=$applied, skipped=$skipped" : '').$extra.'.';

        return redirect()
            ->route('admin.imports.quotas.preview', $import)
            ->with('status', $msg);
    }

    public function hsOptions(Request $request): JsonResponse
    {
        abort_unless(Schema::hasTable('hs_code_pk_mappings'), 404);

        $search = trim((string) $request->query('q', ''));
        $limit = (int) min(max((int) $request->query('limit', 30), 1), 100);

        $period = trim((string) $request->query('period_key', ''));
        $options = $this->fetchHsOptions($search, $period, $limit);

        return response()->json([
            'results' => $options,
        ]);
    }

    public function addManual(Request $request): RedirectResponse
    {
        abort_unless(Schema::hasTable('hs_code_pk_mappings'), 404);

        $data = $request->validate([
            'hs_code' => ['required', 'string', 'max:50'],
            'letter_no' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
        ], [
            'quantity.min' => 'Quantity harus lebih besar dari 0.',
        ]);

        $hsRowQuery = DB::table('hs_code_pk_mappings')
            ->where('hs_code', $data['hs_code'])
            ->orderByDesc('updated_at');

        if ($this->hsHasPeriod) {
            $periodFilter = trim((string) $request->input('hs_period_key', ''));
            if ($periodFilter !== '') {
                $hsRowQuery->where('period_key', $periodFilter);
            }
        }

        $hsRow = $hsRowQuery->first(['hs_code', 'pk_capacity'] + ($this->hsHasDesc ? ['desc'] : []));
        if (!$hsRow) {
            return back()->withErrors(['hs_code' => 'HS code tidak ditemukan pada master HS→PK.'])->withInput();
        }

        $desc = $this->hsHasDesc ? ($hsRow->desc ?? '') : '';
        if ($desc === '') {
            $desc = $this->formatPkLabel($hsRow->pk_capacity);
        }
        $desc = $this->normalizePkDesc($desc, $hsRow->pk_capacity);

        $preview = session('quotas.manual.preview', []);
        $duplicateKey = sprintf('%s|%s|%s|%s', $hsRow->hs_code, $data['letter_no'] ?? '', $data['period_start'], $data['period_end']);
        foreach ($preview as $item) {
            if (($item['duplicate_key'] ?? null) === $duplicateKey) {
                return back()->withErrors(['hs_code' => 'Data dengan kombinasi HS, letter, dan periode yang sama sudah ada di preview.'])->withInput();
            }
        }

        $preview[] = [
            'id' => (string) Str::uuid(),
            'hs_code' => $hsRow->hs_code,
            'hs_desc' => $desc,
            'pk_anchor' => (float) $hsRow->pk_capacity,
            'letter_no' => $data['letter_no'] ?? null,
            'quantity' => (float) $data['quantity'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'duplicate_key' => $duplicateKey,
        ];

        session(['quotas.manual.preview' => $preview]);

        return back()->with('status', 'Item ditambahkan ke preview.');
    }

    public function removeManual(Request $request): RedirectResponse
    {
        $id = (string) $request->input('id');
        $preview = session('quotas.manual.preview', []);
        $preview = array_values(array_filter($preview, fn ($item) => ($item['id'] ?? null) !== $id));
        session(['quotas.manual.preview' => $preview]);
        return back()->with('status', 'Item dihapus dari preview.');
    }

    public function resetManual(): RedirectResponse
    {
        session()->forget('quotas.manual.preview');
        return back()->with('status', 'Preview dikosongkan.');
    }

    public function publishManual(Request $request): RedirectResponse
    {
        $preview = session('quotas.manual.preview', []);
        if (empty($preview)) {
            return back()->withErrors(['publish' => 'No data in the preview.'])->withInput();
        }

        $applied = 0;
        $skipped = 0;
        // Kumpulkan unique tahun dari periode untuk automap setelah publish
        $years = collect($preview)
            ->flatMap(function ($item) {
                $years = [];
                try { if (!empty($item['period_start'])) { $years[] = \Illuminate\Support\Carbon::parse($item['period_start'])->format('Y'); } } catch (\Throwable $e) {}
                try { if (!empty($item['period_end'])) { $years[] = \Illuminate\Support\Carbon::parse($item['period_end'])->format('Y'); } } catch (\Throwable $e) {}
                return $years;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($preview, &$applied, &$skipped) {
            foreach ($preview as $item) {
                $hsCode = (string) ($item['hs_code'] ?? '');
                $letterNo = trim((string) ($item['letter_no'] ?? ''));
                $quantity = (float) ($item['quantity'] ?? 0);
                $periodStart = $item['period_start'] ?? null;
                $periodEnd = $item['period_end'] ?? null;
                $desc = (string) ($item['hs_desc'] ?? '');

                if ($hsCode === '' || !$periodStart || !$periodEnd || $quantity <= 0) {
                    $skipped++;
                    continue;
                }

                $label = $desc !== '' ? $desc : 'HS '.$hsCode;
                $now = now();

                $existingQuery = DB::table('quotas')
                    ->where('period_start', $periodStart)
                    ->where('period_end', $periodEnd)
                    ->where('government_category', $label);

                if ($letterNo !== '') {
                    $existingQuery->where('source_document', $letterNo);
                }

                $existing = $existingQuery->first();

                $baseFields = [
                    'government_category' => $label,
                    'total_allocation' => (int) round($quantity),
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'updated_at' => $now,
                ];
                $notesValue = trim('HS '.$hsCode);

                if ($existing) {
                    $update = $baseFields;
                    if ($letterNo !== '') {
                        $update['source_document'] = $letterNo;
                    }
                    DB::table('quotas')->where('id', $existing->id)->update($update);
                    $applied++;
                    continue;
                }

                // Insert then update quota_number using the ID for unified format
                $name = 'Quota HS '.$hsCode.' '.$periodStart.'-'.$periodEnd;

                $tmpNumber = 'TMP-'.Str::uuid()->toString();
                $id = DB::table('quotas')->insertGetId(array_merge($baseFields, [
                    'quota_number' => $tmpNumber,
                    'name' => $name,
                    'status' => 'available',
                    'is_active' => true,
                    'source_document' => $letterNo !== '' ? $letterNo : null,
                    'forecast_remaining' => (int) round($quantity),
                    'actual_remaining' => (int) round($quantity),
                    'notes' => $notesValue !== '' ? $notesValue : null,
                    'created_at' => $now,
                ]));
                $formatted = sprintf('QUOTA-%06d', (int) $id);
                DB::table('quotas')->where('id', $id)->update(['quota_number' => $formatted]);
                $applied++;
            }
        });

        // Jalankan automap kuota -> produk berdasarkan tahun periode
        $automapRan = false; $automapNotes = '';
        if (!empty($years)) {
            foreach ($years as $y) {
                try {
                    app(\App\Services\ProductQuotaAutoMapper::class)->runForPeriod($y);
                    $automapRan = true;
                } catch (\Throwable $e) {
                    // Ignore automap errors so the publish still succeeds
                }
            }
            $automapNotes = ' Quota automap executed for years: '.implode(', ', $years).'.';
        }

        session()->forget('quotas.manual.preview');

        return back()->with('status', "Publish completed. Applied={$applied}, Skipped={$skipped}.".$automapNotes);
    }

    protected function buildPreviewSummary(array $preview): array
    {
        $totalQty = 0.0;
        foreach ($preview as $item) {
            $totalQty += (float) ($item['quantity'] ?? 0);
        }
        return [
            'count' => count($preview),
            'total_quantity' => $totalQty,
        ];
    }

    protected function formatPkLabel(?float $anchor): string
    {
        if ($anchor === null) { return 'PK N/A'; }
        if ($anchor <= 0.0) { return 'ACC'; }
        $rounded = round($anchor, 2);
        if ($rounded < 8.0) { return '<8'; }
        if ($rounded >= 10.0) { return '>10'; }
        return '8-10';
    }

    protected function fetchHsOptions(?string $search = null, ?string $periodKey = null, int $limit = 30): array
    {
        if (!Schema::hasTable('hs_code_pk_mappings')) {
            return [];
        }

        $search = trim((string) ($search ?? ''));
        $periodKey = trim((string) ($periodKey ?? ''));

        $query = DB::table('hs_code_pk_mappings')
            ->select(['hs_code', 'pk_capacity'])
            ->orderBy('hs_code');

        if ($this->hsHasDesc) {
            $query->addSelect('desc');
        }
        if ($this->hsHasPeriod && $periodKey !== '') {
            $query->where('period_key', $periodKey);
        }
        if ($search !== '') {
            $query->where('hs_code', 'like', $search.'%');
        }

        return $query->limit($limit)->get()->map(function ($row) {
            $desc = $this->hsHasDesc ? ($row->desc ?? '') : '';
            if (strtoupper((string)$row->hs_code) === 'ACC') { $desc = 'Accesory'; }
            if ($desc === '') {
                $desc = $this->formatPkLabel($row->pk_capacity);
            }
            $desc = $this->normalizePkDesc($desc, $row->pk_capacity);
            return [
                'id' => $row->hs_code,
                // Show only the HS code in the dropdown; desc remains available via data-desc
                'text' => $row->hs_code,
                'desc' => $desc,
                'pk' => $row->pk_capacity,
            ];
        })->all();
    }

    protected function fetchHsOptionByCode(string $hsCode): ?array
    {
        if (!Schema::hasTable('hs_code_pk_mappings')) {
            return null;
        }

        $query = DB::table('hs_code_pk_mappings')
            ->where('hs_code', $hsCode)
            ->orderByDesc('updated_at')
            ->limit(1)
            ->select(['hs_code', 'pk_capacity']);

        if ($this->hsHasDesc) {
            $query->addSelect('desc');
        }

        $row = $query->first();
        if (!$row) {
            return null;
        }

        $desc = $this->hsHasDesc ? ($row->desc ?? '') : '';
        if (strtoupper((string)$row->hs_code) === 'ACC') { $desc = 'Accesory'; }
        if ($desc === '') {
            $desc = $this->formatPkLabel($row->pk_capacity);
        }
        $desc = $this->normalizePkDesc($desc, $row->pk_capacity);

        return [
            'id' => $row->hs_code,
            // The dropdown shows only the HS code; desc remains for supplemental display
            'text' => $row->hs_code,
            'desc' => $desc,
        ];
    }

    protected function normalizePkDesc(string $desc, ?float $anchor): string
    {
        $s = strtoupper(trim($desc));
        $s = str_replace('PK', '', $s);
        $s = str_replace(' ', '', $s);
        if ($s === '' || $s === 'PKN/A') {
            return $this->formatPkLabel($anchor);
        }
        if (strpos($s, '-') !== false) { return str_replace('--', '-', $s); }
        if (is_numeric($s)) { return (string)(float)$s; }
        if ($s[0] === '>' || $s[0] === '<') {
            if ($anchor !== null) { return $this->formatPkLabel($anchor); }
            return $s;
        }
        return $desc;
    }

    /**
     * Refresh session-based manual preview with latest HS desc/anchor.
     *
     * @param array<int,array<string,mixed>> $preview
     * @return array<int,array<string,mixed>>
     */
    protected function refreshManualPreview(array $preview): array
    {
        if (!Schema::hasTable('hs_code_pk_mappings') || empty($preview)) {
            return $preview;
        }
        $hasDesc = $this->hsHasDesc;
        foreach ($preview as &$item) {
            $code = (string) ($item['hs_code'] ?? '');
            if ($code === '') { continue; }
            $row = DB::table('hs_code_pk_mappings')
                ->where('hs_code', $code)
                ->orderByDesc('updated_at')
                ->first(['hs_code','pk_capacity'] + ($hasDesc ? ['desc'] : []));
            if ($row) {
                $desc = $hasDesc ? ($row->desc ?? '') : '';
                if (strtoupper((string)$row->hs_code) === 'ACC') { $desc = 'Accesory'; }
                if ($desc === '') { $desc = $this->formatPkLabel($row->pk_capacity); }
                $item['hs_desc'] = $this->normalizePkDesc($desc, $row->pk_capacity);
                $item['pk_anchor'] = (float) ($row->pk_capacity ?? 0);
            }
        }
        unset($item);
        return $preview;
    }
}
