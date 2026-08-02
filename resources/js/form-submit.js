/**
 * Submit-button busy state.
 *
 * Two jobs: tell the user something is happening, and make a second click
 * impossible. The second matters more than it looks — an impatient double-click
 * on "Send invitation" sends two invitations, and on "Add payment" records the
 * same money twice.
 *
 * Progressive enhancement: without JavaScript every form still submits
 * normally, just without the spinner.
 */

const BUSY_LABEL = 'data-busy-label';

/**
 * Forms opt out with `data-no-busy` — useful for anything that submits into a
 * new tab, where the page never navigates and the button would stay stuck.
 */
function findSubmitButton(form) {
    return form.querySelector('button[type="submit"], button:not([type]), input[type="submit"]');
}

function markBusy(button) {
    if (button.dataset.busy === '1') {
        return;
    }

    button.dataset.busy = '1';

    // Freeze the rendered width before swapping the label, so a shorter
    // "Sending…" does not make the button visibly shrink mid-click.
    const { width } = button.getBoundingClientRect();
    button.style.minWidth = `${Math.ceil(width)}px`;

    button.setAttribute('aria-busy', 'true');
    button.classList.add('btn-busy');

    if (button instanceof HTMLButtonElement) {
        button.setAttribute(BUSY_LABEL, button.innerHTML);
        const label = button.dataset.busyText ?? 'Working…';
        button.innerHTML = `<span class="btn-spinner" aria-hidden="true"></span>${label}`;
    }

    button.disabled = true;
}

function clearBusy(button) {
    if (button.dataset.busy !== '1') {
        return;
    }

    delete button.dataset.busy;
    button.disabled = false;
    button.removeAttribute('aria-busy');
    button.classList.remove('btn-busy');
    button.style.minWidth = '';

    const label = button.getAttribute(BUSY_LABEL);
    if (label !== null) {
        button.innerHTML = label;
        button.removeAttribute(BUSY_LABEL);
    }
}

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-busy')) {
        return;
    }

    // defaultPrevented covers the `onsubmit="return confirm(...)"` handlers on
    // the delete forms: answering Cancel must leave the button untouched.
    if (event.defaultPrevented) {
        return;
    }

    const button = findSubmitButton(form);
    if (!button) {
        return;
    }

    /*
     * Deferred by one task on purpose.
     *
     * Disabling a submit button synchronously inside this handler happens
     * before the browser has serialised the form, and a disabled control is
     * excluded from the submission — which would silently drop the button's
     * own name/value. Nothing here relies on that today, but it is a trap that
     * only shows up later, in whichever form adds one first.
     */
    setTimeout(() => markBusy(button), 0);
});

/*
 * Restore on back/forward navigation.
 *
 * A page served from the bfcache comes back exactly as it was left — including
 * a disabled button — so going Back after submitting would land on a form that
 * cannot be submitted again.
 */
window.addEventListener('pageshow', (event) => {
    if (!event.persisted) {
        return;
    }

    document.querySelectorAll('[data-busy="1"]').forEach(clearBusy);
});
