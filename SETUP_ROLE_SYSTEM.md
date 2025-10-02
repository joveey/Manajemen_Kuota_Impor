# Setup Guide - Role & Registration System

## Langkah-langkah Setup

### 1. Reset Database (Jika Diperlukan)

Jika Anda sudah pernah menjalankan migration sebelumnya, reset database terlebih dahulu:

```bash
php artisan migrate:fresh
```

⚠️ **PERINGATAN:** Perintah ini akan menghapus semua data di database!

### 2. Jalankan Migration

Migration akan membuat tabel-tabel berikut:
- `users` - Data user
- `roles` - Data role
- `permissions` - Data permission
- `role_user` - Pivot table user-role
- `permission_role` - Pivot table permission-role

```bash
php artisan migrate
```

### 3. Jalankan Seeder

#### Seed Role & Permission
```bash
php artisan db:seed --class=RolePermissionSeeder
```

Output yang diharapkan:
```
✅ Permissions created successfully!
✅ Roles created successfully!
✅ Admin role: ALL permissions assigned
✅ Manager role: permissions assigned
✅ Editor role: permissions assigned
✅ Viewer role: permissions assigned
🎉 Role & Permission seeding completed!
```

#### Seed User Testing (Opsional)
```bash
php artisan db:seed --class=UserSeeder
```

Output yang diharapkan:
```
✅ Admin user created: admin@example.com / password
✅ Manager user created: manager@example.com / password
✅ Editor user created: editor@example.com / password
✅ Viewer user created: viewer@example.com / password
🎉 User seeding completed!
```

#### Atau Jalankan Semua Sekaligus
```bash
php artisan migrate:fresh --seed
```

### 4. Verifikasi Setup

#### Cek Data di Database

**Cek Roles:**
```sql
SELECT * FROM roles;
```
Harus ada 4 roles: admin, manager, editor, viewer

**Cek Permissions:**
```sql
SELECT * FROM permissions;
```
Harus ada 17 permissions

**Cek Permission-Role Mapping:**
```sql
SELECT r.name as role, p.name as permission 
FROM permission_role pr
JOIN roles r ON pr.role_id = r.id
JOIN permissions p ON pr.permission_id = p.id
ORDER BY r.name, p.name;
```

**Cek Users (jika sudah seed):**
```sql
SELECT u.name, u.email, r.name as role 
FROM users u
JOIN role_user ru ON u.id = ru.user_id
JOIN roles r ON ru.role_id = r.id;
```

### 5. Test Registrasi

#### Via Browser:
1. Buka: `http://localhost:8000/register`
2. Isi form registrasi
3. Submit
4. Seharusnya redirect ke dashboard
5. User baru otomatis mendapat role "viewer"

#### Via Tinker:
```bash
php artisan tinker
```

```php
// Buat user baru
$user = User::create([
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => Hash::make('password'),
    'is_active' => true
]);

// Assign role viewer
$user->assignRole('viewer');

// Cek role
$user->hasRole('viewer'); // true
$user->hasRole('admin'); // false

// Cek permission
$user->hasPermission('read dashboard'); // true
$user->hasPermission('create users'); // false

// Lihat semua roles user
$user->roles;

// Lihat semua permissions user (via roles)
foreach($user->roles as $role) {
    echo $role->name . " permissions:\n";
    foreach($role->permissions as $perm) {
        echo "  - " . $perm->name . "\n";
    }
}
```

### 6. Test Login

#### Login sebagai Admin:
```
Email: admin@example.com
Password: password
```
Seharusnya bisa akses semua fitur.

#### Login sebagai Viewer:
```
Email: viewer@example.com
Password: password
```
Seharusnya hanya bisa read-only.

### 7. Test Middleware

Tambahkan route testing di `routes/web.php`:

```php
// Test role middleware
Route::get('/test-admin', function() {
    return 'Welcome Admin!';
})->middleware(['auth', 'role:admin']);

Route::get('/test-viewer', function() {
    return 'Welcome Viewer!';
})->middleware(['auth', 'role:viewer,admin']);

// Test permission middleware
Route::get('/test-create-users', function() {
    return 'You can create users!';
})->middleware(['auth', 'permission:create users']);
```

