<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'cacheable' => \App\Http\Middleware\SetCacheHeaders::class,
            'account' => \App\Http\Middleware\EnsureAccountType::class,
        ]);

        // Unauthenticated visitors go to the sign-in page, not to a route named
        // "login" that does not exist in this app's naming.
        $middleware->redirectGuestsTo(fn () => route('login'));

        /*
         * An already-signed-in visitor who hits /login goes to their OWN area.
         *
         * This is what lets the public header carry a single static "Sign in"
         * link: the markup stays identical for every visitor (so the CDN can
         * cache it), and the routing decision happens here instead — where a
         * session actually exists.
         *
         * Laravel's default target is /home, which does not exist in this app.
         */
        $middleware->redirectUsersTo(
            fn ($request) => route($request->user()->type->homeRoute())
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
