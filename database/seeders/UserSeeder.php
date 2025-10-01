<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ADMIN USER
        $admin = User::firstOrCreate(
            ['email' => 'admin@quotamonitor.com'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('admin123'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole('admin');
        $this->command->info('✅ Admin user created: admin@quotamonitor.com / admin123');

        // IMPORT MANAGER USER
        $importManager = User::firstOrCreate(
            ['email' => 'irman@quotamonitor.com'],
            [
                'name' => 'Irman (Import Manager)',
                'password' => Hash::make('irman123'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $importManager->assignRole('import_manager');
        $this->command->info('✅ Import Manager created: irman@quotamonitor.com / irman123');

        // MARKETING USER
        $marketing = User::firstOrCreate(
            ['email' => 'marketing@quotamonitor.com'],
            [
                'name' => 'Marketing User',
                'password' => Hash::make('marketing123'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $marketing->assignRole('marketing');
        $this->command->info('✅ Marketing user created: marketing@quotamonitor.com / marketing123');

        // VIEWER USER
        $viewer = User::firstOrCreate(
            ['email' => 'viewer@quotamonitor.com'],
            [
                'name' => 'Viewer User',
                'password' => Hash::make('viewer123'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $viewer->assignRole('viewer');
        $this->command->info('✅ Viewer user created: viewer@quotamonitor.com / viewer123');

        $this->command->info('🎉 User seeding completed!');
    }
}