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
}
