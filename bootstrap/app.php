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
        /*
         * TLS terminates at the edge (Render, and Cloudflare in front of it) and
         * reaches this container over plain HTTP. Without trusting the forwarded
         * headers, $request->isSecure() is false and every asset() — so every
         * @vite tag — is emitted as http:// on an https:// page. The browser
         * blocks that as mixed content and the site renders with no CSS and no
         * JS. Signed invitation URLs break the same way, since the signature
         * covers the scheme.
         *
         * '*' is safe here only because the container has no public address of
         * its own: the edge is the only route in, so nothing can reach us with a
         * forged X-Forwarded-Proto. That stops being true on cPanel, where
         * requests arrive at Apache directly — narrow this to the server's own
         * IP when you move. See DEPLOYMENT.md.
         *
         * Set explicitly rather than via env(): this closure runs on
         * afterResolving(HttpKernel), which is before .env is loaded, so an
         * env() here would read null locally and silently do nothing.
         */
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

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
