<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $query = AuditLog::query()->with('user')->latest('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('method')) {
            $m = strtoupper((string) $request->string('method'));
            // Support user-friendly filter values
            switch ($m) {
                case 'CREATE':
                case 'TAMBAH':
                    $query->where('method', 'POST');
                    break;
                case 'UPDATE':
                case 'UBAH':
                    $query->whereIn('method', ['PUT','PATCH']);
                    break;
                case 'DELETE':
                case 'HAPUS':
                    $query->where('method', 'DELETE');
                    break;
                default:
                    // Accept direct HTTP verbs or comma-separated values
                    $methods = array_filter(array_map('trim', explode(',', $m)));
                    if (count($methods) > 1) {
                        $query->whereIn('method', $methods);
                    } else {
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

        /** @var LengthAwarePaginator $logs */
        $logs = $query->paginate(25)->appends($request->query());

        $users = User::query()->orderBy('name')->pluck('name', 'id');

        return view('admin.audit_logs.index', compact('logs', 'users'));
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
