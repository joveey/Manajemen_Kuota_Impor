<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\FinalReportController;
use App\Http\Controllers\Admin\ProductQuotaMappingController;
use App\Http\Controllers\Admin\QuotaController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\ShipmentController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\AdminController;
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
        Route::patch('users/{user}', [UserController::class, 'update']);
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
        Route::patch('permissions/{permission}', [PermissionController::class, 'update']);
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

    Route::middleware(['permission:read quota'])->group(function () {
        Route::get('product-quotas', [ProductQuotaMappingController::class, 'index'])->name('product-quotas.index');
    });

    Route::middleware(['permission:update quota'])->group(function () {
        Route::post('product-quotas', [ProductQuotaMappingController::class, 'store'])->name('product-quotas.store');
        Route::post('product-quotas/reorder', [ProductQuotaMappingController::class, 'reorder'])->name('product-quotas.reorder');
        Route::match(['put', 'patch'], 'product-quotas/{productQuotaMapping}', [ProductQuotaMappingController::class, 'update'])
            ->name('product-quotas.update');
        Route::delete('product-quotas/{productQuotaMapping}', [ProductQuotaMappingController::class, 'destroy'])
            ->name('product-quotas.destroy');
    });

    Route::middleware(['permission:read reports'])->group(function () {
        Route::get('reports/final', [FinalReportController::class, 'index'])->name('reports.final');
        Route::get('reports/final/export/csv', [FinalReportController::class, 'exportCsv'])->name('reports.final.export.csv');
    });

    Route::resource('purchase-orders', PurchaseOrderController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::get('purchase-orders/export/csv', [PurchaseOrderController::class, 'export'])->name('purchase-orders.export');
    Route::get('purchase-order', [PurchaseOrderController::class, 'index'])->name('purchase-order.index');
    Route::get('purchase-order/create', [PurchaseOrderController::class, 'create'])->name('purchase-order.create');
    Route::post('purchase-order', [PurchaseOrderController::class, 'store'])->name('purchase-order.store');
    Route::get('purchase-order/{purchase_order}', [PurchaseOrderController::class, 'show'])->name('purchase-order.show');
    Route::delete('purchase-order/{purchase_order}', [PurchaseOrderController::class, 'destroy'])->name('purchase-order.destroy');

    Route::get('shipments/create', [ShipmentController::class, 'create'])->name('shipments.create');
    Route::post('shipments', [ShipmentController::class, 'store'])->name('shipments.store');
    Route::get('shipments', [ShipmentController::class, 'index'])->name('shipments.index');
    Route::get('shipments/export/csv', [ShipmentController::class, 'export'])->name('shipments.export');
    Route::get('shipment', [ShipmentController::class, 'index'])->name('shipment.index');

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
