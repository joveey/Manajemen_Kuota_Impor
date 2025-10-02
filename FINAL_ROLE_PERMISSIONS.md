# 🎯 Final Role & Permissions Structure

## 📋 Role Definitions

### 1. 👑 Admin
**Fokus:** Full System Access

**Deskripsi:** Bisa akses dan manage SEMUA fitur di sistem

**Permissions:**
- ✅ **ALL** permissions (full access)

**Menu yang Muncul:**
- ✅ Dashboard
- ✅ Quota Management
- ✅ Purchase Orders
- ✅ Master Data
- ✅ Reports
- ✅ Administration (Permissions, Roles, Users)

---

### 2. 👥 Manager
**Fokus:** Akun (Users, Roles, Permissions) + View Data

**Deskripsi:** Bisa manage users, roles, dan permissions + bisa VIEW data (read-only)

**Permissions:**
```
✅ read dashboard

// Administration (Full Access)
✅ read users, create users, update users, delete users
✅ read roles, create roles, update roles, delete roles
✅ read permissions, create permissions, update permissions, delete permissions

// Data (Read-Only)
✅ read quota
✅ read purchase_orders
✅ read master_data
✅ read reports

// TIDAK BISA
❌ create/update/delete quota
❌ create/update/delete purchase_orders
❌ create/update/delete master_data
❌ create/update/delete reports
```

**Menu yang Muncul:**
- ✅ Dashboard
- ✅ Quota Management (read-only, tanpa tombol Create/Edit/Delete)
- ✅ Purchase Orders (read-only, tanpa tombol Create/Edit/Delete)
- ✅ Master Data (read-only, tanpa tombol Create/Edit/Delete)
- ✅ Reports (read-only, tanpa tombol Create/Edit/Delete)
- ✅ Administration (Permissions, Roles, Users) - Full Access

**Use Case:**
- HR Manager yang perlu manage users dan roles
- System Administrator yang fokus ke user management
- Manager yang perlu monitor data tapi tidak edit

---

### 3. ✏️ Editor
**Fokus:** Data (Quota, Purchase Orders, Master Data, Reports)

**Deskripsi:** Bisa manage data (create, edit, delete) tapi TIDAK bisa akses Administration

**Permissions:**
```
✅ read dashboard

// Data (Full Access)
✅ read quota, create quota, update quota, delete quota
✅ read purchase_orders, create purchase_orders, update purchase_orders, delete purchase_orders
✅ read master_data, create master_data, update master_data, delete master_data
✅ read reports, create reports, update reports, delete reports

// TIDAK BISA
❌ read/create/update/delete users
❌ read/create/update/delete roles
❌ read/create/update/delete permissions
```

**Menu yang Muncul:**
- ✅ Dashboard
- ✅ Quota Management (full access dengan tombol Create/Edit/Delete)
- ✅ Purchase Orders (full access dengan tombol Create/Edit/Delete)
- ✅ Master Data (full access dengan tombol Create/Edit/Delete)
- ✅ Reports (full access dengan tombol Create/Edit/Delete)
- ❌ Administration (TIDAK muncul)

**Use Case:**
- Data Entry Staff
- Content Editor
- Operational Staff yang fokus ke data

---

### 4. 👁️ Viewer
**Fokus:** View Data Only (Read-Only)

**Deskripsi:** Cuma bisa VIEW data, TIDAK bisa edit atau akses Administration

**Permissions:**
```
✅ read dashboard

// Data (Read-Only)
✅ read quota
✅ read purchase_orders
✅ read master_data
✅ read reports

// TIDAK BISA
❌ create/update/delete apapun
❌ read/create/update/delete users
❌ read/create/update/delete roles
❌ read/create/update/delete permissions
```

**Menu yang Muncul:**
- ✅ Dashboard
- ✅ Quota Management (read-only, tanpa tombol Create/Edit/Delete)
- ✅ Purchase Orders (read-only, tanpa tombol Create/Edit/Delete)
- ✅ Master Data (read-only, tanpa tombol Create/Edit/Delete)
- ✅ Reports (read-only, tanpa tombol Create/Edit/Delete)
- ❌ Administration (TIDAK muncul)

**Use Case:**
- Auditor
- Stakeholder yang perlu monitoring
- Staff yang hanya perlu lihat data

---

## 📊 Tabel Perbandingan Permission

