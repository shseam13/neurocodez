// Builds every derived brand asset from the source PNGs in brand/source/.
// Re-run with:  node tools/brand/build-assets.mjs
//
// The source files are opaque RGB with no alpha, so the mark is un-matted from
// its white background rather than colour-keyed — keying leaves white fringing
// on the antialiased edge.

import fs from 'node:fs';
import path from 'node:path';
import { decodePng, encodePng, encodeIco, resize } from './png.mjs';
import { mask, contours, simplify, toPath, bounds } from './trace.mjs';

const ROOT = path.resolve(import.meta.dirname, '../..');
const SRC = path.join(ROOT, 'brand/source');
const OUT = path.join(ROOT, 'public/brand');

const BRAND = { r: 0x91, g: 0x4e, b: 0xe9 };
const DEEP = { r: 0x1c, g: 0x06, b: 0x37 };

fs.mkdirSync(OUT, { recursive: true });
const log = [];

/**
 * Recover per-pixel coverage from a mark composited over white.
 *   pixel = white*(1-t) + ink*t   =>   t = (255 - pixel) / (255 - ink)
 * Green has the widest range for this purple, so it gives the best precision.
 */
function unmatteFromWhite(img, ink) {
    const { width, height, data } = img;
    const out = Buffer.alloc(width * height * 4);
    const span = 255 - ink.g;

    for (let i = 0, n = width * height; i < n; i++) {
        const d = i * 4;
        const t = Math.max(0, Math.min(1, (255 - data[d + 1]) / span));
        out[d] = ink.r;
        out[d + 1] = ink.g;
        out[d + 2] = ink.b;
        out[d + 3] = Math.round(t * 255);
    }
    return { width, height, data: out };
}

function recolour(img, { r, g, b }) {
    const out = Buffer.from(img.data);
    for (let i = 0; i < out.length; i += 4) { out[i] = r; out[i + 1] = g; out[i + 2] = b; }
    return { ...img, data: out };
}

function solid(width, height, { r, g, b }) {
    const data = Buffer.alloc(width * height * 4);
    for (let i = 0; i < data.length; i += 4) { data[i] = r; data[i + 1] = g; data[i + 2] = b; data[i + 3] = 255; }
    return { width, height, data };
}

/** Source-over composite of `top` onto `base` at (ox, oy). */
function composite(base, top, ox, oy) {
    const out = Buffer.from(base.data);
    for (let y = 0; y < top.height; y++) {
        const by = y + oy;
        if (by < 0 || by >= base.height) continue;
        for (let x = 0; x < top.width; x++) {
            const bx = x + ox;
            if (bx < 0 || bx >= base.width) continue;
            const s = (y * top.width + x) * 4, d = (by * base.width + bx) * 4;
            const a = top.data[s + 3] / 255;
            if (a === 0) continue;
            out[d] = Math.round(top.data[s] * a + out[d] * (1 - a));
            out[d + 1] = Math.round(top.data[s + 1] * a + out[d + 1] * (1 - a));
            out[d + 2] = Math.round(top.data[s + 2] * a + out[d + 2] * (1 - a));
            out[d + 3] = Math.max(out[d + 3], top.data[s + 3]);
        }
    }
    return { ...base, data: out };
}

function write(name, buf) {
    fs.writeFileSync(path.join(OUT, name), buf);
    log.push(`  ${name.padEnd(30)} ${(buf.length / 1024).toFixed(1)} KB`);
}

// ---------------------------------------------------------------- vector mark

const markSrc = decodePng(path.join(SRC, 'logo_white.png'));
const transparent = unmatteFromWhite(markSrc, BRAND);

// Trace at half-coverage: the midpoint of the antialiased edge is the truest
// position of the original vector outline.
const inkMask = mask(transparent, (_r, _g, _b, a) => a >= 128);
const traced = contours(inkMask, 40);

const bb = bounds(traced);
const VIEW = 100;
const scale = VIEW / Math.max(bb.width, bb.height);
const padX = (VIEW - bb.width * scale) / 2;
const padY = (VIEW - bb.height * scale) / 2;

const paths = traced.map(({ points }) =>
    toPath(simplify(points, 0.6), {
        scale,
        offsetX: -bb.minX,
        offsetY: -bb.minY,
        cornerDeg: 42,
    })
);

// Sanity check: vector area should land within a few percent of raster ink area.
const rasterInk = inkMask.m.reduce((a, v) => a + v, 0);
const vectorArea = traced.reduce((sum, { points, hole }) => {
    let a = 0;
    for (let i = 0, n = points.length; i < n; i++) {
        const [x1, y1] = points[i], [x2, y2] = points[(i + 1) % n];
        a += x1 * y2 - x2 * y1;
    }
    return sum + (hole ? -1 : 1) * Math.abs(a / 2);
}, 0);
const drift = ((vectorArea - rasterInk) / rasterInk) * 100;

const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${VIEW} ${VIEW}" fill="none" role="img" aria-label="Neuro Codez">
  <title>Neuro Codez</title>
  <g transform="translate(${padX.toFixed(2)} ${padY.toFixed(2)})" fill="currentColor" fill-rule="evenodd">
${paths.map((d) => `    <path d="${d}"/>`).join('\n')}
  </g>
</svg>
`;

fs.writeFileSync(path.join(OUT, 'logo-mark.svg'), svg);
log.push(`  ${'logo-mark.svg'.padEnd(30)} ${(svg.length / 1024).toFixed(1)} KB  (${traced.length} contours, area drift ${drift.toFixed(1)}%)`);

// ------------------------------------------------------- transparent rasters

for (const size of [512, 256, 128]) {
    write(`logo-mark-purple-${size}.png`, encodePng(resize(transparent, size, size)));
}
const whiteMark = recolour(transparent, { r: 255, g: 255, b: 255 });
for (const size of [512, 256, 128]) {
    write(`logo-mark-white-${size}.png`, encodePng(resize(whiteMark, size, size)));
}

// ------------------------------------------------------------------ app icons

const rounded = decodePng(path.join(SRC, 'logo_rounded.png'));
write('icon-512.png', encodePng(resize(rounded, 512, 512)));
write('icon-192.png', encodePng(resize(rounded, 192, 192)));

// Maskable icons get cropped to whatever shape the launcher wants, so all
// meaningful content must sit inside the central 80% safe zone.
const SAFE = 0.8;
const inner = Math.round(512 * SAFE);
const maskable = composite(
    solid(512, 512, DEEP),
    resize(rounded, inner, inner),
    Math.round((512 - inner) / 2),
    Math.round((512 - inner) / 2)
);
write('icon-maskable-512.png', encodePng(maskable));

// --------------------------------------------------------------------- favicon

const icoSizes = [16, 32, 48];
const ico = encodeIco(
    icoSizes.map((size) => ({ size, buffer: encodePng(resize(rounded, size, size)) }))
);
fs.writeFileSync(path.join(ROOT, 'public/favicon.ico'), ico);
log.push(`  ${'../favicon.ico'.padEnd(30)} ${(ico.length / 1024).toFixed(1)} KB  (${icoSizes.join(', ')})`);

write('apple-touch-icon.png', encodePng(resize(rounded, 180, 180)));

console.log('Brand assets written to public/brand/\n');
console.log(log.join('\n'));
console.log(`\nSource ink pixels: ${rasterInk}  |  traced contours: ${traced.length}`);
if (Math.abs(drift) > 4) {
    console.warn(`\nWARNING: vector/raster area drift is ${drift.toFixed(1)}% — inspect logo-mark.svg`);
}
