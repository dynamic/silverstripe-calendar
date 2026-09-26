<?php

namespace Dynamic\Calendar\Tests\Controller;

use Carbon\Carbon;
use Dynamic\Calendar\Controller\CalendarController;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Model\List\PaginatedList;
use SilverStripe\Dev\SapphireTest;

/**
 * Tests for CalendarController parameter handling (start/end vs from/to)
 */
class CalendarControllerParameterTest extends SapphireTest
{
    protected static $fixture_file = '../fixtures.yml';

    public function testGetFromDateAcceptsFromParameter()
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $controller = CalendarController::create($calendar);

        $request = new HTTPRequest('GET', '/', ['from' => '2025-10-01']);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getFromDate');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $request);

        $this->assertNotNull($result);
        $this->assertEquals('2025-10-01', $result->format('Y-m-d'));
    }

    public function testGetFromDateAcceptsStartParameter()
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $controller = CalendarController::create($calendar);

        $request = new HTTPRequest('GET', '/', ['start' => '2025-10-01']);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getFromDate');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $request);

        $this->assertNotNull($result);
        $this->assertEquals('2025-10-01', $result->format('Y-m-d'));
    }

    public function testGetToDateAcceptsToParameter()
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $controller = CalendarController::create($calendar);

        $request = new HTTPRequest('GET', '/', ['to' => '2025-10-31']);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getToDate');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $request);

        $this->assertNotNull($result);
        $this->assertEquals('2025-10-31', $result->format('Y-m-d'));
    }

    public function testGetToDateAcceptsEndParameter()
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $controller = CalendarController::create($calendar);

        $request = new HTTPRequest('GET', '/', ['end' => '2025-10-31']);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getToDate');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $request);

        $this->assertNotNull($result);
        $this->assertEquals('2025-10-31', $result->format('Y-m-d'));
    }

    public function testFromParameterTakesPrecedenceOverStart()
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $controller = CalendarController::create($calendar);

        $request = new HTTPRequest('GET', '/', [
            'from' => '2025-10-01',
            'start' => '2025-09-01',
        ]);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getFromDate');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $request);

        $this->assertNotNull($result);
        $this->assertEquals('2025-10-01', $result->format('Y-m-d'), "'from' parameter should take precedence");
    }

    /**
     * An empty 'from' must fall through to a usable 'start' rather than winning
     * the coalesce and discarding it.
     *
     * Regression guard on the getFromDate() rewrite in #149: `??` alone kept a
     * present-but-empty 'from', and the truthiness check below it then dropped
     * it, so '?from=&start=2025-10-01' resolved to no lower bound at all. The
     * rewritten accessor falls through on the empty string instead, and this
     * pins that - reverting to `??` fails here.
     */
    public function testEmptyFromParameterFallsBackToStartParameter()
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $controller = CalendarController::create($calendar);

        $request = new HTTPRequest('GET', '/', [
            'from' => '',
            'start' => '2025-10-01',
        ]);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getFromDate');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $request);

        $this->assertNotNull($result);
        $this->assertEquals(
            '2025-10-01',
            $result->format('Y-m-d'),
            "An empty 'from' must not suppress a valid 'start'"
        );
    }

    /**
     * Mirror of testEmptyFromParameterFallsBackToStartParameter() for the
     * upper bound.
     */
    public function testEmptyToParameterFallsBackToEndParameter()
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $controller = CalendarController::create($calendar);

        $request = new HTTPRequest('GET', '/', [
            'to' => '',
            'end' => '2025-10-31',
        ]);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getToDate');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $request);

        $this->assertNotNull($result);
        $this->assertEquals(
            '2025-10-31',
            $result->format('Y-m-d'),
            "An empty 'to' must not suppress a valid 'end'"
        );
    }

    /**
     * A usable legacy 'from' still wins over 'start' when it is non-empty - the
     * fall-through must not invert the documented precedence.
     */
    public function testNonEmptyFromStillTakesPrecedenceAfterFallThroughChange()
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $controller = CalendarController::create($calendar);

        $request = new HTTPRequest('GET', '/', [
            'from' => '2025-10-01',
            'start' => '',
        ]);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getFromDate');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $request);

        $this->assertNotNull($result);
        $this->assertEquals(
            '2025-10-01',
            $result->format('Y-m-d'),
            "A non-empty 'from' must still beat 'start'"
        );
    }

    /**
     * Both bounds empty resolves to no filter at all, not to an error: this is
     * the pre-existing behaviour and must survive the rewrite.
     */
    public function testEmptyFromAndToResolveToNoFilter()
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $controller = CalendarController::create($calendar);

        $request = new HTTPRequest('GET', '/', [
            'from' => '',
            'to' => '',
        ]);

        $reflection = new \ReflectionClass($controller);
        $fromMethod = $reflection->getMethod('getFromDate');
        $fromMethod->setAccessible(true);
        $toMethod = $reflection->getMethod('getToDate');
        $toMethod->setAccessible(true);

        $this->assertNull($fromMethod->invoke($controller, $request));
        $this->assertNull($toMethod->invoke($controller, $request));
    }

    /**
     * Create a published event on the fixture calendar, optionally in a category.
     */
    private function createFeedEvent(string $title, ?Category $category = null): EventPage
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $event = EventPage::create([
            'Title' => $title,
            'ParentID' => $calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $event->write();
        $event->publishRecursive();
        if ($category !== null) {
            $event->Categories()->add($category);
        }
        return $event;
    }

    /**
     * Create a category with a unique title.
     */
    private function createCategory(string $title): Category
    {
        $category = Category::create(['Title' => $title . ' ' . uniqid()]);
        $category->write();
        return $category;
    }

    /**
     * Titles contained in a body (ICS text or a rendered Events list).
     *
     * @param iterable|string $subject
     * @return string[]
     */
    private function titlesFrom($subject): array
    {
        if (is_string($subject)) {
            $titles = [];
            foreach (explode("\n", $subject) as $line) {
                if (str_starts_with($line, 'SUMMARY:')) {
                    $titles[] = trim(substr($line, 8));
                }
            }
            return $titles;
        }
        $titles = [];
        foreach ($subject as $event) {
            $titles[] = $event->Title;
        }
        return $titles;
    }

    /**
     * Build a submission of exactly MAX_SUBMITTED_CATEGORIES + 1 real category
     * IDs, with $first at index 0 and $last one past the cap.
     *
     * @return string[]
     */
    private function buildOverCapSubmission(Category $first, Category $last): array
    {
        $submitted = [(string)$first->ID];
        $fillerCount = CalendarController::MAX_SUBMITTED_CATEGORIES - 1;
        for ($i = 0; $i < $fillerCount; $i++) {
            $filler = $this->createCategory('Filler Category ' . $i);
            $submitted[] = (string)$filler->ID;
        }
        $submitted[] = (string)$last->ID;

        $this->assertCount(
            CalendarController::MAX_SUBMITTED_CATEGORIES + 1,
            $submitted,
            'the fixture must submit exactly one more category than the cap, or it proves nothing'
        );
        $this->assertNotContains(
            (string)$last->ID,
            array_slice($submitted, 0, CalendarController::MAX_SUBMITTED_CATEGORIES),
            'the fixture must place the last category past the cap, or it proves nothing'
        );

        return $submitted;
    }

    /**
     * Issue #204: the MAX_SUBMITTED_CATEGORIES cap has to apply on /ical too, not
     * only on the events() AJAX path. The category past the cap must be dropped
     * before the IN() list is built, so its event stays out of the feed.
     */
    public function testIcalAppliesCategorySubmissionCap()
    {
        $controller = CalendarController::create($this->objFromFixture(Calendar::class, 'calendar1'));

        $kept = $this->createCategory('Cap Kept Category');
        $this->createFeedEvent('Cap Kept Category Event', $kept);
        $truncated = $this->createCategory('Cap Truncated Category');
        $this->createFeedEvent('Cap Truncated Category Event', $truncated);

        $submitted = $this->buildOverCapSubmission($kept, $truncated);

        $response = $controller->ical(new HTTPRequest('GET', '/ical', ['categories' => $submitted]));
        $titles = $this->titlesFrom($response->getBody());

        $this->assertContains(
            'Cap Kept Category Event',
            $titles,
            'the category at submission index 0 must survive the cap'
        );
        $this->assertNotContains(
            'Cap Truncated Category Event',
            $titles,
            'a real, matching category one past the cap must be excluded from the ICS feed - if '
                . 'this fails, /ical is querying the whole unbounded submission'
        );
    }

    /**
     * Issue #204: non-scalar submitted values must be dropped on /ical, the same
     * way resolveCategoryIDs() drops them on events(). The sanitised submission
     * must behave exactly like its scalar-only equivalent - same body, same
     * filtered set - rather than reaching byIDs() with an array in it.
     */
    public function testIcalDropsNonScalarCategoryValuesLikeScalarsOnly()
    {
        $controller = CalendarController::create($this->objFromFixture(Calendar::class, 'calendar1'));

        $wanted = $this->createCategory('Selected Category');
        $this->createFeedEvent('Selected Category Event', $wanted);
        $other = $this->createCategory('Unselected Category');
        $this->createFeedEvent('Unselected Category Event', $other);

        // ?categories[][]=1 ...: two nested arrays around one real scalar ID.
        $sanitised = $controller->ical(new HTTPRequest('GET', '/ical', [
            'categories' => [[[ 'nested' => 1 ]], (string)$wanted->ID, [[424242]]],
        ]))->getBody();
        $scalarOnly = $controller->ical(new HTTPRequest('GET', '/ical', [
            'categories' => [(string)$wanted->ID],
        ]))->getBody();

        $this->assertContains(
            'Selected Category Event',
            $this->titlesFrom($sanitised),
            'the one scalar ID in the submission must still filter the feed'
        );
        $this->assertNotContains(
            'Unselected Category Event',
            $this->titlesFrom($sanitised),
            'a submission containing the selected category must still exclude other categories - '
                . 'the nested arrays must not turn the filter off'
        );
        $this->assertSame(
            $this->titlesFrom($scalarOnly),
            $this->titlesFrom($sanitised),
            'a submission with non-scalar values mixed in must resolve to the same feed as the '
                . 'identical scalar-only submission'
        );

        // A submission whose every value is non-scalar keeps nothing to match,
        // which is the pre-existing "present but unmatched" state: no category
        // filter, not an error and not an empty feed.
        $allNonScalar = $controller->ical(new HTTPRequest('GET', '/ical', [
            'categories' => [[['nested' => 1]], [['also' => 2]]],
        ]))->getBody();
        $this->assertEqualsCanonicalizing(
            ['Selected Category Event', 'Unselected Category Event'],
            $this->titlesFrom($allNonScalar),
            'a submission of only nested arrays must collapse to the unfiltered feed of this '
                . 'calendar, the same as a submission that matches no category'
        );
    }

    /**
     * Issue #204: renderCalendar() (the HTML calendar / index action) reads the
     * same param and was likewise uncapped and unsanitised.
     */
    public function testRenderCalendarAppliesCategoryCapAndSanitisation()
    {
        $controller = CalendarController::create($this->objFromFixture(Calendar::class, 'calendar1'));

        $kept = $this->createCategory('Render Kept Category');
        $this->createFeedEvent('Render Kept Category Event', $kept);
        $truncated = $this->createCategory('Render Truncated Category');
        $this->createFeedEvent('Render Truncated Category Event', $truncated);

        // Over the cap, plus a nested array appended past it: the cap must
        // truncate before the query, and the non-scalar value must be dropped
        // rather than reaching byIDs().
        $submitted = $this->buildOverCapSubmission($kept, $truncated);
        $submitted[] = [['nested' => 1]];

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('renderCalendar');
        $method->setAccessible(true);
        $result = $method->invoke($controller, new HTTPRequest('GET', '/', ['categories' => $submitted]));

        $this->assertInstanceOf(PaginatedList::class, $result['Events']);
        $titles = $this->titlesFrom($result['Events']);

        $this->assertContains(
            'Render Kept Category Event',
            $titles,
            'the category at submission index 0 must survive the cap'
        );
        $this->assertNotContains(
            'Render Truncated Category Event',
            $titles,
            'a real category one past the cap must not reach the rendered calendar - if this '
                . 'fails, renderCalendar() is still querying the raw submission'
        );
    }

    /**
     * Issue #204 guard against over-reach: an absent (or falsy) categories param
     * must stay "no category filter", exactly as before - the two endpoints must
     * not pick up events()' default-category fallback.
     */
    public function testIcalWithoutCategoriesParamStaysUnfiltered()
    {
        $controller = CalendarController::create($this->objFromFixture(Calendar::class, 'calendar1'));

        $category = $this->createCategory('Uncategorised Feed Category');
        $this->createFeedEvent('Categorised Feed Event', $category);
        $this->createFeedEvent('Plain Feed Event');

        $body = $controller->ical(new HTTPRequest('GET', '/ical'))->getBody();
        $titles = $this->titlesFrom($body);

        $this->assertContains('Categorised Feed Event', $titles);
        $this->assertContains('Plain Feed Event', $titles);

        // A falsy submission (empty array, empty strings) behaves the same way.
        $falsyTitles = $this->titlesFrom(
            $controller->ical(new HTTPRequest('GET', '/ical', ['categories' => []]))->getBody()
        );
        $this->assertSame($titles, $falsyTitles, 'an empty submission must stay unfiltered');
    }
}
