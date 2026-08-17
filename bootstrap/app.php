<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Support\CloudflareProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
        ]);

        /*
         * The site sits behind Cloudflare, so the connecting address is always
         * an edge node. Trusting those ranges is what restores the visitor's
         * real IP for rate limiting and for the credential-vault access log,
         * and lets Laravel see that the request arrived over HTTPS.
         *
         * Specific ranges, never '*': the origin has a public IP of its own,
         * and trusting any proxy would let whoever finds it forge
         * X-Forwarded-For and put a false address in the audit trail.
         */
        $middleware->trustProxies(
            at: CloudflareProxies::ranges(),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
