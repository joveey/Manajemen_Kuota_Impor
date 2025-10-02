# Quick Reference - Role & Permission

## 🎭 3 Role Utama

### 👑 Admin
**Akses:** SEMUA
- Dashboard ✅
- User Management ✅
- Role Management ✅
- Permission Management ✅
- Quota Management ✅
- Purchase Orders ✅
- Master Data ✅
- Reports ✅

### ✏️ Editor
**Akses:** Data Dashboard Only
- Dashboard ✅
- Quota Management ✅ (CRUD)
- Purchase Orders ✅ (CRUD)
- Master Data ✅ (CRUD)
- Reports ✅ (CRUD)
- User Management ❌
- Role Management ❌
- Permission Management ❌

### 👔 Manager
**Akses:** User & Role Management Only
- Dashboard ✅
- User Management ✅ (CRUD)
- Role Management ✅ (CRUD - kecuali Admin)
- Permission Management ✅ (CRUD)
- Quota Management ❌
- Purchase Orders ❌
- Master Data ❌
- Reports ❌

## 🚀 Quick Commands

```bash
# Terapkan perubahan (Development)
php artisan migrate:fresh --seed

# Terapkan perubahan (Production - tanpa hapus data)
php artisan db:seed --class=RolePermissionSeeder

# Clear cache
php artisan cache:clear
php artisan config:clear
```

## 🔐 Proteksi Khusus

- ⚠️ **Role Admin** hanya bisa diedit/dihapus oleh Admin
- ⚠️ **Manager** tidak bisa edit/delete role Admin
- ⚠️ **Editor** tidak bisa akses user/role management
- ⚠️ **Manager** tidak bisa akses data dashboard

## 📋 Checklist Setelah Update

- [ ] Backup database
- [ ] Jalankan seeder
- [ ] Test login Admin
- [ ] Test login Editor
- [ ] Test login Manager
- [ ] Verifikasi menu yang muncul
- [ ] Test CRUD operations
- [ ] Clear cache browser

## 📞 Need Help?

Lihat dokumentasi lengkap:
- `ROLE_PERMISSION_STRUCTURE.md` - Struktur detail
- `CARA_TERAPKAN_ROLE_BARU.md` - Panduan implementasi
- `CHANGELOG_ROLE_UPDATE.md` - Detail perubahan
