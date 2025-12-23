<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\FinalReportController;
use App\Http\Controllers\Admin\QuotaController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\MappingController;
use App\Http\Controllers\Admin\HsPkImportPageController;
use App\Http\Controllers\Admin\QuotaImportPageController;
use App\Http\Controllers\Admin\MappingPageController;
use App\Http\Controllers\Admin\SchemaCheckController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admin\ProductQuickController;
use App\Http\Controllers\Admin\HsPkManualController;
use App\Http\Controllers\Admin\PurchaseOrderImportController;
use App\Http\Controllers\Admin\GrImportController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application.
| These routes are loaded by the RouteServiceProvider and all will
| be assigned to the "web" middleware group.
|
*/

// Direct the root page to login or dashboard
Route::get('/', function () {
    return Auth::check() 
        ? redirect()->route('admin.dashboard') // Send to admin dashboard if already logged in
        : redirect()->route('login');
});

// Rute Dashboard utama (dilindungi oleh middleware auth)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:read dashboard')
        ->name('dashboard');

    // Visual Dashboard API endpoint (used by dashboard view scripts)
    Route::get('/api/dashboard/summary', [DashboardController::class, 'summary'])
        ->name('api.dashboard.summary');
});

// Rute Profil Pengguna
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// =========================================================================
// RUTE ADMIN UTAMA
// =========================================================================
Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    
    // Dashboard Admin
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:read dashboard')
        ->name('dashboard');
    // Visual Dashboard API (admin-scoped mirror)
    Route::get('/api/dashboard/summary', [DashboardController::class, 'summary'])
        ->name('api.dashboard.summary');
    
    // Users Management - Requires user permissions
    Route::middleware(['permission:read users'])->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/recent', [UserController::class, 'recent'])->name('users.recent');
    });
    Route::middleware(['permission:create users'])->group(function () {
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
    });
    Route::middleware(['permission:read users'])->group(function () {
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
    });
    Route::middleware(['permission:update users'])->group(function () {
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    });
    Route::middleware(['permission:delete users'])->group(function () {
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
    
    // Manajemen Peran
    Route::resource('roles', RoleController::class);
    
    // Permissions Management - Requires permission permissions
    Route::middleware(['permission:read permissions'])->group(function () {
        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
    });
    Route::middleware(['permission:create permissions'])->group(function () {
        Route::get('permissions/create', [PermissionController::class, 'create'])->name('permissions.create');
        Route::post('permissions', [PermissionController::class, 'store'])->name('permissions.store');
    });
    Route::middleware(['permission:read permissions'])->group(function () {
        Route::get('permissions/{permission}', [PermissionController::class, 'show'])->name('permissions.show');
    });
    Route::middleware(['permission:update permissions'])->group(function () {
        Route::get('permissions/{permission}/edit', [PermissionController::class, 'edit'])->name('permissions.edit');
        Route::put('permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update');
    });
    Route::middleware(['permission:delete permissions'])->group(function () {
        Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');
    });
    
    // Admins Management - Only for admin role
    Route::middleware(['role:admin'])->group(function () {
        Route::resource('admins', AdminController::class);
        Route::post('admins/{admin}/convert-to-user', [AdminController::class, 'convertToUser'])
            ->name('admins.convert');
        Route::get('db-schema-check', [SchemaCheckController::class, 'index'])->name('db.schema_check');
    });
    
    // ============================================
    // IMPORT QUOTA MANAGEMENT
    // ============================================

    // Removed master-data UI per request
    // Route::resource('master-data', ProductController::class)->except(['show']);

    // Disable manual create form; use Import Quota page instead
    // Also disable edit/update per request (read-only from UI)
    Route::resource('quotas', QuotaController::class)->except(['create','store','show','edit','update']);
    Route::get('quotas/export/csv', [QuotaController::class, 'export'])->name('quotas.export');
    // Quota Management page has been moved to Import Quota
    Route::get('kuota', function(){
        return redirect()->route('admin.imports.quotas.index')
            ->with('status', 'Quota Management page has been moved to Import Quota.');
    })->name('kuota.index');
    // Redirect legacy create routes to Import Quota
    Route::get('quotas/create', function(){
        return redirect()->route('admin.imports.quotas.index')->with('warning','Manual quota form is disabled. Use Import Quota.');
    })->name('quotas.create');
    Route::get('kuota/create', function(){
        return redirect()->route('admin.imports.quotas.index')->with('warning','Manual quota form is disabled. Use Import Quota.');
    })->name('kuota.create');
    // block legacy store endpoint
    Route::post('kuota', function(){
        return redirect()->route('admin.imports.quotas.index')->with('warning','Manual quota form is disabled. Use Import Quota.');
    })->name('kuota.store');
    // Remove dedicated edit page for quota
    // Route::get('kuota/{quota}/edit', [QuotaController::class, 'edit'])->name('kuota.edit');
    Route::middleware(['permission:read reports'])->group(function () {
        Route::get('reports/final', [FinalReportController::class, 'index'])->name('reports.final');
        Route::get('reports/final/export/csv', [FinalReportController::class, 'exportCsv'])->name('reports.final.export.csv');
    });

    // Constrain route model binding for PurchaseOrder to numeric IDs to avoid clashes
    Route::pattern('purchase_order', '[0-9]+');

    // Quick Product -> HS (quick action)
    Route::middleware(['permission:product.create'])->group(function () {
        Route::get('master-data/model-hs', [ProductQuickController::class, 'index'])->name('master.quick_hs.index');
        Route::post('master-data/store-hs', [ProductQuickController::class, 'store'])->name('master.quick_hs.store');
    });

    Route::post('purchase-orders/sync', [PurchaseOrderController::class, 'syncPeriod'])->name('purchase-orders.sync');
    Route::get('purchase-orders/doc/{poNumber}', [PurchaseOrderController::class, 'showDocument'])
        ->name('purchase-orders.document');

    Route::resource('purchase-orders', PurchaseOrderController::class)->only(['index', 'show', 'destroy']);
    // Manual voyage inline update on PO lines (panel lama)
    Route::post('purchase-orders/lines/{line}/voyage', [PurchaseOrderController::class, 'updateVoyage'])
        ->name('purchase-orders.lines.voyage.update');

    // Voyage page (dedicated management page)
    Route::middleware(['auth', 'permission:read purchase_orders'])->group(function () {
        Route::get('purchase-orders/{po}/voyage', [\App\Http\Controllers\Admin\PurchaseOrderVoyageController::class, 'index'])
            ->name('purchase-orders.voyage.index');
        Route::post('purchase-orders/{po}/voyage/bulk', [\App\Http\Controllers\Admin\PurchaseOrderVoyageController::class, 'bulkUpdate'])
            ->name('purchase-orders.voyage.bulk');
        Route::post('purchase-orders/{po}/voyage/move', [\App\Http\Controllers\Admin\PurchaseOrderVoyageController::class, 'moveSplitQuota'])
            ->name('purchase-orders.voyage.move');
    });
    Route::get('purchase-orders/export/csv', [PurchaseOrderController::class, 'export'])->name('purchase-orders.export');
    Route::post('purchase-orders/{purchaseOrder}/reallocate-quota', [PurchaseOrderController::class, 'reallocateQuota'])->name('purchase-orders.reallocate_quota');
    Route::middleware(['permission:create purchase_orders'])->group(function () {
        Route::get('import/po', [PurchaseOrderImportController::class, 'index'])->name('purchase-orders.import.form');
        Route::post('import/po', [PurchaseOrderImportController::class, 'store'])->name('purchase-orders.import.store');
    });
    Route::middleware(['permission:read quota'])->group(function () {
        Route::get('import/gr', [GrImportController::class, 'index'])->name('imports.gr.index');
    });
    Route::middleware(['permission:create quota'])->group(function () {
        Route::post('import/gr', [GrImportController::class, 'store'])->name('imports.gr.store');
    });

    // HS→PK Imports upload (backend only)
    Route::middleware(['permission:read quota', 'forbid-role:user'])->group(function () {
        // Legacy file-upload routes removed (now manual-only)
        // Route::post('imports/hs-pk', [ImportController::class, 'uploadHsPk'])->name('imports.hs_pk.upload');
        // Route::post('imports/quotas', [ImportController::class, 'uploadQuotas'])->name('imports.quotas.upload');

        Route::get('imports/{import}/summary', [ImportController::class, 'showSummary'])->name('imports.summary');
        Route::get('imports/{import}/items', [ImportController::class, 'listItems'])->name('imports.items');
        Route::post('imports/{import}/publish', [ImportController::class, 'publish'])->name('imports.publish');
        Route::post('imports/{import}/publish-quotas', [ImportController::class, 'publishQuotas'])->name('imports.quotas.publish');

        Route::get('imports/hs-pk', [HsPkImportPageController::class, 'index'])->name('imports.hs_pk.index');
        // Route::post('imports/hs-pk/upload', [HsPkImportPageController::class, 'uploadForm'])->name('imports.hs_pk.upload.form');
        // Route::get('imports/hs-pk/{import}', [HsPkImportPageController::class, 'preview'])->name('imports.hs_pk.preview');
        // Route::post('imports/hs-pk/{import}/publish', [HsPkImportPageController::class, 'publishForm'])->name('imports.hs_pk.publish.form');

        // Manual HS → PK (create + list)
        Route::post('hs-pk/manual', [HsPkManualController::class, 'store'])->name('hs_pk.manual.store');

        Route::get('imports/quotas', [QuotaImportPageController::class, 'index'])->name('imports.quotas.index');
        // CSV upload (Quota) – enabled on Import Quota page
        Route::post('imports/quotas', [ImportController::class, 'uploadQuotas'])->name('imports.quotas.upload');
        // Delete all quotas by Quota No. (soft delete)
        Route::delete('imports/quotas/delete-by-number', [QuotaImportPageController::class, 'deleteByNumber'])
            ->name('imports.quotas.delete-number');
        // Delete a single quota entry by ID
        Route::delete('imports/quotas/{quota}', [QuotaImportPageController::class, 'deleteOne'])
            ->name('imports.quotas.delete-one');
        Route::post('imports/quotas/manual/add', [QuotaImportPageController::class, 'addManual'])->name('imports.quotas.manual.add');
        Route::post('imports/quotas/manual/remove', [QuotaImportPageController::class, 'removeManual'])->name('imports.quotas.manual.remove');
        Route::post('imports/quotas/manual/reset', [QuotaImportPageController::class, 'resetManual'])->name('imports.quotas.manual.reset');
        Route::post('imports/quotas/manual/publish', [QuotaImportPageController::class, 'publishManual'])->name('imports.quotas.manual.publish');
        Route::get('imports/quotas/hs-options', [QuotaImportPageController::class, 'hsOptions'])->name('imports.quotas.hs-options');
        // Route::get('imports/quotas/{import}', [QuotaImportPageController::class, 'preview'])->name('imports.quotas.preview');
        // Route::post('imports/quotas/{import}/publish', [QuotaImportPageController::class, 'publishForm'])->name('imports.quotas.publish.form');

    });


    // Mapping diagnostics & UI
    Route::middleware(['permission:read master_data'])->group(function () {
        Route::get('mapping/unmapped', [MappingController::class, 'unmapped'])->name('mapping.unmapped');
        Route::get('mapping/unmapped/view', [MappingPageController::class, 'unmapped'])->name('mapping.unmapped.page');
        Route::get('mapping/mapped', [MappingPageController::class, 'mapped'])->name('mapping.mapped.page');
        // Batch Import: Model -> HS (Excel/CSV)
        // Route::get('mapping/model-hs', [\App\Http\Controllers\Admin\ModelHsImportController::class, 'index'])->name('mapping.model_hs.index');
        // Route::post('mapping/model-hs/upload', [\App\Http\Controllers\Admin\ModelHsImportController::class, 'upload'])->name('mapping.model_hs.upload');
        // Route::get('mapping/model-hs/preview', [\App\Http\Controllers\Admin\ModelHsImportController::class, 'preview'])->name('mapping.model_hs.preview');
        // Route::post('mapping/model-hs/publish', [\App\Http\Controllers\Admin\ModelHsImportController::class, 'publish'])->name('mapping.model_hs.publish');
    });


    // Read-only PO progress (based on po_headers/po_lines + Invoice as Shipment + GR)
    Route::get('po-progress', [\App\Http\Controllers\Admin\PoProgressController::class, 'index'])
        ->middleware('permission:read purchase_orders')
        ->name('po_progress.index');
    Route::post('po-progress/sync', [\App\Http\Controllers\Admin\PoProgressController::class, 'sync'])
        ->middleware('permission:read purchase_orders')
        ->name('po_progress.sync');
});

