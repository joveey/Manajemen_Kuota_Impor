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
            // Dashboard
            ['name' => 'read dashboard', 'display_name' => 'View Dashboard', 'group' => 'Dashboard', 'description' => 'View dashboard and statistics'],
            
            // User Management
            ['name' => 'read users', 'display_name' => 'View Users', 'group' => 'User Management', 'description' => 'View users list and details'],
            ['name' => 'create users', 'display_name' => 'Create Users', 'group' => 'User Management', 'description' => 'Create new users'],
            ['name' => 'update users', 'display_name' => 'Update Users', 'group' => 'User Management', 'description' => 'Edit existing users'],
            ['name' => 'delete users', 'display_name' => 'Delete Users', 'group' => 'User Management', 'description' => 'Delete users'],
            
            // Role Management
            ['name' => 'read roles', 'display_name' => 'View Roles', 'group' => 'Role Management', 'description' => 'View roles list and details'],
            ['name' => 'create roles', 'display_name' => 'Create Roles', 'group' => 'Role Management', 'description' => 'Create new roles'],
            ['name' => 'update roles', 'display_name' => 'Update Roles', 'group' => 'Role Management', 'description' => 'Edit existing roles'],
            ['name' => 'delete roles', 'display_name' => 'Delete Roles', 'group' => 'Role Management', 'description' => 'Delete roles'],
            
            // Permission Management
            ['name' => 'read permissions', 'display_name' => 'View Permissions', 'group' => 'Permission Management', 'description' => 'View permissions list and details'],
            ['name' => 'create permissions', 'display_name' => 'Create Permissions', 'group' => 'Permission Management', 'description' => 'Create new permissions'],
            ['name' => 'update permissions', 'display_name' => 'Update Permissions', 'group' => 'Permission Management', 'description' => 'Edit existing permissions'],
            ['name' => 'delete permissions', 'display_name' => 'Delete Permissions', 'group' => 'Permission Management', 'description' => 'Delete permissions'],
            
            // Quota Management
            ['name' => 'read quota', 'display_name' => 'View Quota', 'group' => 'Quota Management', 'description' => 'View quota data'],
            ['name' => 'create quota', 'display_name' => 'Create Quota', 'group' => 'Quota Management', 'description' => 'Create new quota'],
            ['name' => 'update quota', 'display_name' => 'Update Quota', 'group' => 'Quota Management', 'description' => 'Edit quota data'],
            ['name' => 'delete quota', 'display_name' => 'Delete Quota', 'group' => 'Quota Management', 'description' => 'Delete quota data'],
            
            // Purchase Orders
            ['name' => 'read purchase_orders', 'display_name' => 'View Purchase Orders', 'group' => 'Purchase Orders', 'description' => 'View purchase orders'],
            ['name' => 'create purchase_orders', 'display_name' => 'Create Purchase Orders', 'group' => 'Purchase Orders', 'description' => 'Create new purchase orders'],
            ['name' => 'update purchase_orders', 'display_name' => 'Update Purchase Orders', 'group' => 'Purchase Orders', 'description' => 'Edit purchase orders'],
            ['name' => 'delete purchase_orders', 'display_name' => 'Delete Purchase Orders', 'group' => 'Purchase Orders', 'description' => 'Delete purchase orders'],
            
            // Master Data
            ['name' => 'read master_data', 'display_name' => 'View Master Data', 'group' => 'Master Data', 'description' => 'View master data'],
            ['name' => 'create master_data', 'display_name' => 'Create Master Data', 'group' => 'Master Data', 'description' => 'Create new master data'],
            ['name' => 'update master_data', 'display_name' => 'Update Master Data', 'group' => 'Master Data', 'description' => 'Edit master data'],
            ['name' => 'delete master_data', 'display_name' => 'Delete Master Data', 'group' => 'Master Data', 'description' => 'Delete master data'],
            
            // Reports
            ['name' => 'read reports', 'display_name' => 'View Reports', 'group' => 'Reports', 'description' => 'View reports'],
            ['name' => 'create reports', 'display_name' => 'Create Reports', 'group' => 'Reports', 'description' => 'Create new reports'],
            ['name' => 'update reports', 'display_name' => 'Update Reports', 'group' => 'Reports', 'description' => 'Edit reports'],
            ['name' => 'delete reports', 'display_name' => 'Delete Reports', 'group' => 'Reports', 'description' => 'Delete reports'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                [
                    'display_name' => $permission['display_name'],
                    'group' => $permission['group'],
                    'description' => $permission['description']
                ]
            );
        }

        $this->command->info('✅ Permissions created successfully!');

        // ========================================
        // CREATE ROLES
        // ========================================
        
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Administrator',
                'description' => 'Full system access with all permissions',
                'is_active' => true
            ]
        );

        $editorRole = Role::firstOrCreate(
            ['name' => 'editor'],
            [
                'display_name' => 'Editor',
                'description' => 'Can create, edit, and delete dashboard data (Quota, Purchase Orders, Master Data, Reports)',
                'is_active' => true
            ]
        );

        $managerRole = Role::firstOrCreate(
            ['name' => 'manager'],
            [
                'display_name' => 'Manager',
                'description' => 'Can manage users, roles, and permissions (except admin role)',
                'is_active' => true
            ]
        );

        $this->command->info('✅ Roles created successfully!');

        // ========================================
        // ASSIGN PERMISSIONS TO ROLES
        // ========================================
        
        // ADMIN: All permissions
        $adminRole->permissions()->sync(Permission::all()->pluck('id'));
        $this->command->info('✅ Admin role: ALL permissions assigned');

        // EDITOR: Can create, edit, delete dashboard data (Quota, Purchase Orders, Master Data, Reports)
        // TIDAK bisa manage users, roles, dan permissions
        $editorPermissions = Permission::whereIn('name', [
            'read dashboard',
            // Quota Management
            'read quota', 'create quota', 'update quota', 'delete quota',
            // Purchase Orders
            'read purchase_orders', 'create purchase_orders', 'update purchase_orders', 'delete purchase_orders',
            // Master Data
            'read master_data', 'create master_data', 'update master_data', 'delete master_data',
            // Reports
            'read reports', 'create reports', 'update reports', 'delete reports',
        ])->pluck('id');
        $editorRole->permissions()->sync($editorPermissions);
        $this->command->info('✅ Editor role: permissions assigned');

        // MANAGER: Can manage users (change role without password, edit, delete)
        // Can manage roles & permissions (except admin role)
        $managerPermissions = Permission::whereIn('name', [
            'read dashboard',
            // User Management
            'read users', 'create users', 'update users', 'delete users',
            // Role Management
            'read roles', 'create roles', 'update roles', 'delete roles',
            // Permission Management
            'read permissions', 'create permissions', 'update permissions', 'delete permissions',
        ])->pluck('id');
        $managerRole->permissions()->sync($managerPermissions);
        $this->command->info('✅ Manager role: permissions assigned');

        $this->command->info('🎉 Role & Permission seeding completed!');
    }
}