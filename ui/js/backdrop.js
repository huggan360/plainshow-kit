/*
 * PlainShow backdrop: the sidebar's glyphs scattered over the page, evenly
 * spaced and turned at random, a shade off the background. A few embers,
 * from deep orange to warm yellow, drift across them and light up the glyphs
 * they pass, with a faint haze around each. They shy away from the pointer:
 * come close and they slip aside, faster and brighter, so you can chase them.
 * Where two meet, the glow adds up and turns more yellow.
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
    var AVOID = 230;              // how close the pointer may come before an ember moves away
    var MAX_SPEED = 1100;         // px per second

    // Each ember: its hue (orange 18 to yellow 46), size, and where on its slow path it starts.
    var EMBERS = [
        { hue: 20, radius: 340, phase: 0.3 },
        { hue: 34, radius: 280, phase: 1.7 },
        { hue: 44, radius: 210, phase: 3.1 },
        { hue: 26, radius: 300, phase: 4.4 },
        { hue: 40, radius: 180, phase: 5.6 }
    ];

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
        var embers = [];

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
            if (embers.length !== (width < 700 ? 3 : EMBERS.length)) placeEmbers();
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

        // Where an ember wants to be: slow overlapping waves, so the path never quite repeats.
        function home(index, t) {
            var a = EMBERS[index].phase;
            return {
                x: width * (0.5 + 0.4 * Math.sin(t * 0.11 + a) + 0.1 * Math.sin(t * 0.27 + a * 2)),
                y: height * (0.5 + 0.38 * Math.cos(t * 0.09 + a * 1.3) + 0.1 * Math.sin(t * 0.23 + a))
            };
        }

        function placeEmbers() {
            var count = width < 700 ? 3 : EMBERS.length;
            var scale = width < 700 ? 0.75 : 1;
            embers = [];
            for (var i = 0; i < count; i++) {
                var p = home(i, time);
                embers.push({ x: p.x, y: p.y, vx: 0, vy: 0, heat: 0, hue: EMBERS[i].hue, radius: EMBERS[i].radius * scale, spin: i % 2 ? 1 : -1 });
            }
        }

        // Pulled home by a soft spring, pushed away by the pointer (and a little sideways, so
        // they slip around it instead of getting stuck), and kept apart from each other.
        function move(dt) {
            var chasing = pointer && performance.now() - pointerAt < 4000;
            embers.forEach(function (e, i) {
                var target = home(i, time);
                var ax = (target.x - e.x) * 0.9;
                var ay = (target.y - e.y) * 0.9;
                var fleeing = 0;
                if (chasing) {
                    var dx = e.x - pointer.x, dy = e.y - pointer.y;
                    var d = Math.hypot(dx, dy);
                    var reach = AVOID + e.radius * 0.25;
                    if (d < reach) {
                        if (d < 1) { dx = Math.cos(i * 2.4); dy = Math.sin(i * 2.4); d = 1; }
                        var push = Math.pow(1 - d / reach, 1.6) * 5200;
                        ax += (dx / d + (-dy / d) * 0.55 * e.spin) * push;
                        ay += (dy / d + (dx / d) * 0.55 * e.spin) * push;
                        fleeing = 1 - d / reach;
                    }
                }
                embers.forEach(function (o) {
                    if (o === e) return;
                    var ox = e.x - o.x, oy = e.y - o.y;
                    var od = Math.hypot(ox, oy) || 1;
                    if (od < 180) {
                        ax += ox / od * (180 - od) * 3;
                        ay += oy / od * (180 - od) * 3;
                    }
                });
                // Soft walls: an ember may half leave the screen, then turns back.
                var m = e.radius * 0.4;
                if (e.x < -m) ax += (-m - e.x) * 8;
                if (e.x > width + m) ax -= (e.x - width - m) * 8;
                if (e.y < -m) ay += (-m - e.y) * 8;
                if (e.y > height + m) ay -= (e.y - height - m) * 8;

                e.vx = (e.vx + ax * dt) * Math.exp(-1.8 * dt);
                e.vy = (e.vy + ay * dt) * Math.exp(-1.8 * dt);
                var speed = Math.hypot(e.vx, e.vy);
                if (speed > MAX_SPEED) { e.vx *= MAX_SPEED / speed; e.vy *= MAX_SPEED / speed; speed = MAX_SPEED; }
                e.x += e.vx * dt;
                e.y += e.vy * dt;
                // Heat: a chased ember glows brighter and more yellow, then cools down.
                var want = Math.max(fleeing, Math.min(speed / 700, 1));
                e.heat += (want - e.heat) * Math.min(1, dt * (want > e.heat ? 6 : 1.2));
            });
        }

        function hsla(h, s, l, a) {
            return 'hsla(' + h.toFixed(1) + ', ' + s + '%, ' + l + '%, ' + a.toFixed(3) + ')';
        }

        function draw() {
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            // A faint haze around each ember, so the glow also warms the page between the glyphs.
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            embers.forEach(function (e, i) {
                var h = e.hue + 8 * Math.sin(time * 0.5 + i) + e.heat * 10;
                var r = e.radius * (1.5 + 0.08 * Math.sin(time * 0.8 + i * 1.9));
                var g = ctx.createRadialGradient(e.x, e.y, 0, e.x, e.y, r);
                g.addColorStop(0, hsla(h, 100, 55, (dark ? 0.085 : 0.07) * (1 + e.heat)));
                g.addColorStop(1, hsla(h - 8, 100, 50, 0));
                ctx.fillStyle = g;
                ctx.fillRect(e.x - r, e.y - r, r * 2, r * 2);
            });
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.drawImage(base, 0, 0);

            var l = light.getContext('2d');
            l.setTransform(dpr, 0, 0, dpr, 0, 0);
            l.globalCompositeOperation = 'source-over';
            l.clearRect(0, 0, width, height);
            // Overlapping embers add up in the dark theme, so where they meet it burns yellow.
            l.globalCompositeOperation = dark ? 'lighter' : 'source-over';
            embers.forEach(function (e, i) {
                // Each ember breathes and its colour wanders between orange and yellow.
                var h = e.hue + 8 * Math.sin(time * 0.5 + i) + e.heat * 10;
                var r = e.radius * (1 + 0.1 * Math.sin(time * 0.8 + i * 1.9) + e.heat * 0.15);
                var k = 1 + e.heat * 0.5;
                var g = l.createRadialGradient(e.x, e.y, 0, e.x, e.y, r);
                if (dark) {
                    g.addColorStop(0, hsla(h + 12, 100, 68, Math.min(0.8 * k, 1)));
                    g.addColorStop(0.28, hsla(h + 4, 100, 58, 0.55 * k));
                    g.addColorStop(0.62, hsla(h - 4, 100, 50, 0.22 * k));
                } else {
                    g.addColorStop(0, hsla(Math.min(h + 6, 40), 100, 48, Math.min(0.6 * k, 0.9)));
                    g.addColorStop(0.3, hsla(h, 100, 47, 0.38 * k));
                    g.addColorStop(0.62, hsla(h - 4, 100, 45, 0.14 * k));
                }
                g.addColorStop(1, hsla(h - 8, 100, 50, 0));
                l.fillStyle = g;
                l.fillRect(e.x - r, e.y - r, r * 2, r * 2);
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
