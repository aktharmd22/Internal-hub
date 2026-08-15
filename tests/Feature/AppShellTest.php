<?php

use App\Models\User;
use App\Support\Navigation;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('guests are sent to the login screen', function () {
    $this->get('/')->assertRedirect('/dashboard');
    $this->get('/dashboard')->assertRedirect(route('login'));
});

test('the dashboard renders for every role', function (string $role) {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Home')
        ->assertSee($user->firstName());
})->with(['admin', 'manager', 'employee']);

test('an employee never sees assets, clients or the vault in the shell', function () {
    $employee = User::factory()->employee()->create();

    $response = $this->actingAs($employee)->get(route('dashboard'))->assertOk();

    $response->assertDontSee(route('assets.index'));
    $response->assertDontSee(route('clients.index'));
    $response->assertDontSee(route('vault.index'));
});

test('hiding the link is not the protection: the route itself is guarded', function (string $route) {
    $employee = User::factory()->employee()->create();

    $this->actingAs($employee)->get(route($route))->assertForbidden();
})->with([
    'assets.index',
    'clients.index',
    'projects.index',
    'team.index',
    'reports.index',
    'vault.index',
    'settings.index',
]);

test('a manager reaches the agency screens but not users, settings or the vault', function () {
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager)->get(route('assets.index'))->assertOk();
    $this->actingAs($manager)->get(route('clients.index'))->assertOk();
    $this->actingAs($manager)->get(route('reports.index'))->assertOk();

    $this->actingAs($manager)->get(route('team.index'))->assertForbidden();
    $this->actingAs($manager)->get(route('settings.index'))->assertForbidden();
    $this->actingAs($manager)->get(route('vault.index'))->assertForbidden();
});

test('an admin reaches every screen in the shell', function () {
    $admin = User::factory()->admin()->create();

    foreach (['assets.index', 'clients.index', 'projects.index', 'team.index', 'reports.index', 'vault.index', 'settings.index'] as $route) {
        $this->actingAs($admin)->get(route($route))->assertOk();
    }
});

test('the bottom tab bar never exceeds five items', function (string $role) {
    $user = User::factory()->{$role}()->create();

    expect(Navigation::tabs($user)->count())->toBeLessThanOrEqual(5);
})->with(['admin', 'manager', 'employee']);

test('the tab bar drops assets for an employee', function () {
    $admin = User::factory()->admin()->create();
    $employee = User::factory()->employee()->create();

    expect(Navigation::tabs($admin)->pluck('route'))->toContain('assets.index')
        ->and(Navigation::tabs($employee)->pluck('route'))->not->toContain('assets.index');
});

test('the more screen lists only what the user may reach', function () {
    $employee = User::factory()->employee()->create();

    $routes = Navigation::overflow($employee)
        ->flatMap(fn (array $group) => $group['items']->pluck('route'))
        ->all();

    expect($routes)->not->toContain('vault.index')
        ->and($routes)->not->toContain('settings.index');

    $this->actingAs($employee)->get(route('more'))->assertOk();
});

test('every navigation destination resolves to a registered route', function () {
    $admin = User::factory()->admin()->create();

    Navigation::sidebar($admin)
        ->flatMap(fn (array $group) => $group['items'])
        ->concat(Navigation::tabs($admin))
        ->each(function (array $item) {
            expect(Route::has($item['route']))->toBeTrue("route [{$item['route']}] is missing");
        });
});
