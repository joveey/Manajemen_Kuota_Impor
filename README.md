# Laravel Application with Role-Based Access Control

## 📋 Overview

Aplikasi Laravel dengan sistem **Role-Based Access Control (RBAC)** lengkap, fitur registrasi user otomatis, dan admin panel untuk management user, role, dan permission.

### ✨ Fitur Utama

- ✅ **User Registration** - Registrasi user baru dengan auto-assign role "viewer"
- ✅ **Role Management** - 4 default roles (admin, manager, editor, viewer)
- ✅ **Permission System** - 17 permissions dengan grouping berdasarkan modul
- ✅ **Admin Panel** - CRUD lengkap untuk users, roles, dan permissions
- ✅ **Middleware Protection** - Route protection berdasarkan role & permission
- ✅ **Active User Check** - Otomatis logout user yang di-nonaktifkan

---

## 🚀 Quick Start

### Prerequisites
- PHP 8.2+
- Composer
- MySQL/PostgreSQL/SQLite
- Node.js & NPM (untuk asset compilation)

### Installation

```bash
# 1. Clone repository
git clone <repository-url>
cd unnamed-project

# 2. Install dependencies
composer install
npm install

# 3. Setup environment
cp .env.example .env
php artisan key:generate

# 4. Configure database di .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

# 5. Run migration & seeder
php artisan migrate:fresh --seed

# 6. Compile assets
npm run dev

# 7. Start server
php artisan serve
```

Aplikasi akan berjalan di: `http://localhost:8000`

---

## 👥 Default Users

Setelah seeding, gunakan akun berikut untuk testing:

| Email | Password | Role | Akses |
|-------|----------|------|-------|
| admin@example.com | password | Admin | Full access |
| manager@example.com | password | Manager | Manage users & roles |
| editor@example.com | password | Editor | Create & edit users |
| viewer@example.com | password | Viewer | Read-only |

---

## 📚 Dokumentasi

### Dokumentasi Lengkap

| File | Deskripsi |
|------|-----------|
| [ROLE_SYSTEM_DOCUMENTATION.md](ROLE_SYSTEM_DOCUMENTATION.md) | Dokumentasi lengkap sistem RBAC |
| [SETUP_ROLE_SYSTEM.md](SETUP_ROLE_SYSTEM.md) | Panduan setup step-by-step |
| [REGISTRATION_GUIDE.md](REGISTRATION_GUIDE.md) | Panduan sistem registrasi |
| [CHANGELOG_ROLE_SYSTEM.md](CHANGELOG_ROLE_SYSTEM.md) | Detail perubahan sistem |
| [SYSTEM_UPDATES_SUMMARY.md](SYSTEM_UPDATES_SUMMARY.md) | Ringkasan perubahan |

### Quick Links

- **Setup Guide:** Baca [SETUP_ROLE_SYSTEM.md](SETUP_ROLE_SYSTEM.md)
- **Role System:** Baca [ROLE_SYSTEM_DOCUMENTATION.md](ROLE_SYSTEM_DOCUMENTATION.md)
- **Registration:** Baca [REGISTRATION_GUIDE.md](REGISTRATION_GUIDE.md)

---

## 🎯 Sistem Role & Permission

### Default Roles

#### 1. **Admin** (Administrator)
- Full system access
- Semua permissions

#### 2. **Manager**
- Manage users (CRUD)
- Manage roles (create, update, view)
- View permissions

#### 3. **Editor**
- Create & update users
- View roles

#### 4. **Viewer** (Default untuk registrasi)
- Read-only access
- View dashboard, users, roles, permissions

### Permission Groups

1. **Dashboard** - Akses dashboard
2. **User Management** - CRUD users
3. **Admin Management** - CRUD admins
4. **Role Management** - CRUD roles
5. **Permission Management** - CRUD permissions

---

## 🔐 Registrasi User Baru

### Alur Registrasi

1. User mengisi form di `/register`
2. Validasi input (name, email unique, password confirmed)
3. User dibuat dengan `is_active = true`
4. Auto-assign role **"viewer"**
5. Auto-login
6. Redirect ke dashboard

### Testing Registrasi

```bash
# Via browser
http://localhost:8000/register

# Via Tinker
php artisan tinker
```

```php
$user = User::create([
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => Hash::make('password'),
    'is_active' => true
]);

$user->assignRole('viewer');
$user->hasRole('viewer'); // true
```

---

## 🛡️ Middleware Protection

### Role Middleware

Proteksi route berdasarkan role:

```php
Route::get('/admin', [AdminController::class, 'index'])
    ->middleware('role:admin');

// Multiple roles (OR logic)
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('role:admin,manager');
```

### Permission Middleware

Proteksi route berdasarkan permission:

