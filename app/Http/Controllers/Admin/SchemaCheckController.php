<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SchemaCheckController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index(): View
    {
        $driver = DB::connection()->getDriverName();
        $tables = ['purchase_orders', 'gr_receipts'];
        $columns = [];
        $errors = [];

        foreach ($tables as $table) {
            try {
                if (!Schema::hasTable($table)) {
                    $columns[$table] = [];
                    $errors[$table] = 'Table not found';
                    continue;
                }
                $columns[$table] = Schema::getColumnListing($table);
            } catch (\Throwable $e) {
                $columns[$table] = [];
                $errors[$table] = $e->getMessage();
            }
        }

        return view('admin.schema_check', compact('driver', 'columns', 'errors'));
    }
}
