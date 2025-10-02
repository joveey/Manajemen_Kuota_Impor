<?php

/**
 * Script untuk membersihkan dan re-assign permissions untuk role Viewer
 * Jalankan dengan: php fix-viewer-permissions.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Role;
use App\Models\Permission;

echo "🔧 Fixing Viewer Role Permissions...\n\n";

// Get viewer role
$viewerRole = Role::where('name', 'viewer')->first();

if (!$viewerRole) {
    echo "❌ Viewer role not found!\n";
    exit(1);
}

echo "✅ Found Viewer role (ID: {$viewerRole->id})\n";

// Get only the permissions that Viewer should have
$allowedPermissions = [
    'read dashboard',
    'read quota',
    'read purchase_orders',
    'read master_data',
    'read reports',
];

echo "\n📋 Allowed permissions for Viewer:\n";
foreach ($allowedPermissions as $perm) {
    echo "   - {$perm}\n";
}

// Get permission IDs
$permissionIds = Permission::whereIn('name', $allowedPermissions)->pluck('id')->toArray();

echo "\n🔄 Syncing permissions...\n";

// Sync permissions (this will remove all other permissions)
$viewerRole->permissions()->sync($permissionIds);

echo "✅ Permissions synced successfully!\n";

// Verify
echo "\n✔️ Verification:\n";
$currentPermissions = $viewerRole->permissions()->pluck('name')->toArray();
echo "   Current permissions: " . implode(', ', $currentPermissions) . "\n";

// Check if viewer has admin permissions (should be false)
$hasReadUsers = $viewerRole->permissions()->where('name', 'read users')->exists();
$hasReadRoles = $viewerRole->permissions()->where('name', 'read roles')->exists();
$hasReadPermissions = $viewerRole->permissions()->where('name', 'read permissions')->exists();

echo "\n🔍 Admin permissions check:\n";
echo "   - read users: " . ($hasReadUsers ? "❌ YES (WRONG!)" : "✅ NO (CORRECT)") . "\n";
echo "   - read roles: " . ($hasReadRoles ? "❌ YES (WRONG!)" : "✅ NO (CORRECT)") . "\n";
echo "   - read permissions: " . ($hasReadPermissions ? "❌ YES (WRONG!)" : "✅ NO (CORRECT)") . "\n";

if (!$hasReadUsers && !$hasReadRoles && !$hasReadPermissions) {
    echo "\n🎉 SUCCESS! Viewer role is now correctly configured.\n";
    echo "\n📝 Next steps:\n";
    echo "   1. Run: php artisan cache:clear\n";
    echo "   2. Run: php artisan config:clear\n";
    echo "   3. Run: php artisan view:clear\n";
    echo "   4. Logout and login again as Viewer\n";
    echo "   5. Hard refresh browser (Ctrl + Shift + R)\n";
} else {
    echo "\n⚠️ WARNING! Viewer still has admin permissions!\n";
}

echo "\n";
