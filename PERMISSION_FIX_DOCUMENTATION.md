# Permission System Fix Documentation

## Masalah yang Ditemukan

### 1. **Routes Tidak Memiliki Permission Middleware**
**Masalah:** Semua route admin (`/admin/users`, `/admin/roles`, `/admin/permissions`) dapat diakses oleh semua user yang terautentikasi, tanpa memeriksa permission mereka.

**Dampak:** 
- Role `viewer` bisa mengakses halaman Users, Roles, dan Permissions
- Role `editor` bisa mengakses halaman yang seharusnya hanya untuk Manager/Admin
- Tidak ada kontrol akses yang sesuai dengan permission yang didefinisikan

### 2. **Sidebar Menampilkan Link Tanpa Permission Check**
**Masalah:** Menu sidebar menampilkan link "Permissions", "Roles", dan "Users" untuk semua user tanpa memeriksa apakah mereka memiliki permission untuk mengaksesnya.

**Dampak:**
- User dengan role `viewer` atau `editor` melihat menu yang tidak seharusnya mereka akses
- Membingungkan user karena mereka melihat menu yang tidak bisa mereka gunakan

### 3. **Admin Tidak Bisa Dihapus Langsung**
**Masalah:** Admin harus dikonversi ke user biasa terlebih dahulu sebelum bisa dihapus.

**Dampak:**
- Proses penghapusan admin menjadi 2 langkah (convert → delete)
- Tidak efisien dan membingungkan

---

## Solusi yang Diterapkan

### 1. **Menambahkan Permission Middleware ke Routes**

**File:** `routes/web.php`

**Perubahan:**
```php
// SEBELUM: Tidak ada middleware permission
Route::resource('users', UserController::class);
Route::resource('roles', RoleController::class);
Route::resource('permissions', PermissionController::class);

// SESUDAH: Setiap route dilindungi dengan permission middleware
// Users Management
Route::middleware(['permission:read users'])->group(function () {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
});
Route::middleware(['permission:create users'])->group(function () {
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
});
// ... dst untuk update dan delete
```

**Mapping Permission ke Route:**
- `read users` → index, show
- `create users` → create, store
- `update users` → edit, update
- `delete users` → destroy

Hal yang sama diterapkan untuk `roles` dan `permissions`.

### 2. **Menambahkan Permission Check di Sidebar**

**File:** `resources/views/layouts/partials/sidebar.blade.php`

**Perubahan:**
```php
// SEBELUM: Menampilkan tanpa check
<li class="nav-item">
    <a href="{{ route('admin.permissions.index') }}">
        <i class="nav-icon fas fa-key"></i>
        <p>Permissions</p>
    </a>
</li>

// SESUDAH: Hanya tampil jika user punya permission
@if(Auth::user()->hasPermission('read permissions'))
<li class="nav-item">
    <a href="{{ route('admin.permissions.index') }}">
        <i class="nav-icon fas fa-key"></i>
        <p>Permissions</p>
    </a>
</li>
@endif
```

**Diterapkan untuk:**
- Permissions → `read permissions`
- Roles → `read roles`
- Users → `read users`
- Admins → `isAdmin()` (tetap sama)

### 3. **Memperbaiki Admin Delete Logic**

**File:** `app/Http/Controllers/Admin/AdminController.php`

**Perubahan:**
```php
// SEBELUM: Tidak bisa delete admin langsung
public function destroy(User $admin)
{
    if ($admin->isAdmin()) {
        return redirect()->route('admin.admins.index')
            ->with('error', 'Cannot delete admin directly. Please convert to regular user first.');
    }
}

// SESUDAH: Bisa delete admin dengan safeguard
public function destroy(User $admin)
{
    // Verify user is admin
    if (!$admin->isAdmin()) {
        return redirect()->route('admin.admins.index')
            ->with('error', 'This user is not an admin.');
    }

    // Prevent deleting current user
    if ($admin->id === auth()->id()) {
        return redirect()->route('admin.admins.index')
            ->with('error', 'Cannot delete your own admin account.');
    }

    // Check if this is the last admin
    $adminCount = User::whereHas('roles', function ($query) {
        $query->where('name', 'admin');
    })->count();

    if ($adminCount <= 1) {
        return redirect()->route('admin.admins.index')
            ->with('error', 'Cannot delete the last admin user. System must have at least one admin.');
    }

    // Detach all roles and delete
    $admin->roles()->detach();
    $admin->delete();

    return redirect()->route('admin.admins.index')
        ->with('success', 'Admin deleted successfully.');
}
```

**Safeguards yang ditambahkan:**
1. Tidak bisa delete diri sendiri
2. Tidak bisa delete admin terakhir (sistem harus punya minimal 1 admin)
3. Otomatis detach semua roles sebelum delete

---

## Hasil Setelah Fix

### Role: Admin
✅ **Dapat mengakses:**
- Dashboard
- Users (CRUD)
- Roles (CRUD)
- Permissions (CRUD)
- Admins (CRUD)
- Semua fitur lainnya

✅ **Sidebar menampilkan:**
- Semua menu termasuk Permissions, Roles, Users, Admins

### Role: Manager
✅ **Dapat mengakses:**
- Dashboard
- Users (CRUD)
- Roles (CRUD)
- Permissions (CRUD)

❌ **Tidak dapat mengakses:**
- Admins management

