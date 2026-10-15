# PlainShow Kit

Shared by every PlainShow app: single sign-on, token checks, access rules, and the UI kit.

| Part | Path | Used by |
|---|---|---|
| PHP SDK | `src/` (`PlainShow\Kit\...`) | login, core, blog, LaTeX, web |
| UI CSS, fonts, icons, brand | `ui/` | every app (PHP apps link `ui/css/plainshow.css`) |
| UI behaviour for server-rendered pages | `ui/js/plainshow.js` | blog, LaTeX, login |

Install with Composer, pinned to a tag:

```json
"repositories": [{ "type": "vcs", "url": "https://github.com/huggan360/plainshow-kit.git" }],
"require": { "plainshow/kit": "^0.1" }
```

## Single sign-on in a plain PHP app

```php
session_start();
$auth = new PlainShow\Kit\PhpSessionAuth([
    'issuer' => 'https://login.plainshow.se',
    'client_id' => '...',
    'client_secret' => '...',
    'redirect_uri' => 'https://blog.plainshow.se/auth/callback',
    'extension' => 'blog',
], onLogin: fn (PlainShow\Kit\Identity $user) => /* sync local profile */ null);

$user = $auth->requireUser();       // signs in if needed, checks access to this extension
$user->can('blog.post.moderate');   // capability check
```

The app exposes `/auth/callback`, `/auth/logout` and `/auth/frontchannel`, each calling the matching method.

## Design rules

- Boxes are an outer shell around an inner panel whose radius is the outer radius minus the padding (`.ps-frame` and `.ps-frame__panel`). Colours and spacing come from claude.ai/downloads.
- Warm neutrals with PlainShow orange (`--ps-accent`, #fc5000, from the logo) as the primary colour: primary buttons, focus, active navigation, meters and the gradient.
- The painted wordmark (`brand/plainshow-logo.webp`, `.ps-brand__logo`) always stands alone, with no icon beside it. The triangle (`brand/plainshow-icon.webp`, `.ps-brand__mark`) is the small mark for tight spaces and favicons.
- The serif (`--ps-font-text`, Source Serif 4) is the main face for reading, headings and navigation, set slightly heavier than regular. Outfit (`--ps-font-ui`) is the one secondary face, for interface text: buttons, badges, labels, inputs, table headers. Neither is used below its regular weight. IBM Plex Mono is for code.
- Page titles sit on the page (`.ps-page-head__title`), not in the top bar.
- One type scale for every app: `--ps-text-xs` (12 px), `sm` (13.5), `md` (15.5, body), `lg` (17.5), `xl` (22), `2xl` (28), `3xl` (36), also as `.ps-text-*` classes. `vendor/bin/check-type <css dirs>` fails on any other font size.
- Apps that navigate across the top (the blog) use `.ps-topnav`; apps with a sidebar use `.ps-shell`. `.ps-hero` keeps photo backgrounds legible.
- The account menu and avatar come from the blog's original styling.
- No middle dots in interface text.

## Checks

```sh
php tests/run.php
```
