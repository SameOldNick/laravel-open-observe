# Changelog

All notable changes are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[1.0.0]: https://github.com/SameOldNick/laravel-open-observe/releases/tag/v1.0.0
