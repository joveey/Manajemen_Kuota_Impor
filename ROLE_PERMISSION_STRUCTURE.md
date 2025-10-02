# Struktur Role dan Permission

Dokumen ini menjelaskan struktur role dan permission yang digunakan dalam sistem.

## 📋 Daftar Role

### 1. **Admin**
- **Display Name**: Administrator
- **Description**: Full system access with all permissions
- **Permissions**: **SEMUA PERMISSION**
  - Dashboard
  - User Management (Create, Read, Update, Delete)
  - Role Management (Create, Read, Update, Delete)
  - Permission Management (Create, Read, Update, Delete)
  - Quota Management (Create, Read, Update, Delete)
  - Purchase Orders (Create, Read, Update, Delete)
  - Master Data (Create, Read, Update, Delete)
  - Reports (Create, Read, Update, Delete)

### 2. **Editor**
- **Display Name**: Editor
- **Description**: Can create, edit, and delete dashboard data (Quota, Purchase Orders, Master Data, Reports)
- **Permissions**:
  - ✅ Read Dashboard
  - ✅ Quota Management (Create, Read, Update, Delete)
  - ✅ Purchase Orders (Create, Read, Update, Delete)
  - ✅ Master Data (Create, Read, Update, Delete)
  - ✅ Reports (Create, Read, Update, Delete)
  - ❌ **TIDAK** bisa manage Users
  - ❌ **TIDAK** bisa manage Roles
  - ❌ **TIDAK** bisa manage Permissions

### 3. **Manager**
- **Display Name**: Manager
- **Description**: Can manage users, roles, and permissions (except admin role)
- **Permissions**:
  - ✅ Read Dashboard
  - ✅ User Management (Create, Read, Update, Delete)
    - Bisa ubah role user tanpa password
    - Bisa edit user
    - Bisa delete user
  - ✅ Role Management (Create, Read, Update, Delete)
    - **Catatan**: Tidak bisa mengedit atau menghapus role Admin
  - ✅ Permission Management (Create, Read, Update, Delete)
  - ❌ **TIDAK** bisa manage data dashboard (Quota, Purchase Orders, Master Data, Reports)

## 📊 Tabel Permission per Role

| Permission Group | Permission | Admin | Editor | Manager |
|-----------------|------------|-------|--------|---------|
| **Dashboard** | Read Dashboard | ✅ | ✅ | ✅ |
| **User Management** | Read Users | ✅ | ❌ | ✅ |
| | Create Users | ✅ | ❌ | ✅ |
| | Update Users | ✅ | ❌ | ✅ |
| | Delete Users | ✅ | ❌ | ✅ |
| **Role Management** | Read Roles | ✅ | ❌ | ✅ |
| | Create Roles | ✅ | ❌ | ✅ |
| | Update Roles | ✅ | ❌ | ✅ |
| | Delete Roles | ✅ | ❌ | ✅ |
| **Permission Management** | Read Permissions | ✅ | ❌ | ✅ |
| | Create Permissions | ✅ | ❌ | ✅ |
| | Update Permissions | ✅ | ❌ | ✅ |
| | Delete Permissions | ✅ | ❌ | ✅ |
| **Quota Management** | Read Quota | ✅ | ✅ | ❌ |
| | Create Quota | ✅ | ✅ | ❌ |
| | Update Quota | ✅ | ✅ | ❌ |
| | Delete Quota | ✅ | ✅ | ❌ |
| **Purchase Orders** | Read Purchase Orders | ✅ | ✅ | ❌ |
| | Create Purchase Orders | ✅ | ✅ | ❌ |
| | Update Purchase Orders | ✅ | ✅ | ❌ |
| | Delete Purchase Orders | ✅ | ✅ | ❌ |
| **Master Data** | Read Master Data | ✅ | ✅ | ❌ |
| | Create Master Data | ✅ | ✅ | ❌ |
| | Update Master Data | ✅ | ✅ | ❌ |
| | Delete Master Data | ✅ | ✅ | ❌ |
| **Reports** | Read Reports | ✅ | ✅ | ❌ |
| | Create Reports | ✅ | ✅ | ❌ |
| | Update Reports | ✅ | ✅ | ❌ |
| | Delete Reports | ✅ | ✅ | ❌ |

## 🔄 Cara Menerapkan Perubahan

Untuk menerapkan struktur role dan permission yang baru, jalankan seeder:

```bash
php artisan db:seed --class=RolePermissionSeeder
```

Atau jika ingin reset semua data dan seed ulang:

```bash
php artisan migrate:fresh --seed
```

## 📝 Catatan Penting

1. **Role Admin** tidak bisa diedit atau dihapus oleh Manager
2. **Manager** bisa mengubah role user tanpa perlu mengubah password
3. **Editor** fokus pada pengelolaan data dashboard, tidak bisa mengakses user management
4. **Manager** fokus pada pengelolaan user dan role, tidak bisa mengakses data dashboard

## 🔐 Implementasi di Controller

Untuk membatasi Manager agar tidak bisa mengedit role Admin, tambahkan validasi di RoleController:

```php
// Di RoleController@update dan RoleController@destroy
if ($role->name === 'admin' && !auth()->user()->hasRole('admin')) {
    abort(403, 'You cannot modify the Admin role.');
}
```

## 📅 Tanggal Update

Terakhir diupdate: <?php echo date('Y-m-d H:i:s'); ?>
