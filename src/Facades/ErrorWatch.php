<?php

namespace ErrorWatch\Laravel\Facades;

use ErrorWatch\Laravel\Client\MonitoringClient;
use ErrorWatch\Laravel\Tracing\Span;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool isEnabled()
 * @method static bool shouldSample(float $rate)
 * @method static mixed getConfig(string $key, mixed $default = null)
 * @method static string|null captureException(\Throwable $exception, array $context = [])
 * @method static string|null captureMessage(string $message, string $level = 'info', array $context = [])
 * @method static string|null captureEvent(array $event)
 * @method static void addBreadcrumb(string $message, string $type = 'default', array $data = [])
 * @method static array getBreadcrumbs()
 * @method static void clearBreadcrumbs()
 * @method static void setUser(array $user)
 * @method static array|null getUser()
 * @method static void clearUser()
 * @method static Span startTransaction(string $name)
 * @method static Span|null getCurrentTransaction()
 * @method static array|null finishTransaction()
 *
 * @see \ErrorWatch\Laravel\Client\MonitoringClient
 */
class ErrorWatch extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return MonitoringClient::class;
    }

    /**
     * Configure the SDK with custom options.
     */
    public static function configure(array $options): void
    {
        /** @var MonitoringClient $client */
        $client = app(MonitoringClient::class);

        foreach ($options as $key => $value) {
            // Update config dynamically
            config(["errorwatch.{$key}" => $value]);
        }
    }

    /**
     * Capture the last error and clear it.
     */
    public static function captureLastError(): ?string
    {
        $error = error_get_last();

        if ($error === null) {
            return null;
        }

        return static::captureMessage(
            $error['message'],
            'error',
            [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type'],
            ]
        );
    }
}
