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
                    $detail = $this->humanizeDescription($log->description);
                    fputcsv($out, [
                        optional($log->created_at)->format('Y-m-d H:i:s'),
                        optional($log->user)->name ?? 'Guest',
                        $label($log->method),
                        $log->path,
                        audit_route_label($log->route_name),
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
                $detail = $this->humanizeDescription($log->description);
                $sheet->setCellValue('A'.$row, optional($log->created_at)->format('Y-m-d H:i:s'));
                $sheet->setCellValue('B'.$row, optional($log->user)->name ?? 'Guest');
                $sheet->setCellValue('C'.$row, $label($log->method));
                $sheet->setCellValue('D'.$row, (string) $log->path);
                $sheet->setCellValue('E'.$row, (string) audit_route_label($log->route_name));
                $sheet->setCellValue('F'.$row, (string) $log->ip_address);
                $sheet->setCellValue('G'.$row, (string) $detail);
                $row++;
            }
        });

        foreach (range('A','G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Wrap detail column to keep content readable
        $sheet->getStyle('G2:G'.max(2, $row-1))->getAlignment()->setWrapText(true);

        $filename = 'audit_logs_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    protected function humanizeDescription($desc): string
    {
        if (is_string($desc)) {
            $trim = trim($desc);
            if ($trim === '' || $trim === 'null') return '';
            if ((str_starts_with($trim, '{') && str_ends_with($trim, '}')) || (str_starts_with($trim, '[') && str_ends_with($trim, ']'))) {
                $decoded = json_decode($trim, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $desc = $decoded;
                }
            }
        }

        if (!is_array($desc)) {
            return is_string($desc) ? $desc : '';
        }

        $blacklist = ['_token','password','password_confirmation','current_password'];
        $parts = [];
        foreach ($desc as $key => $value) {
            if (in_array($key, $blacklist, true)) continue;

            $label = match ($key) {
                'file', 'files' => 'Berkas',
                'name' => 'Nama',
                'email' => 'Email',
                'note', 'notes' => 'Catatan',
                default => ucfirst(str_replace(['_', '-'], ' ', (string) $key)),
            };

            $val = $value;
            if (is_string($val) && str_starts_with($val, 'uploaded_file:')) {
                $val = 'Berkas diunggah: '.substr($val, strlen('uploaded_file:'));
            } elseif (is_bool($val)) {
                $val = $val ? 'Ya' : 'Tidak';
            } elseif (is_array($val)) {
                $flat = [];
                foreach ($val as $v) {
                    if (is_scalar($v)) { $flat[] = (string) $v; }
                }
                $val = count($flat) ? implode(', ', $flat) : json_encode($val, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }

            if ($val === '' || $val === null) continue;
            $parts[] = $label.': '.$val;
        }

        return implode(' | ', $parts);
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
