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
        
        $permissions = [
            // Dashboard
            ['name' => 'dashboard.view', 'display_name' => 'View Dashboard', 'group' => 'Dashboard'],
            
            // User Management
            ['name' => 'users.view', 'display_name' => 'View Users', 'group' => 'User Management'],
            ['name' => 'users.create', 'display_name' => 'Create Users', 'group' => 'User Management'],
            ['name' => 'users.edit', 'display_name' => 'Edit Users', 'group' => 'User Management'],
            ['name' => 'users.delete', 'display_name' => 'Delete Users', 'group' => 'User Management'],
            
            // Role Management
            ['name' => 'roles.view', 'display_name' => 'View Roles', 'group' => 'Role Management'],
            ['name' => 'roles.create', 'display_name' => 'Create Roles', 'group' => 'Role Management'],
            ['name' => 'roles.edit', 'display_name' => 'Edit Roles', 'group' => 'Role Management'],
            ['name' => 'roles.delete', 'display_name' => 'Delete Roles', 'group' => 'Role Management'],
            
            // Permission Management
            ['name' => 'permissions.view', 'display_name' => 'View Permissions', 'group' => 'Permission Management'],
            ['name' => 'permissions.manage', 'display_name' => 'Manage Permissions', 'group' => 'Permission Management'],
            
            // Quota Management
            ['name' => 'quota.view', 'display_name' => 'View Quota', 'group' => 'Quota Management'],
            ['name' => 'quota.create', 'display_name' => 'Create Quota', 'group' => 'Quota Management'],
            ['name' => 'quota.edit', 'display_name' => 'Edit Quota', 'group' => 'Quota Management'],
            ['name' => 'quota.delete', 'display_name' => 'Delete Quota', 'group' => 'Quota Management'],
            ['name' => 'quota.approve', 'display_name' => 'Approve Quota', 'group' => 'Quota Management'],
            
            // PO Management
            ['name' => 'po.view', 'display_name' => 'View Purchase Orders', 'group' => 'PO Management'],
            ['name' => 'po.import', 'display_name' => 'Import PO from SAP', 'group' => 'PO Management'],
            ['name' => 'po.edit', 'display_name' => 'Edit Purchase Orders', 'group' => 'PO Management'],
            ['name' => 'po.delete', 'display_name' => 'Delete Purchase Orders', 'group' => 'PO Management'],
            
            // Master Data
            ['name' => 'master.view', 'display_name' => 'View Master Data', 'group' => 'Master Data'],
            ['name' => 'master.create', 'display_name' => 'Create Master Data', 'group' => 'Master Data'],
            ['name' => 'master.edit', 'display_name' => 'Edit Master Data', 'group' => 'Master Data'],
            ['name' => 'master.delete', 'display_name' => 'Delete Master Data', 'group' => 'Master Data'],
            
            // Reports
            ['name' => 'reports.view', 'display_name' => 'View Reports', 'group' => 'Reports'],
            ['name' => 'reports.export', 'display_name' => 'Export Reports', 'group' => 'Reports'],
            ['name' => 'reports.print', 'display_name' => 'Print Reports', 'group' => 'Reports'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
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

        $importManagerRole = Role::firstOrCreate(
            ['name' => 'import_manager'],
            [
                'display_name' => 'Import Manager',
                'description' => 'Manage import operations, quota, and PO',
                'is_active' => true
            ]
        );

        $marketingRole = Role::firstOrCreate(
            ['name' => 'marketing'],
            [
                'display_name' => 'Marketing',
                'description' => 'View quota, reports, and analytics',
                'is_active' => true
            ]
        );

        $viewerRole = Role::firstOrCreate(
            ['name' => 'viewer'],
            [
                'display_name' => 'Viewer',
                'description' => 'Read-only access to data',
                'is_active' => true
            ]
        );

        $this->command->info('✅ Roles created successfully!');

        // ========================================
        // ASSIGN PERMISSIONS TO ROLES
        // ========================================
        
        // ADMIN: Semua permission
        $adminRole->permissions()->sync(Permission::all()->pluck('id'));
        $this->command->info('✅ Admin role: ALL permissions assigned');

        // IMPORT MANAGER: Manage quota, PO, master data, reports
        $importManagerPermissions = Permission::whereIn('name', [
            'dashboard.view',
            'quota.view', 'quota.create', 'quota.edit', 'quota.approve',
            'po.view', 'po.import', 'po.edit',
            'master.view', 'master.create', 'master.edit',
            'reports.view', 'reports.export', 'reports.print',
        ])->pluck('id');
        $importManagerRole->permissions()->sync($importManagerPermissions);
        $this->command->info('✅ Import Manager role: permissions assigned');

        // MARKETING: View quota, PO, reports
        $marketingPermissions = Permission::whereIn('name', [
            'dashboard.view',
            'quota.view',
            'po.view',
            'master.view',
            'reports.view', 'reports.export',
        ])->pluck('id');
        $marketingRole->permissions()->sync($marketingPermissions);
        $this->command->info('✅ Marketing role: permissions assigned');

        // VIEWER: Read-only
        $viewerPermissions = Permission::whereIn('name', [
            'dashboard.view',
            'quota.view',
            'po.view',
            'master.view',
            'reports.view',
        ])->pluck('id');
        $viewerRole->permissions()->sync($viewerPermissions);
        $this->command->info('✅ Viewer role: permissions assigned');

        $this->command->info('🎉 Role & Permission seeding completed!');
    }
}