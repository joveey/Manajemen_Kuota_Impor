# Cara Menerapkan Struktur Role dan Permission Baru

Dokumen ini menjelaskan langkah-langkah untuk menerapkan struktur role dan permission yang baru.

## 📋 Perubahan yang Dilakukan

### 1. **Struktur Role Baru**
- **Admin**: Semua permission
- **Editor**: Bisa create, edit, delete data dashboard (Quota, Purchase Orders, Master Data, Reports) - TIDAK bisa manage users, roles, permissions
- **Manager**: Bisa manage users, roles, permissions (kecuali role admin) - TIDAK bisa manage data dashboard

### 2. **File yang Diubah**
- `database/seeders/RolePermissionSeeder.php` - Struktur role dan permission
- `app/Http/Controllers/Admin/RoleController.php` - Proteksi role Admin dari Manager

## 🚀 Langkah-langkah Penerapan

### Opsi 1: Seed Ulang (Recommended untuk Development)

Jika Anda ingin **reset semua data** dan mulai dari awal:

```bash
# Reset database dan jalankan semua migration + seeder
php artisan migrate:fresh --seed
```

⚠️ **PERINGATAN**: Perintah ini akan **menghapus semua data** di database!

### Opsi 2: Seed Role & Permission Saja

Jika Anda ingin **memperbarui role dan permission** tanpa menghapus data lain:

```bash
# Jalankan seeder RolePermissionSeeder
php artisan db:seed --class=RolePermissionSeeder
```

Seeder ini menggunakan `firstOrCreate`, jadi:
- Role yang sudah ada akan diupdate
- Permission yang sudah ada akan tetap ada
- Mapping permission ke role akan diupdate

### Opsi 3: Manual Update (Untuk Production)

Jika Anda di production dan tidak ingin menjalankan seeder:

1. **Update Role Description** (Optional):
```sql
UPDATE roles SET description = 'Can create, edit, and delete dashboard data (Quota, Purchase Orders, Master Data, Reports)' WHERE name = 'editor';
UPDATE roles SET description = 'Can manage users, roles, and permissions (except admin role)' WHERE name = 'manager';
```

2. **Update Permission Mapping untuk Editor**:
```sql
-- Hapus semua permission editor yang lama
DELETE FROM permission_role WHERE role_id = (SELECT id FROM roles WHERE name = 'editor');

-- Tambahkan permission baru untuk editor
INSERT INTO permission_role (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
CROSS JOIN roles r
WHERE r.name = 'editor'
AND p.name IN (
    'read dashboard',
    'read quota', 'create quota', 'update quota', 'delete quota',
    'read purchase_orders', 'create purchase_orders', 'update purchase_orders', 'delete purchase_orders',
    'read master_data', 'create master_data', 'update master_data', 'delete master_data',
    'read reports', 'create reports', 'update reports', 'delete reports'
);
```

3. **Update Permission Mapping untuk Manager**:
```sql
-- Hapus semua permission manager yang lama
DELETE FROM permission_role WHERE role_id = (SELECT id FROM roles WHERE name = 'manager');

-- Tambahkan permission baru untuk manager
INSERT INTO permission_role (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
CROSS JOIN roles r
WHERE r.name = 'manager'
AND p.name IN (
    'read dashboard',
    'read users', 'create users', 'update users', 'delete users',
    'read roles', 'create roles', 'update roles', 'delete roles',
    'read permissions', 'create permissions', 'update permissions', 'delete permissions'
);
```

## ✅ Verifikasi Perubahan

Setelah menerapkan perubahan, verifikasi dengan:

### 1. Cek Role yang Ada
```bash
php artisan tinker
```

```php
// Di tinker
Role::with('permissions')->get()->map(function($role) {
    return [
        'name' => $role->name,
        'permissions_count' => $role->permissions->count(),
        'permissions' => $role->permissions->pluck('name')
    ];
});
```

### 2. Test Login dengan Setiap Role

1. **Login sebagai Admin**
   - Harus bisa akses semua menu
   - Bisa edit/delete role Admin

2. **Login sebagai Editor**
   - Bisa akses Dashboard
   - Bisa akses Quota Management, Purchase Orders, Master Data, Reports
   - TIDAK bisa akses Users, Roles, Permissions

3. **Login sebagai Manager**
   - Bisa akses Dashboard
   - Bisa akses Users, Roles, Permissions
   - TIDAK bisa edit/delete role Admin
   - TIDAK bisa akses Quota Management, Purchase Orders, Master Data, Reports

## 🔐 Proteksi Role Admin

RoleController sudah diupdate dengan proteksi:

```php
// Di edit(), update(), dan destroy()
if ($role->name === 'admin' && !auth()->user()->hasRole('admin')) {
    return redirect()->route('admin.roles.index')
        ->with('error', 'You cannot modify the Admin role.');
}
```

Ini memastikan hanya user dengan role Admin yang bisa:
- Edit role Admin
- Update role Admin
- Delete role Admin

## 📊 Ringkasan Permission

| Role | Dashboard | User Mgmt | Role Mgmt | Permission Mgmt | Quota | Purchase Orders | Master Data | Reports |
|------|-----------|-----------|-----------|-----------------|-------|-----------------|-------------|---------|
| **Admin** | ✅ Full | ✅ Full | ✅ Full | ✅ Full | ✅ Full | ✅ Full | ✅ Full | ✅ Full |
| **Editor** | ✅ View | ❌ | ❌ | ❌ | ✅ Full | ✅ Full | ✅ Full | ✅ Full |
| **Manager** | ✅ View | ✅ Full | ✅ Full* | ✅ Full | ❌ | ❌ | ❌ | ❌ |

*Manager tidak bisa edit/delete role Admin

## 🆘 Troubleshooting

### Error: "Class 'RolePermissionSeeder' not found"
```bash
composer dump-autoload
php artisan db:seed --class=RolePermissionSeeder
```

### Permission tidak berubah setelah seed
```bash
# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### User masih bisa akses menu yang seharusnya tidak bisa
1. Logout dan login kembali
2. Clear browser cache
3. Cek permission di database:
```sql
SELECT r.name as role, p.name as permission
FROM roles r
JOIN permission_role pr ON r.id = pr.role_id
JOIN permissions p ON pr.permission_id = p.id
WHERE r.name IN ('editor', 'manager')
ORDER BY r.name, p.name;
```

## 📝 Catatan Penting

1. **Backup Database** sebelum menjalankan `migrate:fresh`
2. **Test di Development** dulu sebelum apply ke Production
3. **Dokumentasikan** perubahan permission untuk tim
4. **Update User Manual** jika ada

## 📞 Kontak

Jika ada pertanyaan atau masalah, hubungi tim development.
