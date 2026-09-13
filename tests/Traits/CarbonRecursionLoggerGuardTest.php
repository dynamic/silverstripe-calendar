<?php

namespace Dynamic\Calendar\Tests\Traits;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Dynamic\Calendar\Traits\CarbonRecursion;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * Unit coverage for CarbonRecursion::createCarbonPeriod()'s error guard.
 *
 * Pre-fix, the catch block resolved and wrote to the injected logger directly
 * (Injector::inst()->get(LoggerInterface::class)->error(...)), so a broken logger
 * service raised a *new*, uncaught throwable from inside a catch whose entire purpose
 * is to prevent exactly that (dynamic/silverstripe-calendar#182). Post-fix it routes
 * through LoggerFallback::logWithFallback(), the same convention CalendarController and
 * EventPage already use.
 *
 * A pure unit test of the trait, mirroring LoggerFallbackTest's approach - no schema or
 * DataObject involved, so the subject is an anonymous class using the trait directly.
 *
 * @package Dynamic\Calendar\Tests\Traits
 */
class CarbonRecursionLoggerGuardTest extends SapphireTest
{
    /**
     * @var bool
     */
    protected $usesDatabase = false;

    /**
     * @var string
     */
    private string $errorLogPath = '';

    /**
     * @var string
     */
    private string $previousErrorLog = '';

    protected function setUp(): void
    {
        parent::setUp();

        $path = tempnam(sys_get_temp_dir(), 'calendar-carbon-recursion-');
        $this->errorLogPath = $path !== false ? $path : (sys_get_temp_dir() . '/calendar-carbon-recursion.log');
        $this->previousErrorLog = (string) ini_get('error_log');
        ini_set('error_log', $this->errorLogPath);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->previousErrorLog);
        if (is_file($this->errorLogPath)) {
            unlink($this->errorLogPath);
        }

        parent::tearDown();
    }

    /**
     * A period-creation failure must still degrade to null (not propagate) when the
     * injected logger is healthy, and the message must reach it at 'error' level.
     */
    public function testPeriodCreationFailureIsRoutedThroughTheInjectedLogger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Error creating Carbon period for event 42'));

        Injector::nest();
        try {
            Injector::inst()->registerService($logger, LoggerInterface::class);

            $result = $this->throwingSubject()->callCreateCarbonPeriod();
        } finally {
            Injector::unnest();
        }

        $this->assertNull($result, 'A period-creation failure must degrade to null, not propagate');
        $this->assertFallbackDidNotFire();
    }

    /**
     * The discriminating case for #182: when the logger service itself cannot be
     * resolved, createCarbonPeriod() must still return null rather than let the
     * resolve failure escape as a new, uncaught throwable. Pre-fix this test fails
     * with an uncaught \Psr\Container\NotFoundExceptionInterface (or similar) instead
     * of returning null.
     */
    public function testPeriodCreationFailureSurvivesAnUnresolvableLogger(): void
    {
        Injector::nest();
        try {
            Injector::inst()->unregisterNamedObject(LoggerInterface::class);
            Injector::inst()->load([
                LoggerInterface::class => [
                    'class' => 'No\Such\Logger\Exists',
                ],
            ]);

            // Must not throw.
            $result = $this->throwingSubject()->callCreateCarbonPeriod();
        } finally {
            Injector::unnest();
        }

        $this->assertNull($result);

        $logged = (string) file_get_contents($this->errorLogPath);
        $this->assertStringContainsString('Error creating Carbon period for event 42', $logged);
        $this->assertStringContainsString('logger service unavailable', $logged);
    }

    /**
     * Same discriminating scenario, but the logger resolves and then throws from the
     * write itself (a handler that raises rather than a missing service) - the other
     * half of LoggerFallback's contract.
     */
    public function testPeriodCreationFailureSurvivesAThrowingLogger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('error')
            ->willThrowException(new \RuntimeException('handler disk full'));

        Injector::nest();
        try {
            Injector::inst()->registerService($logger, LoggerInterface::class);

            $result = $this->throwingSubject()->callCreateCarbonPeriod();
        } finally {
            Injector::unnest();
        }

        $this->assertNull($result);

        $logged = (string) file_get_contents($this->errorLogPath);
        $this->assertStringContainsString('Error creating Carbon period for event 42', $logged);
        $this->assertStringContainsString('logging failed', $logged);
    }

    /**
     * A subject whose createDailyPeriod() always throws, forcing createCarbonPeriod()'s
     * catch block - the code under test - regardless of which logger is registered.
     *
     * @return object
     */
    private function throwingSubject(): object
    {
        return new class {
            use CarbonRecursion;

            public $ID = 42;
            public $StartDate = '2025-06-01';
            public $EndDate = null;
            public $RecursionEndDate = null;
            public $Recursion = 'DAILY';
            public $Interval = 1;

            public function eventRecurs(): bool
            {
                return true;
            }

            protected function createDailyPeriod(Carbon $start, Carbon $end): CarbonPeriod
            {
                throw new \RuntimeException('simulated period-creation failure');
            }

            public function callCreateCarbonPeriod()
            {
                return $this->createCarbonPeriod();
            }
        };
    }

    /**
     * Assert neither LoggerFallback annotation appears - see LoggerFallbackTest's
     * identical helper for why this is checked by annotation, not file emptiness.
     *
     * @return void
     */
    private function assertFallbackDidNotFire(): void
    {
        $logged = is_file($this->errorLogPath) ? (string) file_get_contents($this->errorLogPath) : '';
        $this->assertStringNotContainsString('logger service unavailable', $logged);
        $this->assertStringNotContainsString('logging failed', $logged);
    }
}