Test akses:
1. Login sebagai admin → bisa akses semua route
2. Login sebagai viewer → hanya bisa akses `/test-viewer`
3. Akses tanpa login → redirect ke login (401)
4. Akses tanpa permission → error 403

---

## Troubleshooting

### Error: "Role 'viewer' not found"
**Solusi:**
```bash
php artisan db:seed --class=RolePermissionSeeder
```

### Error: "SQLSTATE[42S02]: Base table or view not found"
**Solusi:**
```bash
php artisan migrate
```

### User tidak bisa login setelah registrasi
**Cek:**
1. Apakah role 'viewer' ada di database?
2. Apakah permission 'read dashboard' ada?
3. Apakah role 'viewer' punya permission 'read dashboard'?

**Solusi:**
```bash
php artisan migrate:fresh --seed
```

### Middleware tidak bekerja
**Cek di `bootstrap/app.php`:**
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => \App\Http\Middleware\RoleMiddleware::class,
        'permission' => \App\Http\Middleware\PermissionMiddleware::class,
        'active' => \App\Http\Middleware\CheckActiveUser::class,
    ]);
})
```

### Permission tidak bekerja setelah update
**Clear cache:**
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

## Checklist Setup

- [ ] Database sudah dibuat
- [ ] File `.env` sudah dikonfigurasi dengan benar
- [ ] Migration berhasil dijalankan
- [ ] Seeder RolePermissionSeeder berhasil
- [ ] Seeder UserSeeder berhasil (opsional)
- [ ] Bisa registrasi user baru
- [ ] User baru otomatis dapat role 'viewer'
- [ ] Bisa login dengan user testing
- [ ] Middleware role bekerja
- [ ] Middleware permission bekerja
- [ ] Middleware active user bekerja

---

## Next Steps

Setelah setup selesai, Anda bisa:

1. **Customize Permissions**
   - Tambah permission baru di seeder
   - Assign ke role yang sesuai

2. **Buat Role Baru**
   - Tambah di seeder atau via admin panel
   - Assign permissions yang diperlukan

3. **Protect Routes**
   - Tambahkan middleware ke route yang perlu proteksi
   - Gunakan `role:` atau `permission:` middleware

4. **Build Admin Panel**
   - CRUD untuk users
   - CRUD untuk roles
   - CRUD untuk permissions
   - Assign roles ke users
   - Assign permissions ke roles

5. **Customize Registration**
   - Ubah default role jika diperlukan
   - Tambah field tambahan di form
   - Tambah email verification

---

## Struktur File Penting

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Auth/
│   │       └── RegisteredUserController.php  # Logic registrasi
│   └── Middleware/
│       ├── RoleMiddleware.php                # Middleware role
│       ├── PermissionMiddleware.php          # Middleware permission
│       └── CheckActiveUser.php               # Middleware active user
├── Models/
│   ├── User.php                              # Model User + methods
│   ├── Role.php                              # Model Role + methods
│   └── Permission.php                        # Model Permission + methods
database/
├── migrations/
│   ├── 0001_01_01_000000_create_users_table.php
│   ├── 2025_10_01_075437_create_roles_table.php
│   ├── 2025_10_01_075440_create_permissions_table.php
│   ├── 2025_10_01_075447_create_role_user_table.php
│   └── 2025_10_01_075452_create_permission_role_table.php
└── seeders/
    ├── RolePermissionSeeder.php              # Seed roles & permissions
    └── UserSeeder.php                        # Seed testing users
resources/
└── views/
    └── auth/
        └── register.blade.php                # Form registrasi
```

---

## Kontak & Support

Jika ada masalah atau pertanyaan, silakan:
1. Cek dokumentasi lengkap di `ROLE_SYSTEM_DOCUMENTATION.md`
2. Cek log Laravel di `storage/logs/laravel.log`
3. Gunakan `php artisan tinker` untuk debugging

---

**Setup selesai! Sistem role & registrasi siap digunakan.** ✅
