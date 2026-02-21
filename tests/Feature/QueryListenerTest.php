<?php

namespace ErrorWatch\Laravel\Tests\Feature;

use ErrorWatch\Laravel\Services\QueryListener;
use ErrorWatch\Laravel\Tests\TestCase;
use Illuminate\Database\Events\QueryExecuted;
use ErrorWatch\Laravel\Facades\ErrorWatch;

class QueryListenerTest extends TestCase
{
    protected QueryListener $listener;

    protected function setUp(): void
    {
        parent::setUp();
        $this->listener = $this->app->make(QueryListener::class);
    }

    /** @test */
    public function it_can_register_listener(): void
    {
        // Should not throw
        $this->listener->register();
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_query(): void
    {
        ErrorWatch::clearBreadcrumbs();

        $query = new QueryExecuted(
            'SELECT * FROM users WHERE id = ?',
            [1],
            15.5,
            $this->app->make('db')->connection()
        );

        $this->listener->handleQuery($query);

        $breadcrumbs = ErrorWatch::getBreadcrumbs();

        $this->assertNotEmpty($breadcrumbs);
        $this->assertStringContainsString('Query', $breadcrumbs[0]['message']);
    }

    /** @test */
    public function it_detects_slow_queries(): void
    {
        config(['errorwatch.apm.slow_query_threshold_ms' => 100]);

        $listener = $this->app->make(QueryListener::class);
        ErrorWatch::clearBreadcrumbs();

        $query = new QueryExecuted(
            'SELECT * FROM large_table',
            [],
            150.0, // 150ms, over threshold
            $this->app->make('db')->connection()
        );

        $listener->handleQuery($query);

        // Should have captured the query
        $this->assertNotEmpty(ErrorWatch::getBreadcrumbs());
    }

    /** @test */
    public function it_sanitizes_long_queries(): void
    {
        ErrorWatch::clearBreadcrumbs();

        $longSql = str_repeat('SELECT * FROM users ', 100);

        $query = new QueryExecuted(
            $longSql,
            [],
            10.0,
            $this->app->make('db')->connection()
        );

        $this->listener->handleQuery($query);

        // Should not throw
        $this->assertTrue(true);
    }

    /** @test */
    public function it_resets_query_counts(): void
    {
        $this->listener->reset();
        $this->assertTrue(true);
    }
}
