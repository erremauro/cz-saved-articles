# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] 2026-10-08
### Added
- `czsa:saved-change` event dispatched on `document` after the bookmark is toggled on an article (`detail: { postId, saved }`), so themes can reflect the saved state in real time.

## [1.1.0] 2026-06-12
### Added
- Dedicated database table `czsa_saved_articles` replacing serialized usermeta storage.
- `CZSA_DB` class (`inc/db.php`) with `save()`, `remove()`, `is_saved()`, `get_all()` and migration helpers.
- `CZSA_DB_VERSION` constant to track schema version independently from plugin version.
- `maybe_upgrade()` hook on `init`: creates the table and migrates all existing usermeta records automatically on first run after update.
- `uninstall.php` — drops the table and removes plugin options and legacy usermeta on uninstall.
### Changed
- `toggle()` REST endpoint now uses `CZSA_DB` instead of reading/writing the full usermeta array.
- Updated author metadata to Roberto Mauro.

## [1.0.2] 2026-05-29
### Changed
- Refine the card styles.

## [1.0.1] 2026-05-27
### Fixed
- Fix an issue with "no articles" page layout.

## [1.0.0] 2026-05-27
### Added
- First Release!


[Unreleased]: https://github.com/erremauro/cz-saved-articles/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/erremauro/cz-saved-articles/releases/tag/v1.1.0
[1.0.2]: https://github.com/erremauro/cz-saved-articles/releases/tag/v1.0.2
[1.0.1]: https://github.com/erremauro/cz-saved-articles/releases/tag/v1.0.1
[1.0.0]: https://github.com/erremauro/cz-saved-articles/releases/tag/v1.0.0
