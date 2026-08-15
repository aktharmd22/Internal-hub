<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest', ['title' => 'Verify email'])] class extends Component
{
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect(route('login'), navigate: true);
    }
}; ?>

<div>
    <x-ui.card>
        <h1 class="t-page-title text-ink-950">Verify your email</h1>
        <p class="t-sub text-ink-600 mt-1">
            We sent a link to {{ auth()->user()->email }}. Open it to activate your account.
        </p>

        @if (session('status') === 'verification-link-sent')
            <p class="mt-4 t-sub text-ok-600 flex items-start gap-1.5">
                <x-icon name="circle-check" class="size-4 mt-0.5 shrink-0" />
                A new link is on its way.
            </p>
        @endif

        <div class="mt-6 flex flex-col gap-2">
            <x-ui.button wire:click="sendVerification" variant="primary" target="sendVerification" class="w-full">
                Resend the link
            </x-ui.button>

            <x-ui.button wire:click="logout" variant="ghost" class="w-full">
                Sign out
            </x-ui.button>
        </div>
    </x-ui.card>
</div>
