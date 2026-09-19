<?php

namespace Dynamic\Calendar\Tests\Traits;

use Dynamic\Calendar\Traits\LoggerFallback;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * Unit coverage for the LoggerFallback trait.
 *
 * The trait is the unit under test here: the module's logging convention (injected
 * LoggerInterface first, error_log() only when that fails) lives in logWithFallback(),
 * and the EventPage/CalendarController call sites pass through it.
 *
 * @package Dynamic\Calendar\Tests\Traits
 */
class LoggerFallbackTest extends SapphireTest
{
    /**
     * This is a pure unit test of a trait - no schema is involved.
     *
     * Untyped to match SapphireTest's own declaration, which redeclaring as bool would
     * conflict with.
     *
     * @var bool
     */
    protected $usesDatabase = false;

    /**
     * @var string Path captured as error_log() destination for the duration of a test.
     */
    private string $errorLogPath = '';

    /**
     * @var string The error_log() setting in force before setUp() replaced it, read with
     *             ini_get() so the real prior value is restored even when ini_set() reports
     *             the change by returning false rather than the old value.
     */
    private string $previousErrorLog = '';

    protected function setUp(): void
    {
        parent::setUp();

        // tempnam() creates the file it names, so that exact path is used as the sink and
        // unlinked in tearDown(); deriving a new path from it would leak the tempnam file.
        $path = tempnam(sys_get_temp_dir(), 'calendar-logger-fallback-');
        $this->errorLogPath = $path !== false ? $path : (sys_get_temp_dir() . '/calendar-logger-fallback.log');
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
     * The default level is 'warning', and the message must reach the injected logger
     * verbatim - this is the point of the convention, and the case the bare error_log()
     * call sites used to miss entirely.
     */
    public function testLogsThroughInjectedLoggerAtDefaultWarningLevel(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->identicalTo('the message'));

        $this->withLogger($logger, function (object $subject): void {
            $subject->log('the message');
        });

        $this->assertFallbackDidNotFire();
    }

    /**
     * An explicit level must be routed to the matching LoggerInterface method rather than
     * being collapsed onto the default.
     */
    public function testLogsAtAnExplicitLevel(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->identicalTo('the message'));
        $logger->expects($this->never())->method('warning');

        $this->withLogger($logger, function (object $subject): void {
            $subject->log('the message', 'error');
        });

