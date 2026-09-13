<?php

namespace Dynamic\Calendar\Tests\Task;

use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use Dynamic\Calendar\Task\AllDayNormalisationTask;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Versioned\Versioned;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Covers the issue #150 backfill: rows whose AllDay column disagreed with what the feed
 * rendered must be normalised, rows that already agree must not be touched, and the counts
 * reported to the operator must be produced by the same predicates the migration applies.
 */
class AllDayNormalisationTaskTest extends SapphireTest
{
    protected $usesDatabase = true;

    /**
     * @var Calendar
     */
    protected $calendar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calendar = Calendar::create([
            'Title' => 'Backfill Calendar',
            'URLSegment' => 'backfill-calendar',
        ]);
        $this->calendar->write();
        $this->calendar->publishRecursive();
    }

    /**
     * @return AllDayNormalisationTask
     */
    private function task(): AllDayNormalisationTask
    {
        return AllDayNormalisationTask::create();
    }

    /**
     * Invoke a protected helper without running execute(), which drives the output stream.
     *
     * @param array<int,mixed> $args
     */
    private function call(AllDayNormalisationTask $task, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionMethod($task, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($task, $args);
    }

    /**
     * Run the task exactly as sake does, so execute() itself is exercised: the table check,
     * both counts, the counters, the verification block and the exit code.
     *
     * @return array{0:int,1:string} Exit code and reported output.
     */
    private function runTask(): array
    {
        $buffer = new BufferedOutput();
        $output = new PolyOutput(
            PolyOutput::FORMAT_ANSI,
            OutputInterface::VERBOSITY_VERBOSE,
            false,
            $buffer
        );
        $code = $this->call($this->task(), 'execute', [new ArrayInput([]), $output]);

        return [$code, $buffer->fetch()];
    }

    /**
     * @return PolyOutput
     */
    private function silentOutput(): PolyOutput
    {
        return new PolyOutput(
            PolyOutput::FORMAT_ANSI,
            OutputInterface::VERBOSITY_QUIET,
            false,
            new BufferedOutput()
        );
    }

    private function normalise(EventPage $event): void
    {
        $this->call($this->task(), 'normaliseEvent', [$event, $this->silentOutput()]);
    }

    /**
     * @param array<string,mixed> $values
     */
    private function createEvent(array $values): EventPage
    {
        $event = EventPage::create(array_merge([
            'ParentID' => $this->calendar->ID,
            'Recursion' => 'NONE',
        ], $values));
        $event->write();
        $event->publishRecursive();

        return $event;
    }

    /**
     * Put a row into a divergent state on both stages, bypassing onBeforeWrite().
     *
     * Both stages are patched because which one the feed reads depends on the current
     * reading mode; leaving live untouched would make the fixture's meaning stage-dependent.
     */
    private function forceDivergentRow(int $id, int $allDay): void
    {
        DB::query(sprintf('UPDATE "EventPage" SET "AllDay" = %d WHERE "ID" = %d', $allDay, $id));

        if (DB::get_schema()->hasTable('EventPage_Live')) {
            DB::query(sprintf('UPDATE "EventPage_Live" SET "AllDay" = %d WHERE "ID" = %d', $allDay, $id));
        }
    }

    /**
     * The task must query the table that actually carries these columns. baseTable() returns
     * the base of the SiteTree hierarchy, which does not, so a guard built on it would pass
     * while every count and migration statement ran against the wrong table.
     */
    public function testQueriesTargetTheEventPageTable(): void
    {
        $task = $this->task();

        $this->assertSame('EventPage', $this->call($task, 'eventTable'));
        $this->assertSame(
            'EventPage',
            DataObject::getSchema()->tableName(EventPage::class),
            'tableName() is the accessor that resolves the class own table'
        );
        $this->assertNotSame(
            'EventPage',
            EventPage::singleton()->baseTable(),
            'Records why baseTable() is the wrong call: it resolves to the hierarchy base'
        );
    }

    /**
     * Direction 1: AllDay off with no clock time used to render as all-day because the
     * serializer keyed off StartTime. The column must be set so it keeps rendering that way.
     */
    public function testPromotesUntimedEventToAllDay(): void
    {
        $event = $this->createEvent([
            'Title' => 'Untimed',
            'StartDate' => '2025-10-01',
            'AllDay' => 0,
        ]);

        $this->normalise($event);

        $this->assertEquals(1, EventPage::get()->byID($event->ID)->AllDay, 'Column must record what was rendered');
        $live = Versioned::get_by_stage(EventPage::class, Versioned::LIVE)->byID($event->ID);
        $this->assertEquals(1, $live->AllDay, 'Live copy must be updated too');
    }

    /**
     * Direction 2: the CMS hides the time fields rather than emptying them, so an all-day
     * row can still carry a clock time. onBeforeWrite() clears it, on both stages.
     */
    public function testClearsTimesOnAllDayRow(): void
    {
        $event = $this->createEvent([
            'Title' => 'Legacy all-day',
            'StartDate' => '2025-10-02',
            'StartTime' => '08:00:00',
            'AllDay' => 0,
        ]);

        $this->forceDivergentRow($event->ID, 1);

        $this->assertSame('08:00:00', EventPage::get()->byID($event->ID)->StartTime, 'Precondition: divergent');

        $this->normalise(EventPage::get()->byID($event->ID));

        $stored = EventPage::get()->byID($event->ID);
        $this->assertNull($stored->StartTime, 'Leftover StartTime must be cleared');
        $this->assertNull($stored->EndTime, 'Leftover EndTime must be cleared');
        $this->assertEquals(1, $stored->AllDay);
        $this->assertSame('2025-10-02', $stored->StartDate);

        $live = Versioned::get_by_stage(EventPage::class, Versioned::LIVE)->byID($event->ID);
        $this->assertNotNull($live, 'A published row must still be on live');
        $this->assertNull($live->StartTime, 'Live must be cleared too, not just draft');
        $this->assertNull($live->EndTime);
    }

    /**
     * A timed event that already agrees with its column must not be promoted, or the backfill
     * would quietly turn every timed event into an all-day banner.
     */
    public function testLeavesTimedEventAlone(): void
    {
        $event = $this->createEvent([
            'Title' => 'Timed',
            'StartDate' => '2025-10-03',
            'StartTime' => '09:00:00',
            'AllDay' => 0,
        ]);
        $versions = Versioned::get_all_versions(EventPage::class, $event->ID)->count();

        $this->normalise($event);

        $stored = EventPage::get()->byID($event->ID);
        $this->assertEquals(0, $stored->AllDay, 'A timed event must stay timed');
        $this->assertSame('09:00:00', $stored->StartTime);
        $this->assertSame(
            $versions,
            Versioned::get_all_versions(EventPage::class, $event->ID)->count(),
            'An already-consistent row must not gain a version'
        );
    }

    /**
     * An all-day row with no times is already consistent: no write, no new version.
     */
    public function testLeavesConsistentAllDayEventAlone(): void
    {
        $event = $this->createEvent([
            'Title' => 'Already all day',
            'StartDate' => '2025-10-04',
            'AllDay' => 1,
        ]);
        $versions = Versioned::get_all_versions(EventPage::class, $event->ID)->count();

        $this->normalise($event);

        $stored = EventPage::get()->byID($event->ID);
        $this->assertEquals(1, $stored->AllDay);
        $this->assertNull($stored->StartTime);
        $this->assertSame(
            $versions,
            Versioned::get_all_versions(EventPage::class, $event->ID)->count(),
            'An already-consistent row must not gain a version'
        );
    }

    /**
     * A row with no StartTime but a lone EndTime used to render as an all-day banner, because
     * the old serializer decided allDay from StartTime alone. It must therefore be promoted,
     * not skipped: leaving it would flip it to a timed event at midnight, the exact regression
     * the backfill exists to prevent. Promotion also clears the orphan EndTime, which never
     * influenced allDay before and does not after.
     */
    public function testEndTimeOnlyRowIsPromotedAndItsOrphanTimeCleared(): void
    {
        $event = $this->createEvent([
            'Title' => 'End time only',
            'StartDate' => '2025-10-06',
            'EndTime' => '10:00:00',
            'AllDay' => 0,
        ]);

        $task = $this->task();
        $counts = $this->call($task, 'countDivergent');
        $this->assertSame(
            1,
            $counts[AllDayNormalisationTask::DIVERGENT_UNTIMED],
            'No StartTime means it rendered all-day, so it is divergent from AllDay = 0'
        );

        $this->normalise($event);

        $stored = EventPage::get()->byID($event->ID);
        $this->assertEquals(1, $stored->AllDay, 'Must keep rendering all-day');
        $this->assertNull($stored->EndTime, 'The orphan EndTime is cleared by the all-day write');
    }

    /**
     * A stored midnight is a TIME, not an absence. This is the case the SQL predicates used to
     * get wrong: on a TIME column MySQL compares '' by coercing it to TIME '00:00:00', so a
     * "StartTime = ''" count called a midnight row "no time" while the PHP branch called it a
     * time. The row was then counted divergent, never touched, counted divergent again, and the
     * task exited FAILURE forever without converging.
     */
    public function testMidnightIsATimeNotAnAbsence(): void
    {
        $event = $this->createEvent([
            'Title' => 'Midnight start',
            'StartDate' => '2025-10-08',
            'StartTime' => '00:00:00',
            'EndTime' => '00:00:00',
            'AllDay' => 0,
        ]);

        $task = $this->task();
        $counts = $this->call($task, 'countDivergent');
        $this->assertSame(0, $counts[AllDayNormalisationTask::DIVERGENT_UNTIMED], 'Midnight is not "no time"');

        $this->normalise($event);

        $stored = EventPage::get()->byID($event->ID);
        $this->assertEquals(0, $stored->AllDay, 'A midnight event stays timed');
        $this->assertSame('00:00:00', $stored->StartTime, 'The midnight must survive');
    }

    /**
     * The matching second symptom: an all-day row carrying a stored midnight does have a
     * leftover time to clear, and both the count and the work must agree about it.
     */
    public function testAllDayRowWithStoredMidnightIsCountedAndCleared(): void
    {
        $event = $this->createEvent([
            'Title' => 'All day with midnight',
            'StartDate' => '2025-10-09',
            'StartTime' => '00:00:00',
            'AllDay' => 0,
        ]);

        // Flag all-day and drop the derived EndTime, so the ONLY evidence of a clock time on
        // this row is the stored midnight. Otherwise the derived 01:00:00 end would classify
        // the row on its own and this test would not isolate the midnight case.
        DB::query(sprintf(
            'UPDATE "EventPage" SET "AllDay" = 1, "EndTime" = NULL WHERE "ID" = %d',
            $event->ID
        ));
        DB::query(sprintf(
            'UPDATE "EventPage_Live" SET "AllDay" = 1, "EndTime" = NULL WHERE "ID" = %d',
            $event->ID
        ));

        $this->assertSame('00:00:00', EventPage::get()->byID($event->ID)->StartTime, 'Precondition: midnight stored');
        $this->assertNull(EventPage::get()->byID($event->ID)->EndTime, 'Precondition: no other time');

        $task = $this->task();
        $counts = $this->call($task, 'countDivergent');
        $this->assertSame(
            1,
            $counts[AllDayNormalisationTask::DIVERGENT_STALE_TIME],
            'A stored midnight on an all-day row is a leftover time, and must be counted'
        );

        [$code, $report] = $this->runTask();

        $this->assertSame(0, $code, "Task should converge:\n{$report}");
        $this->assertStringContainsString('Divergent draft rows: 0 with AllDay off and no clock time, 1', $report);
        $this->assertStringContainsString('times cleared: 1', $report);
        $this->assertStringContainsString('Verified: no divergent draft row remains.', $report);
        $this->assertNull(EventPage::get()->byID($event->ID)->StartTime);
    }

    /**
     * A row that cannot be written must be counted as failed, must not be counted as a
     * success, and must drive the exit code to FAILURE. Only the write seam throws here, so
     * persist()'s own catch and its accounting are what is under test.
     */
    public function testFailedWriteIsCountedAsFailure(): void
    {
        $event = $this->createEvent([
            'Title' => 'Untimed failing row',
            'StartDate' => '2025-10-10',
            'AllDay' => 0,
        ]);

        $task = new class extends AllDayNormalisationTask {
            protected function writeDraft(EventPage $event): void
            {
                throw new \RuntimeException('simulated write failure');
            }
        };

        $buffer = new BufferedOutput();
        $output = new PolyOutput(
            PolyOutput::FORMAT_ANSI,
            OutputInterface::VERBOSITY_VERBOSE,
            false,
            $buffer
        );

        // Swallowed by persist(), reported rather than propagated.
        $this->call($task, 'normaliseEvent', [$event, $output]);

        $failed = new \ReflectionProperty(AllDayNormalisationTask::class, 'failed');
        $failed->setAccessible(true);
        $this->assertSame(1, $failed->getValue($task), 'The failed row must be counted');

        foreach (['promoted', 'cleared', 'heldBack'] as $counter) {
            $property = new \ReflectionProperty(AllDayNormalisationTask::class, $counter);
            $property->setAccessible(true);
            $this->assertSame(0, $property->getValue($task), "{$counter} must not move on failure");
        }

        $this->assertStringContainsString('simulated write failure', $buffer->fetch());
    }

    /**
     * A run that could not write a row must exit non-zero, so a deploy script gating on it
     * cannot wave the backfill through while rows stay divergent.
     */
    public function testExecuteReturnsFailureWhenARowCannotBeWritten(): void
    {
        $this->createEvent(['Title' => 'Untimed', 'StartDate' => '2025-10-15', 'AllDay' => 0]);

        // The row is still divergent afterwards, which is what report() must catch even if
        // the failed counter were ever removed.
        [$code, $report] = $this->runTaskWithFailingWrites();

        $this->assertNotSame(0, $code, "Must not exit clean:\n{$report}");
        $this->assertStringContainsString('failed: 1', $report);
    }

    /**
     * Run the whole task with every draft write failing.
     *
     * @return array{0:int,1:string}
     */
    private function runTaskWithFailingWrites(): array
    {
        $task = new class extends AllDayNormalisationTask {
            protected function writeDraft(EventPage $event): void
            {
                throw new \RuntimeException('simulated write failure');
            }
        };

        $buffer = new BufferedOutput();
        $output = new PolyOutput(
            PolyOutput::FORMAT_ANSI,
            OutputInterface::VERBOSITY_VERBOSE,
            false,
            $buffer
        );
        $code = $this->call($task, 'execute', [new ArrayInput([]), $output]);

        return [$code, $buffer->fetch()];
    }

    /**
     * The count and the migration must be produced by the same predicate: whatever is reported
     * divergent is exactly what gets changed, so the totals reconcile.
     */
    public function testCountsAndWorkReconcile(): void
    {
        $this->createEvent(['Title' => 'Untimed', 'StartDate' => '2025-10-11', 'AllDay' => 0]);
        $this->createEvent([
            'Title' => 'Timed',
            'StartDate' => '2025-10-12',
            'StartTime' => '09:00:00',
            'AllDay' => 0,
        ]);
        $this->createEvent([
            'Title' => 'Midnight',
            'StartDate' => '2025-10-13',
            'StartTime' => '00:00:00',
            'AllDay' => 0,
        ]);
        $legacy = $this->createEvent([
            'Title' => 'Legacy',
            'StartDate' => '2025-10-14',
            'StartTime' => '00:00:00',
            'AllDay' => 0,
        ]);
        $this->forceDivergentRow($legacy->ID, 1);

        $task = $this->task();
        $before = $this->call($task, 'countDivergent');
        $this->assertSame(1, $before[AllDayNormalisationTask::DIVERGENT_UNTIMED]);
        $this->assertSame(1, $before[AllDayNormalisationTask::DIVERGENT_STALE_TIME]);

        [$code, $report] = $this->runTask();

        $this->assertSame(0, $code, $report);
        $this->assertStringContainsString('Promoted to all-day: 1', $report);
        $this->assertStringContainsString('times cleared: 1', $report);
    }

    /**
     * Recurring events go through the same column, and the backfill must not disturb the
     * parent pattern they generate occurrences from.
     */
    public function testRecurringEventBackfill(): void
    {
        $recurring = $this->createEvent([
            'Title' => 'Weekly Untimed',
            'StartDate' => '2025-10-07',
            'AllDay' => 0,
            'Recursion' => 'WEEKLY',
            'Interval' => 1,
            'RecursionEndDate' => '2025-11-30',
        ]);

        $this->normalise($recurring);

        $stored = EventPage::get()->byID($recurring->ID);
        $this->assertEquals(1, $stored->AllDay);
        $this->assertSame('WEEKLY', $stored->Recursion, 'The pattern must survive the backfill');
        $this->assertNull($stored->StartTime);
    }

    /**
     * The live copy is gated on isPublished(): a draft-only event must not gain a live
     * version from a maintenance backfill.
     */
    public function testDraftOnlyEventDoesNotGoLive(): void
    {
        $event = EventPage::create([
            'Title' => 'Draft only',
            'ParentID' => $this->calendar->ID,
            'StartDate' => '2025-10-05',
            'AllDay' => 0,
            'Recursion' => 'NONE',
        ]);
        $event->write();

        $this->normalise($event);

        $this->assertEquals(1, EventPage::get()->byID($event->ID)->AllDay, 'Draft still normalised');
        $this->assertFalse(
            (bool) Versioned::get_by_stage(EventPage::class, Versioned::LIVE)->byID($event->ID),
            'A draft-only event must not appear on live'
        );
    }

    /**
     * Runs the task the way sake does and asserts the whole contract: exit code, the counts
     * it reports, and that both directions actually landed. Reaching execute() matters - the
     * helper-level tests cannot see the counters, the verification block or the exit code.
     */
    public function testExecuteReportsAndNormalisesEndToEnd(): void
    {
        $this->createEvent(['Title' => 'Untimed A', 'StartDate' => '2025-12-01', 'AllDay' => 0]);
        $this->createEvent(['Title' => 'Untimed B', 'StartDate' => '2025-12-02', 'AllDay' => 0]);
        $legacy = $this->createEvent([
            'Title' => 'Legacy all-day',
            'StartDate' => '2025-12-03',
            'StartTime' => '07:00:00',
            'AllDay' => 0,
        ]);
        $this->forceDivergentRow($legacy->ID, 1);
        // Already consistent: must not be counted, written, or promoted.
        $this->createEvent([
            'Title' => 'Timed',
            'StartDate' => '2025-12-04',
            'StartTime' => '09:00:00',
            'AllDay' => 0,
        ]);

        [$code, $report] = $this->runTask();

        $this->assertSame(0, $code, "Task reported failure:\n{$report}");
        $this->assertStringContainsString('Promoted to all-day: 2', $report);
        $this->assertStringContainsString('times cleared: 1', $report);
        $this->assertStringContainsString('failed: 0', $report);
        $this->assertStringContainsString('Verified: no divergent draft row remains.', $report);

        $this->assertEquals(1, EventPage::get()->byID($legacy->ID)->AllDay);
        $this->assertNull(EventPage::get()->byID($legacy->ID)->StartTime);
    }

    /**
     * A second run must be a no-op: the task is idempotent, so re-running it after an
     * upgrade (or twice by mistake) changes nothing and reports nothing to do.
     */
    public function testExecuteIsIdempotent(): void
    {
        $this->createEvent(['Title' => 'Untimed', 'StartDate' => '2025-12-05', 'AllDay' => 0]);

        [$firstCode, $firstReport] = $this->runTask();
        $this->assertSame(0, $firstCode, $firstReport);
        $versions = $this->versionCount();

        [$secondCode, $secondReport] = $this->runTask();

        $this->assertSame(0, $secondCode, $secondReport);
        $this->assertStringContainsString('Divergent draft rows: 0', $secondReport);
        $this->assertStringContainsString('Promoted to all-day: 0', $secondReport);
        // Versions, not rows: the claim is that a second run writes nothing, and a run that
        // rewrote every row would leave the row count untouched while bloating the history.
        $this->assertSame($versions, $this->versionCount(), 'A second run must add no versions');
    }

    /**
     * Total version rows held by every EventPage in the suite database.
     *
     * @return int
     */
    private function versionCount(): int
    {
        $total = 0;

        foreach (EventPage::get() as $event) {
            $total += Versioned::get_all_versions(EventPage::class, $event->ID)->count();
        }

        return $total;
    }

    /**
     * CRITICAL: the live mirror must not publish somebody's unpublished work.
     * Publishing moves the whole draft record, so a draft carrying an unpublished edit must be
     * normalised on draft only and held back from live.
     */
    public function testUnpublishedDraftEditsAreNeverPublished(): void
    {
        $event = $this->createEvent([
            'Title' => 'Published Title',
            'StartDate' => '2025-12-06',
            'AllDay' => 0,
        ]);

        // An unpublished editorial change sitting on draft.
        $draft = EventPage::get()->byID($event->ID);
        $draft->Title = 'SECRET UNPUBLISHED DRAFT TITLE';
        $draft->writeToStage(Versioned::DRAFT);
        $this->assertTrue($draft->stagesDiffer(), 'Precondition: draft differs from live');

        [$code, $report] = $this->runTask();

        $this->assertSame(0, $code, $report);
        $live = Versioned::get_by_stage(EventPage::class, Versioned::LIVE)->byID($event->ID);
        $this->assertSame(
            'Published Title',
            $live->Title,
            'A maintenance backfill must not publish unrelated unpublished draft edits'
        );
        $this->assertEquals(1, EventPage::get()->byID($event->ID)->AllDay, 'Draft is still normalised');
        $this->assertStringContainsString('held back: 1', $report, 'The held-back row must be reported');
    }

    /**
     * CRITICAL: Versioned::DEFAULT_MODE is Stage.Live and a CLI call does not override it,
     * so without pinning, EventPage::get() iterates LIVE rows and writing them back to draft
     * overwrites unpublished draft content. Pinned to draft, a draft-only event is iterated
     * and a live-loaded record cannot clobber the draft.
     */
    public function testExecutePinsTheDraftStageEvenWhenReachedFromLive(): void
    {
        $draftOnly = EventPage::create([
            'Title' => 'Never published',
            'ParentID' => $this->calendar->ID,
            'StartDate' => '2025-12-07',
            'AllDay' => 0,
            'Recursion' => 'NONE',
        ]);
        $draftOnly->write();

        $published = $this->createEvent([
            'Title' => 'Live Title',
            'StartDate' => '2025-12-08',
            'AllDay' => 0,
        ]);
        // A live page with a draft title that must survive the run.
        $draft = EventPage::get()->byID($published->ID);
        $draft->Title = 'DRAFT ONLY EDIT';
        $draft->writeToStage(Versioned::DRAFT);

        // Reach the task the way a live-context caller would.
        Versioned::set_stage(Versioned::LIVE);
        [$code, $report] = $this->runTask();
        Versioned::set_stage(Versioned::DRAFT);

        $this->assertSame(0, $code, $report);
        $this->assertEquals(
            1,
            Versioned::get_by_stage(EventPage::class, Versioned::DRAFT)->byID($draftOnly->ID)->AllDay,
            'A draft-only event must be backfilled, not skipped because it is not on live'
        );
        $this->assertSame(
            'DRAFT ONLY EDIT',
            Versioned::get_by_stage(EventPage::class, Versioned::DRAFT)->byID($published->ID)->Title,
            'Draft content must not be overwritten from the live record'
        );
        $livePublished = Versioned::get_by_stage(EventPage::class, Versioned::LIVE)->byID($published->ID);
        $this->assertSame('Live Title', $livePublished->Title, 'Live content must be untouched');
    }
}
