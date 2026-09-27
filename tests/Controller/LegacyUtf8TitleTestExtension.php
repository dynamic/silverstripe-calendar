<?php

namespace Dynamic\Calendar\Tests\Controller;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Model\List\ArrayList;

/**
 * Stands in for an event whose Title holds a byte sequence that is not valid UTF-8 - the
 * state a bad legacy import leaves behind when the source column was latin1, or when a
 * truncated multibyte character was pasted in by a tool that did not speak UTF-8.
 *
 * The bytes cannot be produced by writing them through the ORM: the test database's columns
 * are utf8mb4 under STRICT_TRANS_TABLES, so MySQL rejects such a row outright ("Incorrect
 * string value: '\xB11'") - a storage-layer failure that never reaches the controller.
 * SilverStripe 6 also has no `onAfterRetrieve` hook to corrupt a row on its way out of the database.
 * So this corrupts the value in memory through `Calendar::getEventsFeed()`'s own
 * `updateEventsFeed` extension point, which is the last thing to touch the feed before
 * `CalendarController::events()` hands it to `json_encode()`. What the controller sees is
 * identical to what it would see from a legacy row: a record field `json_encode()` refuses to
 * encode. Only the arrival of the bytes is doubled; the encode, cache and log paths under test
 * are unmocked.
 *
 * Only ever applied inside CalendarControllerCacheTest::testJsonEncodeFailureIsNotCachedAndLogs().
 * Arm it through `LegacyUtf8TitleTestExtension::$corruptTitleAfter` and disarm it in a
 * `finally` block: it fires on every feed the extended calendar builds while armed.
 *
 * Named without a trailing "Test" so PHPUnit's testsuite (suffix "Test.php") does not try to
 * run it as a test case. Implements TestOnly, the SilverStripe convention for a helper class
 * under tests/.
 *
 * @package Dynamic\Calendar\Tests\Controller
 */
class LegacyUtf8TitleTestExtension extends Extension implements TestOnly
{
    /**
     * Title to corrupt on the way out of the feed, or '' while disarmed. Public and static
     * because the hook runs on a feed the test never holds a reference to.
     *
     * @var string
     */
    public static string $corruptTitleAfter = '';

    /**
     * Append an invalid UTF-8 byte sequence to the Title of any armed event in the feed.
     *
     * @param ArrayList $events
     * @return void
     */
    public function updateEventsFeed(ArrayList &$events): void
    {
        if (self::$corruptTitleAfter === '') {
            return;
        }

        foreach ($events as $event) {
            if ((string) $event->Title !== self::$corruptTitleAfter) {
                continue;
            }

            // "\xB1\x31" is the byte pair the issue names: 0xB1 is not a valid UTF-8 lead
            // sequence, so json_encode() fails on the string with
            // JSON_ERROR_INVALID_UTF8 - the same bytes MySQL's own
            // "Incorrect string value: '\xB11'" message reports.
            $event->setField('Title', $event->Title . "\xB1\x31");
        }
    }
}
