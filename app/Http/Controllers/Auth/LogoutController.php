<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Livewire\Actions\Logout;
use Illuminate\Http\RedirectResponse;

class LogoutController extends Controller
{
    public function __invoke(Logout $logout): RedirectResponse
    {
        $logout();

        return redirect()->route('login');
    }
}
