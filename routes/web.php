<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\AdminController;
use Illuminate\Support\Facades\Auth;

// Redirect root to login or dashboard
Route::get('/', function () {
    return auth::check() 
        ? redirect()->route('dashboard') 
        : redirect()->route('login');
});

// Dashboard Route (Protected)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

// Profile Routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin Routes (Users, Roles, Permissions, Admins)
Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Users Management
    Route::resource('users', UserController::class);
    
    // Roles Management
    Route::resource('roles', RoleController::class);
    
    // Permissions Management
    Route::resource('permissions', PermissionController::class);
    
    // Admins Management
    Route::resource('admins', AdminController::class);
    Route::post('admins/{admin}/convert-to-user', [AdminController::class, 'convertToUser'])
        ->name('admins.convert');
});


// Auth Routes (dari Laravel Breeze)
require __DIR__.'/auth.php';