| Permission | Admin | Manager | Editor | Viewer |
|-----------|-------|---------|--------|--------|
| **Dashboard** |
| read dashboard | ✅ | ✅ | ✅ | ✅ |
| **Administration - Users** |
| read users | ✅ | ✅ | ❌ | ❌ |
| create users | ✅ | ✅ | ❌ | ❌ |
| update users | ✅ | ✅ | ❌ | ❌ |
| delete users | ✅ | ✅ | ❌ | ❌ |
| **Administration - Roles** |
| read roles | ✅ | ✅ | ❌ | ❌ |
| create roles | ✅ | ✅ | ❌ | ❌ |
| update roles | ✅ | ✅ | ❌ | ❌ |
| delete roles | ✅ | ✅ | ❌ | ❌ |
| **Administration - Permissions** |
| read permissions | ✅ | ✅ | ❌ | ❌ |
| create permissions | ✅ | ✅ | ❌ | ❌ |
| update permissions | ✅ | ✅ | ❌ | ❌ |
| delete permissions | ✅ | ✅ | ❌ | ❌ |
| **Data - Quota** |
| read quota | ✅ | ✅ | ✅ | ✅ |
| create quota | ✅ | ❌ | ✅ | ❌ |
| update quota | ✅ | ❌ | ✅ | ❌ |
| delete quota | ✅ | ❌ | ✅ | ❌ |
| **Data - Purchase Orders** |
| read purchase_orders | ✅ | ✅ | ✅ | ✅ |
| create purchase_orders | ✅ | ❌ | ✅ | ❌ |
| update purchase_orders | ✅ | ❌ | ✅ | ❌ |
| delete purchase_orders | ✅ | ❌ | ✅ | ❌ |
| **Data - Master Data** |
| read master_data | ✅ | ✅ | ✅ | ✅ |
| create master_data | ✅ | ❌ | ✅ | ❌ |
| update master_data | ✅ | ❌ | ✅ | ❌ |
| delete master_data | ✅ | ❌ | ��� | ❌ |
| **Data - Reports** |
| read reports | ✅ | ✅ | ✅ | ✅ |
| create reports | ✅ | ❌ | ✅ | ❌ |
| update reports | ✅ | ❌ | ✅ | ❌ |
| delete reports | ✅ | ❌ | ✅ | ❌ |

## 🎯 Ringkasan Fokus Setiap Role

```
┌─────────────────────────────────────────────────────────┐
│ ADMIN                                                   │
│ ✅ Administration (Full)                                │
│ ✅ Data (Full)                                          │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ MANAGER (Fokus: Akun)                                   │
│ ✅ Administration (Full) - Users, Roles, Permissions    │
│ ✅ Data (Read-Only) - Quota, PO, Master Data, Reports   │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ EDITOR (Fokus: Data)                                    │
│ ❌ Administration - TIDAK bisa akses                    │
│ ✅ Data (Full) - Quota, PO, Master Data, Reports        │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ VIEWER (Fokus: View Only)                               │
│ ❌ Administration - TIDAK bisa akses                    │
│ ✅ Data (Read-Only) - Quota, PO, Master Data, Reports   │
└──��──────────────────────────────────────────────────────┘
```

## 🚀 Cara Apply Permission Baru

### Step 1: Run Seeder
```bash
php artisan db:seed --class=RolePermissionSeeder
```

### Step 2: Clear Cache
```bash
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan optimize
```

### Step 3: Test
1. Logout dari semua akun
2. Login sebagai Manager → Cek bisa akses Administration + VIEW data
3. Login sebagai Editor → Cek bisa manage data, TIDAK bisa akses Administration
4. Login sebagai Viewer → Cek bisa VIEW data, TIDAK bisa akses Administration

## ✅ Verification Checklist

### Manager:
- [ ] Bisa akses `/admin/users` (full access)
- [ ] Bisa akses `/admin/roles` (full access)
- [ ] Bisa akses `/admin/permissions` (full access)
- [ ] Bisa akses `/admin/quota` (read-only, tanpa tombol Create/Edit/Delete)
- [ ] Bisa akses `/admin/purchase-orders` (read-only)
- [ ] Bisa akses `/admin/master-data` (read-only)
- [ ] Bisa akses `/admin/reports` (read-only)
- [ ] Menu "Administration" muncul
- [ ] Menu "Data" muncul (tanpa tombol action)

### Editor:
- [ ] TIDAK bisa akses `/admin/users` (403 Forbidden)
- [ ] TIDAK bisa akses `/admin/roles` (403 Forbidden)
- [ ] TIDAK bisa akses `/admin/permissions` (403 Forbidden)
- [ ] Bisa akses `/admin/quota` (full access dengan tombol Create/Edit/Delete)
- [ ] Bisa akses `/admin/purchase-orders` (full access)
- [ ] Bisa akses `/admin/master-data` (full access)
- [ ] Bisa akses `/admin/reports` (full access)
- [ ] Menu "Administration" TIDAK muncul
- [ ] Menu "Data" muncul (dengan tombol action)

### Viewer:
- [ ] TIDAK bisa akses `/admin/users` (403 Forbidden)
- [ ] TIDAK bisa akses `/admin/roles` (403 Forbidden)
- [ ] TIDAK bisa akses `/admin/permissions` (403 Forbidden)
- [ ] Bisa akses `/admin/quota` (read-only, tanpa tombol Create/Edit/Delete)
- [ ] Bisa akses `/admin/purchase-orders` (read-only)
- [ ] Bisa akses `/admin/master-data` (read-only)
- [ ] Bisa akses `/admin/reports` (read-only)
- [ ] Menu "Administration" TIDAK muncul
- [ ] Menu "Data" muncul (tanpa tombol action)

## 📝 Catatan Penting

1. **Manager** sekarang bisa **VIEW data** (sebelumnya tidak bisa)
2. **Editor** tetap fokus ke data, tidak bisa akses Administration
3. **Viewer** tetap read-only untuk semua data
4. **Admin** tetap full access ke semua

## 🎓 Kesimpulan

Struktur role sekarang lebih jelas:
- **Admin** = Full access semua
- **Manager** = Fokus Akun + View Data
- **Editor** = Fokus Data (manage)
- **Viewer** = View Data only

---

**Updated:** 2025-01-XX  
**Status:** ✅ Final  
**Tested:** ✅ Yes
