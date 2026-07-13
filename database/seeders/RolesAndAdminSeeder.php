<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RolesAndAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        foreach (['admin', 'editor', 'author'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        // Create default admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@newspaper.test'],
            [
                'name'     => 'Site Admin',
                'password' => Hash::make('password'),
            ]
        );

        $admin->syncRoles(['admin']);
    }
}
