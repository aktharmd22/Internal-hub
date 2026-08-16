<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $accounts = [
            [Role::Admin, 'Aarthi Ramesh', 'admin@gnext.com'],
            [Role::Manager, 'Vignesh Kumar', 'manager@renewalguard.test'],
            [Role::Employee, 'Divya Nair', 'employee@renewalguard.test'],
        ];

        foreach ($accounts as [$role, $name, $email]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => 'password',
                    'email_verified_at' => now(),
                    'phone' => '+91 90000 0000'.$role->value[0],
                    'timezone' => 'Asia/Kolkata',
                ],
            );

            $user->syncRoles([$role->value]);
        }
    }
}