✅ **Sidebar menampilkan:**
- Permissions, Roles, Users
- TIDAK menampilkan Admins

### Role: Editor
✅ **Dapat mengakses:**
- Dashboard
- Quota Management (CRUD)
- Purchase Orders (CRUD)
- Master Data (CRUD)
- Reports (CRUD)

❌ **Tidak dapat mengakses:**
- Users, Roles, Permissions, Admins

✅ **Sidebar menampilkan:**
- Hanya menu terkait data (Quota, PO, Master Data, Reports)
- TIDAK menampilkan menu Administration

### Role: Viewer (jika ada)
✅ **Dapat mengakses:**
- Dashboard
- View-only untuk data yang dipermit

❌ **Tidak dapat mengakses:**
- Create, Update, Delete operations
- Users, Roles, Permissions, Admins

✅ **Sidebar menampilkan:**
- Hanya menu yang sesuai permission mereka
- TIDAK menampilkan menu Administration

---

## Testing Checklist

### Test dengan Role Manager:
- [ ] Login sebagai Manager
- [ ] Coba akses `/admin/users` → ✅ Berhasil
- [ ] Coba akses `/admin/roles` → ✅ Berhasil
- [ ] Coba akses `/admin/permissions` → ✅ Berhasil
- [ ] Coba akses `/admin/admins` → ❌ Error 403 (Forbidden)
- [ ] Check sidebar → Hanya tampil Users, Roles, Permissions

### Test dengan Role Editor:
- [ ] Login sebagai Editor
- [ ] Coba akses `/admin/users` → ❌ Error 403 (Forbidden)
- [ ] Coba akses `/admin/roles` → ❌ Error 403 (Forbidden)
- [ ] Coba akses `/admin/permissions` → ❌ Error 403 (Forbidden)
- [ ] Check sidebar → TIDAK tampil menu Administration

### Test Admin Delete:
- [ ] Login sebagai Admin
- [ ] Buat admin baru
- [ ] Coba delete admin baru → ✅ Berhasil
- [ ] Coba delete admin terakhir → ❌ Error (tidak bisa)
- [ ] Coba delete diri sendiri → ❌ Error (tidak bisa)

---

## Cara Menjalankan Setelah Fix

1. **Clear cache:**
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

2. **Test dengan berbagai role:**
```bash
# Buat user dengan role berbeda untuk testing
php artisan tinker

# Buat user manager
$user = User::create([
    'name' => 'Test Manager',
    'email' => 'manager@test.com',
    'password' => bcrypt('password'),
    'is_active' => true
]);
$user->assignRole('manager');

# Buat user editor
$user = User::create([
    'name' => 'Test Editor',
    'email' => 'editor@test.com',
    'password' => bcrypt('password'),
    'is_active' => true
]);
$user->assignRole('editor');
```

3. **Login dan test akses:**
- Login dengan setiap role
- Coba akses berbagai halaman
- Verifikasi sidebar hanya menampilkan menu yang sesuai

---

## File yang Dimodifikasi

1. ✅ `routes/web.php` - Menambahkan permission middleware
2. ✅ `resources/views/layouts/partials/sidebar.blade.php` - Menambahkan permission check
3. ✅ `app/Http/Controllers/Admin/AdminController.php` - Memperbaiki delete logic

## File yang TIDAK Perlu Dimodifikasi

- ✅ `app/Http/Middleware/PermissionMiddleware.php` - Sudah benar
- ✅ `app/Http/Middleware/RoleMiddleware.php` - Sudah benar
- ✅ `app/Models/User.php` - Sudah benar
- ✅ `app/Models/Role.php` - Sudah benar
- ✅ `bootstrap/app.php` - Middleware sudah terdaftar

---

## Catatan Penting

1. **Permission Naming Convention:**
   - Format: `{action} {resource}`
   - Contoh: `read users`, `create roles`, `delete permissions`

2. **Middleware Aliases:**
   - `permission:{permission_name}` untuk check permission
   - `role:{role_name}` untuk check role

3. **Blade Directives:**
   - `@if(Auth::user()->hasPermission('permission_name'))` untuk check permission
   - `@if(Auth::user()->isAdmin())` untuk check admin role

4. **Admin Safeguards:**
   - Sistem harus selalu punya minimal 1 admin
   - Admin tidak bisa delete diri sendiri
   - Admin bisa langsung dihapus (tidak perlu convert dulu)

---

## Troubleshooting

### Jika masih bisa akses halaman yang tidak seharusnya:
```bash
php artisan route:clear
php artisan cache:clear
php artisan config:clear
```

### Jika sidebar masih menampilkan menu yang tidak seharusnya:
```bash
php artisan view:clear
```

### Jika permission tidak bekerja:
1. Check apakah middleware terdaftar di `bootstrap/app.php`
2. Check apakah role punya permission yang benar di database
3. Run seeder ulang jika perlu:
```bash
php artisan db:seed --class=RolePermissionSeeder
```

---

## Kesimpulan

Semua masalah permission telah diperbaiki:
1. ✅ Routes dilindungi dengan permission middleware
2. ✅ Sidebar hanya menampilkan menu sesuai permission
3. ✅ Admin bisa dihapus langsung dengan safeguard yang proper

Sistem sekarang bekerja sesuai dengan permission yang didefinisikan untuk setiap role.
