<?php

namespace ErrorWatch\Laravel\Client;

use ErrorWatch\Laravel\Breadcrumbs\BreadcrumbManager;
use ErrorWatch\Laravel\Context\UserContext;
use ErrorWatch\Laravel\Tracing\RequestTracer;
use ErrorWatch\Laravel\Tracing\Span;
use ErrorWatch\Laravel\Tracing\TraceContext;
use ErrorWatch\Laravel\Transport\HttpTransport;
use Throwable;

class MonitoringClient
{
    protected array $config;
    protected HttpTransport $transport;
    protected BreadcrumbManager $breadcrumbs;
    protected UserContext $userContext;
    protected RequestTracer $tracer;
    protected ?Span $currentTransaction = null;

    public function __construct(array $config)
    {
        $this->config = $config;

        $this->transport = new HttpTransport(
            $config['endpoint'] ?? '',
            $config['api_key'] ?? '',
            5
        );

        $this->breadcrumbs = new BreadcrumbManager(
            $config['breadcrumbs']['max_count'] ?? 100
        );

        $this->userContext = new UserContext();
        $this->tracer = new RequestTracer();
    }

    /**
     * Check if the SDK is enabled.
     */
    public function isEnabled(): bool
    {
        return ($this->config['enabled'] ?? true) === true;
    }

    /**
     * Check if an event should be sampled based on the rate.
     */
    public function shouldSample(float $rate): bool
    {
        if ($rate >= 1.0) {
            return true;
        }

        if ($rate <= 0.0) {
            return false;
        }

        return (mt_rand(0, 100) / 100.0) <= $rate;
    }

    /**
     * Get a configuration value.
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Generate a unique event ID.
     */
    protected function generateEventId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Capture an exception.
     */
    public function captureException(Throwable $exception, array $context = []): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $eventId = $this->generateEventId();

        $event = [
            'event_id' => $eventId,
            'type' => 'exception',
            'timestamp' => microtime(true),
            'environment' => $this->config['environment'] ?? 'production',
            'release' => $this->config['release'] ?? null,
            'exception' => $this->formatException($exception),
            'breadcrumbs' => $this->breadcrumbs->all(),
            'user' => $this->userContext->getUser(),
            'contexts' => [
                'runtime' => $this->getRuntimeContext(),
                'os' => $this->getOsContext(),
            ],
            'tags' => $context['tags'] ?? [],
            'extra' => $context['extra'] ?? [],
        ];

        // Add trace context if there's an active transaction
        if ($this->currentTransaction !== null) {
            $event['contexts']['trace'] = [
                'trace_id' => $this->currentTransaction->getTraceId(),
                'span_id' => $this->currentTransaction->getSpanId(),
            ];
        }

        // Apply before_send callback
        if (isset($this->config['before_send']) && is_callable($this->config['before_send'])) {
            $event = call_user_func($this->config['before_send'], $event);
            if ($event === null) {
                return null;
            }
        }

        $this->transport->send($event);

        return $eventId;
    }

    /**
     * Capture a message.
     */
    public function captureMessage(string $message, string $level = 'info', array $context = []): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $eventId = $this->generateEventId();

        $event = [
            'event_id' => $eventId,
            'type' => 'message',
            'timestamp' => microtime(true),
            'environment' => $this->config['environment'] ?? 'production',
            'release' => $this->config['release'] ?? null,
            'message' => $message,
            'level' => $level,
            'breadcrumbs' => $this->breadcrumbs->all(),
            'user' => $this->userContext->getUser(),
            'contexts' => [
                'runtime' => $this->getRuntimeContext(),
            ],
            'tags' => $context['tags'] ?? [],
            'extra' => $context['extra'] ?? [],
        ];

        $this->transport->send($event);

        return $eventId;
    }

    /**
     * Capture a custom event.
     */
    public function captureEvent(array $event): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        // Add defaults
        $event = array_merge([
            'timestamp' => microtime(true),
            'environment' => $this->config['environment'] ?? 'production',
            'breadcrumbs' => $this->breadcrumbs->all(),
            'user' => $this->userContext->getUser(),
        ], $event);

        $this->transport->send($event);

        return $event['event_id'] ?? null;
    }

    /**
     * Add a breadcrumb.
     */
    public function addBreadcrumb(string $message, string $type = 'default', array $data = []): void
    {
        if (!$this->isEnabled() || !($this->config['breadcrumbs']['enabled'] ?? true)) {
            return;
        }

        $this->breadcrumbs->add($message, $type, 'default', $data);
    }

    /**
     * Get all breadcrumbs.
     */
    public function getBreadcrumbs(): array
    {
        return $this->breadcrumbs->all();
    }

    /**
     * Clear all breadcrumbs.
     */
    public function clearBreadcrumbs(): void
    {
        $this->breadcrumbs->clear();
    }

    /**
     * Set the current user.
     */
    public function setUser(array $user): void
    {
        if (!($this->config['user_context']['enabled'] ?? true)) {
            return;
        }

        $this->userContext->setUser($user);
    }

    /**
     * Get the current user.
     */
    public function getUser(): ?array
    {
        return $this->userContext->getUser();
    }

    /**
     * Clear the user context.
     */
    public function clearUser(): void
    {
        $this->userContext->clearUser();
    }

    /**
     * Start a new transaction for APM.
     */
    public function startTransaction(string $name): Span
    {
        $traceContext = TraceContext::generate();
        $this->currentTransaction = new Span($name, $traceContext);

        return $this->currentTransaction;
    }

    /**
     * Get the current transaction.
     */
    public function getCurrentTransaction(): ?Span
    {
        return $this->currentTransaction;
    }

    /**
     * Finish the current transaction and send it.
     */
    public function finishTransaction(): ?array
    {
        if ($this->currentTransaction === null) {
            return null;
        }

        $this->currentTransaction->finish();
        $transactionData = $this->currentTransaction->toArray();

        // Send transaction data
        if ($this->shouldSample($this->config['apm']['sample_rate'] ?? 1.0)) {
            $this->transport->sendTransaction($transactionData);
        }

        $this->currentTransaction = null;

        return $transactionData;
    }

    /**
     * Get the breadcrumb manager.
     */
    public function getBreadcrumbManager(): BreadcrumbManager
    {
        return $this->breadcrumbs;
    }

    /**
     * Get the user context.
     */
    public function getUserContext(): UserContext
    {
        return $this->userContext;
    }

    /**
     * Get the HTTP transport.
     */
    public function getTransport(): HttpTransport
    {
        return $this->transport;
    }

    /**
     * Format an exception for the API.
     */
    protected function formatException(Throwable $exception): array
    {
        return [
            'type' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'stacktrace' => $this->formatStackTrace($exception->getTrace()),
        ];
    }

    /**
     * Format a stack trace.
     */
    protected function formatStackTrace(array $trace): array
    {
        return array_map(function ($frame) {
            return [
                'file' => $frame['file'] ?? '[internal]',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? null,
                'class' => $frame['class'] ?? null,
                'type' => $frame['type'] ?? null,
            ];
        }, $trace);
    }

    /**
     * Get runtime context.
     */
    protected function getRuntimeContext(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'sapi' => PHP_SAPI,
        ];
    }

    /**
     * Get OS context.
     */
    protected function getOsContext(): array
    {
        return [
            'name' => PHP_OS_FAMILY,
            'version' => php_uname('r'),
        ];
    }
}
