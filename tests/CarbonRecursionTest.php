<?php

namespace Dynamic\Calendar\Tests;

use Carbon\Carbon;
use Dynamic\Calendar\Model\EventException;
use Dynamic\Calendar\Model\EventInstance;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
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
     * @var SiteTree
     */
    protected $parentPage;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a parent page for EventPages
        $this->parentPage = SiteTree::create([
            'Title' => 'Events',
            'URLSegment' => 'events',
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
        $dates = array_map(function($occ) { return $occ->StartDate; }, $occurrences);
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
        $dates = array_map(function($occ) { return $occ->StartDate; }, $occurrences);
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
        $dates = array_map(function($occ) { return (string) $occ->StartDate; }, $occurrences);
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
}
