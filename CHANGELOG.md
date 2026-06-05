# Changelog

All notable changes to LiteNote are documented here.

## [1.0.0] - 2026-06-06

### Added
- Reintroduced LiteNote as a clean PHP 8.5 + SQLite personal publishing system.
- Added a root-level application layout with `index.php`, `config.php`, `.env`, `themes/`, `plugins/`, `uploads/`, and `runtime/`.
- Added independent front-end themes. Each theme owns its templates, `functions.php`, `inc/`, `pages/`, and `assets/main.css` / `assets/main.js`.
- Added Ember as the default built-in theme and Kami as an optional editorial theme.
- Added Caddy local development configuration via `Caddyfile`.
- Added X card support, music publishing, talk posts, activity sync, RSS, comments, and admin management pages.

### Changed
- Reset the public project version to `1.0.0`.
- Removed the legacy `public/`, `views/`, `routes/`, `storage/`, `content/`, and `router.php` layout.
- Moved framework code into `core/`, admin UI into `admin/`, theme files into root `themes/`, plugins into root `plugins/`, uploads into root `uploads/`, and runtime data into `runtime/storage/`.
- Moved configuration to root `config.php`; environment secrets remain in root `.env`.
- Removed the web installer pages. Schema creation and upgrades now use the CLI `Installer::install()` service.
- Removed the old `default` theme. Ember is now the system default theme.

### Removed
- Removed outdated release history from the changelog. This is the new baseline release.
- Removed old demo feeds and PHP built-in-server router support.
