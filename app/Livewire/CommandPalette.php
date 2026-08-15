<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Asset;
use App\Models\Client;
use App\Models\Task;
use App\Support\Permissions;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

/**
 * ⌘K on desktop, a long press on the Home tab on mobile.
 *
 * Searches only what the user is allowed to reach — an employee typing a
 * client name finds nothing they could not already open.
 */
class CommandPalette extends Component
{
    public string $query = '';

    public function clear(): void
    {
        $this->reset('query');
    }

    public function render(): View
    {
        return view('livewire.command-palette', [
            'results' => strlen(trim($this->query)) >= 2 ? $this->results() : collect(),
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function results(): Collection
    {
        $user = auth()->user();
        $term = trim($this->query);
        $results = collect();

        $results = $results->concat(
            Task::query()
                ->active()
                ->visibleTo($user)
                ->search($term)
                ->with('client:id,name,company_name')
                ->limit(6)
                ->get()
                ->map(fn (Task $task) => [
                    'group' => 'Tasks',
                    'icon' => 'list-checks',
                    'title' => $task->title,
                    'subtitle' => $task->reference.($task->client ? ' · '.$task->client->displayName() : ''),
                    'url' => route('tasks.show', $task),
                ])
        );

        if ($user->can(Permissions::VIEW_ASSETS)) {
            $results = $results->concat(
                Asset::query()
                    ->active()
                    ->search($term)
                    ->with('client:id,name,company_name')
                    ->limit(6)
                    ->get()
                    ->map(fn (Asset $asset) => [
                        'group' => 'Assets',
                        'icon' => $asset->type->icon(),
                        'title' => $asset->name,
                        'subtitle' => $asset->type->label().' · '.$asset->client->displayName().' · '.$asset->urgencyLabel(),
                        'url' => route('assets.show', $asset),
                    ])
            );
        }

        if ($user->can(Permissions::VIEW_CLIENTS)) {
            $results = $results->concat(
                Client::query()
                    ->active()
                    ->search($term)
                    ->limit(5)
                    ->get()
                    ->map(fn (Client $client) => [
                        'group' => 'Clients',
                        'icon' => 'building-2',
                        'title' => $client->displayName(),
                        'subtitle' => $client->email ?: $client->name,
                        'url' => route('clients.show', $client),
                    ])
            );
        }

        return $results->take(16)->values();
    }
}
