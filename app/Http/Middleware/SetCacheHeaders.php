<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CDN cache headers for public pages.
 *
 * Render's free tier sleeps after ~15 minutes idle. For the internal app that
 * is a shrug; for a public marketing site a 30-60s cold start loses the
 * visitor and drags on search ranking. With Cloudflare in front honouring
 * s-maxage, visitors are served from the edge and the origin being asleep stops
 * mattering.
 */
class SetCacheHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Never cache a personalised or non-GET response. Caching an
        // authenticated page at a shared CDN would serve one visitor's page to
        // another.
        // HEAD is included: CDNs and monitors issue HEAD requests, and they
        // are just as cacheable as the GET they mirror.
        if (! $request->isMethodCacheable() || auth()->check() || ($request->hasSession() && session()->has('errors'))) {
            return $response;
        }

        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        /*
         * max-age=0 for BROWSERS, s-maxage=3600 for the CDN. This split matters.
         *
         * HTML references hashed asset filenames (app-tC7pdS9e.css). Deploy new
         * assets and any browser still holding cached HTML will request the old
         * filenames, which no longer exist — an unstyled site for the whole
         * max-age window. A CDN can be purged on deploy; a visitor's browser
         * cannot, so browsers revalidate every time.
         *
         * That revalidation is cheap: it hits the CDN edge, not the sleeping
         * origin, and returns 304 when nothing changed. stale-while-revalidate
         * lets the edge serve immediately while it refreshes in the background.
         */
        $response->headers->set(
            'Cache-Control',
            'public, max-age=0, must-revalidate, s-maxage=3600, stale-while-revalidate=86400'
        );

        // Tell the CDN the response varies by nothing user-specific, so it can
        // serve one copy to everyone.
        $response->headers->set('Vary', 'Accept-Encoding');

        return $response;
    }
}
