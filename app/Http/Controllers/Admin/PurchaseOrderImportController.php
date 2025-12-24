<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PurchaseOrderImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class PurchaseOrderImportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:create purchase_orders');
    }

    public function index(): View
    {
        return view('admin.import_po', [
            'importResult' => Session::get('import_result'),
        ]);
    }

    public function store(Request $request, PurchaseOrderImportService $service): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx'],
        ]);

        try {
            $result = $service->import($data['file']);
        } catch (\Throwable $e) {
            return back()
                ->withErrors(['file' => $e->getMessage()])
                ->withInput();
        }

        return back()
            ->with('status', 'Import selesai diproses.')
            ->with('import_result', $result);
    }
}
