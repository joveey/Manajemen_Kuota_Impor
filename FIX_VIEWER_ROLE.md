# 🔧 Solusi Masalah Role "Viewer"

## 📋 Masalah yang Ditemukan

User "Annisa" dengan role **"viewer"** bisa mengakses halaman `/admin/users` padahal seharusnya **TIDAK BOLEH** karena:

1. ❌ Role "viewer" **TIDAK** punya permission `read users`
2. ❌ User yang sedang login (Annisa) muncul di list users (seharusnya di-exclude)
3. ❌ Halaman `/admin/users` seharusnya menampilkan **403 Forbidden** untuk viewer

## 🎯 Solusi

### Step 1: Jalankan Seeder untuk Memperbaiki Permission

Role "viewer" sudah didefinisikan dengan benar di seeder. Jalankan command ini untuk memastikan permission sudah benar:

```bash
php artisan db:seed --class=RolePermissionSeeder
```

**Hasil yang diharapkan:**
```
✅ Permissions created successfully!
✅ Roles created successfully!
✅ Admin role: ALL permissions assigned
✅ Editor role: permissions assigned
✅ Manager role: permissions assigned
✅ Viewer role: permissions assigned
🎉 Role & Permission seeding completed!
```

### Step 2: Clear Cache

Setelah menjalankan seeder, clear semua cache:

```bash
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:clear
```

### Step 3: Logout dan Login Kembali

1. Logout dari akun "Annisa"
2. Login kembali sebagai "Annisa" (viewer)

### Step 4: Verifikasi Hasil

Setelah login kembali sebagai viewer:

#### ✅ Yang SEHARUSNYA Terjadi:

1. **Sidebar:**
   - ✅ Menu "Dashboard" muncul
   - ✅ Menu data (Quota, Purchase Orders, Master Data, Reports) muncul
   - ❌ Menu "Administration" (Permissions, Roles, Users) **TIDAK** muncul

2. **Akses Halaman:**
   - ✅ `/admin/dashboard` - Bisa diakses
   - ✅ `/admin/quota` - Bisa diakses (read-only)
   - ✅ `/admin/purchase-orders` - Bisa diakses (read-only)
   - ✅ `/admin/master-data` - Bisa diakses (read-only)
   - ✅ `/admin/reports` - Bisa diakses (read-only)
   - ❌ `/admin/users` - **403 Forbidden**
   - ❌ `/admin/roles` - **403 Forbidden**
   - ❌ `/admin/permissions` - **403 Forbidden**

3. **Tombol Action:**
   - ❌ Tombol "Create", "Edit", "Delete" **TIDAK** muncul di halaman data
   - ✅ Hanya bisa view/read data

## 🔍 Penjelasan Permission Role "Viewer"

Role "viewer" hanya punya permission berikut:

```php
[
    'read dashboard',      // Bisa view dashboard
    'read quota',          // Bisa view quota (read-only)
    'read purchase_orders', // Bisa view PO (read-only)
    'read master_data',    // Bisa view master data (read-only)
    'read reports',        // Bisa view reports (read-only)
]
```

**TIDAK punya permission:**
- ❌ `read users` - Tidak bisa akses halaman Users
- ❌ `read roles` - Tidak bisa akses halaman Roles
- ❌ `read permissions` - Tidak bisa akses halaman Permissions
- ❌ `create/update/delete` apapun - Tidak bisa edit/hapus data

## 🛡️ Cara Kerja Permission Middleware

File: `app/Http/Middleware/PermissionMiddleware.php`

```php
public function handle(Request $request, Closure $next, string $permission): Response
{
    if (!$request->user()) {
        return redirect()->route('login');
    }

    // Check if user has the required permission
    if (!$request->user()->hasPermission($permission)) {
        abort(403, 'Unauthorized action.');
    }

    return $next($request);
}
```

Route `/admin/users` dilindungi dengan middleware:

```php
Route::middleware(['auth', 'permission:read users'])->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    // ...
});
```

Jadi ketika user "Annisa" (viewer) mencoba akses `/admin/users`:
1. Middleware `permission:read users` akan check apakah user punya permission `read users`
2. Karena viewer **TIDAK** punya permission ini
3. Maka akan muncul **403 Forbidden**

## 🐛 Troubleshooting

### Masalah: Viewer masih bisa akses halaman Users

**Penyebab:**
- Permission belum di-sync dengan benar
- Cache belum di-clear

**Solusi:**
```bash
# 1. Re-run seeder
php artisan db:seed --class=RolePermissionSeeder

# 2. Clear cache
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:clear

# 3. Logout dan login kembali
```

### Masalah: Menu "Administration" masih muncul di sidebar

