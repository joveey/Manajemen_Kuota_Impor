<?php

use App\Http\Controllers\Admin\DashboardController;
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
    // MANAJEMEN KUOTA IMPOR - PROTOTYPE FRONTEND
    // ============================================
    
    // Rute Master Data (Produk)
    Route::get('master-data', function () {
        return view('admin.master_data.index');
    })->name('master-data.index');
    
    Route::get('master-data/create', function () {
        return view('admin.master_data.form');
    })->name('master-data.create');
    
    Route::get('master-data/edit/{id}', function ($id) {
        return view('admin.master_data.form');
    })->name('master-data.edit');
    
    // Rute Manajemen Kuota
    Route::get('kuota', function () {
        return view('admin.kuota.index');
    })->name('kuota.index');
    
    Route::get('kuota/create', function () {
        return view('admin.kuota.form');
    })->name('kuota.create');
    
    Route::get('kuota/edit/{id}', function ($id) {
        return view('admin.kuota.form');
    })->name('kuota.edit');
    
    // Rute Purchase Order (PO)
    Route::get('purchase-order', function () {
        return view('admin.purchase_order.index');
    })->name('purchase-order.index');
    
    Route::get('purchase-order/create', function () {
        return view('admin.purchase_order.create');
    })->name('purchase-order.create');
    
    // Rute Pengiriman (Shipment)
    Route::get('shipment', function () {
        return view('admin.shipment.index');
    })->name('shipment.index');

});

// Rute Otentikasi (dari Laravel Breeze atau UI)
require __DIR__.'/auth.php';