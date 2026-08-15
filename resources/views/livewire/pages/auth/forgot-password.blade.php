<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest', ['title' => 'Reset password'])] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $status = Password::sendResetLink($this->only('email'));

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div>
    <x-ui.card>
        <h1 class="t-page-title text-ink-950">Reset password</h1>
        <p class="t-sub text-ink-600 mt-1">
            Enter the email on your account and we will send a link to set a new password.
        </p>

        @if (session('status'))
            <p class="mt-4 t-sub text-ok-600 flex items-start gap-1.5">
                <x-icon name="circle-check" class="size-4 mt-0.5 shrink-0" />
                {{ session('status') }}
            </p>
        @endif

        <form wire:submit="sendPasswordResetLink" class="mt-6 flex flex-col gap-4">
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

            <x-ui.button type="submit" variant="primary" target="sendPasswordResetLink" class="w-full">
                Send reset link
            </x-ui.button>
        </form>
    </x-ui.card>

    <div class="text-center mt-6">
        <a href="{{ route('login') }}" wire:navigate class="t-sub text-accent-600 hover:underline">
            Back to sign in
        </a>
    </div>
</div>
