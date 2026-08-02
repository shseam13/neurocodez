// Raster -> vector. Moore-neighbour contour tracing, Ramer-Douglas-Peucker
// simplification, then a corner-aware Catmull-Rom -> cubic Bezier fit.
//
// Corner awareness is the point: a plain spline through every traced point
// rounds off the notches in the Neuro Codez mark and the letterform stops
// reading as an N. Sharp vertices are detected and preserved.

/** Build a binary ink mask. `isInk(r,g,b,a)` decides membership. */
export function mask(img, isInk) {
    const { width, height, data } = img;
    const m = new Uint8Array(width * height);
    for (let i = 0, n = width * height; i < n; i++) {
        const d = i * 4;
        m[i] = isInk(data[d], data[d + 1], data[d + 2], data[d + 3]) ? 1 : 0;
    }
    return { width, height, m };
}

/**
 * Label each pixel as ink (1), outer background (0) or enclosed hole (2).
 * Holes are background regions unreachable from the image border, and must be
 * traced as their own subpaths or they fill solid.
 */
function markHoles({ width, height, m }) {
    const label = Uint8Array.from(m);
    const seen = new Uint8Array(width * height);
    const stack = [];

    for (let x = 0; x < width; x++) {
        stack.push(x, (height - 1) * width + x);
    }
    for (let y = 0; y < height; y++) {
        stack.push(y * width, y * width + width - 1);
    }

    while (stack.length) {
        const i = stack.pop();
        if (seen[i] || m[i]) continue;
        seen[i] = 1;
        const x = i % width, y = (i - x) / width;
        if (x > 0) stack.push(i - 1);
        if (x < width - 1) stack.push(i + 1);
        if (y > 0) stack.push(i - width);
        if (y < height - 1) stack.push(i + width);
    }

    for (let i = 0; i < label.length; i++) {
        if (!m[i] && !seen[i]) label[i] = 2; // enclosed background
    }
    return label;
}

// Moore neighbourhood, clockwise from due east.
const N8 = [[1, 0], [1, 1], [0, 1], [-1, 1], [-1, 0], [-1, -1], [0, -1], [1, -1]];

function traceRegion(width, height, isMember, startX, startY, visited) {
    const contour = [[startX, startY]];
    const key = (x, y) => y * width + x;
    let cx = startX, cy = startY, dir = 6; // entered from the west

    for (let guard = 0; guard < width * height * 8; guard++) {
        let moved = false;
        // Scan the neighbourhood starting just behind the incoming direction.
        for (let k = 0; k < 8; k++) {
            const d = (dir + 6 + k) % 8;
            const nx = cx + N8[d][0], ny = cy + N8[d][1];
            if (nx < 0 || ny < 0 || nx >= width || ny >= height) continue;
            if (!isMember(nx, ny)) continue;
            cx = nx; cy = ny; dir = d; moved = true;
            visited[key(nx, ny)] = 1;
            break;
        }
        if (!moved) break; // isolated pixel
        if (cx === startX && cy === startY) break;
        contour.push([cx, cy]);
    }

    return contour;
}

/** Trace every ink region and every enclosed hole. */
export function contours(maskObj, minArea = 24) {
    const { width, height } = maskObj;
    const label = markHoles(maskObj);
    const out = [];

    for (const target of [1, 2]) {
        const visited = new Uint8Array(width * height);
        const isMember = (x, y) => label[y * width + x] === target;

        for (let y = 0; y < height; y++) {
            for (let x = 0; x < width; x++) {
                const i = y * width + x;
                if (label[i] !== target || visited[i]) continue;
                // Only start on a boundary pixel of an untouched region.
                const leftInside = x > 0 && label[i - 1] === target;
                if (leftInside && visited[i - 1]) { visited[i] = 1; continue; }
                if (leftInside) continue;

                const c = traceRegion(width, height, isMember, x, y, visited);
                visited[i] = 1;
                if (c.length >= 8 && Math.abs(area(c)) >= minArea) {
                    out.push({ points: c, hole: target === 2 });
                }
                // Consume the rest of this region so it is not re-traced.
                floodMark(width, height, isMember, x, y, visited);
            }
        }
    }

    return out;
}

function floodMark(width, height, isMember, sx, sy, visited) {
    const stack = [sx, sy];
    while (stack.length) {
        const y = stack.pop(), x = stack.pop();
        if (x < 0 || y < 0 || x >= width || y >= height) continue;
        const i = y * width + x;
        if (visited[i] || !isMember(x, y)) continue;
        visited[i] = 1;
        stack.push(x - 1, y, x + 1, y, x, y - 1, x, y + 1);
    }
}

