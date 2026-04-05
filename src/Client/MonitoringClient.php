<?php

declare(strict_types=1);

namespace ErrorWatch\Laravel\Client;

use ErrorWatch\Laravel\Breadcrumbs\BreadcrumbManager;
use ErrorWatch\Laravel\Context\UserContext;
use ErrorWatch\Laravel\Tracing\RequestTracer;
use ErrorWatch\Laravel\Tracing\Span;
use ErrorWatch\Laravel\Tracing\TraceContext;
use ErrorWatch\Laravel\Transport\HttpTransport;
use SplObjectStorage;
use Throwable;

class MonitoringClient
{
    public const VERSION = '0.2.0';

    protected array $config;
    protected HttpTransport $transport;
    protected BreadcrumbManager $breadcrumbs;
    protected UserContext $userContext;
    protected RequestTracer $tracer;
    protected ?Span $currentTransaction = null;

    /** @var SplObjectStorage<Throwable, true> Tracks already-captured exceptions to prevent duplicates */
    private SplObjectStorage $capturedExceptions;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->capturedExceptions = new SplObjectStorage();

        $this->transport = new HttpTransport(
            $config['endpoint'] ?? '',
            $config['api_key'] ?? '',
            $config['transport']['timeout'] ?? 5,
            $config['transport']['circuit_breaker_threshold'] ?? 5,
            $config['transport']['circuit_breaker_cooldown'] ?? 60,
            $config['transport']['retry_attempts'] ?? 2,
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
        $value = $this->config;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
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
     * Returns null if disabled, already captured, or filtered by before_send.
     */
    public function captureException(Throwable $exception, array $context = []): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        // Deduplicate: skip if this exact exception instance was already captured
        if ($this->capturedExceptions->contains($exception)) {
            return null;
        }

        // Prevent self-capture: skip exceptions originating from the SDK itself
        if ($this->isInternalException($exception)) {
            return null;
        }

        $this->capturedExceptions->attach($exception);

        $eventId = $this->generateEventId();

        $stackString = $exception->getTraceAsString();

        $event = [
            'message' => get_class($exception) . ': ' . $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'stack' => $stackString,
            'env' => $this->config['environment'] ?? 'production',
            'level' => $this->mapExceptionLevel($exception),
            'created_at' => (int)(microtime(true) * 1000),
            'breadcrumbs' => $this->formatBreadcrumbsForApi(),
            'release' => $this->config['release'] ?? null,
            'user_id' => $this->userContext->getUser()['id'] ?? null,
        ];

        // Merge context tags and extra data into the payload
        if (!empty($context['tags'] ?? [])) {
            $event['tags'] = $context['tags'];
        }
        if (!empty($context['extra'] ?? [])) {
            $event['extra'] = $context['extra'];
        }
        if (!empty($context['url'] ?? null)) {
            $event['url'] = $context['url'];
        }
        if (!empty($context['status_code'] ?? null)) {
            $event['status_code'] = $context['status_code'];
        }

        // Add trace context if there's an active transaction
        if ($this->currentTransaction !== null) {
            $event['session_id'] = $this->currentTransaction->getTraceId();
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
     * Map exception severity to API level enum.
     */
    protected function mapExceptionLevel(Throwable $exception): string
    {
        if ($exception instanceof \Error) {
            return 'fatal';
        }

        return 'error';
    }

    /**
     * Format breadcrumbs to match the API schema.
     * API expects: { timestamp, category, type?, level?, message?, data? }
     */
    protected function formatBreadcrumbsForApi(): array
    {
        $breadcrumbs = $this->breadcrumbs->all();

        return array_map(function (array $breadcrumb) {
            $validCategories = ['ui', 'navigation', 'console', 'http', 'user'];
            $category = $breadcrumb['category'] ?? 'user';
            if (!in_array($category, $validCategories, true)) {
                $category = 'user';
            }

            return [
                'timestamp' => (int)($breadcrumb['timestamp'] ?? microtime(true) * 1000),
                'category' => $category,
                'type' => $breadcrumb['type'] ?? null,
                'level' => $breadcrumb['level'] ?? 'info',
                'message' => $breadcrumb['message'] ?? null,
                'data' => $breadcrumb['data'] ?? null,
            ];
        }, $breadcrumbs);
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

        // Build a synthetic stack trace from the current call point
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
        $caller = $backtrace[1] ?? $backtrace[0] ?? [];
        foreach ($backtrace as $frame) {
            $framePath = $frame['file'] ?? '';
            if ($framePath !== '' && !str_contains($framePath, '/vendor/')) {
                $caller = $frame;
                break;
            }
        }
        $file = $caller['file'] ?? 'unknown';
        $line = $caller['line'] ?? 0;

        // Build stack string from backtrace
        $stackLines = [];
        foreach ($backtrace as $i => $frame) {
            $f = $frame['file'] ?? '[internal]';
            $l = $frame['line'] ?? 0;
            $fn = isset($frame['class'])
                ? $frame['class'] . ($frame['type'] ?? '::') . ($frame['function'] ?? '')
                : ($frame['function'] ?? '');
            $stackLines[] = "#$i $f($l): $fn()";
        }

        $validLevels = ['fatal', 'error', 'warning', 'info', 'debug'];
        $apiLevel = in_array($level, $validLevels, true) ? $level : 'error';

        $event = [
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'stack' => implode("\n", $stackLines),
            'env' => $this->config['environment'] ?? 'production',
            'level' => $apiLevel,
            'created_at' => (int)(microtime(true) * 1000),
            'breadcrumbs' => $this->formatBreadcrumbsForApi(),
            'release' => $this->config['release'] ?? null,
            'user_id' => $this->userContext->getUser()['id'] ?? null,
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
            'breadcrumbs' => $this->formatBreadcrumbsForApi(),
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
            $this->transport->sendTransaction($transactionData, $this->config['environment'] ?? 'production');
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
     * Clear the captured exceptions tracker (for Octane between-request reset).
     */
    public function clearCapturedExceptions(): void
    {
        $this->capturedExceptions = new SplObjectStorage();
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

    /**
     * Check if an exception originates from the SDK itself.
     * Prevents self-capture loops where SDK errors get reported as app errors.
     */
    protected function isInternalException(Throwable $exception): bool
    {
        // Check the exception class namespace (SDK exception types only, not app exceptions)
        $class = get_class($exception);
        if (str_starts_with($class, 'ErrorWatch\\Laravel\\') && !str_starts_with($class, 'ErrorWatch\\Laravel\\Tests\\')) {
            return true;
        }

        // Check if the exception was thrown from an SDK vendor file
        $file = $exception->getFile();
        if (str_contains($file, 'vendor/errorwatch/sdk-laravel/src/')) {
            return true;
        }

        return false;
    }
}
