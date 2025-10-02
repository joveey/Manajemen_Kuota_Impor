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
            ['name' => 'read dashboard', 'description' => 'View dashboard and statistics'],
            
            // User Management
            ['name' => 'read users', 'description' => 'View users list and details'],
            ['name' => 'create users', 'description' => 'Create new users'],
            ['name' => 'update users', 'description' => 'Edit existing users'],
            ['name' => 'delete users', 'description' => 'Delete users'],
            
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
            
            // Quota Management
            ['name' => 'read quota', 'description' => 'View quota data'],
            ['name' => 'create quota', 'description' => 'Create new quota'],
            ['name' => 'update quota', 'description' => 'Edit quota data'],
            ['name' => 'delete quota', 'description' => 'Delete quota data'],
            
            // Purchase Orders
            ['name' => 'read purchase_orders', 'description' => 'View purchase orders'],
            ['name' => 'create purchase_orders', 'description' => 'Create new purchase orders'],
            ['name' => 'update purchase_orders', 'description' => 'Edit purchase orders'],
            ['name' => 'delete purchase_orders', 'description' => 'Delete purchase orders'],
            
            // Master Data
            ['name' => 'read master_data', 'description' => 'View master data'],
            ['name' => 'create master_data', 'description' => 'Create new master data'],
            ['name' => 'update master_data', 'description' => 'Edit master data'],
            ['name' => 'delete master_data', 'description' => 'Delete master data'],
            
            // Reports
            ['name' => 'read reports', 'description' => 'View reports'],
            ['name' => 'create reports', 'description' => 'Create new reports'],
            ['name' => 'update reports', 'description' => 'Edit reports'],
            ['name' => 'delete reports', 'description' => 'Delete reports'],
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

        $viewerRole = Role::firstOrCreate(
            ['name' => 'viewer'],
            [
                'display_name' => 'Viewer',
                'description' => 'Can only view dashboard and reports (read-only access)',
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

        // MANAGER: Can manage users, roles & permissions (except admin role)
        // + Can VIEW data (Quota, Purchase Orders, Master Data, Reports)
        $managerPermissions = Permission::whereIn('name', [
            'read dashboard',
            // User Management
            'read users', 'create users', 'update users', 'delete users',
            // Role Management
            'read roles', 'create roles', 'update roles', 'delete roles',
            // Permission Management
            'read permissions', 'create permissions', 'update permissions', 'delete permissions',
            // View Data (read-only)
            'read quota',
            'read purchase_orders',
            'read master_data',
            'read reports',
        ])->pluck('id');
        $managerRole->permissions()->sync($managerPermissions);
        $this->command->info('✅ Manager role: permissions assigned');

        // VIEWER: Can only view dashboard and reports (read-only)
        // TIDAK bisa manage users, roles, permissions, atau edit data apapun
        $viewerPermissions = Permission::whereIn('name', [
            'read dashboard',
            // Read-only access to data
            'read quota',
            'read purchase_orders',
            'read master_data',
            'read reports',
        ])->pluck('id');
        $viewerRole->permissions()->sync($viewerPermissions);
        $this->command->info('✅ Viewer role: permissions assigned');

        $this->command->info('🎉 Role & Permission seeding completed!');
    }
}