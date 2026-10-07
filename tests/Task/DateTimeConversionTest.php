<?php

namespace Dynamic\Calendar\Tests\Task;

use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use Dynamic\Calendar\Task\DateTimeConversion;
use SilverStripe\Core\Convert;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Versioned\Versioned;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Covers the legacy StartDatetime/EndDatetime conversion (issue #294).
 *
 * The task reads draft, converts only rows that still carry a legacy value with no modern
 * counterpart, mirrors to live only when the live record already matched the draft one - a
 * maintenance task must never publish somebody's unpublished work - and reports what it did,
 * because it changes content rows.
 */
class DateTimeConversionTest extends SapphireTest
{
    protected $usesDatabase = true;

    /**
     * @var Calendar
     */
    protected $calendar;

    protected function setUp(): void
    {
        parent::setUp();

        // Registered before any row exists: a class-level extension is read when the object is
        // constructed, so an extension added afterwards would not be on those records.
        EventPage::add_extension(PublishHookTestExtension::class);

        $this->calendar = Calendar::create([
            'Title' => 'Conversion Calendar',
            'URLSegment' => 'conversion-calendar',
        ]);
        $this->calendar->write();
        $this->calendar->publishRecursive();

        // The calendar above was just published and fired the hook; only the task's own calls
        // should be recorded from here on.
        PublishHookTestExtension::reset();
    }

    protected function tearDown(): void
    {
        EventPage::remove_extension(PublishHookTestExtension::class);
        PublishHookTestExtension::reset();

        parent::tearDown();
    }

    /**
     * @return DateTimeConversion
     */
    private function task(): DateTimeConversion
    {
        return DateTimeConversion::create();
    }

    /**
     * Invoke a protected helper without running execute(), which drives the output stream.
     *
     * @param array<int,mixed> $args
     */
    private function call(DateTimeConversion $task, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionMethod($task, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($task, $args);
    }

    private function polyOutput(BufferedOutput $buffer): PolyOutput
    {
        return new PolyOutput(
            PolyOutput::FORMAT_ANSI,
            OutputInterface::VERBOSITY_VERBOSE,
            false,
            $buffer
        );
    }

    /**
     * Run the task exactly as sake does, so execute() itself is exercised: the draft pin, the
     * counters, the report and the exit code.
     *
     * @return array{0:int,1:string} Exit code and reported output.
     */
    private function runTask(?DateTimeConversion $task = null): array
    {
        $buffer = new BufferedOutput();
        $code = $this->call($task ?? $this->task(), 'execute', [new ArrayInput([]), $this->polyOutput($buffer)]);

        return [$code, $buffer->fetch()];
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
     * Put a row into the "upgraded but never converted" state on both stages, bypassing
     * onBeforeWrite() and the DBDatetime cast.
     *
     * Both stages are patched because which one the task reads is exactly what is under test
     * here; leaving live untouched would make the fixture's meaning stage-dependent. The
     * modern fields are nulled alongside so the legacy value is the only source, and the
     * $clearModern = false variant is the half-converted row where the modern fields stay.
     */
    private function forceLegacyRow(
        int $id,
        ?string $startDatetime,
        ?string $endDatetime,
        bool $clearModern = true
    ): void {
        $columns = ['StartDatetime' => $startDatetime, 'EndDatetime' => $endDatetime];

        if ($clearModern) {
            $columns['StartDate'] = null;
            $columns['StartTime'] = null;
            $columns['EndDate'] = null;
            $columns['EndTime'] = null;
        }

        $assignments = implode(', ', array_map(
            fn (string $field, ?string $value): string => sprintf(
                '"%s" = %s',
                $field,
                // Literal SQL: the nulls in particular must reach the database as NULL, and a
                // bound null goes through mysqli as the string type.
                $value === null ? 'NULL' : "'" . Convert::raw2sql($value) . "'"
            ),
            array_keys($columns),
            array_values($columns)
        ));

        DB::query(sprintf('UPDATE "EventPage" SET %s WHERE "ID" = %d', $assignments, $id));

        if (DB::get_schema()->hasTable('EventPage_Live')) {
            DB::query(sprintf('UPDATE "EventPage_Live" SET %s WHERE "ID" = %d', $assignments, $id));
        }
    }

    /**
     * Total version rows held by every EventPage in the suite database. A run that rewrote
     * every row would leave the row count untouched while bloating the history, so versions -
     * not rows - are what "wrote nothing" has to mean.
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
     * Raw stored field values, read back through the ORM (a fresh record each call, so
     * nothing stale can be read).
     *
     * @param array<int,string> $columns
     * @return array<string,string|null>
     */
    private function rowValues(int $id, array $columns): array
    {
        $record = DataObject::get_by_id(EventPage::class, $id);
        $this->assertNotNull($record, "Event {$id} must exist for this read");

        $values = [];

        foreach ($columns as $column) {
            $values[$column] = $record->dbObject($column)->getValue();
        }

        return $values;
    }

    /**
     * The conversion itself: legacy composite values land in the separate date and time
     * fields, and a published row whose stages matched is mirrored to live.
     */
    public function testConvertsLegacyDatetimeAndUpdatesLive(): void
    {
        $event = $this->createEvent([
            'Title' => 'Published event',
            'StartDate' => '2025-10-01',
            'StartTime' => '09:00:00',
            'EndDate' => '2025-10-01',
            'EndTime' => '10:00:00',
        ]);
        $this->forceLegacyRow($event->ID, '2025-11-05 14:30:00', '2025-11-05 16:45:00');

        $this->assertNull(EventPage::get()->byID($event->ID)->StartDate, 'Precondition: no modern start date');

        [$code, $report] = $this->runTask();

        $this->assertSame(0, $code, "Task reported failure:\n{$report}");

        $stored = EventPage::get()->byID($event->ID);
        $this->assertSame('2025-11-05', $stored->StartDate);
        $this->assertSame('14:30:00', $stored->StartTime);
        $this->assertSame('2025-11-05', $stored->EndDate);
        $this->assertSame('16:45:00', $stored->EndTime);

        $live = Versioned::get_by_stage(EventPage::class, Versioned::LIVE)->byID($event->ID);
        $this->assertSame('2025-11-05', $live->StartDate, 'A clean published row must be converted on live too');
        $this->assertSame('14:30:00', $live->StartTime);

        $this->assertStringContainsString('Converted: 1', $report);
        $this->assertStringContainsString('Failed: 0', $report);
    }

    /**
     * CRITICAL: the live mirror must not publish somebody's unpublished work. Publishing
     * moves the whole draft record, so a draft carrying an unpublished edit is converted on
     * draft only and held back from live.
     */
    public function testUnpublishedDraftEditsAreNeverPublished(): void
    {
        $event = $this->createEvent([
            'Title' => 'Published Title',
            'StartDate' => '2025-10-02',
            'StartTime' => '09:00:00',
            'EndDate' => '2025-10-02',
            'EndTime' => '10:00:00',
        ]);
        $this->forceLegacyRow($event->ID, '2025-11-06 14:30:00', '2025-11-06 16:45:00');

        // An unpublished editorial change sitting on draft, on top of the conversion target.
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
            'A maintenance conversion must not publish unrelated unpublished draft edits'
        );
        $this->assertNull($live->StartDate, 'The live row must be left exactly as the editor last published it');

        $stored = EventPage::get()->byID($event->ID);
        $this->assertSame('SECRET UNPUBLISHED DRAFT TITLE', $stored->Title, 'Draft keeps its own edit');
        $this->assertSame('2025-11-06', $stored->StartDate, 'Draft is still converted');

        $this->assertStringContainsString('Held back: 1', $report, 'The held-back row must be reported');
        $this->assertStringContainsString('Published: 0', $report);
    }

    /**
     * A draft-only event must not gain a live version from a maintenance conversion.
     */
    public function testDraftOnlyEventDoesNotGoLive(): void
    {
        $event = EventPage::create([
            'Title' => 'Never published',
            'ParentID' => $this->calendar->ID,
            'Recursion' => 'NONE',
        ]);
        $event->write();
        $this->forceLegacyRow($event->ID, '2025-11-07 11:00:00', null);

        [$code, $report] = $this->runTask();

        $this->assertSame(0, $code, $report);

        $stored = EventPage::get()->byID($event->ID);
        $this->assertSame('2025-11-07', $stored->StartDate, 'Draft is converted');
        $this->assertSame('11:00:00', $stored->StartTime);
        $this->assertFalse(
            (bool) Versioned::get_by_stage(EventPage::class, Versioned::LIVE)->byID($event->ID),
            'A draft-only event must not appear on live'
        );
        $this->assertStringContainsString('Published: 0', $report);
    }

    /**
     * A row with nothing to convert must not be written at all. The pre-fix task called
     * writeToStage() unconditionally for every event on the site, and republished every
     * published one, whether or not it held a legacy value.
     */
    public function testEventWithoutLegacyDatetimeIsSkippedWithoutWriting(): void
    {
        $event = $this->createEvent([
            'Title' => 'Already modern',
            'StartDate' => '2025-10-03',
            'StartTime' => '09:00:00',
            'EndDate' => '2025-10-03',
            'EndTime' => '10:30:00',
        ]);
        $this->assertSame(
            '10:30:00',
            $this->rowValues($event->ID, ['EndTime'])['EndTime'],
            'Precondition: the modern fields are populated'
        );
        $versions = $this->versionCount();
        $before = $this->rowValues($event->ID, ['LastEdited']);

        [$code, $report] = $this->runTask();

        $this->assertSame(0, $code, $report);
        $this->assertSame(
            $before,
            $this->rowValues($event->ID, ['LastEdited']),
            'A skipped row must not even have its LastEdited bumped'
        );
        $this->assertSame($versions, $this->versionCount(), 'A row with nothing to convert must not gain a version');
        $this->assertStringContainsString('Skipped: 1', $report);
        $this->assertStringContainsString('Converted: 0', $report);
    }

    /**
     * Conversion fills a missing field only; a date or time an editor already chose is never
     * overwritten from the legacy column - including the half-converted row where only the
     * end half is missing.
     */
    public function testExistingModernFieldsAreNeverOverwritten(): void
    {
        $event = $this->createEvent([
            'Title' => 'Half converted',
            'StartDate' => '2025-10-04',
            'StartTime' => '09:00:00',
            'EndDate' => '2025-10-04',
            'EndTime' => '12:00:00',
        ]);
        // Legacy values on both stages, modern start left in place: only the end half of the
        // row is still missing its modern counterpart.
        $this->forceLegacyRow($event->ID, '2025-11-08 14:30:00', '2025-11-08 18:15:00', false);
        DB::query(sprintf(
            'UPDATE "EventPage" SET "EndDate" = NULL, "EndTime" = NULL WHERE "ID" = %d',
            $event->ID
        ));

        $task = $this->task();
        $conversion = $this->call($task, 'conversionFor', [EventPage::get()->byID($event->ID)]);

        $this->assertIsArray($conversion, 'The missing end half alone makes the row convertible');
        $this->assertArrayNotHasKey('StartDate', $conversion);
        $this->assertArrayNotHasKey('StartTime', $conversion);
        $this->assertSame('2025-11-08', $conversion['EndDate']);
        $this->assertSame('18:15:00', $conversion['EndTime']);

        [$code, $report] = $this->runTask();

        $this->assertSame(0, $code, $report);

        $stored = EventPage::get()->byID($event->ID);
        $this->assertSame('2025-10-04', $stored->StartDate, "The editor's start date survives");
        $this->assertSame('09:00:00', $stored->StartTime);
        $this->assertSame('2025-11-08', $stored->EndDate);
        $this->assertSame('18:15:00', $stored->EndTime);
    }

    /**
     * The stage has to be a precondition of the run, not a side effect of the caller.
     *
     * Neither real entry point reads live today: under sake no reading mode is set at all
     * (choose_site_stage() only runs from VersionedHTTPMiddleware), and DevelopmentAdmin::init()
     * pins draft for browser runs. So this sets the stage the way a caller that got it wrong
     * would - Versioned::DEFAULT_MODE is Stage.Live, and a caller that left it there would make
     * EventPage::get() iterate LIVE rows and write them back over the draft. Pinned to draft, a
     * live-loaded record cannot clobber a draft edit and a draft-only row is still converted.
     */
    public function testExecutePinsTheDraftStageEvenWhenReachedFromLive(): void
    {
        $draftOnly = EventPage::create([
            'Title' => 'Draft only legacy',
            'ParentID' => $this->calendar->ID,
            'Recursion' => 'NONE',
        ]);
        $draftOnly->write();
        $this->forceLegacyRow($draftOnly->ID, '2025-11-09 08:00:00', null);

        $published = $this->createEvent([
            'Title' => 'Live Title',
            'StartDate' => '2025-10-05',
            'StartTime' => '09:00:00',
            'EndDate' => '2025-10-05',
            'EndTime' => '10:00:00',
        ]);
        $this->forceLegacyRow($published->ID, '2025-11-09 12:00:00', null);

        // A live page whose draft carries a different, unpublished title.
        $draft = EventPage::get()->byID($published->ID);
        $draft->Title = 'DRAFT ONLY EDIT';
        $draft->writeToStage(Versioned::DRAFT);

        // Reach the task the way a live-context caller would.
        Versioned::set_stage(Versioned::LIVE);

        try {
            [$code, $report] = $this->runTask();
        } finally {
            // Restored even when the run throws, so a failure here cannot leak live reading
            // mode into the tests that follow.
            Versioned::set_stage(Versioned::DRAFT);
        }

        $this->assertSame(0, $code, $report);
        $this->assertSame(
            '2025-11-09',
            Versioned::get_by_stage(EventPage::class, Versioned::DRAFT)->byID($draftOnly->ID)->StartDate,
            'A draft-only event must be converted, not skipped because it is not on live'
        );
        $this->assertSame(
            'DRAFT ONLY EDIT',
            Versioned::get_by_stage(EventPage::class, Versioned::DRAFT)->byID($published->ID)->Title,
            'Draft content must not be overwritten from the live record'
        );
        $this->assertSame(
            'Live Title',
            Versioned::get_by_stage(EventPage::class, Versioned::LIVE)->byID($published->ID)->Title,
            'Live content must be untouched'
        );
    }

    /**
     * A legacy value the conversion cannot make sense of must be reported and counted as a
     * failure, not thrown at the operator - and must not leave the row half written: the end
     * half of the same row is perfectly readable, and must not be assigned while the start
     * half is being rejected.
     */
    public function testUnparseableLegacyDatetimeIsCountedAndWritesNothing(): void
    {
        $event = $this->createEvent([
            'Title' => 'Corrupt legacy row',
            'StartDate' => '2025-10-06',
            'StartTime' => '09:00:00',
            'EndDate' => '2025-10-06',
            'EndTime' => '10:00:00',
        ]);
        // A genuine legacy row: composite values present, modern columns empty.
        $this->forceLegacyRow($event->ID, '2025-11-15 09:00:00', '2025-11-15 11:00:00');
        $versions = $this->versionCount();

        $event = EventPage::get()->byID($event->ID);
        $this->assertNull($event->StartDate, 'Precondition: nothing but the legacy value to convert');

        // Corrupt the start half in memory only: a DATETIME column cannot hold this, but the
        // guard must not depend on the current column type to stay reachable, and the cleanup
        // task keeps these columns in place until it is dropped.
        $event->setField('StartDatetime', 'definitely not a date');

        $task = $this->task();
        $buffer = new BufferedOutput();
        // Swallowed by the per-row guard, reported rather than propagated.
        $this->call($task, 'convertEvent', [$event, $this->polyOutput($buffer)]);

        $failed = new \ReflectionProperty(DateTimeConversion::class, 'failed');
        $failed->setAccessible(true);
        $this->assertSame(1, $failed->getValue($task), 'A row that cannot be parsed is a failure');

        foreach (['converted', 'published', 'heldBack', 'skipped'] as $counter) {
            $property = new \ReflectionProperty(DateTimeConversion::class, $counter);
            $property->setAccessible(true);
            $this->assertSame(0, $property->getValue($task), "{$counter} must not move on failure");
        }

        $this->assertStringContainsString('definitely not a date', $buffer->fetch());
        $this->assertSame($versions, $this->versionCount(), 'A rejected row must not be written');

        $stored = $this->rowValues($event->ID, ['StartDate', 'StartTime', 'EndDate', 'EndTime']);
        $this->assertSame(
            ['StartDate' => null, 'StartTime' => null, 'EndDate' => null, 'EndTime' => null],
            $stored,
            'The readable end half must not be assigned while the start half is rejected'
        );
    }

    /**
     * The whole task, driven the way sake drives it: exit code, the counts it prints, and
     * that the work behind each count actually landed. Helper-level assertions cannot see the
     * counters, the report line, or the exit code.
     */
    public function testExecuteReportsEveryCount(): void
    {
        // Converted and published (stages matched before the run).
        $converted = $this->createEvent([
            'Title' => 'Converted',
            'StartDate' => '2025-10-07',
            'StartTime' => '09:00:00',
            'EndDate' => '2025-10-07',
            'EndTime' => '10:00:00',
        ]);
        $this->forceLegacyRow($converted->ID, '2025-11-10 13:00:00', '2025-11-10 15:00:00');

        // Converted on draft, held back from live.
        $heldBack = $this->createEvent([
            'Title' => 'Held back',
            'StartDate' => '2025-10-08',
            'StartTime' => '09:00:00',
            'EndDate' => '2025-10-08',
            'EndTime' => '10:00:00',
        ]);
        $this->forceLegacyRow($heldBack->ID, '2025-11-11 13:00:00', null);
        $draft = EventPage::get()->byID($heldBack->ID);
        $draft->Summary = 'unpublished edit';
        $draft->writeToStage(Versioned::DRAFT);

        // Nothing to convert.
        $this->createEvent([
            'Title' => 'Already modern',
            'StartDate' => '2025-10-09',
            'StartTime' => '09:00:00',
            'EndDate' => '2025-10-09',
            'EndTime' => '10:00:00',
        ]);

        [$code, $report] = $this->runTask();

        $this->assertSame(0, $code, $report);
        $this->assertStringContainsString('Converted: 2', $report);
        $this->assertStringContainsString('Published: 1', $report);
        $this->assertStringContainsString('Held back: 1', $report);
        $this->assertStringContainsString('Skipped: 1', $report);
        $this->assertStringContainsString('Failed: 0', $report);
        // The held-back row's live copy is the one still owed a conversion, and the run says so.
        $this->assertStringContainsString('1 published rows still hold an unconverted legacy value', $report);

        $this->assertSame('2025-11-10', EventPage::get()->byID($converted->ID)->StartDate);
        $liveConverted = Versioned::get_by_stage(EventPage::class, Versioned::LIVE)->byID($converted->ID);
        $this->assertSame(
            '2025-11-10',
            $liveConverted->StartDate,
            'The row the run counted as published must be converted on live'
        );
        $this->assertSame('2025-11-11', EventPage::get()->byID($heldBack->ID)->StartDate);
        $this->assertNull(
            Versioned::get_by_stage(EventPage::class, Versioned::LIVE)->byID($heldBack->ID)->StartDate,
            'A held-back row must leave its live copy untouched, not carry the draft edits along'
        );
    }

    /**
     * A second run must be a no-op: the fields it converts to are the ones it checks for, so
     * running the task twice after an upgrade writes nothing new.
     */
    public function testExecuteIsIdempotent(): void
    {
        $event = $this->createEvent([
            'Title' => 'Legacy row',
            'StartDate' => '2025-10-10',
            'StartTime' => '09:00:00',
            'EndDate' => '2025-10-10',
            'EndTime' => '10:00:00',
        ]);
        $this->forceLegacyRow($event->ID, '2025-11-12 13:00:00', '2025-11-12 15:30:00');

        [$firstCode, $firstReport] = $this->runTask();
        $this->assertSame(0, $firstCode, $firstReport);
        $versions = $this->versionCount();

        [$secondCode, $secondReport] = $this->runTask();

        $this->assertSame(0, $secondCode, $secondReport);
        $this->assertStringContainsString('Converted: 0', $secondReport);
        $this->assertSame($versions, $this->versionCount(), 'A second run must add no versions');
    }

    /**
     * A row whose write fails must be counted as failed, must not be counted as converted,
     * and must drive the exit code to FAILURE - and the run must continue to the rows after
     * it rather than aborting half-way with no summary.
     */
    public function testFailedWriteIsCountedAndStopsTheRunFromClaimingSuccess(): void
    {
        $first = $this->createEvent([
            'Title' => 'Legacy row',
            'StartDate' => '2025-10-11',
            'StartTime' => '09:00:00',
            'EndDate' => '2025-10-11',
            'EndTime' => '10:00:00',
        ]);
        $second = $this->createEvent([
            'Title' => 'Second legacy row',
            'StartDate' => '2025-10-12',
            'StartTime' => '09:00:00',
            'EndDate' => '2025-10-12',
            'EndTime' => '10:00:00',
        ]);
        $this->forceLegacyRow($first->ID, '2025-11-13 13:00:00', null);
        $this->forceLegacyRow($second->ID, '2025-11-14 13:00:00', null);

        $task = new class extends DateTimeConversion {
            protected function writeDraft(EventPage $event): void
            {
                throw new \RuntimeException('simulated write failure');
            }
        };

        [$code, $report] = $this->runTask($task);

        $this->assertNotSame(0, $code, "Must not exit clean:\n{$report}");
        $this->assertStringContainsString('Failed: 2', $report);
        $this->assertStringContainsString('Converted: 0', $report);
        // The run continued past the first failure instead of aborting.
        $this->assertSame(2, substr_count($report, 'simulated write failure'), $report);
    }

    /**
     * A publish that throws after the draft write landed must be reported as its own outcome.
     *
     * Counting it as a failed conversion would be worse than the bug it reports: the next run
     * would find the draft already converted, report the row as skipped, and say nothing about
     * the live copy the site is still owed - so the live event would keep rendering with no
     * date at all, forever, behind a green run.
     */
    public function testPublishFailureKeepsTheDraftConversionAndStaysVisible(): void
    {
        $event = $this->createEvent([
            'Title' => 'Publish throws',
            'StartDate' => '2025-10-13',
            'StartTime' => '09:00:00',
            'EndDate' => '2025-10-13',
            'EndTime' => '10:00:00',
        ]);
        $this->forceLegacyRow($event->ID, '2025-11-16 09:00:00', '2025-11-16 11:00:00');

        $task = new class extends DateTimeConversion {
            protected function publishLive(EventPage $event): void
            {
                throw new \RuntimeException('simulated publish failure');
            }
        };

        [$code, $report] = $this->runTask($task);

        $this->assertNotSame(0, $code, "Must not exit clean:\n{$report}");
        $this->assertStringContainsString('Converted: 1', $report, 'The draft conversion did land');
        $this->assertStringContainsString('Published: 0', $report);
        $this->assertStringContainsString('Publish failed: 1', $report);
        $this->assertStringContainsString('Failed: 0', $report, 'A publish failure is not a write failure');
        $this->assertStringContainsString('simulated publish failure', $report);

        $this->assertSame(
            '2025-11-16',
            EventPage::get()->byID($event->ID)->StartDate,
            'The draft conversion must survive a failed publish'
        );
        $live = Versioned::get_by_stage(EventPage::class, Versioned::LIVE)->byID($event->ID);
        $this->assertNull($live->StartDate, 'Precondition: live still holds no modern date');

        // A later run cannot redo the work (draft is already converted) but must still name the
        // live copy that is outstanding, instead of reporting a clean Skipped run.
        [$secondCode, $secondReport] = $this->runTask();

        $this->assertSame(0, $secondCode, $secondReport);
        $this->assertStringContainsString('Converted: 0', $secondReport);
        $this->assertStringContainsString('1 published rows still hold an unconverted legacy value', $secondReport);
    }

    /**
     * The live mirror has to be a publication, not a version move.
     *
     * publishSingle() fires onBeforePublish/onAfterPublish; copyVersionToStage() fires only
     * onBeforeVersionedPublish/onAfterVersionedPublish. Everything that listens for publication
     * - Fluent, Subsites, static publishing - is told by the first and never by the second, and
     * the live field values come out identical either way, so field assertions alone cannot tell
     * the two apart. The control half of this test mirrors through copyVersionToStage() and
     * shows exactly that: the live row converts, and nothing is told.
     */
    public function testLiveMirrorFiresThePublishHooksAndHeldBackRowsAreNeverPublished(): void
    {
        $published = $this->createEvent([
            'Title' => 'Published event',
            'StartDate' => '2025-10-17',
            'StartTime' => '09:00:00',
            'EndDate' => '2025-10-17',
            'EndTime' => '10:00:00',
        ]);
        $this->forceLegacyRow($published->ID, '2025-11-17 09:15:00', null);

        $heldBack = $this->createEvent([
            'Title' => 'Held back event',
            'StartDate' => '2025-10-18',
            'StartTime' => '09:00:00',
            'EndDate' => '2025-10-18',
            'EndTime' => '10:00:00',
        ]);
        $this->forceLegacyRow($heldBack->ID, '2025-11-18 09:15:00', null);
        $draft = EventPage::get()->byID($heldBack->ID);
        $draft->Summary = 'unpublished edit';
        $draft->writeToStage(Versioned::DRAFT);

        $draftOnly = EventPage::create([
            'Title' => 'Draft only event',
            'ParentID' => $this->calendar->ID,
            'Recursion' => 'NONE',
        ]);
        $draftOnly->write();
        $this->forceLegacyRow($draftOnly->ID, '2025-11-19 09:15:00', null);

        // A row with nothing to convert until the control run below, so the control's own
        // publication is the only thing recorded for it.
        $control = $this->createEvent([
            'Title' => 'Control event',
            'StartDate' => '2025-10-19',
            'StartTime' => '09:00:00',
            'EndDate' => '2025-10-19',
            'EndTime' => '10:00:00',
        ]);

        PublishHookTestExtension::reset();
        [$code, $report] = $this->runTask();

        $this->assertSame(0, $code, $report);
        $calls = PublishHookTestExtension::$afterPublishCalls;
        $this->assertSame(
            1,
            $calls[$published->ID] ?? 0,
            'The live mirror must go through the publish hooks, once, for the row whose stages matched'
        );
        $this->assertArrayNotHasKey(
            $heldBack->ID,
            $calls,
            'A held-back row must not be published, so nothing may be told about it'
        );
        $this->assertArrayNotHasKey(
            $draftOnly->ID,
            $calls,
            'A draft-only row must never gain a live version, so the hook must not fire'
        );
        $this->assertArrayNotHasKey($control->ID, $calls, 'Nothing to convert means nothing published');

        // Control: the old mirror moves the fields without firing the publish hooks, which is
        // why "live looks right" was never evidence that anything was told.
        $this->forceLegacyRow($control->ID, '2025-11-20 09:15:00', null);

        $task = new class extends DateTimeConversion {
            protected function publishLive(EventPage $event): void
            {
                $event->copyVersionToStage(Versioned::DRAFT, Versioned::LIVE);
            }
        };

        PublishHookTestExtension::reset();
        [$controlCode, $controlReport] = $this->runTask($task);

        $this->assertSame(0, $controlCode, $controlReport);
        $this->assertSame(
            '2025-11-20',
            Versioned::get_by_stage(EventPage::class, Versioned::LIVE)->byID($control->ID)->StartDate,
            'Precondition: copyVersionToStage() does move the fields, so the field assertions in '
                . 'this suite could never have caught it'
        );
        $this->assertArrayNotHasKey(
            $control->ID,
            PublishHookTestExtension::$afterPublishCalls,
            'copyVersionToStage() publishes without telling anyone, which is what publishSingle() '
                . 'replaced it for'
        );
    }
}
