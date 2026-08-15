<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('toast', message: 'Profile saved.', tone: 'ok');
    }

    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<x-ui.card title="Profile" subtitle="Your name and how the team reaches you.">
    <form wire:submit="updateProfileInformation" class="flex flex-col gap-4 mt-3">
        <x-ui.field
            label="Name"
            for="name"
            required
            autocomplete="name"
            wire:model="name"
            :error="$errors->first('name')"
        />

        <x-ui.field
            label="Email"
            for="email"
            type="email"
            required
            autocomplete="username"
            wire:model="email"
            :error="$errors->first('email')"
        />

        <x-ui.field
            label="Phone"
            for="phone"
            type="tel"
            hint="Used for WhatsApp reminders."
            autocomplete="tel"
            wire:model="phone"
            :error="$errors->first('phone')"
        />

        @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
            <div class="rounded-control bg-warn-50 px-3 py-2.5">
                <p class="t-sub text-warn-600">
                    This email is unverified.
                    <button type="button" wire:click.prevent="sendVerification" class="underline font-medium">
                        Send a new link
                    </button>
                </p>

                @if (session('status') === 'verification-link-sent')
                    <p class="t-meta text-ok-600 mt-1">A new link is on its way.</p>
                @endif
            </div>
        @endif

        <div>
            <x-ui.button type="submit" variant="primary" target="updateProfileInformation">
                Save profile
            </x-ui.button>
        </div>
    </form>
</x-ui.card>
