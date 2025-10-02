# 🚀 Panduan Instalasi Laravel Admin Panel

Panduan lengkap untuk menginstall dan menjalankan Laravel Admin Panel dengan AdminLTE.

## 📋 Prerequisites

Pastikan sistem Anda sudah terinstall:
- PHP >= 8.2
- Composer
- Node.js & NPM
- MySQL/MariaDB
- Git (optional)

## 🔧 Langkah Instalasi

### 1. Clone atau Download Project

```bash
# Jika menggunakan Git
git clone <repository-url>
cd unnamed-project

# Atau extract file ZIP ke folder project
```

### 2. Install Dependencies PHP

```bash
composer install
```

Tunggu hingga semua package Laravel dan dependencies terinstall.

### 3. Install Dependencies JavaScript

```bash
npm install
```

### 4. Setup Environment File

```bash
# Copy file .env.example menjadi .env
cp .env.example .env

# Atau di Windows
copy .env.example .env
```

### 5. Generate Application Key

```bash
php artisan key:generate
```

### 6. Konfigurasi Database

Edit file `.env` dan sesuaikan dengan database Anda:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_admin_panel
DB_USERNAME=root
DB_PASSWORD=
```

### 7. Buat Database

Buat database baru di MySQL:

```sql
CREATE DATABASE laravel_admin_panel;
```

Atau gunakan phpMyAdmin/MySQL Workbench untuk membuat database.

### 8. Jalankan Migration

```bash
php artisan migrate
```

Perintah ini akan membuat semua tabel yang diperlukan:
- users
- roles
- permissions
- role_user (pivot)
- permission_role (pivot)
- password_reset_tokens
- sessions
- cache
- jobs

### 9. Jalankan Seeder

```bash
# Seed roles dan permissions
php artisan db:seed --class=RolePermissionSeeder

# Seed users default
php artisan db:seed --class=UserSeeder
```

Seeder akan membuat:

**Roles:**
- Admin (semua permissions)
- Manager (manage users, roles)
- Editor (create & edit users)
- Viewer (read-only)

**Users:**
- admin@example.com / password (Admin)
- manager@example.com / password (Manager)
- editor@example.com / password (Editor)
- viewer@example.com / password (Viewer)

**Permissions:**
- read/create/update/delete users
- read/create/update/delete admins
- read/create/update/delete roles
- read/create/update/delete permissions
- read dashboard

### 10. Compile Assets

```bash
# Development mode (dengan hot reload)
npm run dev

# Atau production mode
npm run build
```

### 11. Jalankan Server

Buka terminal baru dan jalankan:

```bash
php artisan serve
```

Server akan berjalan di: `http://localhost:8000`

### 12. Login ke Aplikasi

Buka browser dan akses: `http://localhost:8000`

Login dengan salah satu akun:
- **Email:** admin@example.com
- **Password:** password

## 🎯 Testing Fitur

### 1. Dashboard
- Akses: `http://localhost:8000/dashboard`
- Lihat statistik users, admins, roles, permissions

### 2. Permission Management
- Akses: `http://localhost:8000/admin/permissions`
- Coba buat permission baru (harus dimulai dengan: create, read, update, delete)
- Edit dan delete permission

### 3. Role Management
- Akses: `http://localhost:8000/admin/roles`
- Buat role baru
- Assign permissions ke role
- Lihat users yang memiliki role

### 4. User Management
- Akses: `http://localhost:8000/admin/users`
- Buat user baru (non-admin)
- Assign roles ke user
- Edit dan delete user

### 5. Admin Management
- Akses: `http://localhost:8000/admin/admins`
- Buat admin baru
- Edit admin
- **Test fitur khusus:** Convert admin to user (admin tidak bisa langsung dihapus)

## 🔍 Troubleshooting

### Error: "Class 'Role' not found"

**Solusi:**
```bash
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

### Error: "SQLSTATE[HY000] [1045] Access denied"

**Solusi:**
- Periksa konfigurasi database di file `.env`
- Pastikan username dan password MySQL benar
- Pastikan database sudah dibuat

### Error: "Vite manifest not found"

**Solusi:**
```bash
npm install
npm run build
```

### AdminLTE CSS tidak muncul

**Solusi:**
- Periksa koneksi internet (menggunakan CDN)
- Atau install AdminLTE secara lokal:
```bash
npm install admin-lte@^3.2
```

### Error: "Permission denied" saat npm install

**Solusi (Linux/Mac):**
```bash
sudo npm install
```

**Solusi (Windows):**
- Jalankan Command Prompt/PowerShell sebagai Administrator

### Error: Migration failed

**Solusi:**
```bash
# Drop semua tabel dan migrate ulang
php artisan migrate:fresh

# Kemudian seed ulang
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=UserSeeder
```

## 📱 Akses dari Perangkat Lain (Optional)

Jika ingin mengakses dari perangkat lain di jaringan yang sama:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Kemudian akses dari perangkat lain menggunakan IP komputer Anda:
```
http://192.168.x.x:8000
```

## 🔐 Keamanan untuk Production

Jika akan deploy ke production:

### 1. Update .env
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
```

### 2. Generate New Key
```bash
php artisan key:generate
```

### 3. Optimize
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 4. Set Permissions (Linux)
```bash
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 5. Update Password Default
Jangan gunakan password default di production! Update semua password user.

## 📚 Dokumentasi Tambahan

- [README_ADMIN_PANEL.md](README_ADMIN_PANEL.md) - Dokumentasi lengkap fitur
- [Laravel Documentation](https://laravel.com/docs)
- [AdminLTE Documentation](https://adminlte.io/docs)

## 🆘 Butuh Bantuan?

Jika mengalami masalah:
1. Periksa log Laravel: `storage/logs/laravel.log`
2. Periksa console browser (F12) untuk error JavaScript
3. Pastikan semua langkah instalasi sudah diikuti dengan benar

## ✅ Checklist Instalasi

- [ ] PHP >= 8.2 terinstall
- [ ] Composer terinstall
- [ ] Node.js & NPM terinstall
- [ ] MySQL/MariaDB terinstall
- [ ] `composer install` berhasil
- [ ] `npm install` berhasil
- [ ] File `.env` sudah dikonfigurasi
- [ ] Database sudah dibuat
- [ ] `php artisan migrate` berhasil
- [ ] `php artisan db:seed` berhasil
- [ ] `npm run build` berhasil
- [ ] `php artisan serve` berjalan
- [ ] Bisa login dengan admin@example.com
- [ ] Dashboard tampil dengan benar
- [ ] AdminLTE CSS/JS ter-load

---

**Selamat! Sistem Laravel Admin Panel Anda sudah siap digunakan! 🎉**
