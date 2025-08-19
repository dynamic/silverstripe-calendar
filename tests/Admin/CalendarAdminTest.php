<?php

namespace Dynamic\Calendar\Tests\Admin;

use Dynamic\Calendar\Admin\CalendarAdmin;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataList;

/**
 * Class CalendarAdminTest
 * @package Dynamic\Calendar\Tests\Admin
 */
class CalendarAdminTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = '../Calendar.yml';

    /**
     * Test that EventPage is included in managed models
     */
    public function testEventPageInManagedModels()
    {
        $admin = CalendarAdmin::create();
        $managedModels = $admin->config()->get('managed_models');
        
        $this->assertContains(Category::class, $managedModels);
        $this->assertContains(EventPage::class, $managedModels);
    }

    /**
     * Test optimized getList method for EventPage
     */
    public function testOptimizedEventPageList()
    {
        $admin = CalendarAdmin::create();
        $admin->modelClass = EventPage::class;
        
        $list = $admin->getList();
        
        // Test that method returns DataList
        $this->assertInstanceOf(DataList::class, $list);
        
        // Get the SQL query to verify optimizations
        $sql = $list->sql();
        
        // Check that Category joins are included for optimization
        $this->assertStringContainsString('EventPage_Categories', $sql);
        $this->assertStringContainsString('Category', $sql);
        
        // Check that proper sorting is applied
        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('StartDate', $sql);
    }

    /**
     * Test getList method doesn't affect other models
     */
    public function testGetListForOtherModels()
    {
        $admin = CalendarAdmin::create();
        $admin->modelClass = Category::class;
        
        $list = $admin->getList();
        
        // Should not include event-specific optimizations for Category
        $sql = $list->sql();
        $this->assertStringNotContainsString('Dynamic_Calendar_Category_EventPages', $sql);
    }

    /**
     * Test EventPage filtering and management
     */
    public function testEventPageManagement()
    {
        $admin = CalendarAdmin::create();
        $admin->modelClass = EventPage::class;
        
        $list = $admin->getList();
        
        // Test that EventPages can be managed through admin
        $this->assertInstanceOf(DataList::class, $list);
        
        // Test that it includes EventPage objects
        foreach ($list->limit(5) as $item) {
            $this->assertInstanceOf(EventPage::class, $item);
        }
    }
}