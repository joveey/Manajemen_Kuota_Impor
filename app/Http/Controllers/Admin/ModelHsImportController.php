<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ModelHsImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ModelHsImportController extends Controller
{
    public function __construct(private readonly ModelHsImportService $service)
    {
    }

    public function index(): View
    {
        Gate::authorize('permission:read quota');

        return view('admin.import_model_hs', [
            'summary' => null,
            'importErrors' => [],
        ]);
    }

    public function store(Request $request): View
    {
        Gate::authorize('permission:create quota');

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        /** @var UploadedFile $file */
        $file = $data['file'];

        $summary = $this->service->import($file);

        return view('admin.import_model_hs', [
            'summary' => $summary,
            'importErrors' => $summary['errors'] ?? [],
        ]);
    }
}
