# Testing Guide - Permission System

## Quick Test Commands

### 1. Clear All Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### 2. Create Test Users (via Tinker)
```bash
php artisan tinker
```

```php
// Create Manager
$manager = User::create([
    'name' => 'Test Manager',
    'email' => 'manager@test.com',
    'password' => bcrypt('password'),
    'is_active' => true
]);
$manager->assignRole('manager');

// Create Editor
$editor = User::create([
    'name' => 'Test Editor',
    'email' => 'editor@test.com',
    'password' => bcrypt('password'),
    'is_active' => true
]);
$editor->assignRole('editor');

// Create additional Admin (for delete testing)
$admin = User::create([
    'name' => 'Test Admin',
    'email' => 'admin2@test.com',
    'password' => bcrypt('password'),
    'is_active' => true
]);
$admin->assignRole('admin');
```

---

## Test Scenarios

### Test 1: Manager Access
**Login:** manager@test.com / password

**Expected Results:**
| URL | Expected | Status |
|-----|----------|--------|
| `/admin/users` | ✅ Can access | 200 |
| `/admin/roles` | ✅ Can access | 200 |
| `/admin/permissions` | ✅ Can access | 200 |
| `/admin/admins` | ❌ Forbidden | 403 |

**Sidebar Check:**
- ✅ Should see: Permissions, Roles, Users
- ❌ Should NOT see: Admins

---

### Test 2: Editor Access
**Login:** editor@test.com / password

**Expected Results:**
| URL | Expected | Status |
|-----|----------|--------|
| `/admin/users` | ❌ Forbidden | 403 |
| `/admin/roles` | ❌ Forbidden | 403 |
| `/admin/permissions` | ❌ Forbidden | 403 |
| `/admin/admins` | ❌ Forbidden | 403 |

**Sidebar Check:**
- ❌ Should NOT see: Administration section at all
- ✅ Should see: Quota, PO, Master Data, Reports (if implemented)

---

### Test 3: Admin Delete Functionality
**Login:** admin@example.com / password (or your main admin)

**Test Cases:**

1. **Delete Another Admin (Should Work)**
   - Go to `/admin/admins`
   - Find "Test Admin" (admin2@test.com)
   - Click Delete
   - ✅ Expected: Success message "Admin deleted successfully"

2. **Delete Last Admin (Should Fail)**
   - Go to `/admin/admins`
   - Try to delete the last remaining admin
   - ❌ Expected: Error "Cannot delete the last admin user"

3. **Delete Self (Should Fail)**
   - Go to `/admin/admins`
   - Try to delete your own account
   - ❌ Expected: Error "Cannot delete your own admin account"

---

## Test Matrix

### Permission Matrix by Role

| Feature | Admin | Manager | Editor |
|---------|-------|---------|--------|
| View Dashboard | ✅ | ✅ | ✅ |
| View Users | ✅ | ✅ | ❌ |
| Create Users | ✅ | ✅ | ❌ |
| Edit Users | ✅ | ✅ | ❌ |
| Delete Users | ✅ | ✅ | ❌ |
| View Roles | ✅ | ✅ | ❌ |
| Create Roles | ✅ | ✅ | ❌ |
| Edit Roles | ✅ | ✅ | ❌ |
| Delete Roles | ✅ | ✅ | ❌ |
| View Permissions | ✅ | ✅ | ❌ |
| Create Permissions | ✅ | ✅ | ❌ |
| Edit Permissions | ✅ | ✅ | ❌ |
| Delete Permissions | ✅ | ✅ | ❌ |
| View Admins | ✅ | ❌ | ❌ |
| Create Admins | ✅ | ❌ | ❌ |
| Edit Admins | ✅ | ❌ | ❌ |
| Delete Admins | ✅ | ❌ | ❌ |
| View Quota | ✅ | ❌ | ✅ |
| Create Quota | ✅ | ❌ | ✅ |
| Edit Quota | ✅ | ❌ | ✅ |
| Delete Quota | ✅ | ❌ | ✅ |

