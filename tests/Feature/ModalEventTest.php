<?php

use Symfony\Component\Finder\Finder;

/**
 * Livewire and Alpine deliver the same event with different payload shapes:
 *
 *   Alpine   $dispatch('open-modal', 'user-form')  → detail === 'user-form'
 *   Livewire $this->dispatch('open-modal', 'x')    → detail === ['user-form']
 *
 * livewire.js wraps every dispatched param in an array. A modal comparing
 * `$event.detail === name` therefore opens for Alpine and silently ignores
 * everything sent from PHP — the button fires, the event lands, nothing shows.
 *
 * Every modal opened from a component method was dead for that reason: Add
 * person, Add credential, the reminder-rule editor, the task form, the
 * status-reason sheet and every Edit action.
 */
test('the modal unwraps the payload instead of comparing detail directly', function () {
    $modal = file_get_contents(resource_path('views/components/ui/modal.blade.php'));

    expect($modal)->toContain('$modalTarget($event)')
        // The naive comparison is what broke it.
        ->and($modal)->not->toContain('$event.detail === name');
});

test('the unwrapping helper is registered', function () {
    $ui = file_get_contents(resource_path('js/ui.js'));

    expect($ui)->toContain("Alpine.magic('modalTarget'")
        ->and($ui)->toContain('Array.isArray(detail)');
});

/**
 * A dispatch naming a modal that does not exist fails silently, which is
 * indistinguishable from the bug above.
 */
test('every modal opened from php has a modal with that name', function () {
    $dispatched = [];

    foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
        preg_match_all(
            "/dispatch\(\s*'open-modal'\s*,\s*'([^']+)'/",
            $file->getContents(),
            $found,
        );

        foreach ($found[1] as $name) {
            $dispatched[$name] = $file->getRelativePathname();
        }
    }

    expect($dispatched)->not->toBeEmpty();

    $markup = '';

    foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $view) {
        $markup .= $view->getContents();
    }

    $missing = [];

    foreach ($dispatched as $name => $file) {
        if (! str_contains($markup, "name=\"{$name}\"")) {
            $missing[] = "{$file} opens '{$name}' but no modal declares that name";
        }
    }

    expect($missing)->toBe([]);
});

test('the same holds for modals opened from markup', function () {
    $dispatched = [];

    foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $view) {
        preg_match_all(
            "/dispatch\(\s*'open-modal'\s*,\s*'([^']+)'/",
            $view->getContents(),
            $found,
        );

        foreach ($found[1] as $name) {
            $dispatched[$name] = $view->getRelativePathname();
        }
    }

    $markup = '';

    foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $view) {
        $markup .= $view->getContents();
    }

    $missing = [];

    foreach ($dispatched as $name => $file) {
        if (! str_contains($markup, "name=\"{$name}\"")) {
            $missing[] = "{$file} opens '{$name}' but no modal declares that name";
        }
    }

    expect($missing)->toBe([]);
});
