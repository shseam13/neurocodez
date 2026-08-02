// Minimal PNG decode/encode + resize. No external dependencies — Node's zlib does the work.
import zlib from 'node:zlib';
import fs from 'node:fs';

const CRC_TABLE = (() => {
    const t = new Int32Array(256);
    for (let n = 0; n < 256; n++) {
        let c = n;
        for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
        t[n] = c;
    }
    return t;
})();

function crc32(buf) {
    let c = -1;
    for (let i = 0; i < buf.length; i++) c = CRC_TABLE[(c ^ buf[i]) & 0xff] ^ (c >>> 8);
    return (c ^ -1) >>> 0;
}

/** Decode an 8-bit non-interlaced PNG to {width, height, data} where data is RGBA. */
export function decodePng(file) {
    const b = fs.readFileSync(file);
    let p = 8, width, height, depth, colorType;
    const idat = [];

    while (p < b.length) {
        const len = b.readUInt32BE(p);
        const type = b.toString('ascii', p + 4, p + 8);
        if (type === 'IHDR') {
            width = b.readUInt32BE(p + 8);
            height = b.readUInt32BE(p + 12);
            depth = b[p + 16];
            colorType = b[p + 17];
            if (b[p + 20] !== 0) throw new Error(`${file}: interlaced PNG not supported`);
        } else if (type === 'IDAT') {
            idat.push(b.subarray(p + 8, p + 8 + len));
        } else if (type === 'IEND') break;
        p += 12 + len;
    }

    if (depth !== 8) throw new Error(`${file}: only 8-bit depth supported (got ${depth})`);
    const channels = { 0: 1, 2: 3, 4: 2, 6: 4 }[colorType];
    if (!channels) throw new Error(`${file}: palette PNGs not supported`);

    const raw = zlib.inflateSync(Buffer.concat(idat));
    const stride = width * channels;
    const flat = Buffer.alloc(height * stride);
    let q = 0;

    // Undo per-scanline filtering (PNG spec ยง9).
    for (let y = 0; y < height; y++) {
        const filter = raw[q++];
        for (let x = 0; x < stride; x++) {
            const cur = raw[q + x];
            const a = x >= channels ? flat[y * stride + x - channels] : 0;
            const bb = y > 0 ? flat[(y - 1) * stride + x] : 0;
            const c = x >= channels && y > 0 ? flat[(y - 1) * stride + x - channels] : 0;
            let v;
            switch (filter) {
                case 0: v = cur; break;
                case 1: v = cur + a; break;
                case 2: v = cur + bb; break;
                case 3: v = cur + ((a + bb) >> 1); break;
                case 4: {
                    const pp = a + bb - c;
                    const pa = Math.abs(pp - a), pb = Math.abs(pp - bb), pc = Math.abs(pp - c);
                    v = cur + (pa <= pb && pa <= pc ? a : pb <= pc ? bb : c);
                    break;
                }
                default: throw new Error(`${file}: bad filter ${filter}`);
            }
            flat[y * stride + x] = v & 0xff;
        }
        q += stride;
    }

    // Normalise every colour type to RGBA.
    const data = Buffer.alloc(width * height * 4);
    for (let i = 0, n = width * height; i < n; i++) {
        const s = i * channels, d = i * 4;
        if (channels === 4) { data[d] = flat[s]; data[d + 1] = flat[s + 1]; data[d + 2] = flat[s + 2]; data[d + 3] = flat[s + 3]; }
        else if (channels === 3) { data[d] = flat[s]; data[d + 1] = flat[s + 1]; data[d + 2] = flat[s + 2]; data[d + 3] = 255; }
        else if (channels === 2) { data[d] = data[d + 1] = data[d + 2] = flat[s]; data[d + 3] = flat[s + 1]; }
        else { data[d] = data[d + 1] = data[d + 2] = flat[s]; data[d + 3] = 255; }
    }

    return { width, height, data };
}

