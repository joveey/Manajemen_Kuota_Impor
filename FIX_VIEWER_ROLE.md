# Fix Viewer Role - Permission Issue

## ❌ Masalah yang Ditemukan

User dengan role "viewer" bisa mengakses halaman `/admin/users` padahal seharusnya TIDAK BISA karena tidak punya permission `read users`.

### Screenshot Masalah:
- User "Annisa" dengan role "viewer" bisa akses halaman Users
- Sidebar menampilkan menu "Permissions", "Roles", "Users" untuk viewer
- Ini TIDAK SEHARUSNYA terjadi!

### Root Cause:
Role "viewer" tidak didefinisikan di `RolePermissionSeeder.php`, sehingga:
1. Role "viewer" ada di database tapi tidak punya permission yang benar
2. Atau role "viewer" punya permission yang salah (mungkin punya `read users`)

---

## ✅ Solusi

### 1. Menambahkan Role "Viewer" ke Seeder

File: `database/seeders/RolePermissionSeeder.php`

**Ditambahkan:**
```php
$viewerRole = Role::firstOrCreate(
    ['name' => 'viewer'],
    [
        'display_name' => 'Viewer',
        'description' => 'Can only view dashboard and reports (read-only access)',
        'is_active' => true
    ]
);
```

### 2. Assign Permission yang Benar untuk Viewer

**Permission untuk Viewer (Read-Only):**
```php
$viewerPermissions = Permission::whereIn('name', [
    'read dashboard',
    // Read-only access to data
    'read quota',
    'read purchase_orders',
    'read master_data',
    'read reports',
])->pluck('id');
$viewerRole->permissions()->sync($viewerPermissions);
```

**TIDAK termasuk:**
- ❌ `read users` - Viewer TIDAK bisa lihat users
- ❌ `read roles` - Viewer TIDAK bisa lihat roles
- ❌ `read permissions` - Viewer TIDAK bisa lihat permissions
- ❌ Semua `create`, `update`, `delete` permissions

---

## 🚀 Cara Menerapkan Fix

### 1. Jalankan Seeder Ulang
```bash
php artisan db:seed --class=RolePermissionSeeder
```

Ini akan:
- ✅ Membuat role "viewer" jika belum ada
- ✅ Sync permission yang benar untuk role "viewer"
- ✅ Menghapus permission yang tidak seharusnya (seperti `read users`)

### 2. Clear Cache
```bash
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### 3. Test dengan User Viewer

**Login sebagai user dengan role "viewer":**

**Expected Results:**
- ✅ Bisa akses `/dashboard` → 200 OK
- ❌ Tidak bisa akses `/admin/users` → 403 Forbidden
- ❌ Tidak bisa akses `/admin/roles` → 403 Forbidden
- ❌ Tidak bisa akses `/admin/permissions` → 403 Forbidden
- ✅ Sidebar TIDAK menampilkan menu "Administration"
- ✅ Sidebar hanya menampilkan: Dashboard, Quota, PO, Master Data, Reports (read-only)

---

## 📊 Permission Matrix (Updated)

| Resource | Admin | Manager | Editor | Viewer |
|----------|-------|---------|--------|--------|
| Dashboard | ✅ View | ✅ View | ✅ View | ✅ View |
| Users | ✅ CRUD | ✅ CRUD | ❌ None | ❌ None |
| Roles | ✅ CRUD | ✅ CRUD | ❌ None | ❌ None |
| Permissions | ✅ CRUD | ✅ CRUD | ❌ None | ❌ None |
| Admins | ✅ CRUD | ❌ None | ❌ None | ❌ None |
| Quota | ✅ CRUD | ❌ None | ✅ CRUD | ✅ View Only |
| Purchase Orders | ✅ CRUD | ❌ None | ✅ CRUD | ✅ View Only |
| Master Data | ✅ CRUD | ❌ None | ✅ CRUD | ✅ View Only |
| Reports | ✅ CRUD | ❌ None | ✅ CRUD | ✅ View Only |

---

## 🔍 Cara Verify Permission di Database

### Check Permission untuk Role Viewer:
```bash
php artisan tinker
```

```php
$viewer = Role::where('name', 'viewer')->first();
$viewer->permissions->pluck('name');

// Expected output:
// [
//   "read dashboard",
//   "read quota",
//   "read purchase_orders",
//   "read master_data",
//   "read reports"
// ]

// Should NOT include:
// - "read users"
// - "read roles"
// - "read permissions"
// - Any "create", "update", "delete" permissions
```

### Check User's Permissions:
```php
$user = User::where('email', 'annisa@gmail.com')->first();
$user->roles->pluck('name'); // Should show: ["viewer"]

// Check if user has specific permission
$user->hasPermission('read users'); // Should return: false
$user->hasPermission('read dashboard'); // Should return: true
```

---

## 🎯 Expected Behavior After Fix

### Viewer Role:
**Can Access:**
- ✅ Dashboard (view only)
- ✅ Quota data (view only)
- ✅ Purchase Orders (view only)
- ✅ Master Data (view only)
- ✅ Reports (view only)

**Cannot Access:**
- ❌ Users management
- ❌ Roles management
- ❌ Permissions management
- ❌ Admins management
- ❌ Any create/edit/delete operations

**Sidebar:**
- ✅ Dashboard
- ✅ Quota Management (if implemented)
- ✅ Purchase Orders (if implemented)
- ✅ Master Data (if implemented)
- ✅ Reports (if implemented)
- ❌ **ADMINISTRATION section** (should NOT appear)
- ✅ Settings

---

## ⚠️ Important Notes

1. **Seeder is Idempotent:**
   - Menjalankan seeder berkali-kali aman
   - Menggunakan `firstOrCreate()` dan `sync()` untuk avoid duplicates

2. **Existing Data:**
   - Seeder tidak akan menghapus role atau permission yang sudah ada
   - Hanya akan update permission assignments

3. **User Assignments:**
   - User yang sudah assigned ke role "viewer" akan otomatis dapat permission yang baru
   - Tidak perlu re-assign user ke role

---

## 🧪 Testing Checklist

### ✅ Test sebagai Viewer:
- [ ] Login dengan user role "viewer"
- [ ] Akses `/dashboard` → Should work (200)
- [ ] Akses `/admin/users` → Should fail (403)
- [ ] Akses `/admin/roles` → Should fail (403)
- [ ] Akses `/admin/permissions` → Should fail (403)
- [ ] Check sidebar → Should NOT see "Administration" section
- [ ] Check sidebar → Should see Dashboard, Quota, PO, Master Data, Reports

### ✅ Test Permission Check:
- [ ] Run tinker command to verify permissions
- [ ] Confirm viewer role has only read permissions
- [ ] Confirm viewer role does NOT have `read users`, `read roles`, `read permissions`

---

## 📝 Summary

**Problem:** Viewer bisa akses halaman Users (tidak seharusnya)
**Cause:** Role "viewer" tidak didefinisikan di seeder atau punya permission yang salah
**Solution:** Menambahkan role "viewer" ke seeder dengan permission yang benar (read-only untuk data, TIDAK untuk users/roles/permissions)
**Action:** Jalankan `php artisan db:seed --class=RolePermissionSeeder`

**Status:** ✅ FIXED - Tinggal jalankan seeder
