# 🎨 Visual: Role & Permission Structure

## 📊 Struktur Role (Hierarki)

```
┌─────────────────────────────────────────────────────────────────┐
│                          👑 ADMIN                               │
│                     (Full System Access)                        │
│                                                                 │
│  ✅ Administration (Full)    ✅ Data (Full)                     │
│     • Users                     • Quota Management              │
│     • Roles                     • Purchase Orders               │
│     • Permissions               • Master Data                   │
│                                 • Reports                       │
└───────────────────────────────��─────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                        👥 MANAGER                               │
│                    (Fokus: Akun + View Data)                    │
│                                                                 │
│  ✅ Administration (Full)    ✅ Data (View Only)                │
│     • Users                     • Quota Management              │
│     • Roles                     • Purchase Orders               │
│     • Permissions               • Master Data                   │
│                                 • Reports                       │
│                                                                 │
│  📝 Catatan: Bisa manage users & roles, tapi hanya VIEW data    │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                         ✏️ EDITOR                               │
│                      (Fokus: Manage Data)                       │
│                                                                 │
│  ❌ Administration           ✅ Data (Full)                     │
│     • Users                     • Quota Management              │
│     • Roles                     • Purchase Orders               │
│     • Permissions               • Master Data                   │
│                                 • Reports                       │
│                                                                 │
│  📝 Catatan: Fokus ke data, tidak bisa manage users/roles       │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                         👁️ VIEWER                               │
│                      (Fokus: View Only)                         │
│                                                                 │
│  ❌ Administration           ✅ Data (View Only)                │
│     • Users                     • Quota Management              │
│     • Roles                     • Purchase Orders               │
│     • Permissions               • Master Data                   │
│                                 • Reports                       │
│                                                                 │
│  📝 Catatan: Hanya bisa lihat data, tidak bisa edit apapun      │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🎯 Permission Matrix

### Legend:
- ✅ = Full Access (Create, Read, Update, Delete)
- 👁️ = Read Only (View saja)
- ❌ = No Access (403 Forbidden)

```
┌──────────────────────┬───────┬─────────┬────────┬────────┐
│      Feature         │ Admin │ Manager │ Editor │ Viewer │
├───────────────��────��─┼───────┼─────────┼────────┼────────┤
│ ADMINISTRATION       │       │         │        │        │
│  • Users             │  ✅   │   ✅    │   ❌   │   ❌   │
│  • Roles             │  ✅   │   ✅    │   ❌   │   ❌   │
│  • Permissions       │  ✅   │   ✅    │   ❌   │   ❌   │
├──────────────────────┼───────┼─────────┼────────┼────────┤
│ DATA                 │       │         │        │        │
│  • Quota Management  │  ✅   │   👁️   │   ✅   │   👁️  │
│  • Purchase Orders   │  ✅   │   👁️   │   ✅   │   👁️  │
│  • Master Data       │  ✅   │   👁️   │   ✅   │   👁️  │
│  • Reports           │  ✅   │   👁️   │   ✅   │   👁️  │
└──────────────────────┴───────┴─────────┴────────┴────────┘
```

---

## 🔐 Access Control Flow

### Manager Login:
```
Login as Manager
    ↓
Dashboard ✅
    ↓
    ├─→ Administration ✅
    │   ├─→ Users ✅ (Full Access)
    │   ├─→ Roles ✅ (Full Access)
    │   └─→ Permissions ✅ (Full Access)
    │
    └─→ Data 👁️
        ├─→ Quota Management 👁️ (View Only)
        ├─→ Purchase Orders 👁️ (View Only)
        ├─→ Master Data 👁️ (View Only)
        └─→ Reports 👁️ (View Only)
```

### Editor Login:
```
Login as Editor
    ↓
Dashboard ✅
    ↓
    ├─→ Administration ❌ (403 Forbidden)
    │   ├─→ Users ❌
    │   ├─→ Roles ❌
    │   └─→ Permissions ❌
    │
    └─→ Data ✅
        ├─→ Quota Management ✅ (Full Access)
        ├─→ Purchase Orders ✅ (Full Access)
        ├─→ Master Data ✅ (Full Access)
        └─→ Reports ✅ (Full Access)
```

### Viewer Login:
```
Login as Viewer
    ↓
