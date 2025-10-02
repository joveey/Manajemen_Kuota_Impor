# Editor Role Configuration

## Overview
The **Editor** role has been configured to manage data (Quota, Purchase Orders, Master Data, Reports) with full CRUD access, while having **read-only access** to the Administrator section (Users, Roles, Permissions).

## Editor Role Permissions

### ✅ Full Access (Create, Read, Update, Delete)
The editor can fully manage the following data:

1. **Dashboard**
   - View dashboard and statistics

2. **Quota Management**
   - ✅ View quota data (`read quota`)
   - ✅ Create new quota (`create quota`)
   - ✅ Edit quota data (`update quota`)
   - ✅ Delete quota data (`delete quota`)

3. **Purchase Orders**
   - ✅ View purchase orders (`read purchase_orders`)
   - ✅ Create new purchase orders (`create purchase_orders`)
   - ✅ Edit purchase orders (`update purchase_orders`)
   - ✅ Delete purchase orders (`delete purchase_orders`)

4. **Master Data**
   - ✅ View master data (`read master_data`)
   - ✅ Create new master data (`create master_data`)
   - ✅ Edit master data (`update master_data`)
   - ✅ Delete master data (`delete master_data`)

5. **Reports**
   - ✅ View reports (`read reports`)
   - ✅ Create new reports (`create reports`)
   - ✅ Edit reports (`update reports`)
   - ✅ Delete reports (`delete reports`)

### 👁️ Read-Only Access (View Only)
The editor can **view** but **cannot edit, create, or delete** in the Administrator section:

1. **Users Management**
   - ✅ View users list and details (`read users`)
   - ❌ Cannot create users
   - ❌ Cannot edit users
   - ❌ Cannot delete users

2. **Roles Management**
   - ✅ View roles list and details (`read roles`)
   - ❌ Cannot create roles
   - ❌ Cannot edit roles
   - ❌ Cannot delete roles

3. **Permissions Management**
   - ✅ View permissions list and details (`read permissions`)
   - ❌ Cannot create permissions
   - ❌ Cannot edit permissions
   - ❌ Cannot delete permissions

## UI Changes

### Sidebar Navigation
The editor will see:
- ✅ Dashboard
- ✅ Quota Management (with all sub-menus)
- ✅ Purchase Orders (with all sub-menus)
- ✅ Master Data (with all sub-menus)
- ✅ Reports (with all sub-menus)
- ✅ **ADMINISTRATION** section header
- ✅ Permissions (view only)
- ✅ Roles (view only)
- ✅ Users (view only)
- ❌ Admins (not visible - admin only)

### Administrator Pages (Read-Only for Editor)

#### Users Page
- ✅ Can view users list
- ✅ Can click "View" button to see user details
- ❌ "Create User" button is hidden
- ❌ "Edit" button is hidden
- ❌ "Delete" button is hidden

#### Roles Page
- ✅ Can view roles list
- ✅ Can click "View" button to see role details
- ❌ "Create Role" button is hidden
- ❌ "Edit" button is hidden
- ❌ "Delete" button is hidden

#### Permissions Page
- ✅ Can view permissions list
- ✅ Can click "View" button to see permission details
- ❌ "Create Permission" button is hidden
- ❌ "Edit" button is hidden
- ❌ "Delete" button is hidden

## How to Apply Changes

### Step 1: Run Database Seeder
```bash
php artisan db:seed --class=RolePermissionSeeder
```

This will update the editor role with the new permissions.

### Step 2: Clear Cache
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### Step 3: Test the Editor Role
1. Login as a user with the **editor** role
2. Verify you can see the Administrator section in the sidebar
3. Navigate to Users, Roles, or Permissions pages
4. Confirm that:
   - You can view the list
   - You can click "View" to see details
   - Create/Edit/Delete buttons are not visible

## Role Comparison

| Feature | Admin | Editor | Manager | Viewer |
|---------|-------|--------|---------|--------|
| **Dashboard** | ✅ Full | ✅ Full | ✅ Full | ✅ View |
| **Quota Management** | ✅ Full | ✅ Full | ✅ View | ✅ View |
| **Purchase Orders** | ✅ Full | ✅ Full | ✅ View | ✅ View |
| **Master Data** | ✅ Full | ✅ Full | ✅ View | ✅ View |
| **Reports** | ✅ Full | ✅ Full | ✅ View | ✅ View |
| **Users Management** | ✅ Full | ✅ View | ✅ Full | ❌ No Access |
| **Roles Management** | ✅ Full | ✅ View | ✅ Full | ❌ No Access |
| **Permissions Management** | ✅ Full | ✅ View | ✅ Full | ❌ No Access |
| **Admins Management** | ✅ Full | ❌ No Access | ❌ No Access | ❌ No Access |

## Technical Implementation

### Permission Checks in Views
The views use Laravel's `@can` directive to check permissions:

```blade
{{-- Show button only if user has permission --}}
@can('create users')
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
        Create User
    </a>
@endcan

{{-- Show edit button only if user has permission --}}
@can('update users')
    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-warning">
        Edit
    </a>
@endcan

{{-- Show delete button only if user has permission --}}
@can('delete users')
    <button onclick="deleteUser({{ $user->id }})" class="btn btn-danger">
        Delete
    </button>
@endcan
```

### Permission Checks in Controllers
Controllers should also verify permissions:

```php
// In UserController
public function create()
{
    // Only users with 'create users' permission can access
    $this->authorize('create users');
    
    return view('admin.users.create');
}

public function edit(User $user)
{
    // Only users with 'update users' permission can access
    $this->authorize('update users');
    
    return view('admin.users.edit', compact('user'));
}

public function destroy(User $user)
{
    // Only users with 'delete users' permission can access
    $this->authorize('delete users');
    
    $user->delete();
    return redirect()->route('admin.users.index');
}
```

## Security Notes

1. **View-Only Access**: Editors can see administrator data but cannot modify it
2. **Button Hiding**: Create/Edit/Delete buttons are hidden using `@can` directives
3. **Route Protection**: Controllers use `authorize()` to prevent direct URL access
4. **Admin Bypass**: Admin users bypass all permission checks (defined in AuthServiceProvider)

## Troubleshooting

### Editor can't see Administrator section
- Check if user has `read users`, `read roles`, or `read permissions` permission
- Run: `php artisan config:clear`

### Editor can still see Create/Edit/Delete buttons
- Clear view cache: `php artisan view:clear`
- Check if views have proper `@can` directives

### Permission denied errors
- Verify the editor role has the correct permissions
- Re-run seeder: `php artisan db:seed --class=RolePermissionSeeder`

## Summary

The editor role is now configured to:
- ✅ **Manage data** (Quota, Purchase Orders, Master Data, Reports) with full CRUD access
- ✅ **View administrator section** (Users, Roles, Permissions) in read-only mode
- ❌ **Cannot edit, create, or delete** in the administrator section

This configuration allows editors to focus on data management while having visibility into system administration without the ability to make changes.