// Rute Otentikasi (dari Laravel Breeze atau UI)
require __DIR__.'/auth.php';

// =====================
// Analytics (Actual-based)
// =====================
use App\Http\Controllers\AnalyticsController;
Route::middleware(['auth', 'verified', 'permission:read reports'])->prefix('analytics')->name('analytics.')->group(function () {
    Route::get('/', [AnalyticsController::class, 'index'])->name('index');
    Route::get('/data', [AnalyticsController::class, 'data'])->name('data');
    Route::get('/export/csv', [AnalyticsController::class, 'exportCsv'])->name('export.csv');
    Route::get('/export/xlsx', [AnalyticsController::class, 'exportXlsx'])->name('export.xlsx');
});
// Audit Logs (admin-only read)
Route::middleware(['web','auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/audit-logs', [\App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/audit-logs/export', [\App\Http\Controllers\Admin\AuditLogController::class, 'export'])->name('audit-logs.export');
    Route::get('/audit-logs/export-xlsx', [\App\Http\Controllers\Admin\AuditLogController::class, 'exportXlsx'])->name('audit-logs.export.xlsx');
});
// Ensure audit logging middleware is applied to all web routes
try {
    app('router')->pushMiddlewareToGroup('web', \App\Http\Middleware\AuditLogMiddleware::class);
} catch (\Throwable $e) {
    // ignore if router not ready
}
