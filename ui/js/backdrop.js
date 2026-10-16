/*
 * PlainShow backdrop: the sidebar's glyphs scattered over the page, evenly
 * spaced and turned at random, a shade off the background. Two orange lights
 * drift slowly across them and light up the glyphs they pass; moving the
 * pointer quickens them a little, so the page feels alive without moving much.
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
    var LIGHT_RADIUS = 340;
    var FRAME_MS = 1000 / 30;

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
        var frame = 0;

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

        // Where a light is at a moment: slow overlapping waves, so the path never quite repeats.
        function lightAt(index, t) {
            var a = index ? 1.7 : 0.3;
            return {
                x: width * (0.5 + 0.38 * Math.sin(t * 0.11 + a) + 0.1 * Math.sin(t * 0.27 + a * 2)),
                y: height * (0.5 + 0.36 * Math.cos(t * 0.09 + a * 1.3) + 0.1 * Math.sin(t * 0.23 + a))
            };
        }

        function draw() {
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(base, 0, 0);

            var l = light.getContext('2d');
            l.setTransform(dpr, 0, 0, dpr, 0, 0);
            l.globalCompositeOperation = 'source-over';
            l.clearRect(0, 0, width, height);
            for (var i = 0; i < 2; i++) {
                var p = lightAt(i, time);
                var g = l.createRadialGradient(p.x, p.y, 0, p.x, p.y, LIGHT_RADIUS);
                g.addColorStop(0, dark ? 'rgba(255, 112, 40, 0.75)' : 'rgba(236, 76, 0, 0.55)');
                g.addColorStop(0.45, dark ? 'rgba(252, 80, 0, 0.32)' : 'rgba(252, 80, 0, 0.22)');
                g.addColorStop(1, 'rgba(252, 80, 0, 0)');
                l.fillStyle = g;
                l.fillRect(p.x - LIGHT_RADIUS, p.y - LIGHT_RADIUS, LIGHT_RADIUS * 2, LIGHT_RADIUS * 2);
            }
            l.setTransform(1, 0, 0, 1, 0, 0);
            l.globalCompositeOperation = 'destination-in';
            l.drawImage(mask, 0, 0);
            ctx.drawImage(light, 0, 0);
        }

        function tick(now) {
            frame = window.requestAnimationFrame(tick);
            if (now - last < FRAME_MS) return;
            var dt = Math.min((now - (last || now)) / 1000, 0.1);
            last = now;
            // Slow on their own; the pointer adds a little speed that fades away again.
            time += dt * (0.35 + energy);
            energy *= Math.pow(0.35, dt);
            draw();
        }

        layout();
        if (!reduce) {
            frame = window.requestAnimationFrame(tick);
            window.addEventListener('pointermove', function (event) {
                if (lastPointer) {
                    var moved = Math.hypot(event.clientX - lastPointer.x, event.clientY - lastPointer.y);
                    energy = Math.min(energy + moved * 0.004, 2.2);
                }
                lastPointer = { x: event.clientX, y: event.clientY };
            }, { passive: true });
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
