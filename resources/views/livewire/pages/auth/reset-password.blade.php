<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest', ['title' => 'Set a new password'])] class extends Component
{
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = request()->string('email');
    }

    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status != Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return;
        }

        Session::flash('status', __($status));

        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div>
    <x-ui.card class="max-sm:border-0 max-sm:bg-transparent">
        <h1 class="t-page-title text-ink-950 text-center">Set a new password</h1>
        <p class="t-sub text-ink-600 mt-1.5 text-center">At least 10 characters, with letters and numbers.</p>

        <form wire:submit="resetPassword" class="mt-6 flex flex-col gap-4">
            <x-ui.field
                label="Email"
                for="email"
                type="email"
                required
                autofocus
                autocomplete="username"
                wire:model="email"
                :error="$errors->first('email')"
            />

            <x-ui.field
                label="New password"
                for="password"
                type="password"
                required
                autocomplete="new-password"
                wire:model="password"
                :error="$errors->first('password')"
            />

            <x-ui.field
                label="Confirm new password"
                for="password_confirmation"
                type="password"
                required
                autocomplete="new-password"
                wire:model="password_confirmation"
                :error="$errors->first('password_confirmation')"
            />

            <x-ui.button type="submit" variant="primary" target="resetPassword" class="w-full mt-2">
                Save password
            </x-ui.button>
        </form>
    </x-ui.card>
</div>
