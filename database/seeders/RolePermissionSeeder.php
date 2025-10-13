<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        /**
         * Define the permission catalogue once so we can:
         * 1. Keep metadata (group + display name) consistent.
         * 2. Re-use the same canonical permission names when assigning to roles.
         */
        $permissionGroups = [
            'Dashboard' => [
                'read dashboard' => 'View dashboard and statistics.',
            ],
            'Administration' => [
                'read users' => 'View users list and details.',
                'create users' => 'Create new users.',
                'update users' => 'Edit existing users.',
                'delete users' => 'Delete users.',
                'read roles' => 'View roles list and details.',
                'create roles' => 'Create new roles.',
                'update roles' => 'Edit existing roles.',
                'delete roles' => 'Delete roles.',
                'read permissions' => 'View permissions list and details.',
                'create permissions' => 'Create new permissions.',
                'update permissions' => 'Edit existing permissions.',
                'delete permissions' => 'Delete permissions.',
            ],
            'Quota Management' => [
                'read quota' => 'View quota data.',
                'create quota' => 'Create new quota data.',
                'update quota' => 'Edit quota data.',
                'delete quota' => 'Delete quota data.',
            ],
            'Purchase Orders' => [
                'read purchase_orders' => 'View purchase orders.',
                'create purchase_orders' => 'Create new purchase orders.',
                'update purchase_orders' => 'Edit purchase orders.',
                'delete purchase_orders' => 'Delete purchase orders.',
            ],
            'Master Data' => [
                'read master_data' => 'View master data.',
                'create master_data' => 'Create new master data.',
                'update master_data' => 'Edit master data.',
                'delete master_data' => 'Delete master data.',
            ],
            'Reports' => [
                'read reports' => 'View reports.',
                'create reports' => 'Create new reports.',
                'update reports' => 'Edit reports.',
                'delete reports' => 'Delete reports.',
            ],
        ];

        $permissionIds = [];

        foreach ($permissionGroups as $group => $items) {
            foreach ($items as $name => $description) {
                $permission = Permission::updateOrCreate(
                    ['name' => $name],
                    [
                        'display_name' => Str::headline($name),
                        'group' => $group,
                        'description' => $description,
                    ]
                );

                $permissionIds[$name] = $permission->id;
            }
        }

        $this->command?->info('[OK] Permissions created or updated successfully.');

        // Small helper to fetch IDs for the mapping below
        $pluckPermissions = static function (array $names) use ($permissionIds): array {
            return collect($names)
                ->map(fn ($name) => $permissionIds[$name] ?? null)
                ->filter()
                ->values()
                ->all();
        };

        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Administrator',
                'description' => 'Full system access with all permissions.',
                'is_active' => true,
            ]
        );

        $managerRole = Role::firstOrCreate(
            ['name' => 'manager'],
            [
                'display_name' => 'Manager',
                'description' => 'Manages users, roles, and permissions with data read access.',
                'is_active' => true,
            ]
        );

        $editorRole = Role::firstOrCreate(
            ['name' => 'editor'],
            [
                'display_name' => 'Editor',
                'description' => 'Manages quota, PO, master data, and reports (no administration access).',
                'is_active' => true,
            ]
        );

        $viewerRole = Role::firstOrCreate(
            ['name' => 'viewer'],
            [
                'display_name' => 'Viewer',
                'description' => 'Read-only access to dashboard and operational data.',
                'is_active' => true,
            ]
        );

        $this->command?->info('[OK] Roles created or updated successfully.');

        // Assign permission sets based on the final specification.
        $adminRole->permissions()->sync(array_values($permissionIds));
        $this->command?->info('[OK] Admin role: all permissions assigned.');

        $managerRole->permissions()->sync($pluckPermissions([
            'read dashboard',
            'read users', 'create users', 'update users', 'delete users',
            'read roles', 'create roles', 'update roles', 'delete roles',
            'read permissions', 'create permissions', 'update permissions', 'delete permissions',
            'read quota',
            'read purchase_orders',
            'read master_data',
            'read reports',
        ]));
        $this->command?->info('[OK] Manager role: administration + data read permissions assigned.');

        $editorRole->permissions()->sync($pluckPermissions([
            'read dashboard',
            'read quota', 'create quota', 'update quota', 'delete quota',
            'read purchase_orders', 'create purchase_orders', 'update purchase_orders', 'delete purchase_orders',
            'read master_data', 'create master_data', 'update master_data', 'delete master_data',
            'read reports', 'create reports', 'update reports', 'delete reports',
        ]));
        $this->command?->info('[OK] Editor role: data management permissions assigned.');

        $viewerRole->permissions()->sync($pluckPermissions([
            'read dashboard',
            'read quota',
            'read purchase_orders',
            'read master_data',
            'read reports',
        ]));
        $this->command?->info('[OK] Viewer role: read-only permissions assigned.');

        $this->command?->info('[DONE] Role & permission seeding completed.');
    }
}
