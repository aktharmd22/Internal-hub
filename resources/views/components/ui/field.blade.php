@props([
    'label' => null,
    'for' => null,
    'type' => 'text',
    'hint' => null,
    'error' => null,
    'required' => false,
    'options' => [],
    'placeholder' => null,
    'rows' => 4,
])

@php
    $id = $for ?? 'f-'.\Illuminate\Support\Str::random(6);
    $describedBy = collect([
        $hint ? "{$id}-hint" : null,
        $error ? "{$id}-error" : null,
    ])->filter()->implode(' ');

    $control = 'w-full rounded-control border bg-surface text-ink-950 placeholder:text-ink-400 transition-colors '
        .($error ? 'border-danger-600' : 'border-ink-200 hover:border-ink-400');

    $height = 'h-11 md:h-10 px-3';
@endphp

<div {{ $attributes->class('flex flex-col gap-1.5') }}>
    @if ($label)
        <label for="{{ $id }}" class="t-sub font-medium text-ink-800">
            {{ $label }}
            @if ($required)
                <span class="text-danger-600" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    @if (trim($slot) !== '')
        {{ $slot }}
    @elseif ($type === 'textarea')
        <textarea
            id="{{ $id }}"
            rows="{{ $rows }}"
            placeholder="{{ $placeholder }}"
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->except(['class'])->class("{$control} px-3 py-2.5") }}
        ></textarea>
    @elseif ($type === 'select')
        <div class="relative">
            <select
                id="{{ $id }}"
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                @if ($error) aria-invalid="true" @endif
                {{ $attributes->except(['class'])->class("{$control} {$height} appearance-none pr-9") }}
            >
                @if ($placeholder)
                    <option value="">{{ $placeholder }}</option>
                @endif
                @foreach ($options as $value => $text)
                    <option value="{{ $value }}">{{ $text }}</option>
                @endforeach
            </select>
            <x-icon name="chevron-down" class="size-4 text-ink-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
        </div>
    @elseif ($type === 'password')
        {{-- Every password field gets a reveal. Typing a long password blind
             on a phone keyboard is how people end up choosing a short one. --}}
        <div class="relative" x-data="{ show: false }">
            <input
                id="{{ $id }}"
                type="password"
                x-bind:type="show ? 'text' : 'password'"
                placeholder="{{ $placeholder }}"
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                @if ($error) aria-invalid="true" @endif
                {{ $attributes->except(['class'])->class("{$control} {$height} pr-11") }}
            >

            <button
                type="button"
                x-on:click="show = ! show"
                x-bind:aria-label="show ? 'Hide password' : 'Show password'"
                x-bind:aria-pressed="show"
                tabindex="-1"
                class="absolute right-0 inset-y-0 w-11 grid place-items-center rounded-r-control text-ink-400 hover:text-ink-800 transition-colors"
            >
                <x-icon name="eye" class="size-[18px]" x-show="! show" />
                <x-icon name="eye-off" class="size-[18px]" x-show="show" x-cloak />
            </button>
        </div>
    @else
        <input
            id="{{ $id }}"
            type="{{ $type }}"
            placeholder="{{ $placeholder }}"
            @if ($type === 'number') inputmode="numeric" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->except(['class'])->class("{$control} {$height}") }}
        >
    @endif

    @if ($hint && ! $error)
        <p id="{{ $id }}-hint" class="t-meta text-ink-600">{{ $hint }}</p>
    @endif

    @if ($error)
        <p id="{{ $id }}-error" class="t-meta text-danger-600 flex items-center gap-1">
            <x-icon name="circle-alert" class="size-3.5" />
            {{ $error }}
        </p>
    @endif
</div>
