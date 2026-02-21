<?php

namespace ErrorWatch\Laravel\Http\Middleware;

use Closure;
use ErrorWatch\Laravel\Client\MonitoringClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class ErrorWatchMiddleware
{
    protected MonitoringClient $client;
    protected bool $apmEnabled;
    protected array $excludedRoutes;

    public function __construct(MonitoringClient $client)
    {
        $this->client = $client;
        $this->apmEnabled = $client->getConfig('apm.enabled', true);
        $this->excludedRoutes = $client->getConfig('apm.excluded_routes', []);
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip if SDK is disabled
        if (!$this->client->isEnabled()) {
            return $next($request);
        }

        // Check if route is excluded
        if ($this->isExcludedRoute($request)) {
            return $next($request);
        }

        // Start transaction for APM
        if ($this->apmEnabled) {
            $route = $request->route()?->uri() ?? $request->path();
            $this->client->startTransaction("{$request->method()} {$route}");
        }

        // Add request breadcrumb
        if ($this->client->getConfig('breadcrumbs.enabled', true)) {
            $this->client->getBreadcrumbManager()->addRequest(
                $request->method(),
                $request->fullUrl(),
                0 // Will be updated in terminate()
            );
        }

        // Set user context if authenticated
        if ($this->client->getConfig('user_context.enabled', true) && $request->user()) {
            $this->setUserFromRequest($request);
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            // Capture exception
            $this->client->captureException($e);

            // Finish transaction with error
            if ($this->apmEnabled) {
                $transaction = $this->client->getCurrentTransaction();
                if ($transaction) {
                    $transaction->setError($e->getMessage());
                }
            }

            throw $e;
        }

        return $response;
    }

    /**
     * Handle tasks after the response has been sent.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Skip if SDK is disabled
        if (!$this->client->isEnabled()) {
            return;
        }

        // Finish transaction
        if ($this->apmEnabled) {
            $transaction = $this->client->getCurrentTransaction();

            if ($transaction) {
                // Set response status
                $transaction->setTag('http.status_code', $response->status());
                $transaction->setData('response_size', strlen($response->getContent() ?? ''));

                // Mark status based on response code
                if ($response->status() >= 500) {
                    $transaction->setError("HTTP {$response->status()}");
                } elseif ($response->status() >= 400) {
                    $transaction->setStatus('unknown_error', "HTTP {$response->status()}");
                } else {
                    $transaction->setOk();
                }

                $this->client->finishTransaction();
            }
        }
    }

    /**
     * Check if the route is excluded from tracking.
     */
    protected function isExcludedRoute(Request $request): bool
    {
        $path = $request->path();

        foreach ($this->excludedRoutes as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set user context from the authenticated request.
     */
    protected function setUserFromRequest(Request $request): void
    {
        $user = $request->user();

        if (!$user) {
            return;
        }

        $userData = [
            'id' => $user->getAuthIdentifier(),
        ];

        // Add email if available
        if (isset($user->email)) {
            $userData['email'] = $user->email;
        }

        // Add username/name if available
        if (isset($user->name)) {
            $userData['username'] = $user->name;
        } elseif (isset($user->username)) {
            $userData['username'] = $user->username;
        }

        // Add IP address if configured
        if ($this->client->getConfig('user_context.capture_ip', true)) {
            $userData['ip_address'] = $request->ip();
        }

        $this->client->setUser($userData);
    }
}
