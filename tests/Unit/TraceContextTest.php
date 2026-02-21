<?php

namespace ErrorWatch\Laravel\Tests\Unit;

use ErrorWatch\Laravel\Tests\TestCase;
use ErrorWatch\Laravel\Tracing\TraceContext;

class TraceContextTest extends TestCase
{
    /** @test */
    public function it_can_generate_trace_context(): void
    {
        $context = TraceContext::generate();

        $this->assertNotEmpty($context->getTraceId());
        $this->assertNotEmpty($context->getSpanId());
        $this->assertNull($context->getParentSpanId());
    }

    /** @test */
    public function it_generates_valid_trace_id(): void
    {
        $traceId = TraceContext::generateTraceId();

        $this->assertEquals(32, strlen($traceId));
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $traceId);
    }

    /** @test */
    public function it_generates_valid_span_id(): void
    {
        $spanId = TraceContext::generateSpanId();

        $this->assertEquals(16, strlen($spanId));
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $spanId);
    }

    /** @test */
    public function it_can_create_child_context(): void
    {
        $parent = TraceContext::generate();
        $child = $parent->createChild();

        $this->assertEquals($parent->getTraceId(), $child->getTraceId());
        $this->assertEquals($parent->getSpanId(), $child->getParentSpanId());
        $this->assertNotEquals($parent->getSpanId(), $child->getSpanId());
    }

    /** @test */
    public function it_can_convert_to_array(): void
    {
        $context = new TraceContext('abc123', 'def456', 'ghi789');
        $array = $context->toArray();

        $this->assertEquals('abc123', $array['trace_id']);
        $this->assertEquals('def456', $array['span_id']);
        $this->assertEquals('ghi789', $array['parent_span_id']);
    }

    /** @test */
    public function it_can_convert_to_trace_parent_header(): void
    {
        $context = new TraceContext('0123456789abcdef0123456789abcdef', '0123456789abcdef');

        $header = $context->toTraceParentHeader();

        $this->assertEquals('00-0123456789abcdef0123456789abcdef-0123456789abcdef-01', $header);
    }

    /** @test */
    public function it_can_parse_trace_parent_header(): void
    {
        $header = '00-0123456789abcdef0123456789abcdef-0123456789abcdef-01';

        $context = TraceContext::fromTraceParentHeader($header);

        $this->assertNotNull($context);
        $this->assertEquals('0123456789abcdef0123456789abcdef', $context->getTraceId());
        $this->assertEquals('0123456789abcdef', $context->getParentSpanId());
    }

    /** @test */
    public function it_returns_null_for_invalid_header(): void
    {
        $context = TraceContext::fromTraceParentHeader('invalid');

        $this->assertNull($context);
    }

    /** @test */
    public function it_can_set_parent_span_id(): void
    {
        $context = TraceContext::generate();
        $context->setParentSpanId('newparent1234567');

        $this->assertEquals('newparent1234567', $context->getParentSpanId());
    }
}
