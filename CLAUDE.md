# CLAUDE.md — errorwatch-sdk-laravel

## Project Overview

Laravel SDK for [ErrorWatch](https://errorwatch.io) — self-hosted error monitoring & APM.

- **Package**: `errorwatch/sdk-laravel` (Packagist)
- **PHP**: ^8.1
- **Laravel**: 10 / 11 / 12
- **Tests**: PHPUnit (102 tests, 219 assertions)

## Commands

```bash
composer install
composer test              # Run PHPUnit
composer stan              # PHPStan analysis
composer cs:check          # Code style check
composer cs:fix            # Code style fix
```

## CI/CD

GitHub Actions workflow: `.github/workflows/tests.yml`

- Triggers: push to `main`, PR to `main`
- Matrix: PHP [8.1, 8.2, 8.3, 8.4] × Laravel [10, 11, 12] (10 combos)
- Excluded: PHP 8.1 + Laravel 11/12 (incompatible)

## Release Process

### 1. Update version references

```bash
# Update branch-alias in composer.json
# "dev-main": "X.Y.x-dev"
```

### 2. Update CHANGELOG.md and README.md

**CHANGELOG.md** — Follow [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) format:
- Add new version section at the top
- Document Added / Changed / Fixed / Security sections
- Add compare link at the bottom

**README.md** — Update:
- Version notice banner (top of file) with new version number
- Any changed features, requirements, or install instructions
- Badges will auto-update from Packagist/GitHub

### 3. Commit, tag, push

```bash
git add composer.json CHANGELOG.md README.md
git commit -m "chore: prepare release vX.Y.Z"
git tag -a vX.Y.Z -m "vX.Y.Z - Short description"
git push origin main --tags
```

### 4. Create GitHub Release

```bash
gh release create vX.Y.Z --title "vX.Y.Z" --notes "Release notes here"
```

### 5. Packagist

Packagist auto-updates from GitHub tags. Verify at:
https://packagist.org/packages/errorwatch/sdk-laravel

> **Important**: The tag format MUST be `vX.Y.Z` (with `v` prefix) for Packagist to detect it as a stable release. The `branch-alias` in `composer.json` must match the next dev version.

## Architecture

```
src/
├── ErrorWatchServiceProvider.php   # Service provider (auto-discovery)
├── Breadcrumbs/                    # Breadcrumb collection
├── Client/MonitoringClient.php     # Main client (13K) — sends events to API
├── Commands/                       # Artisan commands (install, test)
├── Context/UserContext.php          # Auth user capture
├── Facades/ErrorWatch.php           # Laravel facade
├── Http/Middleware/                  # Request middleware
├── Listeners/EventSubscriber.php    # Event subscriber
├── Logging/                         # Exception handler + Monolog logger
├── Services/                        # Query, Queue, HTTP, Deprecation listeners
├── Tracing/                         # APM spans & trace context
└── Transport/                       # HTTP transport, circuit breaker, retry
```

## Environment Variables

All config via `config/errorwatch.php`. Key env vars:
- `ERRORWATCH_ENABLED` — enable/disable SDK
- `ERRORWATCH_API_KEY` — project API key
- `ERRORWATCH_ENDPOINT` — API server URL
