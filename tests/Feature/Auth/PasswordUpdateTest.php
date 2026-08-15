<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

test('password can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Volt::test('profile.update-password-form')
        ->set('current_password', 'password')
        ->set('password', 'chennai2026rain')
        ->set('password_confirmation', 'chennai2026rain')
        ->call('updatePassword');

    $component
        ->assertHasNoErrors()
        ->assertNoRedirect();

    $this->assertTrue(Hash::check('chennai2026rain', $user->refresh()->password));
});

test('a new password must meet the application password policy', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Volt::test('profile.update-password-form')
        ->set('current_password', 'password')
        ->set('password', 'short1')
        ->set('password_confirmation', 'short1')
        ->call('updatePassword')
        ->assertHasErrors(['password']);
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Volt::test('profile.update-password-form')
        ->set('current_password', 'wrong-password')
        ->set('password', 'chennai2026rain')
        ->set('password_confirmation', 'chennai2026rain')
        ->call('updatePassword');

    $component
        ->assertHasErrors(['current_password'])
        ->assertNoRedirect();
});
