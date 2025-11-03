<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $query = AuditLog::query()->with('user')->latest('created_at');
        $this->applyFilters($request, $query);

        $perPage = (int) $request->input('per_page', 25);
        $perPage = max(5, min(100, $perPage));

        /** @var LengthAwarePaginator $logs */
        $logs = $query->paginate($perPage)->appends($request->query());

        $users = User::query()->orderBy('name')->pluck('name', 'id');

        return view('admin.audit_logs.index', compact('logs', 'users'));
    }

    public function export(Request $request)
    {
        $this->authorizeAdmin($request);

        $query = AuditLog::query()->with('user')->latest('created_at');
        $this->applyFilters($request, $query);

        $filename = 'audit_logs_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Waktu', 'Pengguna', 'Aksi', 'Halaman', 'Fitur', 'IP', 'Detail']);

            $label = function ($method) {
                switch (strtoupper((string)$method)) {
                    case 'POST': return 'Menambah';
                    case 'PUT':
                    case 'PATCH': return 'Mengubah';
                    case 'DELETE': return 'Menghapus';
                    default: return strtoupper((string)$method);
                }
            };

            $query->orderBy('id')->chunk(1000, function ($rows) use ($out, $label) {
                foreach ($rows as $log) {
                    $detail = $log->description;
                    if (is_array($detail)) {
                        $detail = json_encode($detail, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                    }
                    fputcsv($out, [
                        optional($log->created_at)->format('Y-m-d H:i:s'),
                        optional($log->user)->name ?? 'Guest',
                        $label($log->method),
                        $log->path,
                        $log->route_name,
                        $log->ip_address,
                        $detail,
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportXlsx(Request $request)
    {
        $this->authorizeAdmin($request);

        $query = AuditLog::query()->with('user')->latest('created_at');
        $this->applyFilters($request, $query);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Audit Logs');

        $headers = ['Waktu', 'Pengguna', 'Aksi', 'Halaman', 'Fitur', 'IP', 'Detail'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->getStyle('A1:G1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFF3F8');

        $row = 2;
        $label = function ($method) {
            switch (strtoupper((string)$method)) {
                case 'POST': return 'Menambah';
                case 'PUT':
                case 'PATCH': return 'Mengubah';
                case 'DELETE': return 'Menghapus';
                default: return strtoupper((string)$method);
            }
        };

        $query->orderBy('id')->chunk(1000, function ($rows) use (&$row, $sheet, $label) {
            foreach ($rows as $log) {
                $detail = $log->description;
                if (is_array($detail)) {
                    $detail = json_encode($detail, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                }
                $sheet->setCellValueExplicit('A'.$row, optional($log->created_at)->format('Y-m-d H:i:s'));
                $sheet->setCellValueExplicit('B'.$row, optional($log->user)->name ?? 'Guest');
                $sheet->setCellValueExplicit('C'.$row, $label($log->method));
                $sheet->setCellValueExplicit('D'.$row, (string) $log->path);
                $sheet->setCellValueExplicit('E'.$row, (string) $log->route_name);
                $sheet->setCellValueExplicit('F'.$row, (string) $log->ip_address);
                $sheet->setCellValueExplicit('G'.$row, (string) $detail);
                $row++;
            }
        });

        foreach (range('A','G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'audit_logs_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    protected function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        abort_unless($user, 401);

        $authorized = false;

        if (method_exists($user, 'hasRole')) {
            $authorized = $user->hasRole('admin');
        } elseif (property_exists($user, 'is_admin')) {
            $authorized = (bool) $user->is_admin;
        } elseif (method_exists($user, 'roles')) {
            try {
                $authorized = $user->roles()->where('name', 'admin')->exists();
            } catch (\Throwable) {
                $authorized = false;
            }
        }

        abort_unless($authorized, 403, 'Unauthorized');
    }

    protected function applyFilters(Request $request, \Illuminate\Database\Eloquent\Builder $query): void
    {
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('method')) {
            $m = strtoupper((string) $request->string('method'));
            switch ($m) {
                case 'CREATE':
                case 'TAMBAH':
                case 'MENAMBAH':
                    $query->where('method', 'POST');
                    break;
                case 'UPDATE':
                case 'UBAH':
                case 'MENGUBAH':
                    $query->whereIn('method', ['PUT','PATCH']);
                    break;
                case 'DELETE':
                case 'HAPUS':
                case 'MENGHAPUS':
                    $query->where('method', 'DELETE');
                    break;
                default:
                    $methods = array_filter(array_map('trim', explode(',', $m)));
                    if (count($methods) > 1) {
                        $query->whereIn('method', $methods);
                    } elseif (!empty($m)) {
                        $query->where('method', $m);
                    }
            }
        }
        if ($request->filled('route')) {
            $query->where('route_name', 'like', '%'.$request->string('route').'%');
        }
        if ($request->filled('path')) {
            $query->where('path', 'like', '%'.$request->string('path').'%');
        }
        if ($request->filled('from')) {
            $from = $this->parseDate((string) $request->string('from'));
            if ($from) {
                $query->where('created_at', '>=', $from->startOfDay());
            }
        }
        if ($request->filled('to')) {
            $to = $this->parseDate((string) $request->string('to'));
            if ($to) {
                $query->where('created_at', '<=', $to->endOfDay());
            }
        }
        if ($request->filled('q')) {
            $kw = '%'.trim((string) $request->string('q')).'%';
            $query->where(function ($q) use ($kw) {
                $q->where('path', 'like', $kw)
                  ->orWhere('route_name', 'like', $kw)
                  ->orWhere('ip_address', 'like', $kw)
                  ->orWhere('method', 'like', $kw)
                  ->orWhere('action', 'like', $kw);
            })->orWhereHas('user', function ($uq) use ($kw) {
                $uq->where('name', 'like', $kw)->orWhere('email', 'like', $kw);
            });
        }
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') return null;

        // Try common formats safely
        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y'] as $format) {
            try {
                $dt = Carbon::createFromFormat($format, $value);
                if ($dt !== false) return $dt;
            } catch (\Throwable) {
                // continue
            }
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
