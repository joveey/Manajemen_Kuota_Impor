# ⚡ Quick Start Guide

Panduan cepat untuk menjalankan Laravel Admin Panel dalam 5 menit!

## 🚀 Langkah Cepat

### 1. Install Dependencies
```bash
composer install
npm install
```

### 2. Setup Environment
```bash
copy .env.example .env
php artisan key:generate
```

### 3. Konfigurasi Database
Edit `.env`:
```env
DB_DATABASE=laravel_admin_panel
DB_USERNAME=root
DB_PASSWORD=
```

Buat database:
```sql
CREATE DATABASE laravel_admin_panel;
```

### 4. Migrate & Seed
```bash
php artisan migrate
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=UserSeeder
```

### 5. Compile & Run
```bash
npm run build
php artisan serve
```

### 6. Login
- URL: `http://localhost:8000`
- Email: `admin@example.com`
- Password: `password`

## 🎯 Test Fitur

1. **Dashboard** → Lihat statistik
2. **Permissions** → Buat permission baru (harus dimulai dengan: create/read/update/delete)
3. **Roles** → Buat role dan assign permissions
4. **Users** → Buat user baru
5. **Admins** → Buat admin, coba convert to user

## 📚 Dokumentasi Lengkap

- [INSTALLATION_GUIDE.md](INSTALLATION_GUIDE.md) - Panduan instalasi detail
- [README_ADMIN_PANEL.md](README_ADMIN_PANEL.md) - Dokumentasi fitur lengkap
- [PROJECT_SUMMARY.md](PROJECT_SUMMARY.md) - Ringkasan project

## 🆘 Troubleshooting Cepat

**Error migration?**
```bash
php artisan migrate:fresh
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=UserSeeder
```

**CSS tidak muncul?**
```bash
npm run build
```

**Error autoload?**
```bash
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

## ✅ Checklist

- [ ] `composer install` ✓
- [ ] `npm install` ✓
- [ ] `.env` configured ✓
- [ ] Database created ✓
- [ ] `php artisan migrate` ✓
- [ ] `php artisan db:seed` ✓
- [ ] `npm run build` ✓
- [ ] Can login ✓

---

**Selamat mencoba! 🎉**
