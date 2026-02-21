<?php

namespace ErrorWatch\Laravel\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Promise\PromiseInterface;

class HttpTransport
{
    protected Client $client;
    protected string $endpoint;
    protected string $apiKey;
    protected int $timeout;
    protected array $pendingEvents = [];

    public function __construct(
        string $endpoint,
        string $apiKey,
        int $timeout = 5
    ) {
        $this->endpoint = rtrim($endpoint, '/');
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;

        $this->client = new Client([
            'timeout' => $timeout,
            'connect_timeout' => 3,
            'http_errors' => false, // Don't throw on HTTP errors
        ]);
    }

    /**
     * Send an event to the ErrorWatch API.
     */
    public function send(array $payload): bool
    {
        try {
            $response = $this->client->post($this->getEventUrl(), [
                'headers' => $this->getHeaders(),
                'json' => $payload,
            ]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (GuzzleException $e) {
            // Log error but don't throw - we don't want to break the app
            error_log("[ErrorWatch] Failed to send event: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send an event asynchronously (non-blocking).
     */
    public function sendAsync(array $payload): void
    {
        try {
            $request = new Request(
                'POST',
                $this->getEventUrl(),
                $this->getHeaders(),
                json_encode($payload)
            );

            $this->client->sendAsync($request)->then(
                null,
                function (\Exception $e) {
                    error_log("[ErrorWatch] Async send failed: " . $e->getMessage());
                }
            );
        } catch (\Exception $e) {
            error_log("[ErrorWatch] Failed to create async request: " . $e->getMessage());
        }
    }

    /**
     * Send multiple events in a batch.
     */
    public function sendBatch(array $events): bool
    {
        if (empty($events)) {
            return true;
        }

        try {
            $response = $this->client->post($this->getBatchUrl(), [
                'headers' => $this->getHeaders(),
                'json' => ['events' => $events],
            ]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (GuzzleException $e) {
            error_log("[ErrorWatch] Failed to send batch: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send a log entry to the ErrorWatch logs endpoint.
     */
    public function sendLog(array $logEntry): bool
    {
        try {
            $response = $this->client->post($this->getLogsUrl(), [
                'headers' => $this->getHeaders(),
                'json' => $logEntry,
            ]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (GuzzleException $e) {
            error_log("[ErrorWatch] Failed to send log: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send a transaction (APM) to the ErrorWatch API.
     */
    public function sendTransaction(array $transaction): bool
    {
        try {
            $response = $this->client->post($this->getTransactionUrl(), [
                'headers' => $this->getHeaders(),
                'json' => $transaction,
            ]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (GuzzleException $e) {
            error_log("[ErrorWatch] Failed to send transaction: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if the transport is properly configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->endpoint) && !empty($this->apiKey);
    }

    /**
     * Get the event endpoint URL.
     */
    protected function getEventUrl(): string
    {
        return $this->endpoint . '/api/v1/event';
    }

    /**
     * Get the batch endpoint URL.
     */
    protected function getBatchUrl(): string
    {
        return $this->endpoint . '/api/v1/events/batch';
    }

    /**
     * Get the logs endpoint URL.
     */
    protected function getLogsUrl(): string
    {
        return $this->endpoint . '/api/v1/logs';
    }

    /**
     * Get the transaction endpoint URL.
     */
    protected function getTransactionUrl(): string
    {
        return $this->endpoint . '/api/v1/transaction';
    }

    /**
     * Get the common headers for all requests.
     */
    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'ErrorWatch-Laravel-SDK/1.0',
        ];
    }

    /**
     * Flush any pending events (for shutdown handlers).
     */
    public function flush(): bool
    {
        if (empty($this->pendingEvents)) {
            return true;
        }

        $result = $this->sendBatch($this->pendingEvents);
        $this->pendingEvents = [];

        return $result;
    }

    /**
     * Add an event to the pending batch.
     */
    public function queue(array $payload): void
    {
        $this->pendingEvents[] = $payload;
    }
}
