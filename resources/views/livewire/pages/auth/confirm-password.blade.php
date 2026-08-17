<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest', ['title' => 'Confirm password'])] class extends Component
{
    public string $password = '';

    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <x-ui.card class="max-sm:border-0 max-sm:bg-transparent">
        <h1 class="t-page-title text-ink-950 text-center">Confirm password</h1>
        <p class="t-sub text-ink-600 mt-1.5 text-center">
            This area holds client credentials. Enter your password to continue.
        </p>

        <form wire:submit="confirmPassword" class="mt-6 flex flex-col gap-4">
            <x-ui.field
                label="Password"
                for="password"
                type="password"
                required
                autofocus
                autocomplete="current-password"
                wire:model="password"
                :error="$errors->first('password')"
            />

            <x-ui.button type="submit" variant="primary" target="confirmPassword" class="w-full">
                Confirm
            </x-ui.button>
        </form>
    </x-ui.card>
</div>
