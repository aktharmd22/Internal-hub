<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('profile page is displayed', function () {
    $user = User::factory()->employee()->create();

    $this->actingAs($user)
        ->get('/profile')
        ->assertOk()
        ->assertSeeVolt('profile.update-profile-information-form')
        ->assertSeeVolt('profile.update-password-form');
});

test('profile information can be updated', function () {
    $user = User::factory()->employee()->create();

    $this->actingAs($user);

    Volt::test('profile.update-profile-information-form')
        ->set('name', 'Divya Nair')
        ->set('email', 'divya@example.com')
        ->set('phone', '+91 98400 11223')
        ->call('updateProfileInformation')
        ->assertHasNoErrors()
        ->assertNoRedirect();

    $user->refresh();

    expect($user->name)->toBe('Divya Nair')
        ->and($user->email)->toBe('divya@example.com')
        ->and($user->phone)->toBe('+91 98400 11223')
        ->and($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->employee()->create();

    $this->actingAs($user);

    Volt::test('profile.update-profile-information-form')
        ->set('name', 'Divya Nair')
        ->set('email', $user->email)
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('an account cannot be deleted from the profile screen', function () {
    // Accounts are deactivated by an admin, never self-deleted: their tasks,
    // status history and messages have to stay attributable.
    expect(file_exists(resource_path('views/livewire/profile/delete-user-form.blade.php')))->toBeFalse();
});
