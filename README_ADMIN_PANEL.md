# Laravel Admin Panel dengan AdminLTE

Sistem Admin Panel lengkap dengan manajemen Permissions, Roles, Users, dan Admins menggunakan Laravel dan AdminLTE.

## 🎯 Fitur Utama

### 1. **Permission Management**
- ✅ CRUD Permissions
- ✅ Validasi nama permission (harus dimulai dengan: `create`, `read`, `update`, atau `delete`)
- ✅ Lihat roles yang memiliki permission tertentu
- ✅ Pagination dan search

### 2. **Role Management**
- ✅ CRUD Roles
- ✅ Assign multiple permissions ke role
- ✅ Lihat users yang memiliki role tertentu
- ✅ Select all / Deselect all permissions
- ✅ Proteksi untuk system roles (admin, super-admin)

### 3. **User Management**
- ✅ CRUD Users (non-admin)
- ✅ Assign multiple roles ke user
- ✅ Active/Inactive status
- ✅ Password management
- ✅ Lihat semua permissions user melalui roles

### 4. **Admin Management**
- ✅ CRUD Admins
- ✅ Admin role otomatis ter-assign
- ✅ **Fitur Khusus**: Admin tidak bisa langsung dihapus
- ✅ Admin harus dikonversi ke user dulu sebelum bisa dihapus
- ✅ Proteksi: tidak bisa convert/delete akun sendiri

### 5. **Dashboard**
- ✅ Statistik: Total Users, Admins, Roles, Permissions
- ✅ Users by Role chart
- ✅ Recent Users table
- ✅ Quick links ke management pages

## 📁 Struktur File

```
app/
├── Http/
│   └── Controllers/
│       └── Admin/
│           ├── DashboardController.php
│           ├── PermissionController.php
│           ├── RoleController.php
│           ├── UserController.php
│           └── AdminController.php
├── Models/
│   ├── User.php
│   ├── Role.php
│   └── Permission.php

resources/
└── views/
    ├── admin/
    │   ├── permissions/
    │   │   ├── index.blade.php
    │   │   ├── create.blade.php
    │   │   ├── edit.blade.php
    │   │   └── show.blade.php
    │   ├── roles/
    │   │   ├── index.blade.php
    │   │   ├── create.blade.php
    │   │   ├── edit.blade.php
    │   │   └── show.blade.php
    │   ├── users/
    │   │   ├── index.blade.php
    │   │   ├── create.blade.php
    │   │   ├── edit.blade.php
    │   │   └── show.blade.php
    │   ├── admins/
    │   │   ├── index.blade.php
    │   │   ├── create.blade.php
    │   │   ├── edit.blade.php
    │   │   └── show.blade.php
    │   └── dashboard.blade.php
    └── layouts/
        ├── admin.blade.php
        └── partials/
            ├── navbar.blade.php
            ├── sidebar.blade.php
            └── footer.blade.php
```

## 🚀 Instalasi

### 1. Install Dependencies
```bash
composer install
npm install
```

### 2. Setup Environment
```bash
cp .env.example .env
php artisan key:generate
```

### 3. Konfigurasi Database
Edit file `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 4. Jalankan Migration
```bash
php artisan migrate
```

### 5. Jalankan Seeder (Optional)
```bash
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=UserSeeder
```

### 6. Install AdminLTE (via CDN)
AdminLTE sudah dikonfigurasi menggunakan CDN di layout admin. Untuk menggunakan AdminLTE secara lokal:

```bash
npm install admin-lte@^3.2
```

Kemudian update `resources/css/app.css`:
```css
@import 'admin-lte/dist/css/adminlte.min.css';
```

### 7. Compile Assets
```bash
npm run dev
# atau untuk production
npm run build
```

### 8. Jalankan Server
```bash
php artisan serve
```

Akses aplikasi di: `http://localhost:8000`

## 📝 Routes

### Dashboard
- `GET /dashboard` - Dashboard utama

### Permissions
- `GET /admin/permissions` - List permissions
- `GET /admin/permissions/create` - Form create permission
- `POST /admin/permissions` - Store permission
- `GET /admin/permissions/{id}` - Show permission detail
- `GET /admin/permissions/{id}/edit` - Form edit permission
- `PUT /admin/permissions/{id}` - Update permission
- `DELETE /admin/permissions/{id}` - Delete permission

