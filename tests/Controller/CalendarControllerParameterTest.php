<?php

namespace Dynamic\Calendar\Tests\Controller;

use Dynamic\Calendar\Controller\CalendarController;
use Dynamic\Calendar\Page\Calendar;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;

/**
 * Tests for CalendarController parameter handling (start/end vs from/to)
 */
class CalendarControllerParameterTest extends SapphireTest
{
    protected static $fixture_file = '../fixtures.yml';

    public function testGetFromDateAcceptsFromParameter()
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $controller = CalendarController::create($calendar);

        $request = new HTTPRequest('GET', '/', ['from' => '2025-10-01']);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getFromDate');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $request);

        $this->assertNotNull($result);
        $this->assertEquals('2025-10-01', $result->format('Y-m-d'));
    }

    public function testGetFromDateAcceptsStartParameter()
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $controller = CalendarController::create($calendar);

        $request = new HTTPRequest('GET', '/', ['start' => '2025-10-01']);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getFromDate');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $request);

        $this->assertNotNull($result);
        $this->assertEquals('2025-10-01', $result->format('Y-m-d'));
    }

    public function testGetToDateAcceptsToParameter()
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $controller = CalendarController::create($calendar);

        $request = new HTTPRequest('GET', '/', ['to' => '2025-10-31']);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getToDate');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $request);

        $this->assertNotNull($result);
        $this->assertEquals('2025-10-31', $result->format('Y-m-d'));
    }

    public function testGetToDateAcceptsEndParameter()
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $controller = CalendarController::create($calendar);

        $request = new HTTPRequest('GET', '/', ['end' => '2025-10-31']);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getToDate');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $request);

        $this->assertNotNull($result);
        $this->assertEquals('2025-10-31', $result->format('Y-m-d'));
    }

    public function testFromParameterTakesPrecedenceOverStart()
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $controller = CalendarController::create($calendar);

        $request = new HTTPRequest('GET', '/', [
            'from' => '2025-10-01',
            'start' => '2025-09-01',
        ]);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getFromDate');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $request);

        $this->assertNotNull($result);
        $this->assertEquals('2025-10-01', $result->format('Y-m-d'), "'from' parameter should take precedence");
    }

    /**
     * An empty 'from' must fall through to a usable 'start' rather than winning
     * the coalesce and discarding it.
     *
     * Regression guard on the getFromDate() rewrite in #149: `??` alone kept a
     * present-but-empty 'from', and the truthiness check below it then dropped
     * it, so '?from=&start=2025-10-01' resolved to no lower bound at all. The
     * rewritten accessor falls through on the empty string instead, and this
     * pins that - reverting to `??` fails here.
     */
    public function testEmptyFromParameterFallsBackToStartParameter()
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $controller = CalendarController::create($calendar);

        $request = new HTTPRequest('GET', '/', [
            'from' => '',
            'start' => '2025-10-01',
        ]);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getFromDate');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $request);

        $this->assertNotNull($result);
        $this->assertEquals(
            '2025-10-01',
            $result->format('Y-m-d'),
            "An empty 'from' must not suppress a valid 'start'"
        );
    }

    /**
     * Mirror of testEmptyFromParameterFallsBackToStartParameter() for the
     * upper bound.
     */
    public function testEmptyToParameterFallsBackToEndParameter()
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $controller = CalendarController::create($calendar);

        $request = new HTTPRequest('GET', '/', [
            'to' => '',
            'end' => '2025-10-31',
        ]);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getToDate');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $request);

        $this->assertNotNull($result);
        $this->assertEquals(
            '2025-10-31',
            $result->format('Y-m-d'),
            "An empty 'to' must not suppress a valid 'end'"
        );
    }

    /**
     * A usable legacy 'from' still wins over 'start' when it is non-empty - the
     * fall-through must not invert the documented precedence.
     */
    public function testNonEmptyFromStillTakesPrecedenceAfterFallThroughChange()
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $controller = CalendarController::create($calendar);

        $request = new HTTPRequest('GET', '/', [
            'from' => '2025-10-01',
            'start' => '',
        ]);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getFromDate');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $request);

        $this->assertNotNull($result);
        $this->assertEquals(
            '2025-10-01',
            $result->format('Y-m-d'),
            "A non-empty 'from' must still beat 'start'"
        );
    }

    /**
     * Both bounds empty resolves to no filter at all, not to an error: this is
     * the pre-existing behaviour and must survive the rewrite.
     */
    public function testEmptyFromAndToResolveToNoFilter()
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $controller = CalendarController::create($calendar);

        $request = new HTTPRequest('GET', '/', [
            'from' => '',
            'to' => '',
        ]);

        $reflection = new \ReflectionClass($controller);
        $fromMethod = $reflection->getMethod('getFromDate');
        $fromMethod->setAccessible(true);
        $toMethod = $reflection->getMethod('getToDate');
        $toMethod->setAccessible(true);

        $this->assertNull($fromMethod->invoke($controller, $request));
        $this->assertNull($toMethod->invoke($controller, $request));
    }
}
