<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Support\Permissions;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

class ActivityFeed extends Component
{
    public int $limit = 20;

    public function render(): View
    {
        abort_unless(auth()->user()->can(Permissions::VIEW_ACTIVITY_LOG), 403);

        return view('livewire.activity-feed', ['entries' => $this->entries()]);
    }

    /** @return Collection<int, Activity> */
    private function entries(): Collection
    {
        return Activity::query()
            ->with('causer:id,name')
            ->latest()
            ->limit($this->limit)
            ->get();
    }
}