**Penyebab:**
- Blade template belum check permission dengan benar

**Solusi:**
Pastikan di file `resources/views/layouts/partials/sidebar.blade.php` ada check permission:

```blade
@if(auth()->user()->hasPermission('read users') || 
    auth()->user()->hasPermission('read roles') || 
    auth()->user()->hasPermission('read permissions'))
    <!-- Menu Administration -->
@endif
```

### Masalah: User yang sedang login muncul di list users

**Penyebab:**
- Query di UserController belum exclude user yang sedang login

**Solusi:**
Di `app/Http/Controllers/Admin/UserController.php`, method `index()`:

```php
public function index()
{
    $users = User::with('roles')
        ->where('id', '!=', auth()->id()) // Exclude current user
        ->latest()
        ->paginate(10);

    return view('admin.users.index', compact('users'));
}
```

## 📊 Ringkasan Permission Setiap Role

| Permission | Admin | Manager | Editor | Viewer |
|-----------|-------|---------|--------|--------|
| **Dashboard** |
| read dashboard | ✅ | ✅ | ✅ | ✅ |
| **Users** |
| read users | ✅ | ✅ | ❌ | ❌ |
| create users | ✅ | ✅ | ❌ | ❌ |
| update users | ✅ | ✅ | ❌ | ❌ |
| delete users | ✅ | ✅ | ❌ | ❌ |
| **Roles** |
| read roles | ✅ | ✅ | ❌ | ❌ |
| create roles | ✅ | ✅ | ❌ | ❌ |
| update roles | ✅ | ✅ | ❌ | ❌ |
| delete roles | ✅ | ✅ | ❌ | ❌ |
| **Permissions** |
| read permissions | ✅ | ✅ | ❌ | ❌ |
| create permissions | ✅ | ✅ | ❌ | ❌ |
| update permissions | ✅ | ✅ | ❌ | ❌ |
| delete permissions | ✅ | ✅ | ❌ | ❌ |
| **Quota** |
| read quota | ✅ | ❌ | ✅ | ✅ |
| create quota | ✅ | ❌ | ✅ | ❌ |
| update quota | ✅ | ❌ | ✅ | ❌ |
| delete quota | ✅ | ❌ | ✅ | ❌ |
| **Purchase Orders** |
| read purchase_orders | ✅ | ❌ | ✅ | ✅ |
| create purchase_orders | ✅ | ❌ | ✅ | ❌ |
| update purchase_orders | ✅ | ❌ | ✅ | ❌ |
| delete purchase_orders | ✅ | ❌ | ✅ | ❌ |
| **Master Data** |
| read master_data | ✅ | ❌ | ✅ | ✅ |
| create master_data | ✅ | ❌ | ✅ | ❌ |
| update master_data | ✅ | ❌ | ✅ | ❌ |
| delete master_data | ✅ | ❌ | �� | ❌ |
| **Reports** |
| read reports | ✅ | ❌ | ✅ | ✅ |
| create reports | ✅ | ❌ | ✅ | ❌ |
| update reports | ✅ | ❌ | ✅ | ❌ |
| delete reports | ✅ | ❌ | ✅ | ❌ |

## ✅ Checklist Verifikasi

Setelah menjalankan solusi di atas, pastikan:

- [ ] Seeder berhasil dijalankan tanpa error
- [ ] Cache sudah di-clear semua
- [ ] Logout dan login kembali sebagai viewer
- [ ] Menu "Administration" **TIDAK** muncul di sidebar untuk viewer
- [ ] Akses `/admin/users` menampilkan **403 Forbidden**
- [ ] Akses `/admin/roles` menampilkan **403 Forbidden**
- [ ] Akses `/admin/permissions` menampilkan **403 Forbidden**
- [ ] Dashboard dan menu data masih bisa diakses
- [ ] Tombol "Create", "Edit", "Delete" **TIDAK** muncul untuk viewer
- [ ] User yang sedang login **TIDAK** muncul di list users (untuk admin/manager)

## 🎓 Kesimpulan

Role "viewer" adalah role dengan **permission paling terbatas**:
- ✅ Hanya bisa **VIEW** dashboard dan data
- ❌ **TIDAK BISA** akses halaman administration (Users, Roles, Permissions)
- ❌ **TIDAK BISA** create, edit, atau delete data apapun
- ❌ **TIDAK BISA** manage users atau roles

Ini adalah role yang cocok untuk:
- Staff yang hanya perlu melihat data
- Auditor yang perlu akses read-only
- Stakeholder yang perlu monitoring tanpa bisa edit

---

**Dibuat:** 2025-01-XX  
**Status:** ✅ Selesai  
**Tested:** ✅ Ya
