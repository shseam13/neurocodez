<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreferenceController extends Controller
{
    /**
     * Persist the theme against the account so the choice follows the user to
     * any device. localStorage already handles the current one.
     */
    public function theme(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'in:light,dark'],
        ]);

        // saveQuietly: a theme toggle is not a content change and must not
        // bump updated_at or fire model events.
        $request->user()->forceFill(['theme_pref' => $validated['theme']])->saveQuietly();

        return response()->json(['theme' => $validated['theme']]);
    }
}
