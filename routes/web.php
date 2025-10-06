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
    return Auth::check() 
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
    
    // Roles Management - Requires role permissions
    Route::middleware(['permission:read roles'])->group(function () {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    });
    Route::middleware(['permission:create roles'])->group(function () {
        Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    });
    Route::middleware(['permission:read roles'])->group(function () {
        Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
    });
    Route::middleware(['permission:update roles'])->group(function () {
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::patch('roles/{role}', [RoleController::class, 'update']);
    });
    Route::middleware(['permission:delete roles'])->group(function () {
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });
    
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
});


// Auth Routes (dari Laravel Breeze)
require __DIR__.'/auth.php';