```php
Route::get('/users', [UserController::class, 'index'])
    ->middleware('permission:read users');
```

### Active User Check

Otomatis logout user yang di-nonaktifkan:

```php
Route::middleware(['auth', 'active'])->group(function () {
    // Protected routes
});
```

---

## 💻 Penggunaan di Code

### Cek Role

```php
// Di Controller
if (auth()->user()->hasRole('admin')) {
    // Admin only code
}

// Multiple roles
if (auth()->user()->hasRole(['admin', 'manager'])) {
    // Admin atau Manager
}

// Di Blade
@if(auth()->user()->hasRole('admin'))
    <a href="/admin">Admin Panel</a>
@endif
```

### Cek Permission

```php
// Di Controller
if (auth()->user()->hasPermission('create users')) {
    // User boleh create users
}

// Di Blade
@if(auth()->user()->hasPermission('create users'))
    <button>Create User</button>
@endif
```

### Assign Role

```php
$user = User::find(1);
$user->assignRole('admin');

// Atau dengan object
$adminRole = Role::where('name', 'admin')->first();
$user->assignRole($adminRole);
```

### Remove Role

```php
$user->removeRole('viewer');
```

### Sync Roles (Replace all)

```php
$user->syncRoles([1, 2, 3]); // Role IDs
```

---

## 📁 Struktur Project

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/          # Admin panel controllers
│   │   └── Auth/           # Authentication controllers
│   └── Middleware/
│       ├── RoleMiddleware.php
│       ├── PermissionMiddleware.php
│       └── CheckActiveUser.php
├── Models/
│   ├── User.php            # User model + role methods
│   ├── Role.php            # Role model
│   └── Permission.php      # Permission model
database/
├── migrations/             # Database schema
└── seeders/
    ├── RolePermissionSeeder.php
    └── UserSeeder.php
resources/
└── views/
    ├── admin/              # Admin panel views
    └── auth/               # Auth views (login, register)
```

---

## 🧪 Testing

### Manual Testing

```bash
# 1. Test registrasi
http://localhost:8000/register

# 2. Test login
http://localhost:8000/login

# 3. Test admin panel
http://localhost:8000/admin/users
```

### Via Tinker

```bash
php artisan tinker
```

```php
// Test role
$user = User::find(1);
$user->hasRole('admin');
$user->hasPermission('create users');

// Test assign role
$user->assignRole('manager');

// Lihat roles & permissions
$user->roles;
foreach($user->roles as $role) {
    echo $role->name . "\n";
    foreach($role->permissions as $perm) {
        echo "  - " . $perm->name . "\n";
    }
}
```

---

## 🔧 Troubleshooting

### Role 'viewer' not found

```bash
php artisan db:seed --class=RolePermissionSeeder
```

### User tidak bisa login

```bash
php artisan tinker
```

```php
$user = User::where('email', 'test@example.com')->first();
$user->update(['is_active' => true]);
$user->assignRole('viewer');
```

### Clear Cache

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

## 🛠️ Development

### Tambah Permission Baru

Edit `database/seeders/RolePermissionSeeder.php`:

```php
[
    'name' => 'create posts',
    'display_name' => 'Create Posts',
    'group' => 'Content Management',
    'description' => 'Create new posts'
],
```

Jalankan seeder:

```bash
php artisan db:seed --class=RolePermissionSeeder
```

### Tambah Role Baru

```php
$customRole = Role::create([
    'name' => 'moderator',
    'display_name' => 'Moderator',
    'description' => 'Can moderate content',
    'is_active' => true
]);

$customRole->givePermissionTo('read users');
$customRole->givePermissionTo('update users');
```

### Ubah Default Role Registrasi

Edit `app/Http/Controllers/Auth/RegisteredUserController.php`:

```php
// Ganti dari 'viewer' ke role lain
$user->assignRole('editor');
```

---

## 📦 Tech Stack

- **Framework:** Laravel 11.x
- **PHP:** 8.2+
- **Database:** MySQL/PostgreSQL/SQLite
- **Frontend:** Blade Templates + AdminLTE
- **Authentication:** Laravel Breeze
- **Authorization:** Custom RBAC

---

## 📄 License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

## 🤝 Contributing

Contributions are welcome! Please read the documentation before making changes.

---

## 📞 Support

Untuk pertanyaan atau masalah:

1. Baca dokumentasi lengkap di folder docs
2. Cek log Laravel: `storage/logs/laravel.log`
3. Gunakan `php artisan tinker` untuk debugging

---

## ✅ Checklist Setup

- [ ] Install dependencies
- [ ] Setup .env
- [ ] Run migration
- [ ] Run seeder
- [ ] Test registrasi
- [ ] Test login
- [ ] Test admin panel
- [ ] Test middleware

---

**Happy Coding!** 🚀
