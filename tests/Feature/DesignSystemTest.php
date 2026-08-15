<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('the kitchen sink renders every component', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('kitchen-sink'))
        ->assertOk()
        ->assertSee('Type scale')
        ->assertSee('Status badges')
        ->assertSee('Form fields')
        ->assertSee('Overlays')
        ->assertSee('No renewals due in the next 30 days');
});

test('the kitchen sink is not routed in production', function () {
    // The route file only registers it outside production, so a cached
    // production route table can never contain it.
    $source = file_get_contents(base_path('routes/web.php'));

    expect($source)->toContain('if (! app()->isProduction())');
});

test('the theme boots from storage before first paint', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee("localStorage.getItem('rg.theme')", escape: false)
        ->assertSee('prefers-color-scheme: dark', escape: false);
});

test('the viewport is configured for phones', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();

    // `viewport-fit=cover` lets the bottom bar clear the home indicator, and
    // `interactive-widget=resizes-content` keeps the chat composer above the
    // on-screen keyboard in phase 4.
    $response->assertSee('viewport-fit=cover', escape: false);
    $response->assertSee('interactive-widget=resizes-content', escape: false);
});
