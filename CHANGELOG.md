# Changelog

All notable changes are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[1.1.0]: https://github.com/SameOldNick/laravel-open-observe/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/SameOldNick/laravel-open-observe/releases/tag/v1.0.0