        $this->assertFallbackDidNotFire();
    }

    /**
     * An empty message is still a routed message: it must reach the logger as an empty
     * string and must not be treated as a failure that trips the fallback.
     */
    public function testEmptyMessageIsRoutedWithoutTrippingTheFallback(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->identicalTo(''));

        $this->withLogger($logger, function (object $subject): void {
            $subject->log('');
        });

        $this->assertFallbackDidNotFire();
    }

    /**
     * A logger that resolved but raised from its handler must not let the exception escape
     * the call site, and the message must still land in error_log(). The annotation here is
     * deliberately 'logging failed' and not 'logger service unavailable': the service resolved
     * fine, so naming it unavailable would send the reader looking at Injector when the fault
     * is downstream of it. This fallback may still be the only record of the message - a
     * handler that throws before flushing records nothing - which is why it too carries the
     * level and the throw point.
     */
    public function testThrowingLoggerFallsBackToErrorLogAndDoesNotPropagate(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('warning')
            ->willThrowException(new \RuntimeException('handler disk full'));

        $this->withLogger($logger, function (object $subject): void {
            $subject->log('the message');
        });

        $logged = (string) file_get_contents($this->errorLogPath);
        $this->assertStringContainsString('the message', $logged);
        $this->assertStringContainsString('logging failed', $logged);
        $this->assertStringContainsString('RuntimeException', $logged);
        $this->assertStringContainsString('handler disk full', $logged);
        // The level must survive into the fallback: on this path the raw log may be the
        // only place the report exists at all.
        $this->assertStringContainsString('WARNING', $logged);
        $this->assertStringNotContainsString(
            'logger service unavailable',
            $logged,
            'A logger that resolved but failed to write must not be reported as unavailable'
        );
    }

    /**
     * A service registered as Psr\Log\LoggerInterface that is not actually a logger covers
     * the *write* side of the contract - SilverStripe's Injector performs no type check on
     * registerService(), so the service resolves happily and it is the call to warning()
     * that fails. PHP raises an Error (not an Exception) for the undefined method, which is
     * why the trait catches Throwable.
     */
    public function testMisconfiguredLoggerServiceFallsBackToErrorLog(): void
    {
        $this->withLogger(new \stdClass(), function (object $subject): void {
            // No exception may escape the call site.
            $subject->log('orphan message');
        });

        $logged = (string) file_get_contents($this->errorLogPath);
        $this->assertStringContainsString('orphan message', $logged);
        $this->assertStringContainsString('logging failed', $logged);
        $this->assertStringContainsString('stdClass::warning', $logged);
    }

    /**
     * Levels are matched case-insensitively, so an uppercase level must reach the matching
     * method untouched - unannotated, and without tripping the unknown-level path.
     */
    public function testLevelIsMatchedCaseInsensitively(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->logicalAnd(
                $this->identicalTo('shouted message'),
                $this->logicalNot($this->stringContains('not a Psr\Log\LogLevel constant'))
            ));
        $logger->expects($this->never())->method('warning');

        $this->withLogger($logger, function (object $subject): void {
            $subject->log('shouted message', 'ERROR');
        });

        $this->assertFallbackDidNotFire();
    }

    /**
     * The other half of the contract: a Psr\Log\LoggerInterface that cannot be *resolved*
     * at all. Here the fallback is the only surviving record of the message, which is what
     * makes its wording - 'logger service unavailable', the one the write side must not
     * claim - load-bearing.
     */
    public function testUnresolvableLoggerServiceFallsBackToErrorLog(): void
    {
        Injector::nest();

        try {
            // Drop any cached instance, then point the name at a class that does not exist,
            // so resolving it is what fails rather than writing to it.
            Injector::inst()->unregisterNamedObject(LoggerInterface::class);
            Injector::inst()->load([
                LoggerInterface::class => [
                    'class' => 'No\Such\Logger\Exists',
                ],
            ]);

            $this->subject()->log('probe message');
        } finally {
            Injector::unnest();
        }

        $logged = (string) file_get_contents($this->errorLogPath);
        $this->assertStringContainsString('probe message', $logged);
        $this->assertStringContainsString('logger service unavailable', $logged);
        $this->assertStringNotContainsString(
            'logging failed',
            $logged,
            'A service that never resolved must not be reported as a failed log write'
        );
    }

    /**
     * A level that is not a Psr\Log\LogLevel constant is a caller mistake. It must be
     * reported through error() with the bad level named, rather than surfacing as an opaque
     * failed log write and pointing the reader at the logging pipeline.
     */
    public function testUnknownLevelIsReportedThroughErrorAndNamed(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->logicalAnd(
                $this->stringContains('levelled message'),
                $this->stringContains("'fatal'")
            ));
        $logger->expects($this->never())->method('warning');

        $this->withLogger($logger, function (object $subject): void {
            $subject->log('levelled message', 'fatal');
        });

        $this->assertFallbackDidNotFire();
    }

    /**
     * The bad level must still be named when the logger cannot be resolved at all - that
     * fallback is the only surviving record, so it is the one place the caller's mistake
     * cannot be afforded to go missing. This is why the level is corrected before the
     * service is resolved, not after.
     */
    public function testUnknownLevelIsNamedEvenWhenTheLoggerCannotResolve(): void
    {
        Injector::nest();

        try {
            Injector::inst()->unregisterNamedObject(LoggerInterface::class);
            Injector::inst()->load([
                LoggerInterface::class => [
                    'class' => 'No\Such\Logger\Exists',
                ],
            ]);

            $this->subject()->log('orphan on bad level', 'fatal');
        } finally {
            Injector::unnest();
        }

        $logged = (string) file_get_contents($this->errorLogPath);
        $this->assertStringContainsString('orphan on bad level', $logged);
        $this->assertStringContainsString("'fatal'", $logged);
        $this->assertStringContainsString('logger service unavailable', $logged);
    }

    /**
     * Run a callable against the trait subject with the given service registered as the
     * logger.
     *
     * @param object $logger A logger double, or (for the misconfiguration case) a non-logger.
     * @param callable(object):void $run
     * @return void
     */
    private function withLogger(object $logger, callable $run): void
    {
        Injector::nest();

        try {
            Injector::inst()->registerService($logger, LoggerInterface::class);
            $run($this->subject());
        } finally {
            Injector::unnest();
        }
    }

    /**
     * logWithFallback() is protected by design - its consumers are a Page, a Controller
     * and, since #160, the static EventInstanceCache - whose public surface should not
     * widen - so the subject exposes it. EventInstanceCache reaches the method by
     * constructing a throwaway instance rather than by widening the trait, because
     * promoting it to static is #200's decision and not that call site's.
     *
     * @return object
     */
    private function subject(): object
    {
        return new class {
            use LoggerFallback;

            /**
             * @param string $message
             * @param string $level
             * @return void
             */
            public function log(string $message, string $level = 'warning'): void
            {
                $this->logWithFallback($message, $level);
            }
        };
    }

    /**
     * Assert the fallback never fired while the injected logger was healthy.
     *
     * Asserted by the annotations only the fallback writes, rather than by the file being
     * byte-empty: this temp file is the process-wide error_log sink for the duration of the
     * test, so an unrelated PHP diagnostic would otherwise fail these tests with a message
     * about logging. Nor by the message alone, which is vacuous for the empty-message case.
     *
     * @return void
     */
    private function assertFallbackDidNotFire(): void
    {
        $logged = is_file($this->errorLogPath) ? (string) file_get_contents($this->errorLogPath) : '';
        $this->assertStringNotContainsString(
            'logger service unavailable',
            $logged,
            'The resolve fallback must not fire while the logger service resolves'
        );
        $this->assertStringNotContainsString(
            'logging failed',
            $logged,
            'The write fallback must not fire while the logger accepts the message'
        );
    }
}
