<?php

namespace ErrorWatch\Laravel;

use ErrorWatch\Laravel\Client\MonitoringClient;
use ErrorWatch\Laravel\Commands\InstallCommand;
use ErrorWatch\Laravel\Commands\TestCommand;
use ErrorWatch\Laravel\Http\Middleware\ErrorWatchMiddleware;
use ErrorWatch\Laravel\Logging\ErrorWatchHandler;
use ErrorWatch\Laravel\Services\HttpClientListener;
use ErrorWatch\Laravel\Services\QueryListener;
use ErrorWatch\Laravel\Services\QueueListener;
use ErrorWatch\Laravel\Listeners\EventSubscriber;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Monolog\Logger;

class ErrorWatchServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/errorwatch.php',
            'errorwatch'
        );

        // Register MonitoringClient as singleton
        $this->app->singleton(MonitoringClient::class, function ($app) {
            return new MonitoringClient($app['config']->get('errorwatch'));
        });

        // Register facade accessor
        $this->app->alias(MonitoringClient::class, 'errorwatch');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../config/errorwatch.php' => config_path('errorwatch.php'),
        ], 'errorwatch-config');

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                TestCommand::class,
            ]);
        }

        // Only register integrations if enabled
        if (!$this->app['config']->get('errorwatch.enabled', true)) {
            return;
        }

        // Register HTTP middleware
        $this->registerMiddleware();

        // Register event subscribers
        $this->registerEventSubscribers();

        // Register query listener for Eloquent
        $this->registerQueryListener();

        // Register queue listener
        $this->registerQueueListener();

        // Register HTTP client listener
        $this->registerHttpClientListener();

        // Register Monolog handler
        $this->registerMonologHandler();

        // Register Blade directive for session replay
        $this->registerBladeDirective();
    }

    /**
     * Register the HTTP middleware.
     */
    protected function registerMiddleware(): void
    {
        /** @var \Illuminate\Foundation\Application $app */
        $app = $this->app;

        // Check Laravel version for different middleware registration
        if (version_compare($app->version(), '11.0', '>=')) {
            // Laravel 11+: Use middleware group
            $app['router']->pushMiddlewareToGroup('web', ErrorWatchMiddleware::class);
            $app['router']->pushMiddlewareToGroup('api', ErrorWatchMiddleware::class);
        } else {
            // Laravel 10: Register as global middleware
            if ($app->bound(Kernel::class)) {
                $kernel = $app->make(Kernel::class);
                $kernel->pushMiddleware(ErrorWatchMiddleware::class);
            }
        }
    }

    /**
     * Register event subscribers.
     */
    protected function registerEventSubscribers(): void
    {
        if ($this->app['config']->get('errorwatch.security.enabled', true) ||
            $this->app['config']->get('errorwatch.console.enabled', true)) {
            Event::subscribe(EventSubscriber::class);
        }
    }

    /**
     * Register query listener for Eloquent.
     */
    protected function registerQueryListener(): void
    {
        if ($this->app['config']->get('errorwatch.apm.eloquent.enabled', true)) {
            $this->app->make(QueryListener::class)->register();
        }
    }

    /**
     * Register queue listener.
     */
    protected function registerQueueListener(): void
    {
        if ($this->app['config']->get('errorwatch.queue.enabled', true)) {
            $this->app->make(QueueListener::class)->register();
        }
    }

    /**
     * Register HTTP client listener.
     */
    protected function registerHttpClientListener(): void
    {
        if ($this->app['config']->get('errorwatch.apm.http_client.enabled', true)) {
            $this->app->make(HttpClientListener::class)->register();
        }
    }

    /**
     * Register Monolog handler.
     */
    protected function registerMonologHandler(): void
    {
        if (!$this->app['config']->get('errorwatch.monolog.enabled', true)) {
            return;
        }

        // Get the Monolog instance from Laravel's logging system
        if ($this->app->bound('log')) {
            $logger = $this->app->make('log');

            // For Laravel 10+, access the underlying Monolog instance
            if (method_exists($logger, 'getLogger')) {
                $monolog = $logger->getLogger();
            } else {
                $monolog = $logger;
            }

            if ($monolog instanceof Logger) {
                $monolog->pushHandler(
                    new ErrorWatchHandler(
                        $this->app->make(MonitoringClient::class),
                        $this->app['config']->get('errorwatch.monolog.level', 'warning')
                    )
                );
            }
        }
    }

    /**
     * Register Blade directive for session replay.
     */
    protected function registerBladeDirective(): void
    {
        Blade::directive('errorwatchReplay', function () {
            $replayEnabled = $this->app['config']->get('errorwatch.replay.enabled', false);
            $sampleRate = $this->app['config']->get('errorwatch.replay.sample_rate', 0.1);
            $endpoint = $this->app['config']->get('errorwatch.endpoint', '');
            $apiKey = $this->app['config']->get('errorwatch.api_key', '');

            if (!$replayEnabled) {
                return '';
            }

            return "<?php
                if (mt_rand(1, 100) <= ({$sampleRate} * 100)) {
                    echo '<script src=\"{$endpoint}/replay.js\" data-api-key=\"{$apiKey}\"></script>';
                }
            ?>";
        });
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            MonitoringClient::class,
            'errorwatch',
        ];
    }
}
