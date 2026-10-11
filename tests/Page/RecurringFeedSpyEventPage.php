<?php

namespace Dynamic\Calendar\Tests\Page;

use Dynamic\Calendar\Page\EventPage;
use Generator;

/**
 * An EventPage that records every occurrence expansion it is asked for.
 *
 * Calendar::getEventsFeed() expands each recurring event through
 * EventPage::getCachedOccurrences(), so a feed that prunes its recurring candidate set in
 * SQL has to be tested against the call itself, not against its output: an event that was
 * never expanded and one that was expanded but produced no instance in the window are
 * indistinguishable in the returned list (dynamic/silverstripe-calendar#299).
 *
 * It is a DataObject subclass rather than a mock because the feed queries
 * EventPage::get() directly - only a stored record of a subclass that list includes can
 * observe that query.
 */
class RecurringFeedSpyEventPage extends EventPage
{
    /**
     * Declared explicitly rather than derived from the class name: a DataObject subclass
     * that lets the table name fall back to its class would change stored table with every
     * rename, and silverstan requires the property on any DataObject configuration.
     *
     * @var string
     */
    private static string $table_name = 'recurring_feed_spy_event_page';

    /**
     * Titles of every spy event whose getCachedOccurrences() was called, keyed by record ID.
     *
     * Reset by RecurringFeedSpyEventPage::resetExpansions() so one test cannot see another
     * test's expansions (the test database is rebuilt per test, but this array is static
     * and would survive that rebuild).
     *
     * @var array<int,string>
     */
    public static array $expanded = [];

    /**
     * Clear the recorded expansions. Call before the feed call under test.
     *
     * @return void
     */
    public static function resetExpansions(): void
    {
        self::$expanded = [];
    }

    /**
     * Record the expansion request, then behave exactly like EventPage.
     *
     * @param \Carbon\Carbon|string|null $startDate
     * @param \Carbon\Carbon|string|null $endDate
     * @param int|null $limit
     * @return Generator<\Dynamic\Calendar\Model\EventInstance>
     */
    public function getCachedOccurrences($startDate = null, $endDate = null, ?int $limit = null): Generator
    {
        self::$expanded[(int)$this->ID] = (string)$this->Title;

        return parent::getCachedOccurrences($startDate, $endDate, $limit);
    }
}
