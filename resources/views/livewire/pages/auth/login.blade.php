<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest', ['title' => 'Sign in'])] class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    {{-- On a phone the card's chrome earns nothing: the form is the whole
         screen. It becomes a real card from sm: up. --}}
    <x-ui.card class="max-sm:border-0 max-sm:bg-transparent max-sm:[&>div]:px-0">
        <h1 class="t-page-title text-ink-950 text-center">Sign in</h1>

        @if (session('status'))
            <p class="mt-4 t-sub text-ok-600 flex items-start gap-1.5">
                <x-icon name="circle-check" class="size-4 mt-0.5 shrink-0" />
                {{ session('status') }}
            </p>
        @endif

        <form wire:submit="login" class="mt-5 flex flex-col gap-4">
            <x-ui.field
                label="Email"
                for="email"
                type="email"
                required
                autofocus
                autocomplete="username"
                wire:model="form.email"
                :error="$errors->first('form.email')"
            />

            <x-ui.field
                label="Password"
                for="password"
                type="password"
                required
                autocomplete="current-password"
                wire:model="form.password"
                :error="$errors->first('form.password')"
            />

            <div class="flex items-center justify-between gap-3">
                <label for="remember" class="flex items-center gap-2 cursor-pointer select-none">
                    <input
                        wire:model="form.remember"
                        id="remember"
                        name="remember"
                        type="checkbox"
                        class="size-4 rounded border-ink-200 text-accent-600 focus:ring-accent-500"
                    >
                    <span class="t-sub text-ink-600">Keep me signed in</span>
                </label>

                @if (Route::has('password.request'))
                    <a
                        href="{{ route('password.request') }}"
                        wire:navigate
                        class="t-sub text-accent-600 hover:underline"
                    >Forgot password?</a>
                @endif
            </div>

            <x-ui.button type="submit" variant="primary" target="login" class="w-full mt-2">
                Sign in
            </x-ui.button>
        </form>
    </x-ui.card>

    <p class="t-meta text-ink-400 text-center mt-6">
        Accounts are created by an admin. Ask your manager if you need access.
    </p>
</div>
