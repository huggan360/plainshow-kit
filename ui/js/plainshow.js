/*
 * PlainShow UI behaviour for server-rendered pages: theme, dropdowns,
 * the mobile menu and toasts. The React apps use the matching components.
 */
(function () {
    'use strict';

    var THEME_KEY = 'plainshow-theme';

    function storedTheme() {
        try { return window.localStorage.getItem(THEME_KEY); } catch (error) { return null; }
    }

    function applyTheme(theme) {
        var value = theme === 'light' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', value);
        document.querySelectorAll('[data-ps-theme-label]').forEach(function (node) {
            node.textContent = value === 'light' ? 'Dark theme' : 'Light theme';
        });
    }

    applyTheme(storedTheme() || document.documentElement.getAttribute('data-theme') || 'dark');

    function closeAll(except) {
        document.querySelectorAll('[data-ps-dropdown].is-open').forEach(function (node) {
            if (node !== except) {
                node.classList.remove('is-open');
                var trigger = node.querySelector('[data-ps-dropdown-trigger]');
                if (trigger) trigger.setAttribute('aria-expanded', 'false');
                var menu = node.querySelector('[data-ps-dropdown-menu]');
                if (menu) menu.hidden = true;
            }
        });
    }

    document.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target : null;
        if (!target) return;

        var trigger = target.closest('[data-ps-dropdown-trigger]');
        if (trigger) {
            var root = trigger.closest('[data-ps-dropdown]');
            var menu = root && root.querySelector('[data-ps-dropdown-menu]');
            var open = root && !root.classList.contains('is-open');
            closeAll(root);
            if (root && menu) {
                root.classList.toggle('is-open', open);
                menu.hidden = !open;
                trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
            event.preventDefault();
            return;
        }
        if (!target.closest('[data-ps-dropdown-menu]')) closeAll(null);

        if (target.closest('[data-ps-theme-toggle]')) {
            var next = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            try { window.localStorage.setItem(THEME_KEY, next); } catch (error) { /* private mode */ }
            applyTheme(next);
            event.preventDefault();
        }

        var topnavToggle = target.closest('[data-ps-topnav-toggle]');
        if (topnavToggle) {
            var nav = topnavToggle.closest('.ps-topnav');
            if (nav) {
                var open = !nav.classList.contains('is-open');
                nav.classList.toggle('is-open', open);
                topnavToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
            event.preventDefault();
            return;
        }

        var shell = document.querySelector('.ps-shell');
        if (shell && target.closest('[data-ps-menu-toggle]')) {
            shell.classList.toggle('is-menu-open');
            event.preventDefault();
        } else if (shell && shell.classList.contains('is-menu-open') && !target.closest('.ps-sidebar')) {
            shell.classList.remove('is-menu-open');
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        closeAll(null);
        document.querySelectorAll('.ps-topnav.is-open').forEach(function (nav) { nav.classList.remove('is-open'); });
        var shell = document.querySelector('.ps-shell.is-menu-open');
        if (shell) shell.classList.remove('is-menu-open');
    });

    /* Buttons inside forms show a spinner while the request runs. */
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-ps-no-loading')) return;
        var button = event.submitter || form.querySelector('button[type="submit"]');
        if (button) {
            button.classList.add('is-loading');
            button.setAttribute('aria-busy', 'true');
        }
    });

    window.PlainShow = window.PlainShow || {};
    window.PlainShow.toast = function (message, kind) {
        var host = document.querySelector('.ps-toasts');
        if (!host) {
            host = document.createElement('div');
            host.className = 'ps-toasts';
            host.setAttribute('aria-live', 'polite');
            document.body.appendChild(host);
        }
        var toast = document.createElement('div');
        toast.className = 'ps-toast';
        toast.setAttribute('role', kind === 'error' ? 'alert' : 'status');
        var icon = document.createElement('i');
        icon.className = 'bx ' + (kind === 'error' ? 'bx-error-circle' : 'bx-check-circle');
        var text = document.createElement('span');
        text.textContent = message;
        toast.appendChild(icon);
        toast.appendChild(text);
        host.appendChild(toast);
        window.setTimeout(function () { toast.remove(); }, 4500);
    };
    window.PlainShow.applyTheme = applyTheme;
})();
