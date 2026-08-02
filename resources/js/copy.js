/**
 * Copy-to-clipboard buttons.
 *
 * Used for invitation links, which are currently delivered by hand — the host
 * blocks outbound SMTP, so the link is copied out of the admin and sent over
 * WhatsApp or email personally.
 *
 * Delegated from the document so links rendered after load still work.
 */

const RESET_AFTER_MS = 2000;

async function copy(text, field) {
    // navigator.clipboard needs a secure context. Production is HTTPS and
    // localhost counts as secure, but a plain-HTTP staging host would not — so
    // fall back to selecting the text for a manual Ctrl+C rather than failing
    // with nothing on screen.
    if (navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch {
            // Permission denied, or the document was not focused. Fall through.
        }
    }

    field?.focus();
    field?.select();

    return false;
}

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy-target]');
    if (!button) {
        return;
    }

    event.preventDefault();

    const field = document.querySelector(button.dataset.copyTarget);
    if (!field) {
        return;
    }

    const copied = await copy(field.value, field);

    if (button.dataset.resetting === '1') {
        return;
    }

    const original = button.textContent;
    button.dataset.resetting = '1';
    button.textContent = copied ? 'Copied' : 'Press Ctrl+C';

    setTimeout(() => {
        button.textContent = original;
        delete button.dataset.resetting;
    }, RESET_AFTER_MS);
});
