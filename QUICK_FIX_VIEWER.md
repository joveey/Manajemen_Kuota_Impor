# ⚡ Quick Fix: Viewer Role Problem

## 🎯 Masalah
User "Annisa" (viewer) bisa akses `/admin/users` padahal seharusnya **403 Forbidden**.

## ✅ Solusi Cepat

### Windows:
```bash
fix-viewer-permissions.bat
```

### Manual:
```bash
# 1. Fix permissions
php artisan db:seed --class=RolePermissionSeeder

# 2. Clear cache
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:clear

# 3. Optimize
php artisan optimize
```

### Setelah itu:
1. **Logout** dari akun viewer
2. **Login** kembali
3. Coba akses `/admin/users` → Seharusnya **403 Forbidden** ✅

## 🔍 Verifikasi

### ✅ Yang Benar:
- Menu "Administration" **TIDAK** muncul untuk viewer
- Akses `/admin/users` → **403 Forbidden**
- Akses `/admin/roles` → **403 Forbidden**
- Akses `/admin/permissions` → **403 Forbidden**
- Dashboard dan data masih bisa diakses (read-only)
- User yang sedang login **TIDAK** muncul di list users

### ❌ Jika Masih Bermasalah:
```bash
# Hard reset permissions
php artisan migrate:fresh --seed
```
⚠️ **WARNING:** Ini akan menghapus semua data!

## 📋 Permission Viewer

Role "viewer" **HANYA** punya:
```
✅ read dashboard
✅ read quota
✅ read purchase_orders
✅ read master_data
✅ read reports
```

**TIDAK** punya:
```
❌ read users
❌ read roles
❌ read permissions
❌ create/update/delete apapun
```

## 🔧 Perubahan yang Dilakukan

### 1. UserController.php
```php
// Sekarang exclude user yang sedang login
->where('id', '!=', auth()->id())
```

### 2. RolePermissionSeeder.php
```php
// Viewer hanya punya read permissions
$viewerPermissions = Permission::whereIn('name', [
    'read dashboard',
    'read quota',
    'read purchase_orders',
    'read master_data',
    'read reports',
])->pluck('id');
```

## 📚 Dokumentasi Lengkap
Lihat: `FIX_VIEWER_ROLE.md`

---
**Status:** ✅ Fixed  
**Tested:** ✅ Yes
