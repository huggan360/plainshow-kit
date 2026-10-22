/*
 * PlainShow UI behaviour for server-rendered pages: theme, dropdowns,
 * the mobile menu and toasts. The React apps use the matching components.
 */
(function () {
    'use strict';

    var THEME_KEY = 'plainshow-theme';
    var LANGS = ['en', 'nl', 'sv'];

    /* Language: the ps_lang cookie (shared by every PlainShow site), else the browser's. */
    function readCookie(name) {
        var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }
    function detectLang() {
        var chosen = readCookie('ps_lang');
        if (LANGS.indexOf(chosen) >= 0) return chosen;
        var wanted = navigator.languages && navigator.languages.length ? navigator.languages : [navigator.language || 'en'];
        for (var i = 0; i < wanted.length; i++) {
            var code = String(wanted[i]).slice(0, 2).toLowerCase();
            if (LANGS.indexOf(code) >= 0) return code;
        }
        return 'en';
    }
    function cookieDomain(host) {
        if (/^[\d.]+$/.test(host) || host.indexOf('.') < 0) return '';
        var labels = host.split('.');
        return labels.length > 2 ? labels.slice(1).join('.') : host;
    }
    function setLang(code) {
        if (LANGS.indexOf(code) < 0) return;
        var domain = cookieDomain(location.hostname);
        document.cookie = 'ps_lang=' + code + '; path=/; max-age=31536000; SameSite=Lax' + (domain ? '; domain=' + domain : '') + (location.protocol === 'https:' ? '; Secure' : '');
        location.reload();
    }
    var lang = document.documentElement.getAttribute('lang');
    if (LANGS.indexOf(lang) < 0) lang = detectLang();
    var THEME_WORDS = { en: ['Light theme', 'Dark theme'], nl: ['Licht thema', 'Donker thema'], sv: ['Ljust tema', 'Mörkt tema'] };

    function storedTheme() {
        try { return window.localStorage.getItem(THEME_KEY); } catch (error) { return null; }
    }

    function applyTheme(theme) {
        var value = theme === 'light' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', value);
        document.querySelectorAll('[data-ps-theme-label]').forEach(function (node) {
            node.textContent = THEME_WORDS[lang][value === 'light' ? 1 : 0];
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

        var langButton = target.closest('[data-ps-lang]');
        if (langButton) {
            setLang(langButton.getAttribute('data-ps-lang'));
            event.preventDefault();
            return;
        }

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
    window.PlainShow.lang = lang;
    window.PlainShow.setLang = setLang;
    /* Texts for scripts: the page puts its dictionary in window.PS_I18N ({"Source": "Translation"}). */
    window.PlainShow.t = function (text, params) {
        var out = (window.PS_I18N && window.PS_I18N[text]) || text;
        if (params) Object.keys(params).forEach(function (name) { out = out.split('{' + name + '}').join(String(params[name])); });
        return out;
    };
    document.querySelectorAll('[data-ps-lang]').forEach(function (button) {
        var on = button.getAttribute('data-ps-lang') === lang;
        button.classList.toggle('is-active', on);
        button.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
})();
