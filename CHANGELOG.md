# Changelog

## 0.6.0

- `Signed`: requests one PlainShow app makes to another on someone's behalf (the API gateway, personal API tokens), signed over method, path, time, person, context and body. `Signed::identity` turns a verified request into an `Identity`.

## 0.5.1

- Security: wildcard rules such as `*.view` no longer match core's own capabilities. Before this, the default member and guest rules granted `core.audit.view` and `core.system.view`, so they could read the audit log. Core capabilities are now granted by name only.

## 0.5.0

- Outfit is now the face for all text (`--ps-font-text`). The serif moves to titles and smaller titles through `--ps-font-title`: headings, page, frame, modal and widget titles, `.ps-title` and `.ps-heading`. `.ps-reading` and `.ps-serif` keep the serif for long reading text such as blog posts.

## 0.4.0

- `Widgets::sign` and `Widgets::verify`: core asks extensions for dashboard widget data with a signed, short-lived request naming the person.

## 0.3.1

- Eyebrows are muted again; `.ps-eyebrow--accent` makes one orange.

## 0.3.0

- New brand: the orange painted wordmark (`brand/plainshow-logo.webp`) and triangle mark (`brand/plainshow-icon.webp`), with new favicons. `.ps-brand__logo` and `.ps-brand__mark` replace the old icon and text wordmark.
- PlainShow orange (`--ps-accent`) is the primary colour. Primary buttons, focus rings, active navigation, tabs, loaders, meters and the gradient use it instead of blue and purple. `.ps-btn--light` keeps the white button.
- Source Serif 4 replaces Newsreader as the main face. It sits centred next to icons and reads heavier; body text is set at weight 430.
- Outfit replaces Hanken Grotesk as the secondary face.
- `.ps-page-head__title`, `__text` and `__actions` for page titles on the page.

## 0.2.0

- The serif is now the main face, including body text. The sans is kept for small interface text.
- A seven-step type scale (`--ps-text-xs` to `--ps-text-3xl`) replaces every fixed text size, with `.ps-text-*` classes and the `check-type` lint.
- Top navigation (`.ps-topnav`) with a phone sheet, page container (`.ps-page`) and photo hero (`.ps-hero`).

## 0.1.1

- Base element rules use `:where()`, so buttons styled on links keep their colours.

## 0.1.0

- OIDC client with PKCE, state and nonce, and RS256 verification against the login service's JWKS.
- `PhpSessionAuth` for single sign-on in plain PHP apps, including front-channel logout.
- `Access` rules for services and capabilities, shared by login and core.
- UI kit: tokens, `ps-frame` nested boxes, buttons, badges, list rows, forms, app shell, account menu, avatar, table, modal, widgets and loaders.
- Self-hosted fonts (Hanken Grotesk, Newsreader, IBM Plex Mono, Cal Sans, plus specimen candidates) and boxicons.
