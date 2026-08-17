<?php

use App\Livewire\Team\Index as TeamScreen;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->admin()->create();
});

/*
 * Mail is not configured on a fresh install, so the reset link goes nowhere.
 * Without this, an admin has no way to give anyone access at all.
 */
test('an admin can set a password when creating an account', function () {
    Livewire::actingAs($this->admin)
        ->test(TeamScreen::class)
        ->call('newUser')
        ->set('name', 'Meera Iyer')
        ->set('email', 'meera@gnext.com')
        ->set('role', 'employee')
        ->set('password', 'MeeraStarts2026')
        ->call('save')
        ->assertHasNoErrors();

    $user = User::where('email', 'meera@gnext.com')->first();

    expect($user)->not->toBeNull()
        ->and(Hash::check('MeeraStarts2026', $user->password))->toBeTrue()
        ->and($user->hasRole('employee'))->toBeTrue();
});

test('leaving it blank still creates the account, with a password nobody holds', function () {
    Livewire::actingAs($this->admin)
        ->test(TeamScreen::class)
        ->call('newUser')
        ->set('name', 'Suresh Babu')
        ->set('email', 'suresh@gnext.com')
        ->set('role', 'employee')
        ->call('save')
        ->assertHasNoErrors();

    $user = User::where('email', 'suresh@gnext.com')->first();

    expect($user)->not->toBeNull()
        // Not blank, not guessable, and not known to the admin either.
        ->and($user->password)->not->toBeEmpty()
        ->and(Hash::check('', $user->password))->toBeFalse();
});

test('an admin can reset an existing password', function () {
    $employee = User::factory()->employee()->create(['password' => Hash::make('the-old-one')]);

    Livewire::actingAs($this->admin)
        ->test(TeamScreen::class)
        ->call('edit', $employee->id)
        ->set('password', 'ReplacedIt2026')
        ->call('save')
        ->assertHasNoErrors();

    expect(Hash::check('ReplacedIt2026', $employee->fresh()->password))->toBeTrue();
});

/*
 * The field is never populated from the stored hash, so saving an edit without
 * touching it must not wipe the password the person is already using.
 */
test('editing without touching the field leaves the password alone', function () {
    $employee = User::factory()->employee()->create(['password' => Hash::make('keep-this-one')]);

    Livewire::actingAs($this->admin)
        ->test(TeamScreen::class)
        ->call('edit', $employee->id)
        ->set('name', 'Renamed Person')
        ->call('save')
        ->assertHasNoErrors();

    $employee->refresh();

    expect($employee->name)->toBe('Renamed Person')
        ->and(Hash::check('keep-this-one', $employee->password))->toBeTrue();
});

test('opening the edit form never exposes the stored password', function () {
    $employee = User::factory()->employee()->create(['password' => Hash::make('secret-value')]);

    Livewire::actingAs($this->admin)
        ->test(TeamScreen::class)
        ->call('edit', $employee->id)
        ->assertSet('password', '')
        ->assertDontSee('secret-value');
});

test('a weak password is refused', function () {
    Livewire::actingAs($this->admin)
        ->test(TeamScreen::class)
        ->call('newUser')
        ->set('name', 'Weak')
        ->set('email', 'weak@gnext.com')
        ->set('password', 'short')
        ->call('save')
        ->assertHasErrors('password');

    expect(User::where('email', 'weak@gnext.com')->exists())->toBeFalse();
});

test('the generator produces something that passes the policy', function () {
    $component = Livewire::actingAs($this->admin)
        ->test(TeamScreen::class)
        ->call('generatePassword');

    $generated = $component->get('password');

    expect(strlen($generated))->toBe(16);

    $component
        ->set('name', 'Generated User')
        ->set('email', 'generated@gnext.com')
        ->call('save')
        ->assertHasNoErrors();

    expect(Hash::check($generated, User::where('email', 'generated@gnext.com')->first()->password))->toBeTrue();
});

test('only an admin can set anyone password', function () {
    $manager = User::factory()->manager()->create();

    $this->actingAs($manager)->get(route('team.index'))->assertForbidden();
});
