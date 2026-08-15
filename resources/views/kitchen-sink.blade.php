<x-layouts.app title="Kitchen sink">
    <x-slot:actions>
        <div x-data>
            <x-app.theme-toggle compact />
        </div>
    </x-slot:actions>

    <div class="px-4 lg:px-6 py-4 lg:py-6 flex flex-col gap-4 max-w-3xl">

        {{-- Typography ------------------------------------------------------ --}}
        <x-ui.card title="Type scale" subtitle="DM Sans at 400, 500 and 700 only.">
            <div class="flex flex-col gap-3 mt-3">
                <div>
                    <p class="t-meta text-ink-400 mb-0.5">Page title · 20 / 24 · 700</p>
                    <p class="t-page-title text-ink-950">Renewals due this week</p>
                </div>
                <div>
                    <p class="t-meta text-ink-400 mb-0.5">Section heading · 16 / 17 · 500</p>
                    <p class="t-section text-ink-950">Expiring soon</p>
                </div>
                <div>
                    <p class="t-meta text-ink-400 mb-0.5">Body · 15 / 14 · 400</p>
                    <p class="t-body text-ink-950">kanchisilks.com renews with GoDaddy</p>
                </div>
                <div>
                    <p class="t-meta text-ink-400 mb-0.5">Secondary · 13 · 400</p>
                    <p class="t-sub text-ink-600">Owned by Vignesh · Auto-renew off</p>
                </div>
                <div>
                    <p class="t-meta text-ink-400 mb-0.5">Caption · 12 · 400</p>
                    <p class="t-meta text-ink-600">Last verified 2 hours ago</p>
                </div>
                <div>
                    <p class="t-meta text-ink-400 mb-0.5">Metric · 26 / 30 · 700 · tabular</p>
                    <p class="t-metric text-ink-950">₹1,84,250</p>
                </div>
            </div>
        </x-ui.card>

        {{-- Metric cards ---------------------------------------------------- --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach ([
                ['label' => 'Expiring in 7 days', 'value' => '4', 'tone' => 'danger'],
                ['label' => 'Expiring in 30 days', 'value' => '17', 'tone' => 'warn'],
                ['label' => 'Open tasks', 'value' => '23', 'tone' => 'neutral'],
                ['label' => 'Awaiting review', 'value' => '5', 'tone' => 'accent'],
            ] as $metric)
                <x-ui.card>
                    <p class="t-metric text-ink-950">{{ $metric['value'] }}</p>
                    <p class="t-sub text-ink-600 mt-1">{{ $metric['label'] }}</p>
                </x-ui.card>
            @endforeach
        </div>

        {{-- Buttons --------------------------------------------------------- --}}
        <x-ui.card title="Buttons" subtitle="One filled button per screen. Everything else outlines.">
            <div class="flex flex-wrap items-center gap-2 mt-3">
                <x-ui.button variant="primary" icon="plus">Add asset</x-ui.button>
                <x-ui.button variant="secondary">Cancel</x-ui.button>
                <x-ui.button variant="ghost" icon="refresh-cw">Refresh</x-ui.button>
                <x-ui.button variant="danger" icon="trash-2">Delete</x-ui.button>
            </div>

            <div class="flex flex-wrap items-center gap-2 mt-3">
                <x-ui.button variant="primary" size="sm">Renew domain</x-ui.button>
                <x-ui.button variant="secondary" size="sm" icon="pencil">Edit</x-ui.button>
                <x-ui.button variant="secondary" size="sm" disabled>Disabled</x-ui.button>
                <x-ui.button variant="secondary" size="sm" iconTrailing="chevron-right">Next</x-ui.button>
            </div>
        </x-ui.card>

        {{-- Badges ---------------------------------------------------------- --}}
        <x-ui.card title="Status badges" subtitle="Colour never carries meaning alone — the label always does too.">
            <div class="flex flex-wrap items-center gap-2 mt-3">
                <x-ui.badge tone="danger" dot>Overdue</x-ui.badge>
                <x-ui.badge tone="warn" dot>Due in 5 days</x-ui.badge>
                <x-ui.badge tone="ok" dot>Renewed</x-ui.badge>
                <x-ui.badge tone="accent">In progress</x-ui.badge>
                <x-ui.badge tone="neutral">Draft</x-ui.badge>
                <x-ui.badge tone="neutral" size="sm">TSK-0142</x-ui.badge>
            </div>
        </x-ui.card>

        {{-- List rows ------------------------------------------------------- --}}
        <x-ui.card title="List rows" :padding="false" :flush="true">
            <div class="divide-y divide-ink-100">
                <x-ui.list-row
                    href="#"
                    icon="globe"
                    title="kanchisilks.com"
                    subtitle="Domain · GoDaddy · Kanchi Silks"
                >
                    <x-slot:trailing>
                        <x-ui.badge tone="danger" dot>2 days</x-ui.badge>
                    </x-slot:trailing>
                </x-ui.list-row>

                <x-ui.list-row
                    href="#"
                    icon="shield-check"
                    title="SSL · api.tvmlogistics.in"
                    subtitle="Let's Encrypt · TVM Logistics"
                >
                    <x-slot:trailing>
                        <x-ui.badge tone="warn" dot>5 days</x-ui.badge>
                    </x-slot:trailing>
                </x-ui.list-row>

                <x-ui.list-row
                    href="#"
                    title="Migrate staging to PHP 8.3"
                    subtitle="Assigned to Divya · Due Friday"
                >
                    <x-slot:leading>
                        <x-ui.avatar name="Divya Nair" :id="3" />
                    </x-slot:leading>
                    <x-slot:trailing>
                        <x-ui.badge tone="accent">In progress</x-ui.badge>
                    </x-slot:trailing>
                </x-ui.list-row>
            </div>
        </x-ui.card>

        {{-- Swipe row ------------------------------------------------------- --}}
        <x-ui.card title="Swipe to reveal" subtitle="Touch only. The same action lives in the overflow menu." :padding="false" :flush="true">
            <div class="relative overflow-hidden" x-data="swipeRow(112)">
                <div class="absolute inset-y-0 right-0 w-28 flex items-stretch">
                    <button type="button" class="flex-1 bg-ok-600 text-on-solid t-sub font-medium" x-on:click="close()">
                        Mark done
                    </button>
                </div>

                <div
                    class="relative bg-surface touch-pan-y"
                    x-bind:style="`transform: translateX(${offset}px)`"
                    x-bind:class="tracking ? '' : 'transition-transform duration-200'"
                    x-on:pointerdown="start($event)"
                    x-on:pointermove="move($event)"
                    x-on:pointerup="end()"
                    x-on:pointercancel="end()"
                >
                    <x-ui.list-row
                        title="Renew hosting for Anand Textiles"
                        subtitle="Due tomorrow · High priority"
                        icon="server"
                        :chevron="false"
                    >
                        <x-slot:trailing>
                            <x-ui.dropdown align="right" width="w-48">
                                <x-slot:trigger>
                                    <button type="button" class="tap grid place-items-center rounded-control text-ink-400 hover:bg-surface-2">
                                        <x-icon name="ellipsis-vertical" class="size-5" label="More actions" />
                                    </button>
                                </x-slot:trigger>
                                <x-ui.dropdown-item icon="check">Mark done</x-ui.dropdown-item>
                                <x-ui.dropdown-item icon="refresh-cw">Renew</x-ui.dropdown-item>
                                <x-ui.dropdown-item icon="trash-2" tone="danger">Delete</x-ui.dropdown-item>
                            </x-ui.dropdown>
                        </x-slot:trailing>
                    </x-ui.list-row>
                </div>
            </div>
        </x-ui.card>

        {{-- Avatars --------------------------------------------------------- --}}
        <x-ui.card title="Avatars" subtitle="Initials, with a colour derived from the user id.">
            <div class="flex flex-wrap items-center gap-2 mt-3">
                @foreach ([
                    1 => 'Aarthi Ramesh',
                    2 => 'Vignesh Kumar',
                    3 => 'Divya Nair',
                    4 => 'Suresh Babu',
                    5 => 'Meera Iyer',
                ] as $id => $person)
                    <x-ui.avatar :name="$person" :id="$id" />
                @endforeach

                <x-ui.avatar name="Aarthi Ramesh" :id="1" size="sm" />
                <x-ui.avatar name="Aarthi Ramesh" :id="1" size="lg" />
            </div>
        </x-ui.card>

        {{-- Fields ---------------------------------------------------------- --}}
        <x-ui.card title="Form fields">
            <div class="flex flex-col gap-4 mt-3">
                <x-ui.field label="Domain" for="ks-domain" placeholder="example.com" hint="Without http:// or www." />

                <x-ui.field
                    label="Expires on"
                    for="ks-expiry"
                    type="date"
                    hint="Stored as a date, never a timestamp."
                />

                <x-ui.field
                    label="Cost"
                    for="ks-cost"
                    type="number"
                    placeholder="1200"
                    hint="Numeric keypad opens on mobile."
                />

                <x-ui.field
                    label="Asset type"
                    for="ks-type"
                    type="select"
                    placeholder="Choose a type"
                    :options="['domain' => 'Domain', 'hosting' => 'Hosting', 'ssl' => 'SSL certificate', 'vps' => 'VPS']"
                />

                <x-ui.field label="Notes" for="ks-notes" type="textarea" placeholder="Anything the next person needs to know" />

                <x-ui.field
                    label="Registrar login"
                    for="ks-error"
                    error="Enter the registrar this domain is held with."
                />
            </div>
        </x-ui.card>

        {{-- Modal, dropdown, toasts ----------------------------------------- --}}
        <x-ui.card title="Overlays" subtitle="Bottom sheet on a phone, centred dialog on desktop.">
            <div class="flex flex-wrap items-center gap-2 mt-3" x-data>
                <x-ui.button variant="secondary" x-on:click="$dispatch('open-modal', 'ks-demo')">
                    Open modal
                </x-ui.button>

                <x-ui.dropdown align="left">
                    <x-slot:trigger>
                        <x-ui.button variant="secondary" iconTrailing="chevron-down">Dropdown</x-ui.button>
                    </x-slot:trigger>
                    <x-ui.dropdown-item icon="pencil">Edit asset</x-ui.dropdown-item>
                    <x-ui.dropdown-item icon="refresh-cw">Mark renewed</x-ui.dropdown-item>
                    <x-ui.dropdown-item icon="trash-2" tone="danger">Archive</x-ui.dropdown-item>
                </x-ui.dropdown>

                <x-ui.button
                    variant="ghost"
                    x-on:click="$store.toasts.push({ message: 'Domain renewed for 1 year.', tone: 'ok' })"
                >
                    Fire a toast
                </x-ui.button>

                <x-ui.button
                    variant="ghost"
                    x-on:click="$store.toasts.push({ message: 'Could not reach the registry. Retrying.', tone: 'danger' })"
                >
                    Error toast
                </x-ui.button>
            </div>

            <x-ui.modal name="ks-demo" title="Renew domain" subtitle="kanchisilks.com · Kanchi Silks">
                <div class="flex flex-col gap-4">
                    <x-ui.field label="New expiry date" for="ks-modal-date" type="date" />
                    <x-ui.field label="Amount paid" for="ks-modal-amount" type="number" placeholder="1200" />
                    <x-ui.field label="Note" for="ks-modal-note" type="textarea" rows="3" />
                </div>

                <x-slot:footer>
                    <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'ks-demo')">Cancel</x-ui.button>
                    <x-ui.button variant="primary">Renew domain</x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        </x-ui.card>

        {{-- Empty state ----------------------------------------------------- --}}
        <x-ui.card :padding="false">
            <x-ui.empty-state
                icon="calendar-clock"
                headline="No renewals due in the next 30 days"
                body="Everything on the books is paid up. New assets show here as their dates approach."
            >
                <x-ui.button variant="primary" icon="plus">Add an asset</x-ui.button>
            </x-ui.empty-state>
        </x-ui.card>

        {{-- Skeletons ------------------------------------------------------- --}}
        <x-ui.card title="Loading" subtitle="Shown through wire:loading while a list resolves." :padding="false" :flush="true">
            <div class="divide-y divide-ink-100">
                @for ($i = 0; $i < 3; $i++)
                    <div class="flex items-center gap-3 px-4 min-h-16 md:min-h-13">
                        <x-ui.skeleton shape="avatar" />
                        <div class="flex-1 flex flex-col gap-2">
                            <x-ui.skeleton shape="title" />
                            <x-ui.skeleton class="w-3/5" />
                        </div>
                        <x-ui.skeleton shape="chip" />
                    </div>
                @endfor
            </div>
        </x-ui.card>

    </div>
</x-layouts.app>
