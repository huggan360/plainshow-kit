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
- Warm neutrals, with the PlainShow gradient reserved for emphasis.
- `--ps-font-sans`, `--ps-font-serif` and `--ps-font-mono` are the only font settings. The defaults are free faces: Hanken Grotesk, Newsreader and IBM Plex Mono.
- The account menu and avatar come from the blog's original styling.
- No middle dots in interface text.

## Checks

```sh
php tests/run.php
```
