# ErrorWatch Laravel SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/errorwatch/sdk-laravel.svg?style=flat-square)](https://packagist.org/packages/errorwatch/sdk-laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/errorwatch/sdk-laravel.svg?style=flat-square)](https://packagist.org/packages/errorwatch/sdk-laravel)
[![License](https://img.shields.io/packagist/l/errorwatch/sdk-laravel.svg?style=flat-square)](https://packagist.org/packages/errorwatch/sdk-laravel)

A comprehensive Laravel SDK for [ErrorWatch](https://errorwatch.io) - self-hosted error monitoring and APM for modern applications.

> ⚠️ **Beta Version**: This SDK is currently in beta (`v0.1.x`). The API may change before the stable `v1.0.0` release.

## Features

- ✅ **Automatic Exception Capture** - Catches unhandled exceptions automatically
- ✅ **Queue Job Monitoring** - Tracks failed jobs and retries
- ✅ **Eloquent Query Tracing** - APM spans for database queries with N+1 detection
- ✅ **HTTP Client Tracing** - Tracks outgoing HTTP requests
- ✅ **Breadcrumbs** - Automatic context collection (HTTP, DB, Auth, Console, Queue)
- ✅ **User Context** - Captures authenticated user information
- ✅ **Monolog Integration** - Forwards logs to ErrorWatch
- ✅ **Session Replay** - Browser-side session recording
- ✅ **Laravel 10/11/12 Support** - Works with all modern Laravel versions

## Installation

### Beta Installation (Recommended for Early Adopters)

Since this package is in beta, install it with the `@beta` flag:

```bash
composer require errorwatch/sdk-laravel:@beta
```

Or for development:

```bash
composer require errorwatch/sdk-laravel:dev-main --prefer-dist
```

## Configuration

### 1. Publish the configuration file

```bash
php artisan vendor:publish --tag=errorwatch-config
```

### 2. Add environment variables

Add the following to your `.env` file:

```env
ERRORWATCH_ENABLED=true
ERRORWATCH_ENDPOINT=https://api.errorwatch.io
ERRORWATCH_API_KEY=your-api-key-here
```

### 3. Test the installation

```bash
php artisan errorwatch:test
```

## Quick Start

### Basic Usage

```php
use ErrorWatch\Laravel\Facades\ErrorWatch;

// Capture an exception
try {
    // Your code that might throw
} catch (\Throwable $e) {
    ErrorWatch::captureException($e);
}

// Capture a message
ErrorWatch::captureMessage('Something happened', 'info');

// Add context breadcrumbs
ErrorWatch::addBreadcrumb('User clicked checkout', 'user', ['cart_id' => 123]);

// Set user context
ErrorWatch::setUser([
    'id' => auth()->id(),
    'email' => auth()->user()->email,
]);
```

### Automatic Integrations

The SDK automatically integrates with:

| Integration | Description |
|-------------|-------------|
| **HTTP Middleware** | Captures request exceptions and creates transactions |
| **Eloquent ORM** | Query tracing, slow query detection, N+1 detection |
| **Queue Workers** | Job failure tracking with full context |
| **Console Commands** | Command execution tracking and exit code capture |
| **Authentication** | User context and security event breadcrumbs |
| **HTTP Client** | Outgoing request tracing |
| **Monolog** | Log forwarding to ErrorWatch |

## Configuration Reference

All configuration options in `config/errorwatch.php`:

```php
return [
    // Core settings
    'enabled' => env('ERRORWATCH_ENABLED', true),
    'endpoint' => env('ERRORWATCH_ENDPOINT'),
    'api_key' => env('ERRORWATCH_API_KEY'),
    'environment' => env('APP_ENV', 'production'),
    'release' => env('APP_VERSION'),

    // Session replay
    'replay' => [
        'enabled' => env('ERRORWATCH_REPLAY_ENABLED', false),
        'sample_rate' => env('ERRORWATCH_REPLAY_SAMPLE_RATE', 0.1),
    ],

    // Breadcrumbs
    'breadcrumbs' => [
        'enabled' => true,
        'max_count' => 100,
    ],

    // User context
    'user_context' => [
        'enabled' => true,
        'capture_ip' => true,
    ],

    // Console commands
    'console' => [
        'enabled' => true,
        'capture_exit_codes' => true,
    ],

    // Queue/Jobs
    'queue' => [
        'enabled' => true,
        'capture_retries' => false,
    ],

    // APM
    'apm' => [
        'enabled' => true,
        'request_tracking' => true,
        'eloquent' => [
            'enabled' => true,
            'log_queries' => true,
        ],
        'http_client' => [
            'enabled' => true,
            'capture_errors_as_breadcrumbs' => true,
        ],
        'n_plus_one_threshold' => 5,
        'slow_query_threshold_ms' => 500,
        'excluded_routes' => ['telescope/*', 'horizon/*'],
    ],

    // Monolog
    'monolog' => [
        'enabled' => true,
        'level' => 'warning',
        'excluded_channels' => [],
    ],
];
```

## Session Replay

Enable session replay in your `.env`:

```env
ERRORWATCH_REPLAY_ENABLED=true
ERRORWATCH_REPLAY_SAMPLE_RATE=0.1
```

Add the Blade directive to your layout:

```blade
<!DOCTYPE html>
<html>
<head>
    @errorwatchReplay()
</head>
<body>
    @yield('content')
</body>
</html>
```

## Custom Logging

Use the ErrorWatch logging channel:

```php
use Illuminate\Support\Facades\Log;

Log::channel('errorwatch')->warning('Something concerning', [
    'user_id' => 123,
    'action' => 'payment_failed',
]);
```

## APM Transactions

Manually track performance:

```php
use ErrorWatch\Laravel\Facades\ErrorWatch;

// Start a transaction
$transaction = ErrorWatch::startTransaction('process-payment');
$transaction->setTag('payment_method', 'stripe');

// Start child spans
$span = $transaction->startChild('api-call', 'http.client');
// ... make API call
$span->finish();

// Finish transaction
ErrorWatch::finishTransaction();
```

## Artisan Commands

### Install Command

```bash
php artisan errorwatch:install
```

Publishes the configuration file and displays setup instructions.

### Test Command

```bash
php artisan errorwatch:test
```

Sends a test event to verify your configuration.

Options:
- `--message=Your message` - Custom message to send
- `--exception` - Send a test exception instead of a message

## Laravel Version Compatibility

| Laravel | PHP | SDK Version |
|---------|-----|-------------|
| 10.x | 8.1, 8.2 | v0.1.x |
| 11.x | 8.2, 8.3 | v0.1.x |
| 12.x | 8.2, 8.3 | v0.1.x |

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email security@errorwatch.io instead of using the issue tracker.

## Credits

- [ErrorWatch](https://github.com/errorwatch)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Support

- **Documentation**: https://docs.errorwatch.io
- **GitHub Issues**: https://github.com/MakFly/errorwatch-sdk-laravel/issues
- **Email**: support@errorwatch.io
