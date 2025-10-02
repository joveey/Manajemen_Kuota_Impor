# Dokumentasi Sistem Role & Permission

## Overview
Sistem ini menggunakan **Role-Based Access Control (RBAC)** kustom berbasis Eloquent ORM Laravel untuk mengelola hak akses user.

---

## Arsitektur Database

### Tabel Utama

#### 1. **users**
Menyimpan data user aplikasi.
```
- id (primary key)
- name
- email (unique)
- password (hashed)
- is_active (boolean, default: true)
- last_login_at (timestamp, nullable)
- email_verified_at (timestamp, nullable)
- remember_token
- timestamps
```

#### 2. **roles**
Menyimpan daftar role dalam sistem.
```
- id (primary key)
- name (unique) - identifier role (contoh: 'admin', 'viewer')
- display_name (nullable) - nama tampilan (contoh: 'Administrator')
- description (text, nullable)
- is_active (boolean, default: true)
- timestamps
```

#### 3. **permissions**
Menyimpan daftar permission dalam sistem.
```
- id (primary key)
- name (unique) - identifier permission (contoh: 'read users')
- display_name (nullable) - nama tampilan (contoh: 'View Users')
- group (nullable) - grup permission (contoh: 'User Management')
- description (nullable)
- timestamps
```

#### 4. **role_user** (Pivot Table)
Relasi many-to-many antara User dan Role.
```
- id (primary key)
- role_id (foreign key -> roles.id, cascade on delete)
- user_id (foreign key -> users.id, cascade on delete)
- timestamps
- unique constraint: (role_id, user_id)
```

#### 5. **permission_role** (Pivot Table)
Relasi many-to-many antara Permission dan Role.
```
- id (primary key)
- permission_id (foreign key -> permissions.id, cascade on delete)
- role_id (foreign key -> roles.id, cascade on delete)
- timestamps
- unique constraint: (permission_id, role_id)
```

---

## Model & Relasi

### User Model
**Path:** `app/Models/User.php`

**Relasi:**
- `roles()` - BelongsToMany ke Role via tabel `role_user`

**Methods:**
- `hasRole(string|array $roles): bool` - Cek apakah user memiliki role tertentu
- `hasPermission(string $permissionName): bool` - Cek apakah user memiliki permission tertentu (via role)
- `assignRole(Role|string $role): void` - Assign role ke user
- `removeRole(Role|string $role): void` - Hapus role dari user
- `syncRoles(array $roleIds): void` - Replace semua role user
- `isAdmin(): bool` - Shortcut untuk cek apakah user adalah admin

### Role Model
**Path:** `app/Models/Role.php`

**Relasi:**
- `users()` - BelongsToMany ke User via tabel `role_user`
- `permissions()` - BelongsToMany ke Permission via tabel `permission_role`

**Methods:**
- `hasPermission(string $permissionName): bool` - Cek apakah role memiliki permission tertentu
- `givePermissionTo(Permission|string $permission): void` - Berikan permission ke role
- `revokePermissionTo(Permission|string $permission): void` - Hapus permission dari role

### Permission Model
**Path:** `app/Models/Permission.php`

**Relasi:**
- `roles()` - BelongsToMany ke Role via tabel `permission_role`

**Methods:**
- `getGrouped(): array` - Static method untuk mendapatkan permissions yang dikelompokkan berdasarkan group

---

## Middleware

### 1. RoleMiddleware
**Path:** `app/Http/Middleware/RoleMiddleware.php`

**Fungsi:** Memblokir akses jika user tidak memiliki role yang dipersyaratkan.

**Penggunaan di Route:**
```php
Route::get('/admin', [AdminController::class, 'index'])
    ->middleware('role:admin');

// Multiple roles (OR logic)
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('role:admin,manager');
```

**Response:**
- 401 jika user belum login
- 403 jika user tidak memiliki role yang diperlukan

