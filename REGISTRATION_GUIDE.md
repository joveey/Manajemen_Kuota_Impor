# Panduan Lengkap Sistem Registrasi

## Overview
Sistem registrasi user dengan auto-assignment role "viewer" dan akses read-only default.

---

## Alur Registrasi

### 1. User Mengisi Form Registrasi
**URL:** `/register`  
**View:** `resources/views/auth/register.blade.php`

**Form Fields:**
- Name (required)
- Email (required, unique)
- Password (required, min 8 chars)
- Password Confirmation (required)

### 2. Validasi Input
```php
'name' => ['required', 'string', 'max:255'],
'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
'password' => ['required', 'confirmed', Rules\Password::defaults()],
```

### 3. Pembuatan User
```php
$user = User::create([
    'name' => $request->name,
    'email' => $request->email,
    'password' => Hash::make($request->password),
    'is_active' => true,
]);
```

### 4. Auto-Assign Role
```php
$user->assignRole('viewer');
```

### 5. Event & Login
```php
event(new Registered($user));
Auth::login($user);
```

### 6. Redirect
```php
return redirect(route('dashboard'));
```

---

## Default Role: Viewer

User baru otomatis mendapat role **"viewer"** dengan permissions:

| Permission | Deskripsi |
|------------|-----------|
| read dashboard | Akses dashboard |
| read users | Lihat daftar user |
| read roles | Lihat daftar role |
| read permissions | Lihat daftar permission |

**Tidak bisa:**
- Create, update, delete users
- Manage roles
- Manage permissions

---

## Testing

### Manual Test
```
1. Buka http://localhost:8000/register
2. Isi form registrasi
3. Submit
4. Harus redirect ke dashboard
5. User harus sudah login
6. Cek database: role = viewer
```

### Via Tinker
```bash
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

// Verifikasi
$user->hasRole('viewer');              // true
$user->hasPermission('read dashboard'); // true
```

---

## Troubleshooting

### Role 'viewer' not found
```bash
php artisan db:seed --class=RolePermissionSeeder
```

### User tidak bisa login
```php
// Cek status
$user->is_active;  // Harus true
$user->roles;      // Harus ada role

// Fix
$user->update(['is_active' => true]);
$user->assignRole('viewer');
```

---

## Dokumentasi Terkait

- [Role System Documentation](ROLE_SYSTEM_DOCUMENTATION.md)
- [Setup Guide](SETUP_ROLE_SYSTEM.md)
- [Changelog](CHANGELOG_ROLE_SYSTEM.md)
