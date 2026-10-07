<?php

namespace Dynamic\Calendar\Tests\Task;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;

/**
 * Counts the publication hooks SilverStripe fires on an EventPage, so a test can tell
 * publishSingle() from copyVersionToStage().
 *
 * Those two are not interchangeable: publishSingle() invokes onBeforePublish and
 * onAfterPublish, while copyVersionToStage() invokes only onBeforeVersionedPublish and
 * onAfterVersionedPublish. A module that listens for publication - Fluent, Subsites, static
 * publishing - is told by the first and not the second, which is why the conversion task
 * publishes through publishSingle(). Field values on the live row look identical either way,
 * so nothing but a hook recorder can tell them apart.
 *
 * Only ever applied from DateTimeConversionTest's setUp(), and removed in its tearDown().
 * Named without a trailing "Test" so PHPUnit's testsuite (suffix "Test.php") does not try to
 * run it as a test case, and kept out of the test file so PSR-1's one-class-per-file rule
 * holds - the same shape as tests/Controller/AlternateAbsoluteLinkTestExtension.php.
 * Implements TestOnly, the SilverStripe convention for a helper class under tests/, so it
 * stays out of the module's normal class list.
 *
 * @package Dynamic\Calendar\Tests\Task
 */
class PublishHookTestExtension extends Extension implements TestOnly
{
    /**
     * onAfterPublish() calls recorded per EventPage id, since the last reset().
     *
     * @var array<int,int>
     */
    public static array $afterPublishCalls = [];

    /**
     * Forget everything recorded so far.
     *
     * Called after the fixtures are built, because creating and publishing a fixture row fires
     * the same hook and would otherwise be counted as the task's work.
     *
     * @return void
     */
    public static function reset(): void
    {
        static::$afterPublishCalls = [];
    }

    /**
     * @return void
     */
    public function onAfterPublish()
    {
        $id = (int) $this->getOwner()->ID;
        static::$afterPublishCalls[$id] = (static::$afterPublishCalls[$id] ?? 0) + 1;
    }
}
