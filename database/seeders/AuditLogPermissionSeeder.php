<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AuditLogPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create view permission for audit logs
        $permName = 'audit_logs.read';
        $perm = Permission::firstOrCreate(['name' => $permName]);

        // Attach to admin role if exists
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole && method_exists($adminRole, 'permissions')) {
            $adminRole->permissions()->syncWithoutDetaching([$perm->id]);
        }
    }
}

