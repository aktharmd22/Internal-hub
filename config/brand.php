<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Where the logo lives
    |--------------------------------------------------------------------------
    |
    | The company logo is served straight from the web root rather than through
    | `storage:link`. That symlink is one of the first things to break on shared
    | hosting, and a broken symlink means a missing logo on every screen.
    |
    | This is a setting rather than a hardcoded `public_path()` so the test
    | suite can point it at a temporary directory. Tests that write to the real
    | public/ directory end up deleting the customer's artwork.
    |
    */

    'path' => public_path(),

    /*
    | Subdirectory used for logos uploaded through Settings. Files dropped in
    | at the root as logo.png are picked up too, and are the fallback.
    */

    'directory' => 'brand',

];
