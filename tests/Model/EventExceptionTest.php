<?php

namespace Dynamic\Calendar\Tests\Model;

use Carbon\Carbon;
use Dynamic\Calendar\Model\EventException;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\DateField;

/**
 * Class EventExceptionTest
 * @package Dynamic\Calendar\Tests\Model
 */
class EventExceptionTest extends SapphireTest
{
    /**
     * @var Calendar
     */
    protected $calendar;

    /**
     * @var EventPage
     */
    protected $recurringEvent;

    /**
     * Setup test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create test calendar
        $this->calendar = Calendar::create([
            'Title' => 'Test Calendar',
            'URLSegment' => 'test-calendar',
        ]);
        $this->calendar->write();
        $this->calendar->publishRecursive();

        // Create test recurring event
        $this->recurringEvent = EventPage::create([
            'Title' => 'Test Recurring Event',
            'URLSegment' => 'test-recurring-event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::today()->format('Y-m-d'),
            'EndDate' => Carbon::today()->format('Y-m-d'),
            'StartTime' => '10:00:00',
            'EndTime' => '11:00:00',
            'Recursion' => 'WEEKLY',
            'Interval' => 1,
            'RecursionEndDate' => Carbon::today()->addWeeks(10)->format('Y-m-d'),
        ]);
        $this->recurringEvent->write();
        $this->recurringEvent->publishRecursive();
    }

    /**
     * Test basic EventException creation
     */
    public function testEventExceptionCreation()
    {
        $exception = EventException::create([
            'OriginalEventID' => $this->recurringEvent->ID,
            'InstanceDate' => Carbon::today()->addWeek()->format('Y-m-d'),
            'Action' => 'DELETED',
            'Reason' => 'Test deletion'
        ]);
        $exception->write();

        $this->assertTrue($exception->exists());
        $this->assertEquals($this->recurringEvent->ID, $exception->OriginalEventID);
        $this->assertEquals('DELETED', $exception->Action);
        $this->assertEquals('Test deletion', $exception->Reason);
    }

    /**
     * Test isDeleted method
     */
    public function testIsDeleted()
    {
        $deletedException = EventException::create([
            'OriginalEventID' => $this->recurringEvent->ID,
            'InstanceDate' => Carbon::today()->addWeek()->format('Y-m-d'),
            'Action' => 'DELETED',
        ]);

        $modifiedException = EventException::create([
            'OriginalEventID' => $this->recurringEvent->ID,
            'InstanceDate' => Carbon::today()->addWeeks(2)->format('Y-m-d'),
            'Action' => 'MODIFIED',
        ]);

        $this->assertTrue($deletedException->isDeleted());
        $this->assertFalse($modifiedException->isDeleted());
    }

    /**
     * Test isModified method
     */
    public function testIsModified()
    {
        $deletedException = EventException::create([
            'OriginalEventID' => $this->recurringEvent->ID,
            'InstanceDate' => Carbon::today()->addWeek()->format('Y-m-d'),
            'Action' => 'DELETED',
        ]);

        $modifiedException = EventException::create([
            'OriginalEventID' => $this->recurringEvent->ID,
            'InstanceDate' => Carbon::today()->addWeeks(2)->format('Y-m-d'),
            'Action' => 'MODIFIED',
        ]);

        $this->assertFalse($deletedException->isModified());
        $this->assertTrue($modifiedException->isModified());
    }

    /**
     * Test getTitle method
     */
    public function testGetTitle()
    {
        $deletedException = EventException::create([
            'OriginalEventID' => $this->recurringEvent->ID,
            'InstanceDate' => '2025-06-25',
            'Action' => 'DELETED',
        ]);

        $modifiedException = EventException::create([
            'OriginalEventID' => $this->recurringEvent->ID,
            'InstanceDate' => '2025-07-02',
            'Action' => 'MODIFIED',
            'ModifiedTitle' => 'Modified Event Title',
        ]);

        $this->assertEquals('Delete Test Recurring Event on 2025-06-25', $deletedException->getTitle());
        $this->assertEquals('Modify Test Recurring Event on 2025-07-02', $modifiedException->getTitle());
    }

    /**
     * Test override functionality
     */
    public function testOverrideFunctionality()
    {
        $exception = EventException::create([
            'OriginalEventID' => $this->recurringEvent->ID,
            'InstanceDate' => Carbon::today()->addWeek()->format('Y-m-d'),
            'Action' => 'MODIFIED',
            'ModifiedTitle' => 'Override Title',
            'ModifiedStartTime' => '14:00:00',
        ]);

        // Test hasOverride
        $this->assertTrue($exception->hasOverride('Title'));
        $this->assertTrue($exception->hasOverride('StartTime'));
        $this->assertFalse($exception->hasOverride('EndTime'));

        // Test getOverride
        $this->assertEquals('Override Title', $exception->getOverride('Title'));
        $this->assertEquals('14:00:00', $exception->getOverride('StartTime'));
        $this->assertNull($exception->getOverride('EndTime'));

        // Test getOverrides
        $overrides = $exception->getOverrides();
        $this->assertArrayHasKey('Title', $overrides);
        $this->assertArrayHasKey('StartTime', $overrides);
        $this->assertArrayNotHasKey('EndTime', $overrides);
        $this->assertEquals('Override Title', $overrides['Title']);
        $this->assertEquals('14:00:00', $overrides['StartTime']);
    }

    /**
     * Test getCMSFields returns instance dropdown for recurring events
     */
    public function testGetCMSFieldsInstanceDropdown()
    {
        $exception = EventException::create([
            'OriginalEventID' => $this->recurringEvent->ID,
        ]);

        $fields = $exception->getCMSFields();
        $instanceField = $fields->fieldByName('Root.Main.InstanceDate');

        // Should be a dropdown for recurring events
        $this->assertInstanceOf(DropdownField::class, $instanceField);
        $this->assertEquals('Event Instance', $instanceField->Title());

        $source = $instanceField->getSource();
        $this->assertGreaterThan(0, count($source), 'Should have instance options');
    }

    /**
     * Test getCMSFields returns date field for non-recurring events
     */
    public function testGetCMSFieldsDateFieldFallback()
    {
        // Create non-recurring event
        $nonRecurringEvent = EventPage::create([
            'Title' => 'Non-Recurring Event',
            'URLSegment' => 'non-recurring-event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::today()->format('Y-m-d'),
            'EndDate' => Carbon::today()->format('Y-m-d'),
            'StartTime' => '10:00:00',
            'EndTime' => '11:00:00',
            'Recursion' => 'NONE',
        ]);
        $nonRecurringEvent->write();

        $exception = EventException::create([
            'OriginalEventID' => $nonRecurringEvent->ID,
        ]);

        $fields = $exception->getCMSFields();
        $instanceField = $fields->fieldByName('Root.Main.InstanceDate');

        // Should be a date field for non-recurring events
        $this->assertInstanceOf(DateField::class, $instanceField);
        $this->assertEquals('Instance Date', $instanceField->Title());
    }

    /**
     * Test permissions
     */
    public function testPermissions()
    {
        $exception = EventException::create([
            'OriginalEventID' => $this->recurringEvent->ID,
            'InstanceDate' => Carbon::today()->addWeek()->format('Y-m-d'),
            'Action' => 'DELETED',
        ]);

        // Login as admin to test permissions
        $this->logInWithPermission('ADMIN');

        // Test that permissions are properly configured for admin users
        $this->assertTrue($exception->canView());
        $this->assertTrue($exception->canEdit());
        $this->assertTrue($exception->canDelete());
        $this->assertTrue($exception->canCreate());
    }
}
