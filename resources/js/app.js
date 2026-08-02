import './video-modal.js';
import './markdown-editor.js';
import './form-submit.js';
import './copy.js';

/**
 * Theme control.
 *
 * The initial theme is resolved by the inline script in the layout <head>, so
 * this file only handles switching after load. It deliberately does not resolve
 * the theme on boot — doing that here would run after first paint and flash.
 */

const STORAGE_KEY = 'nc-theme';

function currentTheme() {
    return document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
}

function applyTheme(theme) {
    const next = theme === 'light' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);

    try {
        localStorage.setItem(STORAGE_KEY, next);
    } catch {
        // Private mode or blocked storage — the theme still applies for this page.
    }

    // Persist against the account so the choice follows the user to any device.
    // Failure is non-fatal: localStorage already carries it on this one.
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (token && document.body.dataset.authenticated === '1') {
        fetch('/preferences/theme', {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                Accept: 'application/json',
            },
            body: JSON.stringify({ theme: next }),
        }).catch(() => {});
    }

    document.dispatchEvent(new CustomEvent('theme:changed', { detail: { theme: next } }));
}

function toggleTheme() {
    applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
}

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-theme-toggle]')) {
        event.preventDefault();
        toggleTheme();
    }
});

window.NeuroTheme = { current: currentTheme, apply: applyTheme, toggle: toggleTheme };

/**
 * PWA registration.
 *
 * Only over HTTPS or on localhost — browsers refuse a service worker anywhere
 * else, and attempting it just logs a confusing error.
 */
if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js').catch(() => {
            // A failed registration must never break the page; the app works
            // perfectly without it.
        });
    });
}
