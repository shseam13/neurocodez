<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\AccountType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Confines each account type to its own application.
 *
 * This is the first of two locks on portal data. It keeps a client out of the
 * admin routes entirely; policies and model scopes then stop one client seeing
 * another's records. Route-level checks alone are not enough — but neither are
 * policies alone, and a wrong turn here is the cheapest one to prevent.
 */
class EnsureAccountType
{
    public function handle(Request $request, Closure $next, string ...$types): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $allowed = array_map(fn (string $t) => AccountType::from($t), $types);

        if (! in_array($user->type, $allowed, true)) {
            /*
             * Send them to their own home rather than showing 403.
             *
             * A client who bookmarked an admin URL, or followed a stale link,
             * has done nothing wrong — bouncing them home is both friendlier
             * and less informative about what exists on the other side.
             */
            return redirect()->route($user->type->homeRoute());
        }

        if (! $user->is_active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => __('That account has been deactivated.'),
            ]);
        }

        return $next($request);
    }
}
