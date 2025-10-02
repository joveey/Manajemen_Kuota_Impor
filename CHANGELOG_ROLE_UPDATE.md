# Changelog - Update Struktur Role dan Permission

## [Update] - 2024

### 🎯 Tujuan Perubahan
Mengubah struktur role dan permission agar lebih sesuai dengan kebutuhan bisnis:
- **Admin**: Full access ke semua fitur
- **Editor**: Fokus pada pengelolaan data dashboard
- **Manager**: Fokus pada pengelolaan user dan role

### ✨ Perubahan Utama

#### 1. **Role Editor - Perubahan Besar**

**Sebelum:**
- Bisa create dan edit users
- Bisa view roles
- Akses terbatas ke dashboard

**Sesudah:**
- ✅ Full access ke Quota Management (Create, Read, Update, Delete)
- ✅ Full access ke Purchase Orders (Create, Read, Update, Delete)
- ✅ Full access ke Master Data (Create, Read, Update, Delete)
- ✅ Full access ke Reports (Create, Read, Update, Delete)
- ❌ TIDAK bisa manage Users
- ❌ TIDAK bisa manage Roles
- ❌ TIDAK bisa manage Permissions

**Alasan:** Editor sekarang fokus pada pengelolaan konten/data dashboard, bukan user management.

#### 2. **Role Manager - Perubahan Besar**

**Sebelum:**
- Bisa manage users
- Bisa view dan create roles (terbatas)
- Bisa view permissions
- Bisa view semua data

**Sesudah:**
- ✅ Full access ke User Management (Create, Read, Update, Delete)
- ✅ Full access ke Role Management (Create, Read, Update, Delete) - **kecuali role Admin**
- ✅ Full access ke Permission Management (Create, Read, Update, Delete)
- ❌ TIDAK bisa edit atau delete role Admin
- ❌ TIDAK bisa manage data dashboard (Quota, Purchase Orders, Master Data, Reports)

**Alasan:** Manager sekarang fokus pada pengelolaan user dan role, bukan data operasional.

#### 3. **Role Admin - Tidak Berubah**
- Tetap memiliki semua permission
- Satu-satunya role yang bisa edit/delete role Admin sendiri

#### 4. **Role Viewer - Dihapus**
Role Viewer tidak lagi diperlukan dalam struktur baru.

### 📝 File yang Diubah

#### 1. `database/seeders/RolePermissionSeeder.php`
**Perubahan:**
- Update deskripsi role Editor dan Manager
- Hapus role Viewer
- Update mapping permission untuk Editor (fokus ke data dashboard)
- Update mapping permission untuk Manager (fokus ke user/role management)

**Kode Sebelum:**
```php
// EDITOR: Can create and edit users
$editorPermissions = Permission::whereIn('name', [
    'read dashboard',
    'read users', 'create users', 'update users',
    'read roles',
])->pluck('id');

// MANAGER: Can manage users, roles, and view everything
$managerPermissions = Permission::whereIn('name', [
    'read dashboard',
    'read users', 'create users', 'update users', 'delete users',
    'read roles', 'create roles', 'update roles',
    'read permissions',
])->pluck('id');
```

**Kode Sesudah:**
```php
// EDITOR: Can create, edit, delete dashboard data
$editorPermissions = Permission::whereIn('name', [
    'read dashboard',
    // Quota Management
    'read quota', 'create quota', 'update quota', 'delete quota',
    // Purchase Orders
    'read purchase_orders', 'create purchase_orders', 'update purchase_orders', 'delete purchase_orders',
    // Master Data
    'read master_data', 'create master_data', 'update master_data', 'delete master_data',
    // Reports
    'read reports', 'create reports', 'update reports', 'delete reports',
])->pluck('id');

// MANAGER: Can manage users, roles & permissions (except admin role)
$managerPermissions = Permission::whereIn('name', [
    'read dashboard',
    // User Management
    'read users', 'create users', 'update users', 'delete users',
    // Role Management
    'read roles', 'create roles', 'update roles', 'delete roles',
    // Permission Management
    'read permissions', 'create permissions', 'update permissions', 'delete permissions',
])->pluck('id');
```

#### 2. `app/Http/Controllers/Admin/RoleController.php`
**Perubahan:**
- Tambah proteksi di method `edit()` untuk mencegah Manager edit role Admin
- Tambah proteksi di method `update()` untuk mencegah Manager update role Admin
- Update proteksi di method `destroy()` untuk mencegah Manager delete role Admin

**Kode yang Ditambahkan:**
```php
// Di edit() dan update()
if ($role->name === 'admin' && !auth()->user()->hasRole('admin')) {
    return redirect()->route('admin.roles.index')
        ->with('error', 'You cannot modify the Admin role.');
}
```

