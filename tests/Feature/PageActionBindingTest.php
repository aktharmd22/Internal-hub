<?php

use Symfony\Component\Finder\Finder;

/**
 * Page actions are pushed into the topbar with @push('page-actions'). The
 * topbar is rendered by the layout, which sits OUTSIDE the Livewire component's
 * root element — and Livewire only binds wire: directives within a component's
 * own DOM.
 *
 * So a `wire:click` in one of those blocks compiles, renders, looks completely
 * correct, and does nothing at all when clicked. Seventeen buttons across seven
 * screens shipped that way, because every test called the methods directly
 * rather than going through the markup.
 *
 * The working pattern is `x-on:click="Livewire.dispatch('some:event')"` paired
 * with a `#[On('some:event')]` listener on the component.
 */
function pageActionBlocks(): array
{
    $blocks = [];

    $views = Finder::create()
        ->files()
        ->in(resource_path('views'))
        ->name('*.blade.php');

    foreach ($views as $view) {
        $contents = $view->getContents();

        if (! preg_match_all("/@push\('page-actions'\)(.*?)@endpush/s", $contents, $matches)) {
            continue;
        }

        foreach ($matches[1] as $block) {
            $blocks[] = ['file' => $view->getRelativePathname(), 'body' => $block];
        }
    }

    return $blocks;
}

test('there are page-action blocks to check', function () {
    expect(pageActionBlocks())->not->toBeEmpty();
});

test('no wire: directive is pushed into the topbar, where it cannot bind', function () {
    $offenders = [];

    foreach (pageActionBlocks() as $block) {
        // wire:navigate is a link attribute handled globally by Livewire's
        // navigate feature, not a component binding, so it works out there.
        preg_match_all('/\bwire:(?!navigate)[a-z.]+=/', $block['body'], $found);

        foreach ($found[0] as $directive) {
            $offenders[] = "{$block['file']} → {$directive}";
        }
    }

    expect($offenders)->toBe([]);
});

test('every event a page action dispatches has a listener behind it', function () {
    $dispatched = [];

    foreach (pageActionBlocks() as $block) {
        preg_match_all("/Livewire\.dispatch\(\s*'([^']+)'/", $block['body'], $found);

        foreach ($found[1] as $event) {
            $dispatched[$event] = $block['file'];
        }
    }

    expect($dispatched)->not->toBeEmpty();

    // Listeners may live on the page component or on a sibling like a form
    // modal, so the whole app directory is searched.
    $source = '';

    foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
        $source .= $file->getContents();
    }

    $missing = [];

    foreach ($dispatched as $event => $file) {
        if (! str_contains($source, "#[On('{$event}')]")) {
            $missing[] = "{$file} dispatches '{$event}' but nothing listens for it";
        }
    }

    expect($missing)->toBe([]);
});