### 2. PermissionMiddleware
**Path:** `app/Http/Middleware/PermissionMiddleware.php`

**Fungsi:** Memblokir akses jika user tidak memiliki permission yang dipersyaratkan.

**Penggunaan di Route:**
```php
Route::get('/users', [UserController::class, 'index'])
    ->middleware('permission:read users');
```

**Response:**
- 401 jika user belum login
- 403 jika user tidak memiliki permission yang diperlukan

### 3. CheckActiveUser
**Path:** `app/Http/Middleware/CheckActiveUser.php`

**Fungsi:** Memaksa logout user jika akun di-nonaktifkan (is_active = false).

**Response:**
- Redirect ke login dengan pesan error jika akun non-aktif

---

## Alur Registrasi User Baru

### Controller: RegisteredUserController
**Path:** `app/Http/Controllers/Auth/RegisteredUserController.php`

### Proses Registrasi:

1. **Validasi Input**
   ```php
   - name: required, string, max:255
   - email: required, unique, valid email format
   - password: required, confirmed, sesuai aturan password
   ```

2. **Pembuatan User**
   ```php
   User::create([
       'name' => $request->name,
       'email' => $request->email,
       'password' => Hash::make($request->password),
       'is_active' => true, // User aktif by default
   ]);
   ```

3. **Auto-Assign Role Default**
   ```php
   $user->assignRole('viewer'); // Role default untuk user baru
   ```

4. **Event & Login**
   ```php
   event(new Registered($user)); // Trigger event (untuk email verification, dll)
   Auth::login($user); // Auto-login setelah registrasi
   ```

5. **Redirect**
   ```php
   return redirect(route('dashboard')); // Redirect ke dashboard
   ```

### View: Register Form
**Path:** `resources/views/auth/register.blade.php`

**Fitur:**
- Form input: name, email, password, password_confirmation
- Informasi bahwa user baru akan menjadi "Viewer" dengan akses read-only
- Link ke halaman login
- Menggunakan AdminLTE styling

---

## Default Roles & Permissions

### Roles yang Tersedia:

#### 1. **Admin** (administrator)
- **Display Name:** Administrator
- **Description:** Full system access with all permissions
- **Permissions:** SEMUA permission yang ada di sistem

#### 2. **Manager**
- **Display Name:** Manager
- **Description:** Can manage users and view all data
- **Permissions:**
  - read dashboard
  - read users, create users, update users, delete users
  - read roles, create roles, update roles
  - read permissions

#### 3. **Editor**
- **Display Name:** Editor
- **Description:** Can create and edit content
- **Permissions:**
  - read dashboard
  - read users, create users, update users
  - read roles

#### 4. **Viewer** (Default untuk registrasi)
- **Display Name:** Viewer
- **Description:** Read-only access to data
- **Permissions:**
  - read dashboard
  - read users
  - read roles
  - read permissions

### Permission Groups:

1. **Dashboard**
   - read dashboard

2. **User Management**
   - read users, create users, update users, delete users

3. **Admin Management**
   - read admins, create admins, update admins, delete admins

4. **Role Management**
   - read roles, create roles, update roles, delete roles

5. **Permission Management**
   - read permissions, create permissions, update permissions, delete permissions

---

## Seeding Data

### RolePermissionSeeder
**Path:** `database/seeders/RolePermissionSeeder.php`

**Fungsi:**
1. Membuat semua permissions dengan display_name dan group
2. Membuat 4 default roles (admin, manager, editor, viewer)
3. Assign permissions ke masing-masing role

**Cara Menjalankan:**
```bash
php artisan db:seed --class=RolePermissionSeeder
```

### UserSeeder
**Path:** `database/seeders/UserSeeder.php`

**Fungsi:** Membuat 4 user testing dengan role berbeda-beda.

**Default Users:**
```
1. admin@example.com / password (Role: admin)
2. manager@example.com / password (Role: manager)
3. editor@example.com / password (Role: editor)
4. viewer@example.com / password (Role: viewer)
```

