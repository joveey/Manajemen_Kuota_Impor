<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ========================================
        // CREATE PERMISSIONS
        // ========================================
        
        // Permissions harus dimulai dengan: create, read, update, delete
        $permissions = [
            // User Management
            ['name' => 'read users', 'description' => 'View users list and details'],
            ['name' => 'create users', 'description' => 'Create new users'],
            ['name' => 'update users', 'description' => 'Edit existing users'],
            ['name' => 'delete users', 'description' => 'Delete users'],
            
            // Admin Management
            ['name' => 'read admins', 'description' => 'View admins list and details'],
            ['name' => 'create admins', 'description' => 'Create new admins'],
            ['name' => 'update admins', 'description' => 'Edit existing admins'],
            ['name' => 'delete admins', 'description' => 'Convert admins to users'],
            
            // Role Management
            ['name' => 'read roles', 'description' => 'View roles list and details'],
            ['name' => 'create roles', 'description' => 'Create new roles'],
            ['name' => 'update roles', 'description' => 'Edit existing roles'],
            ['name' => 'delete roles', 'description' => 'Delete roles'],
            
            // Permission Management
            ['name' => 'read permissions', 'description' => 'View permissions list and details'],
            ['name' => 'create permissions', 'description' => 'Create new permissions'],
            ['name' => 'update permissions', 'description' => 'Edit existing permissions'],
            ['name' => 'delete permissions', 'description' => 'Delete permissions'],
            
            // Dashboard
            ['name' => 'read dashboard', 'description' => 'View dashboard and statistics'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                ['description' => $permission['description']]
            );
        }

        $this->command->info('✅ Permissions created successfully!');

        // ========================================
        // CREATE ROLES
        // ========================================
        
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['description' => 'Full system access with all permissions']
        );

        $managerRole = Role::firstOrCreate(
            ['name' => 'manager'],
            ['description' => 'Can manage users and view all data']
        );

        $editorRole = Role::firstOrCreate(
            ['name' => 'editor'],
            ['description' => 'Can create and edit content']
        );

        $viewerRole = Role::firstOrCreate(
            ['name' => 'viewer'],
            ['description' => 'Read-only access to data']
        );

        $this->command->info('✅ Roles created successfully!');

        // ========================================
        // ASSIGN PERMISSIONS TO ROLES
        // ========================================
        
        // ADMIN: All permissions
        $adminRole->permissions()->sync(Permission::all()->pluck('id'));
        $this->command->info('✅ Admin role: ALL permissions assigned');

        // MANAGER: Can manage users, roles, and view everything
        $managerPermissions = Permission::whereIn('name', [
            'read dashboard',
            'read users', 'create users', 'update users', 'delete users',
            'read roles', 'create roles', 'update roles',
            'read permissions',
        ])->pluck('id');
        $managerRole->permissions()->sync($managerPermissions);
        $this->command->info('✅ Manager role: permissions assigned');

        // EDITOR: Can create and edit users
        $editorPermissions = Permission::whereIn('name', [
            'read dashboard',
            'read users', 'create users', 'update users',
            'read roles',
        ])->pluck('id');
        $editorRole->permissions()->sync($editorPermissions);
        $this->command->info('✅ Editor role: permissions assigned');

        // VIEWER: Read-only access
        $viewerPermissions = Permission::whereIn('name', [
            'read dashboard',
            'read users',
            'read roles',
            'read permissions',
        ])->pluck('id');
        $viewerRole->permissions()->sync($viewerPermissions);
        $this->command->info('✅ Viewer role: permissions assigned');

        $this->command->info('🎉 Role & Permission seeding completed!');
    }
}