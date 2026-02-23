<p align="center">
  <a href="https://errorwatch.io">
    <img src="https://raw.githubusercontent.com/MakFly/sentry-like/main/packages/sdk-laravel/docs/logo.svg" alt="ErrorWatch" width="120" height="120">
  </a>
</p>

<h1 align="center">ErrorWatch Laravel SDK</h1>

<p align="center">
  <strong>Self-hosted error monitoring & APM for Laravel applications</strong>
</p>

<p align="center">
  <a href="https://packagist.org/packages/errorwatch/sdk-laravel">
    <img src="https://img.shields.io/packagist/v/errorwatch/sdk-laravel.svg?style=flat-square" alt="Latest Version on Packagist">
  </a>
  <a href="https://packagist.org/packages/errorwatch/sdk-laravel">
    <img src="https://img.shields.io/packagist/dt/errorwatch/sdk-laravel.svg?style=flat-square" alt="Total Downloads">
  </a>
  <a href="https://github.com/MakFly/errorwatch-sdk-laravel/actions">
    <img src="https://img.shields.io/github/actions/workflow/status/MakFly/errorwatch-sdk-laravel/tests.yml?branch=main&style=flat-square" alt="Build Status">
  </a>
  <a href="LICENSE">
    <img src="https://img.shields.io/packagist/l/errorwatch/sdk-laravel.svg?style=flat-square" alt="License">
  </a>
  <a href="https://discord.gg/errorwatch">
    <img src="https://img.shields.io/discord/123456789?style=flat-square&color=7289da" alt="Discord">
  </a>
</p>

<p align="center">
  <a href="#installation">Installation</a> •
  <a href="#quick-start">Quick Start</a> •
  <a href="#features">Features</a> •
  <a href="#configuration">Configuration</a> •
  <a href="#documentation">Documentation</a>
</p>

---

> ⚠️ **Beta Notice**: This SDK is currently in beta (`v0.1.x`). The API is stable but may receive minor improvements before `v1.0.0`. We recommend testing in staging environments first.

---

## Why ErrorWatch?

**Self-hosted error monitoring** that doesn't cost a fortune. Unlike Sentry's per-event pricing, ErrorWatch runs on your infrastructure with predictable costs.

- 🔒 **Full Data Ownership** - All error data stays on your servers
- 💰 **Predictable Costs** - No per-event pricing surprises
- ⚡ **Zero Vendor Lock-in** - Standard SQL database, easy to export
- 🎯 **Laravel Native** - Built specifically for Laravel, by Laravel developers

## Features

### Error Monitoring
- ✅ **Automatic Exception Capture** - Unhandled exceptions are caught automatically
- ✅ **Stack Trace Parsing** - Clean, readable stack traces with context
- ✅ **Error Grouping** - Smart fingerprinting groups similar errors
- ✅ **Breadcrumbs** - 360° context for every error (HTTP, DB, Auth, Queue, Console)

### Performance Monitoring (APM)
- ✅ **Request Tracing** - End-to-end transaction tracking
- ✅ **Eloquent Query Spans** - See every database query with duration
- ✅ **N+1 Detection** - Automatic alerts for repeated queries
- ✅ **Slow Query Alerts** - Configurable threshold for query performance
- ✅ **HTTP Client Tracing** - Track outgoing API calls

### Laravel Integration
- ✅ **Queue Job Monitoring** - Failed jobs captured with full context
- ✅ **Auth Integration** - Automatic user context from authenticated requests
- ✅ **Console Commands** - Track Artisan command execution and failures
- ✅ **Monolog Handler** - Forward logs to ErrorWatch in real-time
- ✅ **Session Replay** - Replay user sessions to understand errors

### Developer Experience
- ✅ **Auto-Discovery** - Zero-configuration service provider
- ✅ **Artisan Commands** - `errorwatch:install` and `errorwatch:test`
- ✅ **Blade Directive** - `@errorwatchReplay()` for session recording
- ✅ **Facade** - Clean `ErrorWatch::captureException()` syntax
- ✅ **Full TypeScript Support** - For your frontend integration

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | ^8.1 |
| Laravel | 10.x, 11.x, 12.x |
| Guzzle | ^7.0 |

## Installation

Install via Composer:

```bash
composer require errorwatch/sdk-laravel:@beta
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=errorwatch-config
```

Add your ErrorWatch credentials to `.env`:

```env
ERRORWATCH_ENABLED=true
ERRORWATCH_ENDPOINT=https://api.errorwatch.io
ERRORWATCH_API_KEY=your-api-key-here
```

Test the installation:

```bash
php artisan errorwatch:test
```

That's it! Your Laravel application is now monitoring errors.

## Quick Start

### Automatic Error Capture

Errors are captured automatically via middleware. No code changes needed!

```php
// This exception will be automatically captured and sent to ErrorWatch
throw new RuntimeException('Something went wrong');
```

### Manual Exception Capture

```php
use ErrorWatch\Laravel\Facades\ErrorWatch;

try {
    // Risky operation
    ProcessPayment::dispatch($order);
} catch (PaymentFailedException $e) {
    // Capture with additional context
    ErrorWatch::captureException($e, [
        'extra' => [
            'order_id' => $order->id,
            'amount' => $order->total,
        ],
        'tags' => [
            'payment_provider' => 'stripe',
        ],
    ]);

    throw $e;
}
```

### Add Context with Breadcrumbs

```php
use ErrorWatch\Laravel\Facades\ErrorWatch;

// Track user actions
ErrorWatch::addBreadcrumb('User initiated checkout', 'user', [
    'cart_items' => 3,
    'cart_total' => 149.99,
]);

// When an error occurs later, you'll see the full context!
```

### Set User Context

