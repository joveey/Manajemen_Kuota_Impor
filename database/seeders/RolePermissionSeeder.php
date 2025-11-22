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
            'Global' => [
                // Global create: allowed to create in all modules
                'create' => 'Create any resource across the system.',
                // Global read all
                'read' => 'Read access to all modules.',
                // Limited read: excludes Administration (users/roles/permissions)
                'read limited' => 'Read access to operational modules only (no administration).',
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

        // Remove granular permissions so only 'create', 'read', and 'read limited' remain
        try {
            $toRemove = Permission::query()
                ->where(function ($q) {
                    // remove any 'update *' and 'delete *'
                    $q->where('name', 'like', 'update %')
                      ->orWhere('name', 'like', 'delete %')
                      // remove any 'create *' granular permissions (we keep only the global 'create')
                      ->orWhere('name', 'like', 'create %')
                    // remove any 'read *' per module, except 'read limited'
                      ->orWhere(function ($q2) {
                          $q2->where('name', 'like', 'read %')
                             ->where('name', '!=', 'read limited');
                      });
                })
                ->orWhereIn('name', ['po.create', 'po.update', 'product.create'])
                ->get();

            foreach ($toRemove as $perm) {
                $perm->roles()->detach();
                $perm->delete();
            }
            if ($toRemove->count()) {
                $this->command?->info('[OK] Removed granular update/delete/create permissions, keeping only global create + reads.');
            }
        } catch (\Throwable $e) {
            // ignore if relations/tables not ready during early seeding
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

        // Ensure only the roles we need are present
        // Drop legacy roles we no longer use
        try {
            foreach (['manager', 'editor'] as $obsolete) {
                if ($role = Role::where('name', $obsolete)->first()) {
                    $role->permissions()->detach();
                    $role->users()->detach();
                    $role->delete();
                    $this->command?->info("[OK] Removed obsolete role: {$obsolete}.");
                }
            }
        } catch (\Throwable $e) {
            // ignore if relations not available during early seeding
        }

        $userRole = Role::firstOrCreate(
            ['name' => 'user'],
            [
                'display_name' => 'User',
                'description' => 'Read-only access to overview & operational (no administration).',
                'is_active' => true,
            ]
        );

        $this->command?->info('[OK] Roles created or updated successfully.');

        // Migrate legacy role name "viewer" -> "user" if necessary
        try {
            $legacyViewer = Role::where('name', 'viewer')->first();
            if ($legacyViewer && isset($userRole)) {
                // Re-attach all users to the new "user" role
                foreach ($legacyViewer->users()->pluck('id') as $uid) {
                    \App\Models\User::find($uid)?->assignRole('user');
                }
                $legacyViewer->delete();
                $this->command?->info('[OK] Migrated legacy role "viewer" to "user".');
            }
        } catch (\Throwable $e) {
            // ignore if relations not available during early seeding
        }

        // Assign permission sets based on the final specification.
        $adminRole->permissions()->sync(array_values($permissionIds));
        $this->command?->info('[OK] Admin role: all permissions assigned.');

        $userRole->permissions()->sync($pluckPermissions([
            'read limited',
        ]));
        $this->command?->info('[OK] User role: limited read (operational only) assigned.');

        $this->command?->info('[DONE] Role & permission seeding completed.');
    }
}
