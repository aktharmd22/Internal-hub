<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('toast', message: 'Password updated.', tone: 'ok');
    }
}; ?>

<x-ui.card title="Password" subtitle="At least 10 characters, with letters and numbers.">
    <form wire:submit="updatePassword" class="flex flex-col gap-4 mt-3">
        <x-ui.field
            label="Current password"
            for="update_password_current_password"
            type="password"
            autocomplete="current-password"
            wire:model="current_password"
            :error="$errors->first('current_password')"
        />

        <x-ui.field
            label="New password"
            for="update_password_password"
            type="password"
            autocomplete="new-password"
            wire:model="password"
            :error="$errors->first('password')"
        />

        <x-ui.field
            label="Confirm new password"
            for="update_password_password_confirmation"
            type="password"
            autocomplete="new-password"
            wire:model="password_confirmation"
            :error="$errors->first('password_confirmation')"
        />

        <div>
            <x-ui.button type="submit" variant="primary" target="updatePassword">
                Update password
            </x-ui.button>
        </div>
    </form>
</x-ui.card>
