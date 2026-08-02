/**
 * Click-to-play video lightbox.
 *
 * Progressive enhancement: every video card is a real <a> pointing at YouTube,
 * so middle-click, "open in new tab", crawlers and a JS failure all still work.
 * This intercepts the plain left-click and plays the video in place instead.
 *
 * The iframe is created on open and destroyed on close. Never rendered upfront:
 * a grid of embeds would load a player per card (heavy on mobile data) and let
 * YouTube profile the visitor before they chose to watch anything.
 *
 * On view counts: an embedded play counts toward the video's YouTube views
 * because the visitor actively clicks. Muted autoplay generally does not — so
 * the click-to-play design is also the correct one for the channel.
 */

const EMBED_PARAMS = new URLSearchParams({
    autoplay: '1',
    rel: '0', // keep suggestions to this channel where possible
    modestbranding: '1',
    playsinline: '1', // iOS: play inline rather than hijacking fullscreen
});

let modal = null;
let previouslyFocused = null;

function build() {
    const el = document.createElement('div');
    el.className = 'video-modal';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-label', 'Video player');
    el.hidden = true;

    el.innerHTML = `
        <div class="video-modal-backdrop" data-close></div>
        <div class="video-modal-panel">
            <div class="video-modal-bar">
                <h2 class="video-modal-title"></h2>
                <button type="button" class="video-modal-close" data-close aria-label="Close video">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" aria-hidden="true">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="video-modal-frame"></div>
            <div class="video-modal-foot">
                <a class="video-modal-yt" target="_blank" rel="noopener noreferrer">
                    Watch on YouTube &mdash; like, comment &amp; subscribe &rarr;
                </a>
            </div>
        </div>
    `;

    document.body.appendChild(el);

    el.addEventListener('click', (event) => {
        if (event.target.closest('[data-close]')) close();
    });

    return el;
}

function open(videoId, title, watchUrl) {
    modal ??= build();
    previouslyFocused = document.activeElement;

    modal.querySelector('.video-modal-title').textContent = title || 'Video';
    modal.querySelector('.video-modal-yt').href = watchUrl || `https://www.youtube.com/watch?v=${videoId}`;

    const frame = modal.querySelector('.video-modal-frame');
    frame.innerHTML = '';

    const iframe = document.createElement('iframe');
    // youtube-nocookie still reports views; it just defers tracking cookies
    // until playback actually starts.
    iframe.src = `https://www.youtube-nocookie.com/embed/${videoId}?${EMBED_PARAMS}`;
    iframe.title = title || 'YouTube video player';
    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
    iframe.allowFullscreen = true;
    iframe.referrerPolicy = 'strict-origin-when-cross-origin';
    frame.appendChild(iframe);

    modal.hidden = false;
    document.body.classList.add('video-modal-open');

    modal.querySelector('.video-modal-close').focus();
    document.addEventListener('keydown', onKeydown);
}

function close() {
    if (!modal || modal.hidden) return;

    // Destroying the iframe is what actually stops playback — hiding the modal
    // would leave audio running behind it.
    modal.querySelector('.video-modal-frame').innerHTML = '';
    modal.hidden = true;
    document.body.classList.remove('video-modal-open');

    document.removeEventListener('keydown', onKeydown);
    previouslyFocused?.focus();
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        close();
        return;
    }

    // Keep Tab inside the dialog while it is open.
    if (event.key !== 'Tab' || !modal) return;

    const focusable = modal.querySelectorAll('button, a[href], iframe');
    if (!focusable.length) return;

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-video-id]');
    if (!trigger) return;

    // Leave modified clicks alone: ctrl/cmd/middle-click means "new tab", and
    // hijacking that is exactly the behaviour people hate.
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;

    event.preventDefault();
    open(
        trigger.dataset.videoId,
        trigger.dataset.videoTitle,
        trigger.getAttribute('href'),
    );
});