function chunk(type, payload) {
    const out = Buffer.alloc(payload.length + 12);
    out.writeUInt32BE(payload.length, 0);
    out.write(type, 4, 'ascii');
    payload.copy(out, 8);
    out.writeUInt32BE(crc32(out.subarray(4, 8 + payload.length)), 8 + payload.length);
    return out;
}

/** Encode RGBA to an 8-bit RGBA PNG. */
export function encodePng({ width, height, data }) {
    const stride = width * 4;
    const raw = Buffer.alloc(height * (stride + 1));
    for (let y = 0; y < height; y++) {
        raw[y * (stride + 1)] = 0; // filter: none — these are small, flat images
        data.copy(raw, y * (stride + 1) + 1, y * stride, y * stride + stride);
    }

    const ihdr = Buffer.alloc(13);
    ihdr.writeUInt32BE(width, 0);
    ihdr.writeUInt32BE(height, 4);
    ihdr[8] = 8;  // bit depth
    ihdr[9] = 6;  // colour type: RGBA

    return Buffer.concat([
        Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
        chunk('IHDR', ihdr),
        chunk('IDAT', zlib.deflateSync(raw, { level: 9 })),
        chunk('IEND', Buffer.alloc(0)),
    ]);
}

/**
 * Area-average downscale with premultiplied alpha.
 * Premultiplying matters: averaging raw RGB across transparent pixels drags
 * background colour into the edges and produces a dark halo.
 */
export function resize(img, outW, outH) {
    const { width: w, height: h, data } = img;
    const out = Buffer.alloc(outW * outH * 4);
    const sx = w / outW, sy = h / outH;

    for (let y = 0; y < outH; y++) {
        const y0 = y * sy, y1 = Math.min(h, (y + 1) * sy);
        for (let x = 0; x < outW; x++) {
            const x0 = x * sx, x1 = Math.min(w, (x + 1) * sx);
            let r = 0, g = 0, b = 0, a = 0, wsum = 0;

            for (let py = Math.floor(y0); py < Math.ceil(y1); py++) {
                const cy = Math.min(y1, py + 1) - Math.max(y0, py);
                if (cy <= 0) continue;
                for (let px = Math.floor(x0); px < Math.ceil(x1); px++) {
                    const cx = Math.min(x1, px + 1) - Math.max(x0, px);
                    if (cx <= 0) continue;
                    const weight = cx * cy;
                    const i = (py * w + px) * 4;
                    const al = data[i + 3] / 255;
                    r += data[i] * al * weight;
                    g += data[i + 1] * al * weight;
                    b += data[i + 2] * al * weight;
                    a += data[i + 3] * weight;
                    wsum += weight;
                }
            }

            const d = (y * outW + x) * 4;
            if (wsum > 0) {
                const alpha = a / wsum;
                const un = alpha > 0 ? 255 / alpha : 0;
                out[d] = Math.round(Math.min(255, (r / wsum) * un));
                out[d + 1] = Math.round(Math.min(255, (g / wsum) * un));
                out[d + 2] = Math.round(Math.min(255, (b / wsum) * un));
                out[d + 3] = Math.round(alpha);
            }
        }
    }

    return { width: outW, height: outH, data: out };
}

/** Wrap PNG buffers in an ICO container. Every current browser reads PNG-in-ICO. */
export function encodeIco(pngs) {
    const header = Buffer.alloc(6 + pngs.length * 16);
    header.writeUInt16LE(0, 0);
    header.writeUInt16LE(1, 2); // type: icon
    header.writeUInt16LE(pngs.length, 4);

    let offset = header.length;
    pngs.forEach(({ size, buffer }, i) => {
        const e = 6 + i * 16;
        header[e] = size >= 256 ? 0 : size;
        header[e + 1] = size >= 256 ? 0 : size;
        header.writeUInt16LE(1, e + 4);   // colour planes
        header.writeUInt16LE(32, e + 6);  // bits per pixel
        header.writeUInt32BE(0, e + 8);
        header.writeUInt32LE(buffer.length, e + 8);
        header.writeUInt32LE(offset, e + 12);
        offset += buffer.length;
    });

    return Buffer.concat([header, ...pngs.map((p) => p.buffer)]);
}
