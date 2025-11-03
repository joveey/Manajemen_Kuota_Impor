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
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole('admin');
        $this->command->info('✅ Admin user created: admin@example.com / password');

        // CLEANUP: remove legacy seeded users for roles we don't use anymore
        try {
            foreach ([
                'manager@example.com' => 'manager',
                'editor@example.com' => 'editor',
            ] as $email => $roleName) {
                if ($u = User::where('email', $email)->first()) {
                    // detach potential role relation gracefully
                    try { $u->removeRole($roleName); } catch (\Throwable $e) {}
                    $u->delete();
                    $this->command->info("🗑️ Removed legacy user: {$email}");
                }
            }
        } catch (\Throwable $e) {
            // ignore cleanup errors
        }

        // REGULAR USER
        $regular = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Regular User',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $regular->assignRole('user');
        $this->command->info('✅ Regular user created: user@example.com / password');

        $this->command->info('🎉 User seeding completed!');
    }
}
