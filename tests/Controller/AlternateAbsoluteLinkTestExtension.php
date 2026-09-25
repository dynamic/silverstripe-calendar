<?php

namespace Dynamic\Calendar\Tests\Controller;

use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;

/**
 * Stands in for the extension silverstripe/subsites puts on SiteTree: it replaces a page's
 * absolute link with one on another host, which is how a subsite keeps its own domain in the
 * links it emits.
 *
 * Only ever applied inside CalendarControllerTest::testOccurrenceUrlInheritsAlternateAbsoluteLink(),
 * which asserts that an occurrence's url inherits it instead of resolving the host itself and
 * losing the host its parent event has.
 *
 * Named without a trailing "Test" so PHPUnit's testsuite (suffix "Test.php") does not try to
 * run it as a test case, and kept out of the test file so PSR-1's one-class-per-file rule holds.
 * Implements TestOnly, the SilverStripe convention for a helper class under tests/, so it stays
 * out of the module's normal class list. It is the only such class in this module's tests so
 * far.
 *
 * @package Dynamic\Calendar\Tests\Controller
 */
class AlternateAbsoluteLinkTestExtension extends Extension implements TestOnly
{
    /**
     * @param string|null $action
     * @return string|null
     */
    public function alternateAbsoluteLink($action = null)
    {
        $owner = $this->getOwner();
        if (!$owner instanceof EventPage) {
            return null;
        }

        return 'https://alternate.example.com/' . ltrim((string) $owner->RelativeLink($action), '/');
    }
}
