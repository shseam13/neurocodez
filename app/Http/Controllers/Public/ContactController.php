<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('public.contact');
    }

    public function store(Request $request): RedirectResponse
    {
        /*
         * Honeypot. Real people never see or fill `website`; most bots fill
         * every field they find.
         *
         * We pretend it worked rather than showing an error — telling a bot it
         * was detected just teaches whoever wrote it to adapt.
         */
        if (filled($request->input('website'))) {
            return back()->with('sent', true);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:190'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ], [
            'message.min' => 'Please tell us a little more about what you need.',
        ]);

        // Either is fine, but with neither there is no way to reply.
        if (blank($data['email'] ?? null) && blank($data['phone'] ?? null)) {
            return back()
                ->withInput()
                ->withErrors(['email' => 'Please leave an email address or a phone number so we can reply.']);
        }

        Lead::create([
            ...$data,
            'source' => 'contact_form',
            'status' => 'new',
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        return back()->with('sent', true);
    }
}