---

## Automated Test Script

Create a test file: `tests/Feature/PermissionTest.php`

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    /** @test */
    public function manager_can_access_users_page()
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $response = $this->actingAs($manager)->get('/admin/users');
        $response->assertStatus(200);
    }

    /** @test */
    public function manager_cannot_access_admins_page()
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $response = $this->actingAs($manager)->get('/admin/admins');
        $response->assertStatus(403);
    }

    /** @test */
    public function editor_cannot_access_users_page()
    {
        $editor = User::factory()->create();
        $editor->assignRole('editor');

        $response = $this->actingAs($editor)->get('/admin/users');
        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_delete_another_admin()
    {
        $admin1 = User::factory()->create();
        $admin1->assignRole('admin');

        $admin2 = User::factory()->create();
        $admin2->assignRole('admin');

        $response = $this->actingAs($admin1)->delete("/admin/admins/{$admin2->id}");
        $response->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $admin2->id]);
    }

    /** @test */
    public function admin_cannot_delete_self()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->delete("/admin/admins/{$admin->id}");
        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
```

Run tests:
```bash
php artisan test --filter=PermissionTest
```

---

## Manual Testing Checklist

### ✅ Manager Role
- [ ] Login as manager
- [ ] Access `/admin/users` → Should work
- [ ] Access `/admin/roles` → Should work
- [ ] Access `/admin/permissions` → Should work
- [ ] Access `/admin/admins` → Should get 403
- [ ] Check sidebar → Should see Users, Roles, Permissions
- [ ] Check sidebar → Should NOT see Admins

### ✅ Editor Role
- [ ] Login as editor
- [ ] Access `/admin/users` → Should get 403
- [ ] Access `/admin/roles` → Should get 403
- [ ] Access `/admin/permissions` → Should get 403
- [ ] Check sidebar → Should NOT see Administration section

### ✅ Admin Delete
- [ ] Login as admin
- [ ] Create second admin
- [ ] Delete second admin → Should work
- [ ] Try to delete last admin → Should fail
- [ ] Try to delete self → Should fail

---

## Expected Error Messages

### 403 Forbidden
```
Forbidden - You do not have permission to access this resource
```

### Cannot Delete Self
```
Cannot delete your own admin account.
```

### Cannot Delete Last Admin
```
Cannot delete the last admin user. System must have at least one admin.
```

---

## Debugging Tips

### If permissions don't work:
1. Check middleware registration in `bootstrap/app.php`
2. Clear all caches
3. Check database: `SELECT * FROM permission_role WHERE role_id = X`
4. Check user roles: `SELECT * FROM role_user WHERE user_id = X`

### If sidebar still shows wrong menus:
1. Clear view cache: `php artisan view:clear`
2. Check blade syntax in `sidebar.blade.php`
3. Test permission method: `Auth::user()->hasPermission('read users')`

### If routes still accessible:
1. Clear route cache: `php artisan route:clear`
2. Check route list: `php artisan route:list | grep admin`
3. Verify middleware is applied

---

## Success Criteria

✅ **All tests pass when:**
1. Manager can access Users, Roles, Permissions
2. Manager cannot access Admins
3. Editor cannot access any Administration pages
4. Admin can delete other admins
5. Admin cannot delete self or last admin
6. Sidebar shows correct menus for each role
7. No 403 errors for allowed pages
8. 403 errors for forbidden pages

---

## Quick Verification

Run this in browser console on any admin page:
```javascript
// Check current user permissions
fetch('/api/user/permissions')
  .then(r => r.json())
  .then(console.log);
```

Or in tinker:
```php
$user = User::find(1);
$user->roles->pluck('name'); // Check roles
$user->roles->first()->permissions->pluck('name'); // Check permissions
```

---

## Status Indicators

- ✅ = Should work / Should see
- ❌ = Should fail / Should NOT see
- 🔒 = Protected by permission
- 🔓 = Public/accessible
