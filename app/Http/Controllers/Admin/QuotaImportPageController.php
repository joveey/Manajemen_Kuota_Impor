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

        $preview = session('quotas.manual.preview', []);
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
            ->where('quota_number', 'like', 'MAN-%')
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
            ->with('status', 'Upload berhasil. Ringkasan siap ditinjau.');
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
            $msg = $payload['error'] ?? 'Publish gagal.';
            return back()->withErrors(['publish' => $msg]);
        }

        $applied = $payload['applied'] ?? null;
        $skipped = $payload['skipped'] ?? null;
        $version = $payload['version'] ?? null;
        $extra = !empty($payload['ran_automap']) ? ' + automap' : '';
        $msg = 'Publish berhasil'.($version !== null ? " (v$version)" : '').($applied !== null && $skipped !== null ? ": applied=$applied, skipped=$skipped" : '').$extra.'.';

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
            return back()->withErrors(['publish' => 'Tidak ada data di preview.'])->withInput();
        }

        $applied = 0;
        $skipped = 0;

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

                $quotaNumber = 'MAN-'.Str::upper(Str::random(10));
                $name = 'Quota HS '.$hsCode.' '.$periodStart.'-'.$periodEnd;

                DB::table('quotas')->insert(array_merge($baseFields, [
                    'quota_number' => $quotaNumber,
                    'name' => $name,
                    'status' => 'available',
                    'is_active' => true,
                    'source_document' => $letterNo !== '' ? $letterNo : null,
                    'forecast_remaining' => (int) round($quantity),
                    'actual_remaining' => (int) round($quantity),
                    'notes' => $notesValue !== '' ? $notesValue : null,
                    'created_at' => $now,
                ]));
                $applied++;
            }
        });

        session()->forget('quotas.manual.preview');

        return back()->with('status', "Publish selesai. Applied={$applied}, Skipped={$skipped}.");
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
        if ($anchor === null) {
            return 'PK N/A';
        }
        if ($anchor <= 0.0) {
            return 'ACC';
        }
        $rounded = round($anchor, 2);
        $fraction = $rounded - floor($rounded);

        if (abs($fraction - 0.99) < 0.02) {
            return '<'.number_format(floor($rounded) + 1, 0, '.', '');
        }
        if (abs($fraction - 0.01) < 0.02) {
            return '>'.number_format(floor($rounded), 0, '.', '');
        }

        if (abs($fraction) < 0.01) {
            return number_format($rounded, 0, '.', '');
        }

        return number_format($rounded, 2, '.', '');
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
            if ($desc === '') {
                $desc = $this->formatPkLabel($row->pk_capacity);
            }
            return [
                'id' => $row->hs_code,
                // Tampilkan hanya HS code pada dropdown; desc tetap tersedia via data-desc
                'text' => $row->hs_code,
                'desc' => $desc,
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
        if ($desc === '') {
            $desc = $this->formatPkLabel($row->pk_capacity);
        }

        return [
            'id' => $row->hs_code,
            // Dropdown menampilkan hanya HS code; desc tetap untuk tampilan pendamping
            'text' => $row->hs_code,
            'desc' => $desc,
        ];
    }
}