### Roles
- `GET /admin/roles` - List roles
- `GET /admin/roles/create` - Form create role
- `POST /admin/roles` - Store role
- `GET /admin/roles/{id}` - Show role detail
- `GET /admin/roles/{id}/edit` - Form edit role
- `PUT /admin/roles/{id}` - Update role
- `DELETE /admin/roles/{id}` - Delete role

### Users
- `GET /admin/users` - List users (non-admin)
- `GET /admin/users/create` - Form create user
- `POST /admin/users` - Store user
- `GET /admin/users/{id}` - Show user detail
- `GET /admin/users/{id}/edit` - Form edit user
- `PUT /admin/users/{id}` - Update user
- `DELETE /admin/users/{id}` - Delete user

### Admins
- `GET /admin/admins` - List admins
- `GET /admin/admins/create` - Form create admin
- `POST /admin/admins` - Store admin
- `GET /admin/admins/{id}` - Show admin detail
- `GET /admin/admins/{id}/edit` - Form edit admin
- `PUT /admin/admins/{id}` - Update admin
- `POST /admin/admins/{id}/convert-to-user` - Convert admin to user

## 🔐 Permission Naming Convention

Semua permission **HARUS** dimulai dengan salah satu prefix berikut:
- `create` - Untuk membuat resource baru
- `read` - Untuk melihat/membaca resource
- `update` - Untuk mengupdate resource
- `delete` - Untuk menghapus resource

### Contoh Valid:
✅ `create new admin`
✅ `read users list`
✅ `update user profile`
✅ `delete old records`

### Contoh Invalid:
❌ `manage users`
❌ `view dashboard`
❌ `edit profile`

## 🛡️ Fitur Keamanan

### 1. Admin Protection
- Admin tidak bisa dihapus langsung
- Harus dikonversi ke user terlebih dahulu
- User tidak bisa convert/delete akun sendiri

### 2. Role Protection
- System roles (admin, super-admin) tidak bisa dihapus
- Validasi sebelum delete role

### 3. Permission Validation
- Nama permission harus mengikuti convention
- Validasi di controller dan form

## 🎨 Customization

### Mengubah Tema
Edit file `resources/views/layouts/partials/sidebar.blade.php`:
```html
<!-- Untuk dark sidebar -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">

<!-- Untuk light sidebar -->
<aside class="main-sidebar sidebar-light-primary elevation-4">
```

### Mengubah Brand Logo
Edit file `resources/views/layouts/partials/sidebar.blade.php`:
```html
<a href="{{ route('dashboard') }}" class="brand-link">
    <img src="YOUR_LOGO_URL" alt="Logo" class="brand-image img-circle elevation-3">
    <span class="brand-text font-weight-light">Your App Name</span>
</a>
```

## 📊 Database Schema

### Users Table
- `id` - Primary key
- `name` - User name
- `email` - Email (unique)
- `password` - Hashed password
- `is_active` - Boolean status
- `last_login_at` - Timestamp
- `created_at`, `updated_at`

### Roles Table
- `id` - Primary key
- `name` - Role name (unique)
- `description` - Role description
- `created_at`, `updated_at`

### Permissions Table
- `id` - Primary key
- `name` - Permission name (unique)
- `description` - Permission description
- `created_at`, `updated_at`

### Pivot Tables
- `role_user` - Many-to-many: users ↔ roles
- `permission_role` - Many-to-many: roles ↔ permissions

## 🐛 Troubleshooting

### Error: Class 'Role' not found
Pastikan model Role sudah dibuat dan namespace-nya benar.

### AdminLTE CSS tidak muncul
1. Pastikan CDN link di layout admin sudah benar
2. Atau install AdminLTE via npm dan compile assets

### Permission validation error
Pastikan nama permission dimulai dengan: create, read, update, atau delete

## 📚 Resources

- [Laravel Documentation](https://laravel.com/docs)
- [AdminLTE Documentation](https://adminlte.io/docs)
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission)

## 👨‍💻 Developer Notes

### Menambah Permission Baru
1. Buat permission via UI atau seeder
2. Assign ke role yang sesuai
3. Gunakan di controller atau blade:
```php
// Di Controller
if (!auth()->user()->hasPermission('create users')) {
    abort(403);
}

// Di Blade
@if(auth()->user()->hasPermission('create users'))
    <!-- Show content -->
@endif
```

### Menambah Role Baru
1. Buat role via UI
2. Assign permissions yang diperlukan
3. Assign role ke users

## 📄 License

This project is open-sourced software licensed under the MIT license.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

---

**Dibuat dengan ❤️ menggunakan Laravel & AdminLTE**