```php
// In your authentication listener or middleware
ErrorWatch::setUser([
    'id' => auth()->id(),
    'email' => auth()->user()->email,
    'username' => auth()->user()->name,
]);

// Now every error includes who experienced it
```

### APM Transactions

```php
use ErrorWatch\Laravel\Facades\ErrorWatch;

// Track custom operations
$transaction = ErrorWatch::startTransaction('process-payout');
$transaction->setTag('payout.method', 'bank_transfer');

$span = $transaction->startChild('api-call', 'http.client');
// ... make external API call
$span->finish();

ErrorWatch::finishTransaction();
```

## Configuration

All configuration options in `config/errorwatch.php`:

```php
return [
    // Core
    'enabled' => env('ERRORWATCH_ENABLED', true),
    'endpoint' => env('ERRORWATCH_ENDPOINT'),
    'api_key' => env('ERRORWATCH_API_KEY'),
    'environment' => env('APP_ENV', 'production'),
    'release' => env('APP_VERSION'),

    // Session Replay
    'replay' => [
        'enabled' => env('ERRORWATCH_REPLAY_ENABLED', false),
        'sample_rate' => env('ERRORWATCH_REPLAY_SAMPLE_RATE', 0.1),
    ],

    // Breadcrumbs
    'breadcrumbs' => [
        'enabled' => true,
        'max_count' => 100,  // Keep last 100 breadcrumbs
    ],

    // User Context
    'user_context' => [
        'enabled' => true,
        'capture_ip' => true,
    ],

    // Queue Monitoring
    'queue' => [
        'enabled' => true,
        'capture_retries' => false,  // Only capture final failures
    ],

    // APM
    'apm' => [
        'enabled' => true,
        'eloquent' => [
            'enabled' => true,
            'log_queries' => true,
        ],
        'http_client' => [
            'enabled' => true,
        ],
        'n_plus_one_threshold' => 5,      // Alert after 5 identical queries
        'slow_query_threshold_ms' => 500,  // Alert queries > 500ms
        'excluded_routes' => ['telescope/*', 'horizon/*'],
    ],

    // Logging
    'monolog' => [
        'enabled' => true,
        'level' => 'warning',  // warning, error, critical, alert, emergency
    ],
];
```

## Session Replay

Enable session replay to see exactly what users did before an error:

```env
ERRORWATCH_REPLAY_ENABLED=true
ERRORWATCH_REPLAY_SAMPLE_RATE=0.1  # 10% of sessions
```

Add to your layout Blade file:

```blade
<!DOCTYPE html>
<html>
<head>
    <title>{{ config('app.name') }}</title>
    @errorwatchReplay()
</head>
<body>
    @yield('content')
</body>
</html>
```

## Artisan Commands

### Install Command

```bash
php artisan errorwatch:install

# Output:
# ✓ Configuration file published to config/errorwatch.php
#
# Next steps:
# 1. Add ERRORWATCH_ENDPOINT and ERRORWATCH_API_KEY to your .env
# 2. Run php artisan errorwatch:test to verify
```

### Test Command

```bash
# Send a test message
php artisan errorwatch:test

# Send a test exception
php artisan errorwatch:test --exception

# Custom message
php artisan errorwatch:test --message="Testing from production"
```

## Automatic Integrations

The SDK automatically integrates with these Laravel features:

| Integration | What it captures |
|-------------|-----------------|
| **HTTP Middleware** | Request/response timing, exceptions |
| **Eloquent ORM** | SQL queries, duration, N+1 patterns |
| **Queue Workers** | Job failures, retries, duration |
| **Auth** | User context on login/logout |
| **HTTP Client** | Outgoing API calls with timing |
| **Console** | Command execution, exit codes |
| **Monolog** | Log entries above threshold |

## Self-Hosting ErrorWatch

ErrorWatch is self-hosted. Deploy your own instance:

```bash
# Using Docker Compose
git clone https://github.com/MakFly/errorwatch
cd errorwatch
docker-compose up -d
```

See the [self-hosting documentation](https://docs.errorwatch.io/self-hosting) for detailed setup instructions.

## Documentation

- 📖 [Full Documentation](https://docs.errorwatch.io)
- 🚀 [Getting Started Guide](https://docs.errorwatch.io/laravel/getting-started)
- 🔧 [Configuration Reference](https://docs.errorwatch.io/laravel/configuration)
- 📊 [APM Guide](https://docs.errorwatch.io/laravel/apm)
- 🔌 [Self-Hosting](https://docs.errorwatch.io/self-hosting)

## Comparison with Sentry

| Feature | ErrorWatch | Sentry |
|---------|------------|--------|
| Self-hosted | ✅ | ⚠️ (Enterprise only) |
| Pricing | Free (self-hosted) | Per-event |
| Data ownership | 100% yours | Stored by Sentry |
| Laravel native | ✅ | ⚠️ (Generic SDK) |
| N+1 detection | ✅ | ❌ |
| Session replay | ✅ | 💰 Paid add-on |

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

```bash
# Run tests
composer test

# Run static analysis
composer stan
```

## Security

If you discover a security vulnerability, please email [security@errorwatch.io](mailto:security@errorwatch.io). All security vulnerabilities will be promptly addressed.

## License

The MIT License (MIT). See [LICENSE](LICENSE) for more information.

## Support

- 📧 Email: [support@errorwatch.io](mailto:support@errorwatch.io)
- 💬 Discord: [Join our community](https://discord.gg/errorwatch)
- 🐛 Issues: [GitHub Issues](https://github.com/MakFly/errorwatch-sdk-laravel/issues)
- 📖 Docs: [docs.errorwatch.io](https://docs.errorwatch.io)

---

<p align="center">
  Made with ❤️ by the <a href="https://errorwatch.io">ErrorWatch Team</a>
</p>
