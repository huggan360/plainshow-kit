/*
 * PlainShow backdrop: the sidebar's glyphs scattered over the page, evenly
 * spaced and turned at random, a shade off the background. Large soft blobs
 * of light in the theme colour (--ps-backdrop-hue, orange by default) come and go: each fades in somewhere, drifts and slowly
 * changes shape, then fades out while another appears elsewhere. The light
 * shows only on the glyphs, never on the page between them. They shy
 * away from the pointer: come close and they slip aside, so you can chase them.
 *
 * Add <canvas class="ps-backdrop" aria-hidden="true"></canvas> as the first
 * child of <body>, or set data-ps-backdrop on <body> and one is created.
 * It draws once, still, when the person prefers reduced motion.
 */
(function () {
    'use strict';

    var DEFAULT_ICONS = ['bx-grid-alt', 'bx-news', 'bx-wallet', 'bx-code-curly', 'bx-server', 'bx-cloud', 'bx-user-circle',
        'bx-shield-quarter', 'bx-palette', 'bx-group', 'bx-extension', 'bx-key', 'bx-refresh', 'bx-list-ul', 'bx-code-alt',
        'bx-camera', 'bx-calendar', 'bx-file', 'bx-chat', 'bx-sun', 'bx-chip', 'bx-bookmark', 'bx-note', 'bx-images'];
    var CELL = 96;            // spacing between glyphs, in CSS pixels
    var MIN_SIZE = 30;
    var MAX_SIZE = 42;
    var FRAME_MS = 1000 / 30;     // at rest
    var ACTIVE_MS = 1000 / 60;    // while the pointer moves
    var AVOID = 230;              // how close the pointer may come before a blob moves away
    var MAX_SPEED = 1100;         // px per second

    var BLOBS = 5;                // three on phones
    var SPREAD = [0, 4, 8, 12];   // each blob a few degrees off the theme's hue (orange: deep orange to amber)

    /** A number from the stylesheet for the theme colour (data-accent on <html>). */
    function themeNumber(name, fallback) {
        var value = parseFloat(getComputedStyle(document.documentElement).getPropertyValue(name));
        return isNaN(value) ? fallback : value;
    }

    function start(canvas) {
        var ctx = canvas.getContext('2d');
        if (!ctx) return;
        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var dpr = Math.min(window.devicePixelRatio || 1, 1.5);
        var width = 0, height = 0;
        var mask = document.createElement('canvas');   // glyphs in white, the shape every light is cut to
        var base = document.createElement('canvas');   // glyphs in their resting shade
        var light = document.createElement('canvas');  // the lights, cut to the glyphs
        var glyphs = [];
        var dark = true;
        var time = Math.random() * 1000;
        var energy = 0;
        var last = 0;
        var lastPointer = null;
        var pointer = null;
        var pointerAt = 0;
        var frame = 0;
        var blobs = [];
        // The theme colour's hue, and how strong its light is (bright colours such as teal are toned down to match orange).
        var hue = themeNumber('--ps-backdrop-hue', 17);
        var strength = themeNumber('--ps-backdrop-strength', 1);
        // A new theme colour shows at once, on the lights already there too.
        new MutationObserver(function () { hue = themeNumber('--ps-backdrop-hue', 17); strength = themeNumber('--ps-backdrop-strength', 1); }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-accent'] });

        function characters() {
            var names = {};
            document.querySelectorAll('.ps-sidebar .bx, .ps-nav .bx').forEach(function (node) {
                node.classList.forEach(function (name) { if (/^bx[sl]?-/.test(name)) names[name] = true; });
            });
            DEFAULT_ICONS.forEach(function (name) { names[name] = true; });
            var probe = document.createElement('i');
            probe.style.cssText = 'position:absolute;visibility:hidden;pointer-events:none';
            document.body.appendChild(probe);
            var chars = [];
            Object.keys(names).forEach(function (name) {
                probe.className = 'bx ' + name;
                var content = getComputedStyle(probe, '::before').content;
                if (content && content !== 'none' && content !== 'normal') {
                    var ch = content.replace(/^["']|["']$/g, '');
                    if (ch) chars.push(ch);
                }
            });
            probe.remove();
            return chars;
        }

        // A random number that stays the same for the same cell, so glyphs do not jump around on resize.
        function seeded(x, y, salt) {
            var n = Math.sin(x * 127.1 + y * 311.7 + salt * 74.7) * 43758.5453;
            return n - Math.floor(n);
        }

        function layout() {
            var chars = characters();
            if (!chars.length) return;
            width = window.innerWidth;
            height = window.innerHeight;
            [canvas, mask, base, light].forEach(function (c) {
                c.width = Math.round(width * dpr);
                c.height = Math.round(height * dpr);
            });
            canvas.style.width = width + 'px';
            canvas.style.height = height + 'px';
            glyphs = [];
            var cols = Math.ceil(width / CELL) + 1;
            var rows = Math.ceil(height / CELL) + 1;
            for (var row = 0; row < rows; row++) {
                for (var col = 0; col < cols; col++) {
                    // Every other row shifts half a cell, so the spacing reads as even rather than as a grid.
                    var x = (col + (row % 2 ? 0.5 : 0)) * CELL + (seeded(col, row, 1) - 0.5) * CELL * 0.45;
                    var y = row * CELL * 0.92 + (seeded(col, row, 2) - 0.5) * CELL * 0.4;
                    glyphs.push({
                        x: x, y: y,
                        size: MIN_SIZE + seeded(col, row, 3) * (MAX_SIZE - MIN_SIZE),
                        angle: seeded(col, row, 4) * Math.PI * 2,
                        ch: chars[Math.floor(seeded(col, row, 5) * chars.length) % chars.length]
                    });
                }
            }
            if (blobs.length !== (width < 700 ? 3 : BLOBS)) placeBlobs();
            paintGlyphs();
        }

        function paintGlyphs() {
            dark = document.documentElement.getAttribute('data-theme') !== 'light';
            [[mask, '#ffffff'], [base, dark ? 'rgba(255, 255, 255, 0.045)' : 'rgba(20, 20, 19, 0.06)']].forEach(function (pair) {
                var c = pair[0].getContext('2d');
                c.setTransform(dpr, 0, 0, dpr, 0, 0);
                c.clearRect(0, 0, width, height);
                c.fillStyle = pair[1];
                c.textAlign = 'center';
                c.textBaseline = 'middle';
                glyphs.forEach(function (g) {
                    c.save();
                    c.translate(g.x, g.y);
                    c.rotate(g.angle);
                    c.font = g.size + 'px boxicons';
                    c.fillText(g.ch, 0, 0);
                    c.restore();
                });
            });
            draw();
        }

        function rand(min, max) { return min + Math.random() * (max - min); }

        // A new blob somewhere on the screen, away from the pointer and the other blobs.
        function spawn(blob, age) {
            var best = null, bestScore = -1;
            for (var tries = 0; tries < 8; tries++) {
                var x = rand(0.05, 0.95) * width, y = rand(0.05, 0.95) * height, score = Infinity;
                blobs.forEach(function (o) { if (o !== blob) score = Math.min(score, Math.hypot(o.x - x, o.y - y)); });
                if (pointer) score = Math.min(score, Math.hypot(pointer.x - x, pointer.y - y) * 1.5);
                if (score > bestScore) { bestScore = score; best = { x: x, y: y }; }
            }
            var small = width < 700;
            var heading = rand(0, Math.PI * 2);
            blob.x = best.x; blob.y = best.y; blob.vx = 0; blob.vy = 0;
            blob.heading = heading;
            blob.turn = rand(-0.12, 0.12);                 // radians per second, so paths curve
            blob.speed = rand(14, 34);                     // px per second
            blob.radius = small ? rand(240, 360) : rand(380, 580);
            blob.hue = SPREAD[Math.floor(Math.random() * SPREAD.length)];
            blob.life = rand(16, 28);                      // seconds from fading in to gone
            blob.age = age || 0;
            blob.seed = rand(0, 100);
            blob.heat = 0;
        }

        function placeBlobs() {
            var count = width < 700 ? 3 : BLOBS;
            blobs = [];
            for (var i = 0; i < count; i++) {
                var blob = {};
                blobs.push(blob);
                // Start at different points of their lives, so they never all fade together.
                spawn(blob, 0);
                blob.age = blob.life * (0.2 + 0.6 * i / count);
            }
        }

        // How visible a blob is over its life: a slow fade in, a while at full, a slow fade out.
        function presence(blob) {
            var t = blob.age / blob.life;
            var ease = function (x) { x = Math.max(0, Math.min(1, x)); return x * x * (3 - 2 * x); };
            return ease(t / 0.3) * ease((1 - t) / 0.35);
        }

        function move(dt) {
            var chasing = pointer && performance.now() - pointerAt < 4000;
            blobs.forEach(function (e, i) {
                e.age += dt;
                if (e.age >= e.life) { spawn(e, 0); return; }
                e.heading += e.turn * dt;
                // Steer towards the drift, pushed by the pointer and away from other blobs.
                var ax = (Math.cos(e.heading) * e.speed - e.vx) * 1.2;
                var ay = (Math.sin(e.heading) * e.speed - e.vy) * 1.2;
                var fleeing = 0;
                if (chasing) {
                    var dx = e.x - pointer.x, dy = e.y - pointer.y;
                    var d = Math.hypot(dx, dy);
                    var reach = AVOID + e.radius * 0.3;
                    if (d < reach) {
                        if (d < 1) { dx = Math.cos(i * 2.4); dy = Math.sin(i * 2.4); d = 1; }
                        var push = Math.pow(1 - d / reach, 1.6) * 4200;
                        var side = i % 2 ? 1 : -1;
                        ax += (dx / d - dy / d * 0.55 * side) * push;
                        ay += (dy / d + dx / d * 0.55 * side) * push;
                        fleeing = 1 - d / reach;
                    }
                }
                blobs.forEach(function (o) {
                    if (o === e) return;
                    var ox = e.x - o.x, oy = e.y - o.y;
                    var od = Math.hypot(ox, oy) || 1;
                    var room = (e.radius + o.radius) * 0.45;
                    if (od < room) { ax += ox / od * (room - od) * 0.6; ay += oy / od * (room - od) * 0.6; }
                });
                // Soft walls: the middle of a blob stays on the screen.
                if (e.x < 0) { ax -= e.x * 3; }
                if (e.x > width) { ax -= (e.x - width) * 3; }
                if (e.y < 0) { ay -= e.y * 3; }
                if (e.y > height) { ay -= (e.y - height) * 3; }

                e.vx = (e.vx + ax * dt) * Math.exp(-1.6 * dt);
                e.vy = (e.vy + ay * dt) * Math.exp(-1.6 * dt);
                var speed = Math.hypot(e.vx, e.vy);
                if (speed > MAX_SPEED) { e.vx *= MAX_SPEED / speed; e.vy *= MAX_SPEED / speed; speed = MAX_SPEED; }
                e.x += e.vx * dt;
                e.y += e.vy * dt;
                // A chased blob warms up a little, then calms down.
                var want = Math.max(fleeing, Math.min(speed / 900, 1));
                e.heat += (want - e.heat) * Math.min(1, dt * (want > e.heat ? 5 : 1));
            });
        }

        function hsla(h, s, l, a) {
            return 'hsla(' + h.toFixed(1) + ', ' + s + '%, ' + l + '%, ' + a.toFixed(3) + ')';
        }

        // A blob is three soft lobes circling its middle at different speeds, so its shape keeps changing.
        function lobes(e) {
            var out = [];
            for (var k = 0; k < 3; k++) {
                var spin = (k === 1 ? -1 : 1) * (0.09 + k * 0.04);
                var angle = e.seed + k * 2.1 + time * spin;
                var reach = e.radius * (0.22 + 0.12 * Math.sin(time * 0.13 + e.seed + k));
                out.push({
                    x: e.x + Math.cos(angle) * reach,
                    y: e.y + Math.sin(angle) * reach * 0.8,
                    r: e.radius * (0.62 + 0.14 * Math.sin(time * 0.17 + e.seed * 2 + k * 1.7))
                });
            }
            return out;
        }

        function draw() {
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            var shapes = blobs.map(function (e) {
                var h = hue + e.hue + 3 * Math.sin(time * 0.3 + e.seed) + e.heat * 4;
                return { e: e, h: h, alpha: presence(e) * (1 + e.heat * 0.25), lobes: lobes(e) };
            });

            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.drawImage(base, 0, 0);

            var l = light.getContext('2d');
            l.setTransform(dpr, 0, 0, dpr, 0, 0);
            l.globalCompositeOperation = 'source-over';
            l.clearRect(0, 0, width, height);
            shapes.forEach(function (s) {
                if (s.alpha < 0.005) return;
                s.lobes.forEach(function (o) {
                    var g = l.createRadialGradient(o.x, o.y, 0, o.x, o.y, o.r);
                    if (dark) {
                        g.addColorStop(0, hsla(s.h + 4, 100, 58, 0.42 * strength * s.alpha));
                        g.addColorStop(0.4, hsla(s.h, 100, 52, 0.24 * strength * s.alpha));
                        g.addColorStop(0.75, hsla(s.h - 3, 100, 48, 0.08 * strength * s.alpha));
                    } else {
                        g.addColorStop(0, hsla(s.h + 2, 100, 46, 0.34 * strength * s.alpha));
                        g.addColorStop(0.4, hsla(s.h, 100, 45, 0.18 * strength * s.alpha));
                        g.addColorStop(0.75, hsla(s.h - 3, 100, 44, 0.06 * strength * s.alpha));
                    }
                    g.addColorStop(1, hsla(s.h - 4, 100, 48, 0));
                    l.fillStyle = g;
                    l.fillRect(o.x - o.r, o.y - o.r, o.r * 2, o.r * 2);
                });
            });
            l.setTransform(1, 0, 0, 1, 0, 0);
            l.globalCompositeOperation = 'destination-in';
            l.drawImage(mask, 0, 0);
            ctx.drawImage(light, 0, 0);
        }

        function tick(now) {
            frame = window.requestAnimationFrame(tick);
            var active = pointer && now - pointerAt < 3000;
            if (now - last < (active ? ACTIVE_MS : FRAME_MS) - 1) return;
            var dt = Math.min((now - (last || now)) / 1000, 0.05);
            last = now;
            // Slow on their own; moving the pointer adds a little speed that fades away again.
            time += dt * (0.35 + energy);
            energy *= Math.pow(0.35, dt);
            move(dt);
            draw();
        }

        layout();
        if (!reduce) {
            frame = window.requestAnimationFrame(tick);
            var follow = function (event) {
                if (lastPointer) {
                    var moved = Math.hypot(event.clientX - lastPointer.x, event.clientY - lastPointer.y);
                    energy = Math.min(energy + moved * 0.002, 1.2);
                }
                lastPointer = { x: event.clientX, y: event.clientY };
                pointer = lastPointer;
                pointerAt = performance.now();
            };
            window.addEventListener('pointermove', follow, { passive: true });
            window.addEventListener('pointerdown', follow, { passive: true });
            document.documentElement.addEventListener('pointerleave', function () { pointer = null; lastPointer = null; });
            window.addEventListener('blur', function () { pointer = null; lastPointer = null; });
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    window.cancelAnimationFrame(frame);
                } else {
                    last = 0;
                    frame = window.requestAnimationFrame(tick);
                }
            });
        }
        var resizeTimer = 0;
        window.addEventListener('resize', function () {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(layout, 150);
        });
        new MutationObserver(paintGlyphs).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
        // The glyph font may arrive after the first paint.
        if (document.fonts && document.fonts.load) {
            document.fonts.load('32px boxicons').then(layout, function () {});
        }
    }

    function init() {
        var canvas = document.querySelector('canvas.ps-backdrop');
        if (!canvas && document.body && document.body.hasAttribute('data-ps-backdrop')) {
            canvas = document.createElement('canvas');
            canvas.className = 'ps-backdrop';
            canvas.setAttribute('aria-hidden', 'true');
            document.body.insertBefore(canvas, document.body.firstChild);
        }
        if (canvas && !canvas.dataset.started) {
            canvas.dataset.started = '1';
            start(canvas);
        }
    }

    window.PlainShow = window.PlainShow || {};
    window.PlainShow.backdrop = init;
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
