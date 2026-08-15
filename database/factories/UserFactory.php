<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'phone' => '+91 '.fake()->numberBetween(70000, 99999).' '.fake()->numberBetween(10000, 99999),
            'is_active' => true,
            'timezone' => 'Asia/Kolkata',
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function admin(): static
    {
        return $this->withRole(RoleEnum::Admin);
    }

    public function manager(): static
    {
        return $this->withRole(RoleEnum::Manager);
    }

    public function employee(): static
    {
        return $this->withRole(RoleEnum::Employee);
    }

    /**
     * Creating the role on demand keeps tests from having to remember to run
     * the roles seeder before every factory call.
     */
    public function withRole(RoleEnum $case): static
    {
        return $this->afterCreating(function (User $user) use ($case): void {
            foreach (Permissions::for($case) as $permission) {
                Permission::findOrCreate($permission, 'web');
            }

            $role = Role::findOrCreate($case->value, 'web');
            $role->syncPermissions(Permissions::for($case));

            $user->syncRoles([$role]);
        });
    }
}
