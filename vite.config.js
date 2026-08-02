import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // Self-hosted, not a CDN link: the app must render correctly
            // offline as a PWA, and no third party needs our users' IPs.
            fonts: [
                bunny('Inter', {
                    weights: [400, 500, 600, 700],
                }),
                // Inter carries no Bengali script. Without this, Bengali video
                // titles and copy fall back to whatever the device happens to
                // have, so the site looks different on every machine. This is
                // also the font that fixes the ৳ (U+09F3) glyph in invoices —
                // dompdf's bundled DejaVu fonts have no Bengali either.
                bunny('Noto Sans Bengali', {
                    weights: [400, 500, 600, 700],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
