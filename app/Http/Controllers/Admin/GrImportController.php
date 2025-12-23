<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GrImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GrImportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read quota')->only('index');
        $this->middleware('permission:create quota')->only('store');
    }

    public function index(): View
    {
        return view('admin.import_gr', [
            'importResult' => session('import_result'),
        ]);
    }

    public function store(Request $request, GrImportService $service): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        try {
            $result = $service->import($data['file']);
        } catch (\Throwable $e) {
            return back()
                ->withErrors(['file' => $e->getMessage()])
                ->withInput();
        }

        return back()
            ->with('status', 'Import GR selesai diproses.')
            ->with('import_result', $result);
    }
}
