# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - 2026-05-11

### Added

- **Four-character currency codes** on shipping rules (ISO 4217 and extended codes), including migration and admin create/edit copy.
- **Uninstall cleanup**: `ATUShippingUninstallCommand` can remove migration rows from the `migrations` table for a cleaner uninstall.

### Changed

- **Laravel 13** and updated Composer constraints; **Livewire 4** admin routes and sidebar references; optional **ATU Multi-Currency** (^2.0) noted in README and `suggest`.
- **Migrations** are loaded from the package via `loadMigrationsFrom()` (no copies into the app’s `database/migrations`); install/uninstall docs and command output updated accordingly.
- **Install flow** (`atushipping:install`): clearer env handling, API/admin route injection, Flux sidebar hints, and improved feedback.
- **README**: logging behavior, environment variables, rule evaluation, Multi-Currency integration, and general structure.

### Removed

- Outdated standalone release note files (replaced by this changelog).

[1.3.0]: https://github.com/vormia-folks/atu-shipping/compare/v1.2.1...v1.3.0
