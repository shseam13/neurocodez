<?php

declare(strict_types=1);

return [
    /*
     * Default currency. Money is stored in minor units (poisha) as integers,
     * never floats — see App\Support\Money.
     */
    'currency' => env('NEURO_CURRENCY', 'BDT'),

    /*
     * Whether invoices render the ৳ glyph or the ISO code.
     *
     * dompdf's bundled DejaVu fonts contain NO Bengali script, so U+09F3 comes
     * out as an empty box unless a Bengali-capable font (Noto Sans Bengali) has
     * been registered with dompdf. Leave this false until that font is wired
     * up — a broken currency symbol on a client invoice is not a small bug.
     */
    'use_taka_glyph' => env('NEURO_TAKA_GLYPH', false),

    /*
     * Default accrual basis for new projects. Each project still owns its own,
     * because real referral deals differ.
     *
     *   collected — commission accrues only as the client actually pays (safe)
     *   agreed    — full commission owed the moment the project is signed
     */
    'default_commission_basis' => env('NEURO_COMMISSION_BASIS', 'collected'),

    'youtube' => [
        /*
         * Channel id, @handle, or full channel URL — an @handle is easiest and
         * gets resolved to the UC… id once, then cached for 30 days.
         *
         * Videos are mirrored into the local `videos` table by `youtube:sync`,
         * so pages never call YouTube during a request.
         */
        'channel' => env('NEURO_YOUTUBE_CHANNEL'),
    ],

    'site' => [
        'tagline' => env('NEURO_TAGLINE', 'Connect . Create . Serve'),
        'description' => env(
            'NEURO_DESCRIPTION',
            'Neuro Codez builds websites, web applications and brand identities — and teaches the craft on YouTube.'
        ),
        'email' => env('NEURO_PUBLIC_EMAIL'),
        'phone' => env('NEURO_PUBLIC_PHONE'),
        'whatsapp' => env('NEURO_WHATSAPP'),
        // Order here is the order they render in the footer.
        'social' => [
            'youtube' => env('NEURO_SOCIAL_YOUTUBE'),
            'facebook' => env('NEURO_SOCIAL_FACEBOOK'),
            'instagram' => env('NEURO_SOCIAL_INSTAGRAM'),
            'github' => env('NEURO_SOCIAL_GITHUB'),
            'linkedin' => env('NEURO_SOCIAL_LINKEDIN'),
        ],
    ],

    'invoice' => [
        'page_size' => 'a4',
        // Consumer printers cannot print to the edge; roughly the outer 5mm is
        // unprintable. The letterhead stays inside the margins so it prints
        // identically on every printer instead of being clipped.
        'margin_mm' => ['top' => 14, 'right' => 14, 'bottom' => 18, 'left' => 14],
        'due_days' => env('NEURO_INVOICE_DUE_DAYS', 14),
    ],
];
