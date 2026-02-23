<?php

namespace ErrorWatch\Laravel\Logging;

use ErrorWatch\Laravel\Client\MonitoringClient;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Throwable;

class ErrorWatchExceptionHandler implements ExceptionHandler
{
    protected ExceptionHandler $handler;
    protected ErrorWatchLogger $logger;

    public function __construct(ExceptionHandler $handler, MonitoringClient $client, array $config)
    {
        $this->handler = $handler;
        $this->logger = new ErrorWatchLogger($client, $config);
    }

    public function report(Throwable $e): void
    {
        if ($this->shouldReport($e)) {
            $this->logger->handleException($e);
        }

        $this->handler->report($e);
    }

    public function shouldReport(Throwable $e): bool
    {
        return $this->handler->shouldReport($e);
    }

    public function render($request, Throwable $e)
    {
        return $this->handler->render($request, $e);
    }

    public function renderForConsole($output, Throwable $e)
    {
        return $this->handler->renderForConsole($output, $e);
    }
}
