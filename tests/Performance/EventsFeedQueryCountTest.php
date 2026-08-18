<?php

namespace Dynamic\Calendar\Tests\Performance;

use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Kernel;
use SilverStripe\Dev\CliDebugView;
use SilverStripe\Dev\SapphireTest;

/**
 * Guards the events feed against N+1 regressions.
 *
 * The invariant is scaling, not an absolute count: doubling the dataset must
 * not meaningfully change the number of queries one feed request issues.
 * Before 2.2.0 the feed cost ~6 queries per returned row (category many-many
 * per row - twice, one EventException query per generated occurrence date, one
 * SiteTree parent fetch per event); a 5,000-event site paid ~80,000 queries
 * for a one-month window.
 */
class EventsFeedQueryCountTest extends SapphireTest
{
    protected $usesDatabase = true;

    private ?string $previousEnvironment = null;

    private QueryCountingDebugView $counter;

    protected function setUp(): void
    {
        parent::setUp();

        // benchmarkQuery() only counts under Director::isDev().
        $kernel = Injector::inst()->get(Kernel::class);
        $this->previousEnvironment = $kernel->getEnvironment();
        $kernel->setEnvironment(Kernel::DEV);

        $this->counter = new QueryCountingDebugView();
        Injector::inst()->registerService($this->counter, CliDebugView::class);
    }

    protected function tearDown(): void
    {
        Injector::inst()->get(Kernel::class)->setEnvironment($this->previousEnvironment);
        parent::tearDown();
    }

    public function testFeedQueryCountDoesNotGrowWithDatasetSize(): void
    {
        $calendar = Calendar::create(['Title' => 'Query Count Cal', 'URLSegment' => 'query-count-cal']);
        $calendar->write();

        $categories = [];
        for ($i = 1; $i <= 5; $i++) {
            $category = Category::create(['Title' => "QC Category {$i}", 'URLSegment' => "qc-category-{$i}"]);
            $category->write();
            $categories[] = $category;
        }

        $this->seedEvents($calendar, $categories, 20, 0);
        // Warm schema/config caches so the first measurement is not bootstrap.
        $calendar->getEventsFeed(null, null, '2025-06-01', '2025-06-30')->toArray();

        $atN = $this->countFeedQueries($calendar);

        // Guard against the counter silently not engaging (e.g. the kernel not
        // in dev mode) - a broken counter would make this test pass vacuously.
        $this->assertGreaterThan(0, $atN, 'Query counter did not engage');

        $this->seedEvents($calendar, $categories, 20, 20);
        $at2N = $this->countFeedQueries($calendar);

        $delta = $at2N - $atN;

        $this->assertLessThanOrEqual(
            5,
            $delta,
            sprintf(
                'Feed query count grew with dataset size (%d queries at N=20, %d at N=40). '
                . 'A delta proportional to rows means an N+1 has regressed.',
                $atN,
                $at2N
            )
        );
    }

    private function seedEvents(Calendar $calendar, array $categories, int $count, int $offset): void
    {
        for ($i = 0; $i < $count; $i++) {
            $n = $offset + $i;
            $event = EventPage::create([
                'Title' => "QC Event {$n}",
                'URLSegment' => "qc-event-{$n}",
                'ParentID' => $calendar->ID,
                'StartDate' => sprintf('2025-06-%02d', ($n % 28) + 1),
                'Recursion' => $n % 5 === 0 ? 'WEEKLY' : 'NONE',
                'Interval' => 1,
                'RecursionEndDate' => $n % 5 === 0 ? '2025-07-31' : null,
            ]);
            $event->write();
            $event->Categories()->add($categories[$n % count($categories)]);
        }
    }

    private function countFeedQueries(Calendar $calendar): int
    {
        $this->counter->startCounting();

        try {
            $calendar->getEventsFeed(null, null, '2025-06-01', '2025-06-30')->toArray();
        } finally {
            $this->counter->stopCounting();
        }

        return $this->counter->getCount();
    }
}
