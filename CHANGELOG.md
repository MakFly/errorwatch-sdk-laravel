# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.5.0] - 2026-03-30

### Added
- Infrastructure monitoring, cron tracking, and extended alert channels
- Production-ready resilience with circuit breaker, retry handler, and deduplication
- CI/CD with PHPUnit test matrix (PHP 8.1-8.4 × Laravel 10-12)
- 102 tests, 219 assertions across unit and feature suites

### Changed
- Streamlined ErrorWatch configuration and MonitoringClient architecture
- Internationalization support via next-intl integration

### Fixed
- 7 critical security vulnerabilities from audit
- 5 functional bugs (P0)

### Security
- Patched XSS, injection, and auth bypass vulnerabilities

## [0.4.2] - 2026-03-16

### Changed
- Updated .gitignore and removed obsolete documentation files

## [0.4.1] - 2026-03-16

### Changed
- Added real SVG logo and updated README

## [0.4.0] - 2026-03-16

### Fixed
- Corrected API endpoints and added deprecation handler

## [0.2.0] - 2026-03-16

### Fixed
- **Breaking**: Replace `Authorization: Bearer {key}` with `X-API-Key: {key}` header across all HTTP transport calls to match the monitoring server's expected authentication scheme
- Fixed same incorrect auth header in the session-replay Blade view fetch fallback

## [0.1.0] - 2026-03-15

### Added
- Initial beta release
- Automatic exception capture via middleware
- Queue job failure tracking
- Eloquent query tracing with N+1 detection
- HTTP client request tracing
- Breadcrumbs for HTTP, DB, Auth, Console, Queue events
- User context capture from authenticated requests
- Monolog integration for log forwarding
- Session replay via Blade directive
- Artisan commands (`errorwatch:install`, `errorwatch:test`)
- Full Laravel 10/11/12 support

### Features
- `ErrorWatch::captureException()` - Capture exceptions manually
- `ErrorWatch::captureMessage()` - Capture messages
- `ErrorWatch::addBreadcrumb()` - Add custom breadcrumbs
- `ErrorWatch::setUser()` - Set user context
- `ErrorWatch::startTransaction()` - Start APM transactions
- `@errorwatchReplay()` Blade directive for session recording

### Configuration
- 30+ configuration options
- Environment variable support for all options
- Configurable sampling rates
- Excludable routes and channels

[0.5.0]: https://github.com/MakFly/errorwatch-sdk-laravel/compare/v0.4.2...v0.5.0
[0.4.2]: https://github.com/MakFly/errorwatch-sdk-laravel/compare/v0.4.1...v0.4.2
[0.4.1]: https://github.com/MakFly/errorwatch-sdk-laravel/compare/v0.4.0...v0.4.1
[0.4.0]: https://github.com/MakFly/errorwatch-sdk-laravel/compare/v0.2.0...v0.4.0
[0.2.0]: https://github.com/MakFly/errorwatch-sdk-laravel/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/MakFly/errorwatch-sdk-laravel/releases/tag/v0.1.0
