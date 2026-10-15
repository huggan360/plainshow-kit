# Changelog

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
