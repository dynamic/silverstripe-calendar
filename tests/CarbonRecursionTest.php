<?php

namespace Dynamic\Calendar\Tests;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Dynamic\Calendar\Model\EventException;
use Dynamic\Calendar\Model\EventInstance;
use Dynamic\Calendar\Model\EventInstanceCache;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\Connect\MySQLDatabase;
use SilverStripe\ORM\DB;
use Psr\Log\LoggerInterface;
use SilverStripe\Versioned\Versioned;

/**
 * Carbon Recursion Test
 *
 * Tests the new Carbon-based recursion system functionality.
 *
 * @package Dynamic\Calendar\Tests
 */
class CarbonRecursionTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'CarbonRecursionTest.yml';

    /**
     * @var Calendar
     */
    protected $parentPage;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a Calendar parent page for EventPages
        $this->parentPage = Calendar::create([
            'Title' => 'Test Calendar',
            'URLSegment' => 'test-calendar',
        ]);
        $this->parentPage->write();
        $this->parentPage->publishRecursive();
    }

    /**
     * Test creating a simple recurring event
     */
    public function testCreateRecurringEvent()
    {
        $event = EventPage::create([
            'Title' => 'Weekly Team Meeting',
            'StartDate' => '2025-06-16',
            'StartTime' => '09:00:00',
            'EndTime' => '10:00:00',
            'Recursion' => 'WEEKLY',
            'Interval' => 1,
            'RecursionEndDate' => '2025-12-31',
            'ParentID' => $this->parentPage->ID,
        ]);

        $event->write();
        $event->publishRecursive();

        $this->assertTrue($event->eventRecurs());
        $this->assertEquals('WEEKLY', $event->Recursion);
    }

    /**
     * Test generating occurrences for a weekly event
     */
    public function testWeeklyOccurrences()
    {
        $event = EventPage::create([
            'Title' => 'Weekly Meeting',
            'StartDate' => '2025-06-16', // Monday
            'StartTime' => '14:00:00',
            'EndTime' => '15:00:00',
            'Recursion' => 'WEEKLY',
            'Interval' => 1,
            'RecursionEndDate' => '2025-07-14',
            'ParentID' => $this->parentPage->ID,
        ]);

        $event->write();

        // Get occurrences for the next month
        $occurrences = iterator_to_array($event->getOccurrences('2025-06-16', '2025-07-14'));

        // Should have 5 weekly occurrences (including start date)
        $this->assertCount(5, $occurrences);

        // Check that all occurrences are EventInstance objects
        foreach ($occurrences as $occurrence) {
            $this->assertInstanceOf(EventInstance::class, $occurrence);
            $this->assertEquals('Weekly Meeting', $occurrence->Title);
            $this->assertEquals('14:00:00', $occurrence->StartTime);
        }

        // Check specific dates
        $dates = array_map(function ($occ) {
            return $occ->StartDate;
        }, $occurrences);
        $expectedDates = ['2025-06-16', '2025-06-23', '2025-06-30', '2025-07-07', '2025-07-14'];

        $this->assertEquals($expectedDates, $dates);
    }

    /**
     * Test monthly recurring event
     */
    public function testMonthlyOccurrences()
    {
        $event = EventPage::create([
            'Title' => 'Monthly Report',
            'StartDate' => '2025-06-15',
            'Recursion' => 'MONTHLY',
            'Interval' => 1,
            'RecursionEndDate' => '2025-12-31',
            'ParentID' => $this->parentPage->ID,
        ]);

        $event->write();

        // Get occurrences for 6 months
        $occurrences = iterator_to_array($event->getOccurrences('2025-06-15', '2025-11-15'));

        // Should have 6 monthly occurrences
        $this->assertCount(6, $occurrences);

        // Check that dates are correct
        $dates = array_map(function ($occ) {
            return $occ->StartDate;
        }, $occurrences);
        $expectedDates = ['2025-06-15', '2025-07-15', '2025-08-15', '2025-09-15', '2025-10-15', '2025-11-15'];

        $this->assertEquals($expectedDates, $dates);
    }

    /**
     * Test event exceptions (modified instances)
     */
    public function testEventExceptions()
    {
        $event = EventPage::create([
            'Title' => 'Daily Standup',
            'StartDate' => '2025-06-16',
            'StartTime' => '09:00:00',
            'Recursion' => 'DAILY',
            'Interval' => 1,
            'RecursionEndDate' => '2025-06-20',
            'ParentID' => $this->parentPage->ID,
        ]);

        $event->write();

        // Create an exception for the 18th (modify the time)
        $exception = $event->createException(
            '2025-06-18',
            'MODIFIED',
            ['StartTime' => '10:00:00', 'Title' => 'Modified Standup'],
            'Time change for this day'
        );

        $this->assertInstanceOf(EventException::class, $exception);
        $this->assertEquals('MODIFIED', $exception->Action);
        $this->assertTrue($exception->hasOverride('StartTime'));
        $this->assertEquals('10:00:00', $exception->getOverride('StartTime'));

        // Get occurrences and check that the exception is applied
        $occurrences = iterator_to_array($event->getOccurrences('2025-06-16', '2025-06-20'));

        // Find the modified occurrence
        $modifiedOccurrence = null;
        foreach ($occurrences as $occurrence) {
            if ((string) $occurrence->StartDate === '2025-06-18') {
                $modifiedOccurrence = $occurrence;
                break;
            }
        }

        $this->assertNotNull($modifiedOccurrence);
        $this->assertEquals('Modified Standup', $modifiedOccurrence->Title);
        $this->assertEquals('10:00:00', $modifiedOccurrence->StartTime);
        $this->assertTrue($modifiedOccurrence->isModified());
    }

    /**
     * Test deleted event exceptions
     */
    public function testDeletedExceptions()
    {
        $event = EventPage::create([
            'Title' => 'Daily Workout',
            'StartDate' => '2025-06-16',
            'Recursion' => 'DAILY',
            'Interval' => 1,
            'RecursionEndDate' => '2025-06-20',
            'ParentID' => $this->parentPage->ID,
        ]);

        $event->write();

        // Delete the occurrence on the 18th
        $exception = $event->createException(
            '2025-06-18',
            'DELETED',
            [],
            'Cancelled for this day'
        );

        $this->assertEquals('DELETED', $exception->Action);

        // Get occurrences - should not include the deleted one
        $occurrences = iterator_to_array($event->getOccurrences('2025-06-16', '2025-06-20'));

        // Should have 4 occurrences instead of 5 (one deleted)
        $this->assertCount(4, $occurrences);

        // Check that the 18th is not included
        $dates = array_map(function ($occ) {
            return (string) $occ->StartDate;
        }, $occurrences);
        $this->assertNotContains('2025-06-18', $dates);
        $this->assertContains('2025-06-16', $dates);
        $this->assertContains('2025-06-17', $dates);
        $this->assertContains('2025-06-19', $dates);
        $this->assertContains('2025-06-20', $dates);
    }

    /**
     * Test next occurrence functionality
     */
    public function testNextOccurrence()
    {
        $event = EventPage::create([
            'Title' => 'Weekly Planning',
            'StartDate' => '2025-06-23', // Next Monday from today (2025-06-16)
            'Recursion' => 'WEEKLY',
            'Interval' => 1,
            'RecursionEndDate' => '2025-12-31',
            'ParentID' => $this->parentPage->ID,
        ]);

        $event->write();

        $nextOccurrence = $event->getNextOccurrence('2025-06-16');

        $this->assertInstanceOf(EventInstance::class, $nextOccurrence);
        $this->assertEquals('2025-06-23', $nextOccurrence->StartDate);
        $this->assertEquals('Weekly Planning', $nextOccurrence->Title);
    }

    /**
     * Test occurrence counting
     */
    public function testOccurrenceCounting()
    {
        $event = EventPage::create([
            'Title' => 'Bi-weekly Review',
            'StartDate' => '2025-06-16',
            'Recursion' => 'WEEKLY',
            'Interval' => 2, // Every 2 weeks
            'RecursionEndDate' => '2025-12-31',
            'ParentID' => $this->parentPage->ID,
        ]);

        $event->write();

        // Count occurrences until the end of the year
        $count = $event->countOccurrences('2025-12-31');

        // Should be approximately 14 bi-weekly occurrences from June to December
        $this->assertGreaterThan(10, $count);
        $this->assertLessThan(20, $count);
    }

    /**
     * Test recurrence description
     */
    public function testRecurrenceDescription()
    {
        $dailyEvent = EventPage::create([
            'Recursion' => 'DAILY',
            'Interval' => 1,
            'ParentID' => $this->parentPage->ID,
        ]);

        $this->assertEquals('Daily', $dailyEvent->getRecurrenceDescription());

        $weeklyEvent = EventPage::create([
            'Recursion' => 'WEEKLY',
            'Interval' => 2,
            'ParentID' => $this->parentPage->ID,
        ]);

        $this->assertEquals('Every 2 weeks', $weeklyEvent->getRecurrenceDescription());

        $monthlyEvent = EventPage::create([
            'Recursion' => 'MONTHLY',
            'Interval' => 3,
            'RecursionEndDate' => '2025-12-31',
            'ParentID' => $this->parentPage->ID,
        ]);

        $this->assertEquals('Every 3 months until Dec 31, 2025', $monthlyEvent->getRecurrenceDescription());
    }

    /**
     * Test non-recurring events
     */
    public function testNonRecurringEvent()
    {
        $event = EventPage::create([
            'Title' => 'One-time Meeting',
            'StartDate' => '2025-06-20',
            'Recursion' => 'NONE',
            'ParentID' => $this->parentPage->ID,
        ]);

        $event->write();

        $this->assertFalse($event->eventRecurs());

        // Get occurrences - should return the single event if in range
        $occurrences = iterator_to_array($event->getOccurrences('2025-06-19', '2025-06-21'));
        $this->assertCount(1, $occurrences);
        $this->assertEquals('2025-06-20', $occurrences[0]->StartDate);

        // Should return empty if out of range
        $occurrences = iterator_to_array($event->getOccurrences('2025-06-21', '2025-06-25'));
        $this->assertCount(0, $occurrences);
    }

    /**
     * Test that RecursionEndDate is respected even when querying larger date ranges
     *
     * This tests the bug fix where RecursionEndDate was being ignored when
     * date ranges were passed to getOccurrences()
     */
    public function testRecursionEndDateRespected()
    {
        $event = EventPage::create([
            'Title' => 'Daily Reminder',
            'StartDate' => '2025-06-01',
            'StartTime' => '08:00:00',
            'Recursion' => 'DAILY',
            'Interval' => 1,
            'RecursionEndDate' => '2025-06-10', // Event should stop on June 10
            'ParentID' => $this->parentPage->ID,
        ]);

        $event->write();

        // Query for a much larger range (entire month)
        // Even though we're querying until June 30, the event should stop at RecursionEndDate
        $occurrences = iterator_to_array($event->getOccurrences('2025-06-01', '2025-06-30'));

        // Should only have occurrences from June 1-10 (10 days)
        $this->assertCount(10, $occurrences, 'Event should only generate 10 occurrences despite larger query range');

        // Check that all occurrences are within the RecursionEndDate
        foreach ($occurrences as $occurrence) {
            $occurrenceDate = Carbon::parse($occurrence->StartDate);
            $endDate = Carbon::parse('2025-06-10');
            $this->assertLessThanOrEqual(
                $endDate,
                $occurrenceDate,
                "Occurrence {$occurrence->StartDate} should not be after RecursionEndDate 2025-06-10"
            );
        }

        // Verify the last occurrence is on the RecursionEndDate
        $lastOccurrence = end($occurrences);
        $this->assertEquals('2025-06-10', $lastOccurrence->StartDate, 'Last occurrence should be on RecursionEndDate');

        // Verify no occurrences exist after the RecursionEndDate
        $dates = array_map(function ($occ) {
            return $occ->StartDate;
        }, $occurrences);
        $this->assertNotContains('2025-06-11', $dates, 'Should not have occurrence after RecursionEndDate');
        $this->assertNotContains('2025-06-15', $dates, 'Should not have occurrence after RecursionEndDate');
    }

    /**
     * Test that RecursionEndDate works correctly with weekly events
     */
    public function testRecursionEndDateWithWeeklyEvents()
    {
        $event = EventPage::create([
            'Title' => 'Weekly Class',
            'StartDate' => '2025-06-02', // Monday
            'StartTime' => '10:00:00',
            'Recursion' => 'WEEKLY',
            'Interval' => 1,
            'RecursionEndDate' => '2025-06-16', // Should include Jun 2, 9, 16 but not 23
            'ParentID' => $this->parentPage->ID,
        ]);

        $event->write();

        // Query for 2 months even though event ends much sooner
        $occurrences = iterator_to_array($event->getOccurrences('2025-06-01', '2025-07-31'));

        // Should have exactly 3 weekly occurrences
        $this->assertCount(3, $occurrences, 'Weekly event should generate exactly 3 occurrences');

        $dates = array_map(function ($occ) {
            return $occ->StartDate;
        }, $occurrences);
        $expectedDates = ['2025-06-02', '2025-06-09', '2025-06-16'];

        $this->assertEquals($expectedDates, $dates, 'Should only have occurrences up to RecursionEndDate');
        $this->assertNotContains('2025-06-23', $dates, 'Should not include occurrence after RecursionEndDate');
    }

    /**
     * Test that RecursionEndDate works when it falls between recurring intervals
     */
    public function testRecursionEndDateBetweenIntervals()
    {
        $event = EventPage::create([
            'Title' => 'Every 3 Days',
            'StartDate' => '2025-06-01',
            'Recursion' => 'DAILY',
            'Interval' => 3, // Every 3 days: 1st, 4th, 7th, 10th, 13th...
            'RecursionEndDate' => '2025-06-08', // Ends between 7th and 10th
            'ParentID' => $this->parentPage->ID,
        ]);

        $event->write();

        $occurrences = iterator_to_array($event->getOccurrences('2025-06-01', '2025-06-30'));

        // Should have occurrences on: Jun 1, 4, 7 (but not 10 since it's after end date)
        $this->assertCount(3, $occurrences);

        $dates = array_map(function ($occ) {
            return $occ->StartDate;
        }, $occurrences);
        $expectedDates = ['2025-06-01', '2025-06-04', '2025-06-07'];

        $this->assertEquals($expectedDates, $dates);
        $this->assertNotContains('2025-06-10', $dates, 'Should not include Jun 10 as it exceeds RecursionEndDate');
    }

    /**
     * Number of SQL statements executed by this DB connection so far.
     *
     * MariaDB's session-scope 'Questions' counter, used to show that exception lookups no
     * longer scale with the number of generated occurrence dates. It counts every
     * statement on the connection - including the probe itself - so callers compare
     * differences and every test using it carries a positive control.
     */
    private function databaseQueryCount(): int
    {
        if (!DB::get_conn() instanceof MySQLDatabase) {
            $this->markTestSkipped(
                'The query counter reads SHOW SESSION STATUS, which only exists on MySQL/MariaDB; '
                . 'this run uses ' . get_class(DB::get_conn())
            );
        }

        $row = DB::query("SHOW SESSION STATUS LIKE 'Questions'")->record();
        if (!isset($row['Value'])) {
            $this->fail('SHOW SESSION STATUS LIKE \'Questions\' returned no row; the query counter is unavailable');
        }
        return (int) $row['Value'];
    }

    /**
     * Expand one window on a freshly loaded event and report how many queries it cost.
     *
     * @return array{queries:int,count:int}
     */
    private function expandAndCountQueries(int $eventId, string $start, string $end): array
    {
        $event = EventPage::get()->byID($eventId);
        $this->assertNotNull($event, 'Test event must exist');

        $before = $this->databaseQueryCount();
        $occurrences = iterator_to_array($event->getOccurrences($start, $end));
        $after = $this->databaseQueryCount();

        return ['queries' => $after - $before, 'count' => count($occurrences)];
    }

    /**
     * Regression test for dynamic/silverstripe-calendar#260.
     *
     * An unbounded DAILY event that started more than ~3 years ago used to iterate from
     * its StartDate, so CarbonPeriod's lazy range filter rejected more than its 1000
     * consecutive rejections and threw Carbon\Exceptions\UnreachableException out of the
     * expansion - a correctly parsed Y-m-d feed window returned HTTP 500.
     */
    public function testOldDailyEventExpandsOneMonthWindowWithoutBailing()
    {
        $event = EventPage::create([
            'Title' => 'Ancient Daily Event',
            'StartDate' => '2021-01-04',
            'StartTime' => '08:00:00',
            'Recursion' => 'DAILY',
            'Interval' => 1,
            'ParentID' => $this->parentPage->ID,
        ]);
        $event->write();
        $event->publishRecursive();

        $occurrences = iterator_to_array($event->getOccurrences('2025-06-01', '2025-06-30'));
        $dates = array_map(function ($occurrence) {
            return (string) $occurrence->StartDate;
        }, $occurrences);

        $this->assertCount(30, $occurrences, 'A one-month window must expand to its 30 dates');
        $this->assertEquals('2025-06-01', $dates[0], 'Expansion starts at the range start');
        $this->assertEquals('2025-06-30', end($dates), 'Expansion ends at the range end');
        $this->assertContains('2025-06-15', $dates);
    }

    /**
     * Regression test for dynamic/silverstripe-calendar#260.
     *
     * Exception lookups used to be one SELECT per generated occurrence date. They are now
     * a single memoised query per expansion, so the cost must not grow with the window.
     */
    public function testExceptionQueriesStayConstantAsTheWindowGrows()
    {
        $event = EventPage::create([
            'Title' => 'June Daily Event',
            'StartDate' => '2025-06-01',
            'StartTime' => '09:00:00',
            'Recursion' => 'DAILY',
            'Interval' => 1,
            'RecursionEndDate' => '2025-06-30',
            'ParentID' => $this->parentPage->ID,
        ]);
        $event->write();
        $event->publishRecursive();

        // Positive control: prove the counter actually observes an EventException query,
        // so a counter that silently always reads zero cannot make the assertions below
        // pass for the wrong reason.
        $controlEvent = EventPage::get()->byID($event->ID);
        $controlBefore = $this->databaseQueryCount();
        EventException::findForEventAndDate($controlEvent, '2025-06-05');
        $controlAfter = $this->databaseQueryCount();
        $this->assertGreaterThanOrEqual(
            2,
            $controlAfter - $controlBefore,
            'The query counter must observe a single EventException lookup'
        );

        $short = $this->expandAndCountQueries($event->ID, '2025-06-01', '2025-06-10');
        $long = $this->expandAndCountQueries($event->ID, '2025-06-01', '2025-06-30');

        $this->assertSame(10, $short['count'], 'Short window sanity check');
        $this->assertSame(30, $long['count'], 'Long window sanity check');
        $this->assertSame(
            $short['queries'],
            $long['queries'],
            'Tripling the window must not triple the exception lookups (short: '
            . $short['queries'] . ', long: ' . $long['queries'] . ')'
        );
        $this->assertLessThan(
            $long['count'],
            $long['queries'],
            'Exception lookups must not be one query per generated date'
        );
    }

    /**
     * Regression test for dynamic/silverstripe-calendar#260.
     *
     * getOccurrences() must catch UnreachableException where it is actually thrown - while
     * iterating, because CarbonPeriod's filter() is lazy - log it, and stop generating
     * instead of crashing the render path. The overridden period builder below puts the
     * lattice ~25 years behind the requested window, so Carbon gives up after
     * CarbonPeriod::NEXT_MAX_ATTEMPTS (1000) consecutive rejections of its lazy range filter.
     */
    public function testUnreachableIterationIsCaughtAndLogged()
    {
        $event = new class extends EventPage {
            protected function createDailyPeriod(Carbon $start, Carbon $end): CarbonPeriod
            {
                return CarbonPeriod::create(
                    Carbon::parse('2000-01-01'),
                    '1 day',
                    Carbon::parse('2025-07-10')
                );
            }
        };

        $event->Title = 'Unreachable Daily Event';
        $event->StartDate = '2025-06-20';
        $event->Recursion = 'DAILY';
        $event->Interval = 1;
        $event->ParentID = $this->parentPage->ID;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Error iterating occurrences for event'));

        Injector::nest();
        try {
            Injector::inst()->registerService($logger, LoggerInterface::class);

            // iterator_to_array() forces the generator: before the fix the
            // UnreachableException escaped this line instead of being logged.
            $occurrences = iterator_to_array($event->getOccurrences('2025-06-20', '2025-06-25'));
        } finally {
            Injector::unnest();
        }

        $this->assertCount(0, $occurrences, 'An unreachable rule stops generation instead of throwing');
    }

    /**
     * Happy path: snapping the lattice must not move any occurrence. Weekly event on
     * Mondays, queried from a Monday three and a half years later.
     */
    public function testSnappedWeeklyEventKeepsTheSameDates()
    {
        $event = EventPage::create([
            'Title' => 'Long Running Weekly Event',
            'StartDate' => '2022-01-03', // Monday
            'StartTime' => '10:00:00',
            'Recursion' => 'WEEKLY',
            'Interval' => 1,
            'ParentID' => $this->parentPage->ID,
        ]);
        $event->write();
        $event->publishRecursive();

        $occurrences = iterator_to_array($event->getOccurrences('2025-06-02', '2025-06-30'));
        $dates = array_map(function ($occurrence) {
            return (string) $occurrence->StartDate;
        }, $occurrences);

        $this->assertEquals(
            ['2025-06-02', '2025-06-09', '2025-06-16', '2025-06-23', '2025-06-30'],
            $dates,
            'Every Monday in the window is returned, and only Mondays'
        );
    }

    /**
     * Snapping must land on the event's own lattice, not on the range start: an
     * every-3-days event only occurs on eventStart + n * 3 days.
     */
    public function testSnapLandsOnTheEventLatticeNotTheRangeStart()
    {
        $event = EventPage::create([
            'Title' => 'Every Three Days',
            'StartDate' => '2021-01-01',
            'Recursion' => 'DAILY',
            'Interval' => 3,
            'ParentID' => $this->parentPage->ID,
        ]);
        $event->write();
        $event->publishRecursive();

        $occurrences = iterator_to_array($event->getOccurrences('2025-06-01', '2025-06-20'));
        $dates = array_map(function ($occurrence) {
            return (string) $occurrence->StartDate;
        }, $occurrences);

        $expected = [];
        for ($cursor = Carbon::parse('2021-01-01'); $cursor->lte('2025-06-20'); $cursor->addDays(3)) {
            if ($cursor->gte('2025-06-01')) {
                $expected[] = $cursor->format('Y-m-d');
            }
        }

        $this->assertNotEmpty($expected, 'The reference lattice must contain dates in the window');
        $this->assertEquals($expected, $dates, 'Occurrences are exactly the lattice dates inside the window');
        $this->assertNotContains('2025-06-01', $dates, 'The range start itself is not an occurrence');
    }

    /**
     * A DELETED exception still skips its date inside an expanded window, and a MODIFIED
     * exception is still applied to it, after the per-expansion memo was introduced.
     */
    public function testExceptionsStillApplyThroughTheMemo()
    {
        $event = EventPage::create([
            'Title' => 'Memoised Exceptions Event',
            'StartDate' => '2025-06-02',
            'Recursion' => 'DAILY',
            'Interval' => 1,
            'RecursionEndDate' => '2025-06-11',
            'ParentID' => $this->parentPage->ID,
        ]);
        $event->write();
        $event->publishRecursive();

        $event->createException('2025-06-05', 'DELETED', [], 'Cancelled');
        $event->createException('2025-06-06', 'MODIFIED', ['Title' => 'Changed title'], 'Moved');

        $occurrences = iterator_to_array($event->getOccurrences('2025-06-02', '2025-06-11'));
        $dates = array_map(function ($occurrence) {
            return (string) $occurrence->StartDate;
        }, $occurrences);

        $this->assertCount(9, $occurrences, 'The deleted instance is skipped');
        $this->assertNotContains('2025-06-05', $dates);
        $this->assertContains('2025-06-06', $dates);

        $modified = null;
        foreach ($occurrences as $occurrence) {
            if ((string) $occurrence->StartDate === '2025-06-06') {
                $modified = $occurrence;
            }
        }
        $this->assertNotNull($modified);
        $this->assertEquals('Changed title', $modified->Title, 'The modified override is still applied');
    }

    /**
     * Regression test for the memo added in dynamic/silverstripe-calendar#260: an
     * exception written after an expansion has already populated the memo must be visible
     * to the next expansion, and removing it again must undo that.
     *
     * Both write paths are covered separately: a direct EventException::createDeletion()
     * that goes nowhere near the memo (only getOccurrences()'s per-expansion refresh can
     * see it, so dropping that refresh fails this test), and createException()/
     * removeException(), whose clearExceptionMap() calls must also take effect.
     */
    public function testMemoIsRefreshedBetweenExpansions()
    {
        $event = EventPage::create([
            'Title' => 'Memo Refresh Event',
            'StartDate' => '2025-06-03',
            'Recursion' => 'DAILY',
            'Interval' => 1,
            'RecursionEndDate' => '2025-06-12',
            'ParentID' => $this->parentPage->ID,
        ]);
        $event->write();
        $event->publishRecursive();

        $first = iterator_to_array($event->getOccurrences('2025-06-03', '2025-06-12'));
        $this->assertCount(10, $first, 'Populate the memo before writing any exception');

        // The discriminating half first: write straight through EventException, so no
        // memo invalidation hook runs at all, and do it before anything that clears the
        // memo - otherwise a lazily rebuilt map would hide a missing per-expansion refresh.
        EventException::createDeletion($event, '2025-06-09', 'Written behind the memo');

        $second = $this->expandDates($event, '2025-06-03', '2025-06-12');
        $this->assertCount(9, $second, 'An exception written without touching the memo is still picked up');
        $this->assertNotContains('2025-06-09', $second);

        $event->createException('2025-06-07', 'DELETED', [], 'Cancelled');

        $third = $this->expandDates($event, '2025-06-03', '2025-06-12');
        $this->assertCount(8, $third, 'The new exception is seen by the next expansion');
        $this->assertNotContains('2025-06-07', $third);

        $this->assertTrue($event->removeException('2025-06-07'));

        $fourth = $this->expandDates($event, '2025-06-03', '2025-06-12');
        $this->assertCount(9, $fourth, 'Removing the exception restores the occurrence');
        $this->assertContains('2025-06-07', $fourth);
    }

    /**
     * Expand a window and return just the instance dates.
     *
     * @return array<int,string>
     */
    private function expandDates(EventPage $event, string $start, string $end): array
    {
        $dates = [];
        foreach ($event->getOccurrences($start, $end) as $occurrence) {
            $dates[] = (string) $occurrence->StartDate;
        }
        return $dates;
    }

    /**
     * An expansion that was cut short by UnreachableException must not be written to the
     * instance cache: getCachedOccurrences() would otherwise serve the incomplete set as a
     * valid HIT for the whole TTL, which is the failure mode #226 removed for a failed
     * json_encode().
     */
    public function testInterruptedExpansionIsNotCached()
    {
        $event = new class extends EventPage {
            protected function createDailyPeriod(Carbon $start, Carbon $end): CarbonPeriod
            {
                return CarbonPeriod::create(
                    Carbon::parse('2000-01-01'),
                    '1 day',
                    Carbon::parse('2025-07-10')
                );
            }
        };

        $event->Title = 'Interrupted Expansion Event';
        $event->StartDate = '2025-06-20';
        $event->Recursion = 'DAILY';
        $event->Interval = 1;
        $event->ParentID = $this->parentPage->ID;

        // Deliberately not written: DataObject rejects an anonymous class as an allowed
        // ClassName, and the cache key only needs the object, not a persisted ID.
        EventInstanceCache::clearAllCache();

        $this->assertNull(
            EventInstanceCache::getCachedInstances($event, '2025-06-20', '2025-06-25'),
            'Nothing is cached before the expansion runs'
        );

        // The expansion is cut short (and logs) instead of throwing; see
        // testUnreachableIterationIsCaughtAndLogged for that part.
        iterator_to_array($event->getCachedOccurrences('2025-06-20', '2025-06-25'));

        $this->assertNull(
            EventInstanceCache::getCachedInstances($event, '2025-06-20', '2025-06-25'),
            'An interrupted expansion must not be cached as if it were a result set'
        );
    }

    /**
     * A window entirely before StartDate yields nothing instead of erroring.
     */
    public function testWindowBeforeStartDateIsEmpty()
    {
        $event = EventPage::create([
            'Title' => 'Future Event',
            'StartDate' => '2027-01-05',
            'Recursion' => 'DAILY',
            'Interval' => 1,
            'RecursionEndDate' => '2027-01-15',
            'ParentID' => $this->parentPage->ID,
        ]);
        $event->write();
        $event->publishRecursive();

        $occurrences = iterator_to_array($event->getOccurrences('2026-06-01', '2026-06-30'));

        $this->assertSame([], $occurrences, 'A window before StartDate yields no instances');
    }

    /**
     * A window whose end is before its start yields nothing and does not throw.
     */
    public function testInvertedWindowIsEmpty()
    {
        $event = EventPage::create([
            'Title' => 'Inverted Window Event',
            'StartDate' => '2021-01-04',
            'Recursion' => 'DAILY',
            'Interval' => 1,
            'ParentID' => $this->parentPage->ID,
        ]);
        $event->write();
        $event->publishRecursive();

        $occurrences = iterator_to_array($event->getOccurrences('2025-06-30', '2025-06-01'));

        $this->assertSame([], $occurrences, 'An end before start yields no instances');
    }

    /**
     * Test that createCarbonPeriod handles Throwable (such as Error) and returns null safely
     */
    public function testCreateCarbonPeriodHandlesThrowable()
    {
        $event = new class extends EventPage {
            protected function createDailyPeriod(\Carbon\Carbon $start, \Carbon\Carbon $end): \Carbon\CarbonPeriod
            {
                throw new \TypeError('Forced TypeError for testing throwable guard');
            }
        };

        $event->Title = 'Error Test Event';
        $event->StartDate = '2025-06-20';
        $event->Recursion = 'DAILY';

        // Protected method createCarbonPeriod is invoked via getOccurrences()
        $occurrences = iterator_to_array($event->getOccurrences('2025-06-20', '2025-06-25'));
        $this->assertCount(0, $occurrences);
    }
}