function area(pts) {
    let a = 0;
    for (let i = 0, n = pts.length; i < n; i++) {
        const [x1, y1] = pts[i], [x2, y2] = pts[(i + 1) % n];
        a += x1 * y2 - x2 * y1;
    }
    return a / 2;
}

/** Ramer-Douglas-Peucker, closed-polygon variant. */
export function simplify(points, epsilon) {
    if (points.length < 4) return points;

    const rdp = (pts) => {
        if (pts.length < 3) return pts;
        const [ax, ay] = pts[0], [bx, by] = pts[pts.length - 1];
        const dx = bx - ax, dy = by - ay;
        const len = Math.hypot(dx, dy) || 1e-9;
        let far = 0, idx = 0;

        for (let i = 1; i < pts.length - 1; i++) {
            const d = Math.abs((pts[i][0] - ax) * dy - (pts[i][1] - ay) * dx) / len;
            if (d > far) { far = d; idx = i; }
        }

        if (far <= epsilon) return [pts[0], pts[pts.length - 1]];
        return [...rdp(pts.slice(0, idx + 1)).slice(0, -1), ...rdp(pts.slice(idx))];
    };

    // Split the ring at its two most distant points so RDP has stable anchors.
    const half = Math.floor(points.length / 2);
    const a = rdp([...points.slice(0, half + 1)]);
    const b = rdp([...points.slice(half), points[0]]);
    return [...a.slice(0, -1), ...b.slice(0, -1)];
}

/** Interior turn angle in degrees at each vertex (180 = straight). */
function turnAngle(prev, cur, next) {
    const v1x = cur[0] - prev[0], v1y = cur[1] - prev[1];
    const v2x = next[0] - cur[0], v2y = next[1] - cur[1];
    const dot = v1x * v2x + v1y * v2y;
    const det = v1x * v2y - v1y * v2x;
    return Math.abs((Math.atan2(det, dot) * 180) / Math.PI);
}

/**
 * Emit an SVG path. Vertices whose direction changes by more than
 * `cornerDeg` become hard corners; everything else is smoothed.
 */
export function toPath(points, { cornerDeg = 42, scale = 1, offsetX = 0, offsetY = 0, precision = 2 } = {}) {
    const n = points.length;
    if (n < 3) return '';

    const P = points.map(([x, y]) => [(x + offsetX) * scale, (y + offsetY) * scale]);
    const f = (v) => Number(v.toFixed(precision));

    const corner = P.map((_, i) => turnAngle(P[(i - 1 + n) % n], P[i], P[(i + 1) % n]) > cornerDeg);

    let d = `M${f(P[0][0])} ${f(P[0][1])}`;

    for (let i = 0; i < n; i++) {
        const p0 = P[(i - 1 + n) % n], p1 = P[i], p2 = P[(i + 1) % n], p3 = P[(i + 2) % n];

        if (corner[i] && corner[(i + 1) % n]) {
            d += `L${f(p2[0])} ${f(p2[1])}`;
            continue;
        }

        // Catmull-Rom -> cubic Bezier. Tangents are zeroed at corners so the
        // curve arrives and leaves cleanly instead of overshooting.
        const t1x = corner[i] ? 0 : (p2[0] - p0[0]) / 6;
        const t1y = corner[i] ? 0 : (p2[1] - p0[1]) / 6;
        const t2x = corner[(i + 1) % n] ? 0 : (p3[0] - p1[0]) / 6;
        const t2y = corner[(i + 1) % n] ? 0 : (p3[1] - p1[1]) / 6;

        d += `C${f(p1[0] + t1x)} ${f(p1[1] + t1y)},${f(p2[0] - t2x)} ${f(p2[1] - t2y)},${f(p2[0])} ${f(p2[1])}`;
    }

    return d + 'Z';
}

/** Tight bounding box across a set of contours. */
export function bounds(list) {
    let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
    for (const { points } of list) {
        for (const [x, y] of points) {
            if (x < minX) minX = x;
            if (y < minY) minY = y;
            if (x > maxX) maxX = x;
            if (y > maxY) maxY = y;
        }
    }
    return { minX, minY, maxX, maxY, width: maxX - minX, height: maxY - minY };
}
