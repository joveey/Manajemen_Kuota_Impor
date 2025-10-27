<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Quota;
use App\Services\HsCodeResolver;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class MappingController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read master_data');
    }

    public function unmapped(Request $request)
    {
        $data = $request->validate([
            'period' => ['required'],
            'reason' => ['nullable', 'in:missing_hs,missing_pk,no_matching_quota'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $periodKey = (string)$data['period'];
        $perPage = (int)($data['per_page'] ?? 50);
        $reasonFilter = $data['reason'] ?? null;

        // Derive date range for period
        [$periodStart, $periodEnd] = $this->derivePeriodRange($periodKey);

        // Prefetch quotas in period
        $quotas = Quota::query()
            ->where(function ($q) use ($periodStart) {
                $q->whereNull('period_end')->orWhere('period_end', '>=', $periodStart);
            })
            ->where(function ($q) use ($periodEnd) {
                $q->whereNull('period_start')->orWhere('period_start', '<=', $periodEnd);
            })
            ->get(['id','government_category','min_pk','max_pk','is_min_inclusive','is_max_inclusive']);

        $resolver = app(HsCodeResolver::class);

        // Build unmapped list in-memory
        $items = [];
        foreach (Product::query()->get(['id','code','name','sap_model','pk_capacity']) as $product) {
            $hs = $product->hs_code ?? null; // property may not exist; treated as null
            if ($hs === null || $hs === '') {
                $reason = 'missing_hs';
                if (!$reasonFilter || $reasonFilter === $reason) {
                    $items[] = [
                        'product_id' => $product->id,
                        'model' => $product->sap_model ?? $product->code ?? $product->name,
                        'hs_code' => null,
                        'resolved_pk' => null,
                        'reason' => $reason,
                    ];
                }
                continue;
            }

            $pk = $resolver->resolveForProduct($product);
            if ($pk === null) {
                $reason = 'missing_pk';
                if (!$reasonFilter || $reasonFilter === $reason) {
                    $items[] = [
                        'product_id' => $product->id,
                        'model' => $product->sap_model ?? $product->code ?? $product->name,
                        'hs_code' => $hs,
                        'resolved_pk' => null,
                        'reason' => $reason,
                    ];
                }
                continue;
            }

            // Check if there exists any quota that contains this PK
            $hasCandidate = false;
            foreach ($quotas as $q) {
                if ($this->pkInRange((float)$pk, $q->min_pk, $q->max_pk, (bool)$q->is_min_inclusive, (bool)$q->is_max_inclusive)) {
                    $hasCandidate = true; break;
                }
            }

            if (!$hasCandidate) {
                $reason = 'no_matching_quota';
                if (!$reasonFilter || $reasonFilter === $reason) {
                    $items[] = [
                        'product_id' => $product->id,
                        'model' => $product->sap_model ?? $product->code ?? $product->name,
                        'hs_code' => $hs,
                        'resolved_pk' => (float)$pk,
                        'reason' => $reason,
                    ];
                }
            }
        }

        // Paginate in-memory
        $total = count($items);
        $page = (int) max((int)$request->query('page', 1), 1);
        $offset = ($page - 1) * $perPage;
        $paged = array_slice($items, $offset, $perPage);
        $paginator = new LengthAwarePaginator($paged, $total, $perPage, $page);

        return response()->json([
            'period' => $periodKey,
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'data' => $paginator->items(),
        ]);
    }

    private function pkInRange(float $pk, $min, $max, bool $minIncl, bool $maxIncl): bool
    {
        $minOk = true;
        if (!is_null($min)) {
            $minOk = $minIncl ? ($pk >= (float)$min) : ($pk > (float)$min);
        }
        $maxOk = true;
        if (!is_null($max)) {
            $maxOk = $maxIncl ? ($pk <= (float)$max) : ($pk < (float)$max);
        }
        return $minOk && $maxOk;
    }

    private function derivePeriodRange(string $key): array
    {
        $key = trim($key);
        if (preg_match('/^\d{4}$/', $key)) {
            $y = (int)$key; return [sprintf('%04d-01-01', $y), sprintf('%04d-12-31', $y)];
        }
        if (preg_match('/^\d{4}-\d{2}$/', $key)) {
            [$y,$m] = explode('-', $key); $y=(int)$y; $m=(int)$m;
            $start = Carbon::create($y,$m,1);
            $end = $start->copy()->endOfMonth();
            return [$start->toDateString(), $end->toDateString()];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $key)) {
            return [$key, $key];
        }
        // Fallback to today
        $today = Carbon::now()->toDateString();
        return [$today, $today];
    }
}
