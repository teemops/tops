<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Run SetOrganizationContext before HandleInertiaRequests so shared props get organization_role from cookie
        $middleware->web(append: [
            \App\Http\Middleware\SetOrganizationContext::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Cookie set by frontend (current org) must be readable as plain text
        $middleware->encryptCookies(except: ['current_organization_id']);

        // Register API middleware aliases
        $middleware->alias([
            'firebase.auth' => \App\Http\Middleware\VerifyFirebaseToken::class,
            'organization.context' => \App\Http\Middleware\SetOrganizationContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
