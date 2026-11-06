# Changelog

All notable changes are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-11-05

### Added

- Exception reporting to OpenObserve with the `ReportsException` handler, registered through Laravel's exception handler in `bootstrap/app.php`. Reported records include the message, stack trace, code, file, line, and timestamp.
- `should_report` filtering for exception reporting, which accepts an exception class whitelist or blacklist, or a container-resolved callable.
- Publishable `config/openobserve.php` configuration file, published with `php artisan vendor:publish --tag=openobserve-config`. Exception reporting can be disabled with `OPENOBSERVE_EXCEPTIONS_ENABLED`, and its stream changed with `OPENOBSERVE_EXCEPTIONS_STREAM`.
- Reusable `CreateOpenObserveClient` factory for building an `OpenObserveClient` from a configuration array.

### Changed

- Laravel 11 is no longer supported; the package requires Laravel 12 or 13.
- `CreateOpenObserveLogger` builds its client through `CreateOpenObserveClient`.
- The `openobserve:test-connection` command declares its signature and description with class properties instead of console attributes.

### Fixed

- Log entries use Laravel's `JsonFormatter` when available (Laravel 13 and later) and Monolog's `JsonFormatter` on Laravel 12, so formatting works on both supported versions.

## [1.1.0] - 2026-10-24

### Added

- `auth.token` configuration option for passing an OpenObserve ingest token's `Authorization` header value verbatim, instead of splitting it into `auth.username` and `auth.password`. It takes precedence over the username and password.

## [1.0.0] - 2026-10-17

### Added

- Initial release.
- `openobserve` logging driver for Laravel 11, 12, and 13.
- Support for the `custom` logging driver with `CreateOpenObserveLogger`.
- Configurable base URL, organization, stream, basic authentication, HTTP options, log level, and bubbling.
- Response verification with configurable opt-out.
- Single and batched log delivery to the `_json` and `_multi` endpoints.
- `openobserve:test-connection` Artisan command with custom config, stream, and debug options.
- Reusable `OpenObserveConnectionTester` for jobs and other application code.
- Laravel Boost AI guidelines.

[2.0.0]: https://github.com/SameOldNick/laravel-open-observe/compare/v1.1.0...v2.0.0
[1.1.0]: https://github.com/SameOldNick/laravel-open-observe/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/SameOldNick/laravel-open-observe/releases/tag/v1.0.0
