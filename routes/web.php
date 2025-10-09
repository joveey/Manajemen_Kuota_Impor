<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
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
    
    // Manajemen Pengguna - Membutuhkan izin pengguna
    Route::resource('users', UserController::class)->except(['show']);
    
    // Manajemen Peran - Membutuhkan izin peran
    Route::resource('roles', RoleController::class)->except(['show']);
    
    // Manajemen Izin - Membutuhkan izin
    Route::resource('permissions', PermissionController::class)->except(['show']);
    
    // Manajemen Admin - Hanya untuk peran admin
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
    Route::get('kuota', [QuotaController::class, 'index'])->name('kuota.index');
    Route::get('kuota/create', [QuotaController::class, 'create'])->name('kuota.create');
    Route::post('kuota', [QuotaController::class, 'store'])->name('kuota.store');
    Route::get('kuota/{quota}/edit', [QuotaController::class, 'edit'])->name('kuota.edit');
    Route::post('quotas/{quota}/attach-product', [QuotaController::class, 'attachProduct'])
        ->name('quotas.attach-product');
    Route::delete('quotas/{quota}/detach-product/{product}', [QuotaController::class, 'detachProduct'])
        ->name('quotas.detach-product');

    Route::resource('purchase-orders', PurchaseOrderController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::get('purchase-order', [PurchaseOrderController::class, 'index'])->name('purchase-order.index');
    Route::get('purchase-order/create', [PurchaseOrderController::class, 'create'])->name('purchase-order.create');
    Route::post('purchase-order', [PurchaseOrderController::class, 'store'])->name('purchase-order.store');
    Route::get('purchase-order/{purchase_order}', [PurchaseOrderController::class, 'show'])->name('purchase-order.show');
    Route::delete('purchase-order/{purchase_order}', [PurchaseOrderController::class, 'destroy'])->name('purchase-order.destroy');

    Route::get('shipments/create', [ShipmentController::class, 'create'])->name('shipments.create');
    Route::post('shipments', [ShipmentController::class, 'store'])->name('shipments.store');
    Route::get('shipments', [ShipmentController::class, 'index'])->name('shipments.index');
    Route::post('shipments/{shipment}/receive', [ShipmentController::class, 'receive'])->name('shipments.receive');
    Route::get('shipment', [ShipmentController::class, 'index'])->name('shipment.index');

});

// Rute Otentikasi (dari Laravel Breeze atau UI)
require __DIR__.'/auth.php';
