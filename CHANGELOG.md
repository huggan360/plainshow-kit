# Changelog

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
