/**
 * Live Markdown preview.
 *
 * Renders on the SERVER via the same MarkdownService the published page uses,
 * rather than with a client-side library. A JS renderer would drift from the
 * real output on the things that matter here — code-block handling, HTML
 * sanitising and heading anchors — so the author would be previewing something
 * subtly different from what visitors get.
 */

const DEBOUNCE_MS = 400;

function init(root) {
    const textarea = root.querySelector('[data-md-input]');
    const preview = root.querySelector('[data-md-preview]');
    const meta = root.querySelector('[data-md-meta]');
    const endpoint = root.dataset.mdPreviewUrl;
    const token = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!textarea || !preview || !endpoint) return;

    let timer = null;
    let inFlight = null;
    let lastRendered = null;

    async function render() {
        const body = textarea.value;
        if (body === lastRendered) return;

        // Supersede a slower earlier request so panes cannot arrive out of order.
        inFlight?.abort();
        const controller = new AbortController();
        inFlight = controller;

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                signal: controller.signal,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token ?? '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ body_markdown: body }),
            });

            if (!response.ok) return;

            const data = await response.json();
            preview.innerHTML = data.html || '<p class="text-ink-muted">Nothing to preview yet.</p>';
            lastRendered = body;

            if (meta) {
                meta.textContent = `${data.reading_minutes} min read`;
            }
        } catch (error) {
            // An aborted request is the expected path while typing, not a fault.
            if (error.name !== 'AbortError') {
                preview.innerHTML = '<p class="text-overdue">Preview unavailable.</p>';
            }
        }
    }

    textarea.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(render, DEBOUNCE_MS);
    });

    // Tab should indent code, not jump to the next field, while writing.
    textarea.addEventListener('keydown', (event) => {
        if (event.key !== 'Tab' || event.shiftKey) return;
        event.preventDefault();

        const { selectionStart: start, selectionEnd: end, value } = textarea;
        textarea.value = `${value.slice(0, start)}    ${value.slice(end)}`;
        textarea.selectionStart = textarea.selectionEnd = start + 4;
    });

    root.querySelectorAll('[data-md-tab]').forEach((tab) => {
        tab.addEventListener('click', (event) => {
            event.preventDefault();
            const target = tab.dataset.mdTab;

            root.querySelectorAll('[data-md-tab]').forEach((t) => {
                t.classList.toggle('is-active', t === tab);
            });
            root.querySelectorAll('[data-md-pane]').forEach((pane) => {
                pane.hidden = pane.dataset.mdPane !== target;
            });

            if (target === 'preview') render();
        });
    });

    render();
}

document.querySelectorAll('[data-md-editor]').forEach(init);
