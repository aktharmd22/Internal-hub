<div class="px-4 lg:px-6 py-4 flex flex-col gap-4 max-w-3xl">

    @unless ($previewed)
        {{-- Mode switch --}}
        <div class="inline-flex rounded-control bg-surface-2 p-0.5 self-start" role="group" aria-label="Import method">
            @foreach (['csv' => 'From a CSV', 'paste' => 'Paste a list'] as $value => $label)
                <button
                    type="button"
                    wire:click="$set('mode', '{{ $value }}')"
                    aria-pressed="{{ $mode === $value ? 'true' : 'false' }}"
                    class="h-9 px-3.5 rounded-[7px] text-[13px] font-medium transition-colors
                        {{ $mode === $value ? 'bg-surface text-ink-950 shadow-float' : 'text-ink-600' }}"
                >{{ $label }}</button>
            @endforeach
        </div>

        @if ($mode === 'csv')
            <x-ui.card title="Import from a CSV" subtitle="Nothing is saved until you have seen the preview.">
                <div class="flex flex-col gap-4 mt-3">
                    <div class="rounded-control border border-ink-100 px-3.5 py-3">
                        <p class="t-sub text-ink-950 font-medium">Start from the template</p>
                        <p class="t-meta text-ink-600 mt-1">
                            Columns: {{ implode(', ', App\Services\Import\AssetCsvParser::COLUMNS) }}.
                            Dates read as d/m/Y or YYYY-MM-DD. Export a spreadsheet as CSV first.
                        </p>
                        <x-ui.button variant="secondary" size="sm" icon="file-text" wire:click="downloadTemplate" class="mt-3">
                            Download the template
                        </x-ui.button>
                    </div>

                    <div>
                        <label for="csv-file" class="t-sub font-medium text-ink-800">CSV file</label>
                        <input
                            id="csv-file"
                            type="file"
                            wire:model="file"
                            accept=".csv,text/csv"
                            class="mt-1.5 w-full t-sub text-ink-600 file:mr-3 file:rounded-control file:border file:border-ink-200 file:bg-surface file:px-3 file:py-2 file:t-sub file:text-ink-800"
                        >
                        @error('file')
                            <p class="t-meta text-danger-600 mt-1.5 flex items-center gap-1">
                                <x-icon name="circle-alert" class="size-3.5" />{{ $message }}
                            </p>
                        @enderror

                        <div wire:loading wire:target="file" class="t-meta text-ink-600 mt-2">Reading the file…</div>
                    </div>

                    <div>
                        <x-ui.button variant="primary" wire:click="previewCsv" target="previewCsv">
                            Preview the rows
                        </x-ui.button>
                    </div>
                </div>
            </x-ui.card>
        @else
            <x-ui.card title="Paste a list of domains" subtitle="One per line. We look each one up and fill in the expiry date.">
                <div class="flex flex-col gap-4 mt-3">
                    <x-ui.field
                        label="Client"
                        for="paste-client"
                        type="select"
                        required
                        placeholder="Choose a client"
                        :options="$clients->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])->all()"
                        wire:model="pasteClientId"
                        :error="$errors->first('pasteClientId')"
                    />

                    <x-ui.field
                        label="Domains"
                        for="paste-domains"
                        type="textarea"
                        rows="8"
                        placeholder="kanchisilks.com&#10;tvmlogistics.in&#10;https://www.anandtextiles.com"
                        hint="Up to 100 at a time. Prefixes, www and trailing slashes are all fine."
                        wire:model="pasted"
                        :error="$errors->first('pasted')"
                    />

                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input type="checkbox" wire:model="lookupExpiry" class="size-4 rounded border-ink-200 text-accent-600 focus:ring-accent-500">
                        <span class="min-w-0">
                            <span class="block t-sub text-ink-950">Look up expiry dates</span>
                            <span class="block t-meta text-ink-600">Queries the registry over RDAP. Slower, but you type nothing.</span>
                        </span>
                    </label>

                    <div>
                        <x-ui.button variant="primary" wire:click="previewPaste" target="previewPaste">
                            Look them up
                        </x-ui.button>
                        <span wire:loading wire:target="previewPaste" class="t-meta text-ink-600 ml-3">
                            Checking each domain with its registry…
                        </span>
                    </div>
                </div>
            </x-ui.card>
        @endif
    @else
        {{-- Preview ------------------------------------------------------ --}}
        <x-ui.card>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="t-section text-ink-950">{{ count($preview) }} {{ str('row')->plural(count($preview)) }} read</p>
                    <p class="t-sub text-ink-600 mt-0.5">
                        {{ $validCount }} will be imported · {{ $skipCount }} skipped
                    </p>
                </div>

                <div class="flex gap-2">
                    <x-ui.button variant="ghost" wire:click="startOver">Start over</x-ui.button>
                    <x-ui.button variant="primary" wire:click="commit" target="commit" :disabled="$validCount === 0">
                        Import {{ $validCount }} {{ str('asset')->plural($validCount) }}
                    </x-ui.button>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card :padding="false" :flush="true" title="Preview">
            <ul class="divide-y divide-ink-100">
                @foreach ($preview as $index => $row)
                    <li wire:key="row-{{ $index }}" class="flex items-start gap-3 px-4 py-3 {{ $row['skip'] ? 'opacity-60' : '' }}">
                        <button
                            type="button"
                            wire:click="toggleRow({{ $index }})"
                            class="shrink-0 mt-0.5 grid place-items-center size-5 rounded border transition-colors
                                {{ $row['skip'] ? 'border-ink-200 bg-surface' : 'border-accent-600 bg-accent-600 text-on-solid' }}"
                            :aria-pressed="{{ $row['skip'] ? 'false' : 'true' }}"
                            aria-label="{{ $row['skip'] ? 'Include this row' : 'Skip this row' }}"
                        >
                            @unless ($row['skip'])
                                <x-icon name="check" class="size-3.5" />
                            @endunless
                        </button>

                        <div class="min-w-0 flex-1">
                            <p class="t-body text-ink-950 truncate">
                                {{ $row['attributes']['name'] ?: '(no name)' }}
                            </p>
                            <p class="t-meta text-ink-600 mt-0.5 truncate">
                                Line {{ $row['line'] }}
                                @if ($row['attributes']['type']) · {{ App\Enums\AssetType::from($row['attributes']['type'])->label() }} @endif
                                @if ($row['attributes']['client_name']) · {{ $row['attributes']['client_name'] }} @endif
                                @if ($row['attributes']['expires_at']) · expires {{ \Illuminate\Support\Carbon::parse($row['attributes']['expires_at'])->format('j M Y') }} @endif
                            </p>

                            <div class="flex flex-wrap gap-1.5 mt-1.5">
                                @if ($row['duplicate'])
                                    <x-ui.badge tone="warn" size="sm">Already on this client</x-ui.badge>
                                @endif
                                @if ($row['newClient'])
                                    <x-ui.badge tone="accent" size="sm">Creates a new client</x-ui.badge>
                                @endif
                                @foreach ($row['errors'] as $error)
                                    <x-ui.badge tone="danger" size="sm">{{ $error }}</x-ui.badge>
                                @endforeach
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endunless
</div>
