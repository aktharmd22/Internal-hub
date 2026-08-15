<?php

declare(strict_types=1);

namespace App\Livewire\Team;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Team')]
class Index extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $role = 'employee';

    public function mount(): void
    {
        abort_unless(auth()->user()->can(Permissions::MANAGE_USERS), 403);
    }

    public function newUser(): void
    {
        $this->reset(['editingId', 'name', 'email', 'phone']);
        $this->role = 'employee';
        $this->resetValidation();
        $this->dispatch('open-modal', 'user-form');
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
        $this->role = $user->role()?->value ?? 'employee';

        $this->dispatch('open-modal', 'user-form');
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?: null,
            ]);
        } else {
            // A temporary password, sent nowhere: the new user resets it from
            // the login screen. Nothing here ever mails a password.
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?: null,
                'password' => Hash::make(Str::password(16)),
                'email_verified_at' => now(),
                'timezone' => config('app.timezone'),
            ]);
        }

        $user->syncRoles([$data['role']]);

        $this->dispatch('close-modal', 'user-form');
        $this->dispatch('toast', message: $this->editingId ? 'Account updated.' : 'Account created. They can set a password with "Forgot password".', tone: 'ok');
        $this->reset(['editingId', 'name', 'email', 'phone']);
    }

    public function toggleActive(int $id): void
    {
        $user = User::findOrFail($id);

        abort_if($user->id === auth()->id(), 403, 'You cannot deactivate your own account.');

        $user->forceFill(['is_active' => ! $user->is_active])->save();

        $this->dispatch(
            'toast',
            message: $user->is_active ? "{$user->firstName()} is active again." : "{$user->firstName()} is deactivated and will be signed out.",
            tone: $user->is_active ? 'ok' : 'warn',
        );
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'phone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', Rule::in(Role::values())],
        ];
    }

    public function render(): View
    {
        return view('livewire.team.index', [
            'people' => $this->people(),
        ]);
    }

    /**
     * The scorecard. On-time percentage and reopen rate only mean anything
     * next to the volume they are drawn from, so the count travels with them.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function people(): Collection
    {
        $users = User::query()->with('roles')->orderBy('name')->get();

        $stats = Task::query()
            ->whereNotNull('assigned_to')
            ->selectRaw('assigned_to')
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as completed', [TaskStatus::Completed->value])
            ->selectRaw('sum(case when status = ? and due_at is not null and completed_at <= due_at then 1 else 0 end) as on_time', [TaskStatus::Completed->value])
            ->selectRaw('sum(case when status = ? and due_at is not null then 1 else 0 end) as with_due', [TaskStatus::Completed->value])
            ->selectRaw('sum(reopen_count) as reopens')
            ->selectRaw('sum(case when status not in ('.implode(',', array_fill(0, count(TaskStatus::closedValues()), '?')).') then 1 else 0 end) as open_tasks', TaskStatus::closedValues())
            ->groupBy('assigned_to')
            ->get()
            ->keyBy('assigned_to');

        return $users->map(function (User $user) use ($stats) {
            $row = $stats->get($user->id);

            $completed = (int) ($row->completed ?? 0);
            $withDue = (int) ($row->with_due ?? 0);
            $onTime = (int) ($row->on_time ?? 0);
            $reopens = (int) ($row->reopens ?? 0);

            return [
                'user' => $user,
                'open' => (int) ($row->open_tasks ?? 0),
                'completed' => $completed,
                'onTimePercent' => $withDue > 0 ? (int) round($onTime / $withDue * 100) : null,
                'reopenRate' => $completed > 0 ? round($reopens / $completed * 100) : null,
            ];
        });
    }
}
