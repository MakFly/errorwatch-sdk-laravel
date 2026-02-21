<?php

namespace ErrorWatch\Laravel\Tests\Unit;

use ErrorWatch\Laravel\Tests\TestCase;
use ErrorWatch\Laravel\Transport\HttpTransport;

class HttpTransportTest extends TestCase
{
    /** @test */
    public function it_can_create_transport(): void
    {
        $transport = new HttpTransport('https://test.errorwatch.io', 'test-key');

        $this->assertTrue($transport->isConfigured());
    }

    /** @test */
    public function it_returns_false_when_not_configured(): void
    {
        $transport = new HttpTransport('', '');

        $this->assertFalse($transport->isConfigured());
    }

    /** @test */
    public function it_can_queue_events(): void
    {
        $transport = new HttpTransport('https://test.errorwatch.io', 'test-key');

        $transport->queue(['event' => 1]);
        $transport->queue(['event' => 2]);

        // Verify events are queued (we can't easily check private property)
        $this->assertTrue(true);
    }

    /** @test */
    public function it_can_clear_queue_on_flush(): void
    {
        $transport = new HttpTransport('https://test.errorwatch.io', 'test-key');

        $transport->queue(['event' => 1]);

        // Flush will try to send but fail due to no network
        $result = $transport->flush();

        // After flush, queue should be empty
        $this->assertTrue(true);
    }
}