**Cara Menjalankan:**
```bash
php artisan db:seed --class=UserSeeder
```

**Atau jalankan semua seeder:**
```bash
php artisan migrate:fresh --seed
```

---

## Cara Penggunaan

### 1. Cek Role di Controller
```php
if (auth()->user()->hasRole('admin')) {
    // Kode untuk admin
}

// Multiple roles
if (auth()->user()->hasRole(['admin', 'manager'])) {
    // Kode untuk admin atau manager
}
```

### 2. Cek Permission di Controller
```php
if (auth()->user()->hasPermission('create users')) {
    // User boleh create users
}
```

### 3. Cek di Blade Template
```blade
@if(auth()->user()->hasRole('admin'))
    <a href="/admin">Admin Panel</a>
@endif

@if(auth()->user()->hasPermission('create users'))
    <button>Create User</button>
@endif
```

### 4. Assign Role ke User
```php
$user = User::find(1);
$user->assignRole('admin');

// Atau dengan object Role
$adminRole = Role::where('name', 'admin')->first();
$user->assignRole($adminRole);
```

### 5. Remove Role dari User
```php
$user->removeRole('viewer');
```

### 6. Sync Roles (Replace semua role)
```php
$user->syncRoles([1, 2, 3]); // Role IDs
```

### 7. Berikan Permission ke Role
```php
$role = Role::find(1);
$role->givePermissionTo('create users');
```

### 8. Hapus Permission dari Role
```php
$role->revokePermissionTo('delete users');
```

---

## Best Practices

### 1. Naming Convention
- **Role names:** lowercase, singular (contoh: 'admin', 'manager')
- **Permission names:** format "action resource" (contoh: 'read users', 'create posts')
- **Display names:** Title Case untuk tampilan UI

### 2. Permission Grouping
Kelompokkan permissions berdasarkan modul/fitur untuk memudahkan management:
```php
'User Management', 'Role Management', 'Dashboard', dll.
```

### 3. Security
- Selalu gunakan middleware untuk proteksi route
- Jangan hardcode role/permission di banyak tempat
- Gunakan gate/policy untuk authorization logic yang kompleks

### 4. Testing
Pastikan test setiap role memiliki akses yang sesuai:
```php
$this->actingAs($adminUser)
    ->get('/admin')
    ->assertStatus(200);

$this->actingAs($viewerUser)
    ->get('/admin')
    ->assertStatus(403);
```

---

## Troubleshooting

### User tidak bisa login setelah registrasi
- Pastikan role 'viewer' sudah ada di database
- Jalankan seeder: `php artisan db:seed --class=RolePermissionSeeder`

### Permission tidak bekerja
- Cek apakah role sudah di-assign permission yang benar
- Cek relasi di model sudah benar
- Clear cache: `php artisan cache:clear`

### Middleware tidak bekerja
- Pastikan middleware sudah didaftarkan di `bootstrap/app.php` atau `app/Http/Kernel.php`
- Cek nama middleware di route sudah benar

---

## Migration Commands

```bash
# Fresh migration dengan seeding
php artisan migrate:fresh --seed

# Rollback migration
php artisan migrate:rollback

# Rollback semua dan migrate ulang
php artisan migrate:refresh

# Seed ulang tanpa migrate
php artisan db:seed
```

---

## Kesimpulan

Sistem role & permission ini memberikan:
- ✅ Kontrol akses berbasis role yang fleksibel
- ✅ Permission granular untuk setiap fitur
- ✅ Auto-assign role 'viewer' untuk user baru
- ✅ Middleware untuk proteksi route
- ✅ Helper methods untuk pengecekan akses
- ✅ Seeder untuk data default
- ✅ User aktif by default dengan opsi deaktivasi

Sistem ini siap digunakan dan dapat dikembangkan sesuai kebutuhan aplikasi.
