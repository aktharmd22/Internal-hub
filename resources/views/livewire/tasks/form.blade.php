<div>
    <x-ui.modal
        name="task-form"
        :title="$taskId ? 'Edit task' : ($parent_id ? 'Add a subtask' : 'New task')"
        width="lg"
    >
        <form wire:submit="save" class="flex flex-col gap-4" id="task-form-element">
            <x-ui.field
                label="What needs doing"
                for="task-title"
                required
                placeholder="Move DNS to Cloudflare"
                wire:model="title"
                :error="$errors->first('title')"
            />

            <x-ui.field
                label="Details"
                for="task-description"
                type="textarea"
                rows="4"
                placeholder="Anything the person doing this needs to know"
                wire:model="description"
                :error="$errors->first('description')"
            />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <x-ui.field
                    label="Client"
                    for="task-client"
                    type="select"
                    placeholder="No client"
                    :options="$clients->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])->all()"
                    wire:model.live="client_id"
                    :error="$errors->first('client_id')"
                />

                <x-ui.field
                    label="Project"
                    for="task-project"
                    type="select"
                    placeholder="No project"
                    :options="$projects->pluck('name', 'id')->all()"
                    wire:model="project_id"
                    :error="$errors->first('project_id')"
                />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <x-ui.field
                    label="Assign to"
                    for="task-assignee"
                    type="select"
                    placeholder="Nobody yet"
                    :options="$users->pluck('name', 'id')->all()"
                    wire:model="assigned_to"
                    :error="$errors->first('assigned_to')"
                />

                <x-ui.field
                    label="Priority"
                    for="task-priority"
                    type="select"
                    :options="App\Enums\TaskPriority::options()"
                    wire:model="priority"
                    :error="$errors->first('priority')"
                />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <x-ui.field
                    label="Due date"
                    for="task-due"
                    type="date"
                    hint="Falls due at 5pm."
                    wire:model="due_at"
                    :error="$errors->first('due_at')"
                />

                <x-ui.field
                    label="Estimate (minutes)"
                    for="task-estimate"
                    type="number"
                    placeholder="60"
                    wire:model="estimated_minutes"
                    :error="$errors->first('estimated_minutes')"
                />
            </div>
        </form>

        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'task-form')">Cancel</x-ui.button>
            <x-ui.button variant="primary" type="submit" form="task-form-element" target="save">
                {{ $taskId ? 'Save changes' : 'Create task' }}
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
