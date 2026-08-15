<?php

use App\Enums\Role;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('the seeder creates every role and permission', function () {
    expect(Spatie\Permission\Models\Role::pluck('name')->sort()->values()->all())
        ->toBe(collect(Role::values())->sort()->values()->all());

    expect(Permission::count())
        ->toBe(count(Permissions::all()));
});

test('an admin holds every permission', function () {
    $admin = User::factory()->admin()->create();

    foreach (Permissions::all() as $permission) {
        expect($admin->can($permission))->toBeTrue("admin should hold {$permission}");
    }
});

test('a manager cannot manage users, settings or credentials', function () {
    $manager = User::factory()->manager()->create();

    expect($manager->can(Permissions::MANAGE_USERS))->toBeFalse()
        ->and($manager->can(Permissions::MANAGE_SETTINGS))->toBeFalse()
        ->and($manager->can(Permissions::VIEW_CREDENTIALS))->toBeFalse()
        ->and($manager->can(Permissions::MANAGE_CREDENTIALS))->toBeFalse();
});

test('a manager can run the agency day to day', function () {
    $manager = User::factory()->manager()->create();

    expect($manager->can(Permissions::VIEW_CLIENTS))->toBeTrue()
        ->and($manager->can(Permissions::MANAGE_ASSETS))->toBeTrue()
        ->and($manager->can(Permissions::APPROVE_TASKS))->toBeTrue()
        ->and($manager->can(Permissions::VIEW_REPORTS))->toBeTrue();
});

test('an employee holds no blanket permissions', function () {
    $employee = User::factory()->employee()->create();

    foreach (Permissions::all() as $permission) {
        expect($employee->can($permission))->toBeFalse("employee should not hold {$permission}");
    }
});

test('the role helper reports the assigned role', function () {
    expect(User::factory()->admin()->create()->role())->toBe(Role::Admin)
        ->and(User::factory()->manager()->create()->isManager())->toBeTrue()
        ->and(User::factory()->employee()->create()->isEmployee())->toBeTrue()
        ->and(User::factory()->employee()->create()->isAdmin())->toBeFalse();
});