Dashboard ✅
    ↓
    ├─→ Administration ❌ (403 Forbidden)
    │   ├─→ Users ❌
    │   ├─→ Roles ❌
    │   └─→ Permissions ❌
    │
    └─→ Data 👁️
        ├─→ Quota Management 👁️ (View Only)
        ├─→ Purchase Orders 👁️ (View Only)
        ├─→ Master Data 👁️ (View Only)
        └─→ Reports 👁️ (View Only)
```

---

## 🎨 UI Differences by Role

### Admin:
```
Sidebar:
├─ 📊 Dashboard
├─ 📈 Quota Management      [+ Create] [Edit] [Delete]
├─ 🛒 Purchase Orders       [+ Create] [Edit] [Delete]
├─ 💾 Master Data           [+ Create] [Edit] [Delete]
├─ 📋 Reports               [+ Create] [Edit] [Delete]
└─ ⚙️ Administration
   ├─ 🔑 Permissions        [+ Create] [Edit] [Delete]
   ├─ 👥 Roles              [+ Create] [Edit] [Delete]
   └─ 👤 Users              [+ Create] [Edit] [Delete]
```

### Manager:
```
Sidebar:
├─ 📊 Dashboard
├─ 📈 Quota Management      (View Only - No Buttons)
├─ 🛒 Purchase Orders       (View Only - No Buttons)
├─ 💾 Master Data           (View Only - No Buttons)
├─ 📋 Reports               (View Only - No Buttons)
└─ ⚙��� Administration
   ├─ 🔑 Permissions        [+ Create] [Edit] [Delete]
   ├─ 👥 Roles              [+ Create] [Edit] [Delete]
   └─ 👤 Users              [+ Create] [Edit] [Delete]
```

### Editor:
```
Sidebar:
├─ 📊 Dashboard
├─ 📈 Quota Management      [+ Create] [Edit] [Delete]
├─ 🛒 Purchase Orders       [+ Create] [Edit] [Delete]
├─ 💾 Master Data           [+ Create] [Edit] [Delete]
└─ 📋 Reports               [+ Create] [Edit] [Delete]

(No Administration Menu)
```

### Viewer:
```
Sidebar:
├─ 📊 Dashboard
├─ 📈 Quota Management      (View Only - No Buttons)
├─ 🛒 Purchase Orders       (View Only - No Buttons)
├─ 💾 Master Data           (View Only - No Buttons)
└─ 📋 Reports               (View Only - No Buttons)

(No Administration Menu)
```

---

## 🔄 Permission Comparison

### Administration Access:
```
Admin    ████████████████████ 100% (Full)
Manager  ████████████████████ 100% (Full)
Editor   ░░░░░░░░░░░░░░░░░░░░   0% (None)
Viewer   ░░░░░░░��░░░░░░░░░░░░   0% (None)
```

### Data Access:
```
Admin    ████████████████████ 100% (Full)
Manager  ██████████░░░░░░░░░░  50% (View Only)
Editor   ████████████████████ 100% (Full)
Viewer   ██████████░░░░░░░░░░  50% (View Only)
```

---

## 📝 Use Cases

### 👑 Admin
**Scenario:** System Administrator
- Perlu full control atas sistem
- Bisa manage users, roles, permissions
- Bisa manage semua data
- Emergency access untuk semua fitur

### 👥 Manager
**Scenario:** HR Manager / User Manager
- Fokus ke user management
- Perlu assign roles ke users
- Perlu monitor data tapi tidak edit
- Tidak perlu edit data operasional

### ✏️ Editor
**Scenario:** Data Entry Staff / Content Manager
- Fokus ke input dan edit data
- Tidak perlu manage users
- Full access ke data operasional
- Tidak perlu akses administration

### 👁️ Viewer
**Scenario:** Auditor / Stakeholder / Read-Only User
- Hanya perlu lihat data
- Tidak perlu edit apapun
- Monitoring dan reporting
- No administrative access

---

## 🚀 Implementation

File yang diupdate:
- ✅ `database/seeders/RolePermissionSeeder.php`
- ✅ `app/Http/Controllers/Admin/UserController.php`

Command untuk apply:
```bash
update-all-permissions.bat
```

Atau manual:
```bash
php artisan db:seed --class=RolePermissionSeeder
php artisan cache:clear
php artisan optimize
```

---

**Created:** 2025-01-XX  
**Status:** ✅ Final  
**Version:** 1.0.0
