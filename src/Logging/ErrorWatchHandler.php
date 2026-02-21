<?php

namespace ErrorWatch\Laravel\Logging;

use ErrorWatch\Laravel\Client\MonitoringClient;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class ErrorWatchHandler extends AbstractProcessingHandler
{
    protected MonitoringClient $client;
    protected array $excludedChannels;
    protected bool $captureContext;
    protected bool $captureExtra;

    public function __construct(
        MonitoringClient $client,
        string|int $level = Level::Warning,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);

        $this->client = $client;
        $this->excludedChannels = $client->getConfig('monolog.excluded_channels', []);
        $this->captureContext = $client->getConfig('monolog.capture_context', true);
        $this->captureExtra = $client->getConfig('monolog.capture_extra', true);
    }

    /**
     * Write the log record to ErrorWatch.
     */
    protected function write(LogRecord $record): void
    {
        if (!$this->client->isEnabled()) {
            return;
        }

        if (!$this->client->getConfig('monolog.enabled', true)) {
            return;
        }

        // Skip excluded channels
        $channel = $record->channel;
        if (in_array($channel, $this->excludedChannels, true)) {
            return;
        }

        $level = strtolower($record->level->getName());
        $message = $record->message;

        $context = [];

        // Add context if configured
        if ($this->captureContext && !empty($record->context)) {
            $context['context'] = $record->context;
        }

        // Add extra if configured
        if ($this->captureExtra && !empty($record->extra)) {
            $context['extra'] = $record->extra;
        }

        // Add channel info
        $context['extra'] = array_merge($context['extra'] ?? [], [
            'monolog_channel' => $channel,
        ]);

        // Capture the message
        $this->client->captureMessage($message, $level, $context);

        // Also send to logs endpoint if live logging is enabled
        if ($this->client->getConfig('logs.enabled', true)) {
            $this->sendLog($record);
        }
    }

    /**
     * Send log to the live logs endpoint.
     */
    protected function sendLog(LogRecord $record): void
    {
        $excludedLogChannels = $this->client->getConfig('logs.excluded_channels', []);

        if (in_array($record->channel, $excludedLogChannels, true)) {
            return;
        }

        $logEntry = [
            'level' => strtolower($record->level->getName()),
            'message' => $record->message,
            'timestamp' => $record->datetime->format('U.u'),
            'channel' => $record->channel,
            'context' => $this->captureContext ? $record->context : [],
            'extra' => $this->captureExtra ? $record->extra : [],
        ];

        $this->client->getTransport()->sendLog($logEntry);
    }
}
