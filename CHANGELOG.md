# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-03-30

### Added
- Infrastructure monitoring, cron tracking, and extended alert channels
- Production-ready resilience with circuit breaker, retry handler, and deduplication
- CI/CD with PHPUnit test matrix (PHP 8.1-8.4 × Laravel 10-12)
- 102 tests, 219 assertions across unit and feature suites

### Changed
- Migrated all test annotations from `/** @test */` to PHP 8 `#[Test]` attributes (0 PHPUnit deprecations)
- Streamlined ErrorWatch configuration and MonitoringClient architecture
- Updated branch-alias to `1.0.x-dev`

### Fixed
- 7 critical security vulnerabilities from audit
- 5 functional bugs (P0)
- Removed references to non-existent `docs.errorwatch.io`
- Fixed logo URL pointing to old repository

### Security
- Patched XSS, injection, and auth bypass vulnerabilities

## [0.5.0] - 2026-03-30

### Added
- GitHub Actions CI/CD workflow
- CLAUDE.md with release process documentation

### Changed
- Updated branch-alias from `0.2.x-dev` to `0.5.x-dev`

## [0.2.0] - 2026-03-16

### Fixed
- **Breaking**: Replace `Authorization: Bearer {key}` with `X-API-Key: {key}` header across all HTTP transport calls
- Fixed incorrect auth header in session-replay Blade view fetch fallback

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

[1.0.0]: https://github.com/MakFly/errorwatch-sdk-laravel/compare/v0.5.0...v1.0.0
[0.5.0]: https://github.com/MakFly/errorwatch-sdk-laravel/compare/v0.2.0...v0.5.0
[0.2.0]: https://github.com/MakFly/errorwatch-sdk-laravel/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/MakFly/errorwatch-sdk-laravel/releases/tag/v0.1.0
