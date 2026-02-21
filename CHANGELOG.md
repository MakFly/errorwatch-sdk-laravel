# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2024-XX-XX

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

[0.1.0]: https://github.com/MakFly/errorwatch-sdk-laravel/releases/tag/v0.1.0
