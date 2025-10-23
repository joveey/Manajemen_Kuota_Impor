<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\FinalReportController;
use App\Http\Controllers\Admin\QuotaController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\ShipmentController;
use App\Http\Controllers\Admin\ReceiptController;
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
use App\Http\Controllers\Admin\OpenPoImportController;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Di sini Anda bisa mendaftarkan rute web untuk aplikasi Anda. Rute-rute
| ini dimuat oleh RouteServiceProvider dan semuanya akan
| ditugaskan ke grup middleware "web".
|
*/

// Mengarahkan halaman utama ke login atau dashboard
Route::get('/', function () {
    return Auth::check() 
        ? redirect()->route('admin.dashboard') // Arahkan ke dashboard admin jika sudah login
        : redirect()->route('login');
});

// Rute Dashboard utama (dilindungi oleh middleware auth)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
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
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Users Management - Requires user permissions
    Route::middleware(['permission:read users'])->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
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
    });
    
    // ============================================
    // MANAJEMEN KUOTA IMPOR
    // ============================================

    Route::resource('master-data', ProductController::class)->except(['show']);

    Route::resource('quotas', QuotaController::class);
    Route::get('quotas/export/csv', [QuotaController::class, 'export'])->name('quotas.export');
    Route::get('kuota', [QuotaController::class, 'index'])->name('kuota.index');
    Route::get('kuota/create', [QuotaController::class, 'create'])->name('kuota.create');
    Route::post('kuota', [QuotaController::class, 'store'])->name('kuota.store');
    Route::get('kuota/{quota}/edit', [QuotaController::class, 'edit'])->name('kuota.edit');
    Route::post('quotas/{quota}/attach-product', [QuotaController::class, 'attachProduct'])
        ->name('quotas.attach-product');
    Route::delete('quotas/{quota}/detach-product/{product}', [QuotaController::class, 'detachProduct'])
        ->name('quotas.detach-product');

    Route::middleware(['permission:read reports'])->group(function () {
        Route::get('reports/final', [FinalReportController::class, 'index'])->name('reports.final');
        Route::get('reports/final/export/csv', [FinalReportController::class, 'exportCsv'])->name('reports.final.export.csv');
    });

    // Constrain route model binding for PurchaseOrder to numeric IDs to avoid clashes
    Route::pattern('purchase_order', '[0-9]+');

    // Quick Product -> HS (manual)
    Route::middleware(['permission:product.create'])->group(function () {
        Route::get('master-data/create-hs', [\App\Http\Controllers\Admin\ProductQuickController::class, 'create'])
            ->name('master.quick_hs.create');
        Route::post('master-data/store-hs', [\App\Http\Controllers\Admin\ProductQuickController::class, 'store'])
            ->name('master.quick_hs.store');
    });

    Route::resource('purchase-orders', PurchaseOrderController::class)->only(['index', 'show', 'destroy']);
    Route::get('purchase-orders/export/csv', [PurchaseOrderController::class, 'export'])->name('purchase-orders.export');

    Route::prefix('shipments')->name('shipments.')->group(function () {
        Route::get('/', [ShipmentController::class, 'index'])->name('index');
        Route::get('/create', [ShipmentController::class, 'create'])->name('create');
        Route::post('/', [ShipmentController::class, 'store'])->name('store');
        Route::get('/export/csv', [ShipmentController::class, 'export'])->name('export');
        Route::get('/{shipment}', [ShipmentController::class, 'show'])->name('show');
    });

    // Receipts
    Route::post('shipments/{shipment}/receipts', [ReceiptController::class, 'store'])
        ->name('shipments.receipts.store');
    Route::get('shipments/{shipment}/receipts/create', function(\App\Models\Shipment $shipment) {
        return view('admin.shipments.receipts.create', compact('shipment'));
    })->name('shipments.receipts.create');

    // HS→PK Imports upload (backend only)
    Route::middleware(['permission:read quota'])->group(function () {
        Route::post('imports/hs-pk', [ImportController::class, 'uploadHsPk'])->name('imports.hs_pk.upload');
        Route::post('imports/quotas', [ImportController::class, 'uploadQuotas'])->name('imports.quotas.upload');

        Route::get('imports/{import}/summary', [ImportController::class, 'showSummary'])->name('imports.summary');
        Route::get('imports/{import}/items', [ImportController::class, 'listItems'])->name('imports.items');
        Route::post('imports/{import}/publish', [ImportController::class, 'publish'])->name('imports.publish');
        Route::post('imports/{import}/publish-quotas', [ImportController::class, 'publishQuotas'])->name('imports.quotas.publish');

        Route::get('imports/hs-pk', [HsPkImportPageController::class, 'index'])->name('imports.hs_pk.index');
        Route::post('imports/hs-pk/upload', [HsPkImportPageController::class, 'uploadForm'])->name('imports.hs_pk.upload.form');
        Route::get('imports/hs-pk/{import}', [HsPkImportPageController::class, 'preview'])->name('imports.hs_pk.preview');
        Route::post('imports/hs-pk/{import}/publish', [HsPkImportPageController::class, 'publishForm'])->name('imports.hs_pk.publish.form');

        Route::get('imports/quotas', [QuotaImportPageController::class, 'index'])->name('imports.quotas.index');
        Route::post('imports/quotas/upload', [QuotaImportPageController::class, 'uploadForm'])->name('imports.quotas.upload.form');
        Route::get('imports/quotas/{import}', [QuotaImportPageController::class, 'preview'])->name('imports.quotas.preview');
        Route::post('imports/quotas/{import}/publish', [QuotaImportPageController::class, 'publishForm'])->name('imports.quotas.publish.form');
    });

    // =====================
    // Open PO Import
    // =====================
    Route::middleware(['role:admin,manager,editor'])->prefix('open-po')->name('openpo.')->group(function () {
        Route::get('/import', [OpenPoImportController::class, 'form'])->name('form');
        Route::post('/preview', [OpenPoImportController::class, 'preview'])->name('preview');
        // Allow reloading preview via GET (reads session)
        Route::get('/preview', [OpenPoImportController::class, 'previewPage'])->name('preview.page');
        Route::post('/publish', [OpenPoImportController::class, 'publish'])->name('publish');
    });

    // Mapping diagnostics
    Route::get('mapping/unmapped', [MappingController::class, 'unmapped'])->name('mapping.unmapped');
    // Mapping UI page (uses JSON endpoint above via XHR)
    Route::get('mapping/unmapped/view', [MappingPageController::class, 'unmapped'])->name('mapping.unmapped.page');
    // Mapped Model -> HS UI page
    Route::get('mapping/mapped', [MappingPageController::class, 'mapped'])->name('mapping.mapped.page');


});

// Rute Otentikasi (dari Laravel Breeze atau UI)
require __DIR__.'/auth.php';

// =====================
// Analytics (Actual-based)
// =====================
use App\Http\Controllers\AnalyticsController;
Route::middleware(['auth', 'verified'])->prefix('analytics')->name('analytics.')->group(function () {
    Route::get('/', [AnalyticsController::class, 'index'])->name('index');
    Route::get('/data', [AnalyticsController::class, 'data'])->name('data');
    Route::get('/export/csv', [AnalyticsController::class, 'exportCsv'])->name('export.csv');
    Route::get('/export/xlsx', [AnalyticsController::class, 'exportXlsx'])->name('export.xlsx');
    Route::get('/export/pdf', [AnalyticsController::class, 'exportPdf'])->name('export.pdf');
});

// (Product Mapping custom module removed — restoring original routes)