### 📊 Perbandingan Permission

| Permission Group | Permission | Admin | Editor (Lama) | Editor (Baru) | Manager (Lama) | Manager (Baru) |
|-----------------|------------|-------|---------------|---------------|----------------|----------------|
| Dashboard | Read | ✅ | ✅ | ✅ | ✅ | ✅ |
| User Management | Full CRUD | ✅ | Create, Update | ❌ | Full CRUD | ✅ Full CRUD |
| Role Management | Full CRUD | ✅ | Read | ❌ | Create, Update | ✅ Full CRUD* |
| Permission Mgmt | Full CRUD | ✅ | ❌ | ❌ | Read | ✅ Full CRUD |
| Quota Mgmt | Full CRUD | ✅ | ❌ | ✅ Full CRUD | ❌ | ❌ |
| Purchase Orders | Full CRUD | ✅ | ❌ | ✅ Full CRUD | ❌ | ❌ |
| Master Data | Full CRUD | ✅ | ❌ | ✅ Full CRUD | ❌ | ❌ |
| Reports | Full CRUD | ✅ | ❌ | ✅ Full CRUD | ❌ | ❌ |

*Manager tidak bisa edit/delete role Admin

### 🚀 Cara Menerapkan

#### Development:
```bash
php artisan migrate:fresh --seed
```

#### Production:
```bash
php artisan db:seed --class=RolePermissionSeeder
```

Lihat file `CARA_TERAPKAN_ROLE_BARU.md` untuk panduan lengkap.

### ⚠️ Breaking Changes

1. **User dengan role Editor** akan kehilangan akses ke:
   - User Management
   - Role Management
   
   Tapi mendapat akses baru ke:
   - Quota Management
   - Purchase Orders
   - Master Data
   - Reports

2. **User dengan role Manager** akan kehilangan akses ke:
   - Quota Management
   - Purchase Orders
   - Master Data
   - Reports
   
   Tapi mendapat akses baru ke:
   - Full Permission Management
   - Full Role Management (kecuali Admin)

3. **Role Viewer dihapus** - User dengan role Viewer perlu dipindahkan ke role lain

### 📋 Action Items

- [ ] Backup database sebelum update
- [ ] Test di development environment
- [ ] Update user manual/dokumentasi
- [ ] Informasikan perubahan ke semua user
- [ ] Reassign user dengan role Viewer ke role yang sesuai
- [ ] Verifikasi permission setelah update
- [ ] Test login dengan setiap role

### 🔍 Testing Checklist

#### Admin
- [ ] Bisa akses semua menu
- [ ] Bisa edit role Admin
- [ ] Bisa delete role Admin
- [ ] Bisa manage semua data

#### Editor
- [ ] Bisa akses Dashboard
- [ ] Bisa CRUD Quota Management
- [ ] Bisa CRUD Purchase Orders
- [ ] Bisa CRUD Master Data
- [ ] Bisa CRUD Reports
- [ ] TIDAK bisa akses Users
- [ ] TIDAK bisa akses Roles
- [ ] TIDAK bisa akses Permissions

#### Manager
- [ ] Bisa akses Dashboard
- [ ] Bisa CRUD Users
- [ ] Bisa CRUD Roles (kecuali Admin)
- [ ] Bisa CRUD Permissions
- [ ] TIDAK bisa edit role Admin
- [ ] TIDAK bisa delete role Admin
- [ ] TIDAK bisa akses Quota Management
- [ ] TIDAK bisa akses Purchase Orders
- [ ] TIDAK bisa akses Master Data
- [ ] TIDAK bisa akses Reports

### 📚 Dokumentasi Terkait

- `ROLE_PERMISSION_STRUCTURE.md` - Struktur lengkap role dan permission
- `CARA_TERAPKAN_ROLE_BARU.md` - Panduan implementasi
- `ROLE_SYSTEM_DOCUMENTATION.md` - Dokumentasi sistem role (existing)

### 👥 Impact Analysis

**Editor (Dampak Tinggi):**
- Perubahan fokus dari user management ke data management
- Perlu training untuk fitur-fitur data dashboard
- Kehilangan akses user management

**Manager (Dampak Sedang):**
- Perubahan fokus dari data viewing ke user/role management
- Mendapat akses penuh ke permission management
- Kehilangan akses ke data operasional

**Admin (Tidak Ada Dampak):**
- Tetap memiliki full access

### 🔄 Rollback Plan

Jika perlu rollback, restore dari backup database atau jalankan seeder versi lama.

### 📞 Support

Jika ada pertanyaan atau masalah setelah update, hubungi tim development.

---

**Catatan:** Perubahan ini dibuat untuk memisahkan tanggung jawab dengan lebih jelas antara pengelolaan data (Editor) dan pengelolaan user/role (Manager).
