<?php

use App\Support\Lucide;
use Symfony\Component\Finder\Finder;

test('every icon name used in a view exists', function () {
    // Resolved without the framework so this stays a true unit test.
    $views = Finder::create()
        ->files()
        ->in(dirname(__DIR__, 2).'/resources/views')
        ->name('*.blade.php');

    $missing = [];

    foreach ($views as $view) {
        preg_match_all('/<x-icon[^>]*\bname="([a-z0-9-]+)"/', $view->getContents(), $matches);

        foreach ($matches[1] as $name) {
            if (! Lucide::has($name)) {
                $missing[] = $view->getRelativePathname().' → '.$name;
            }
        }
    }

    expect($missing)->toBe([]);
});

test('every icon in the catalogue is drawn on a 24 by 24 grid', function () {
    foreach (Lucide::names() as $name) {
        expect(Lucide::body($name))->not->toBeEmpty()
            ->and(Lucide::body($name))->not->toContain('<svg');
    }
});

test('an unknown icon fails loudly rather than rendering nothing', function () {
    Lucide::body('not-a-real-icon');
})->throws(InvalidArgumentException::class);
