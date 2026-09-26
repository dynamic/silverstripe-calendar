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
    public function testRenderCalendarAppliesCategoryCap()
    {
        $controller = CalendarController::create($this->objFromFixture(Calendar::class, 'calendar1'));

        $kept = $this->createCategory('Render Kept Category');
        $this->createFeedEvent('Render Kept Category Event', $kept);
        $truncated = $this->createCategory('Render Truncated Category');
        $this->createFeedEvent('Render Truncated Category Event', $truncated);

        // One real category past the cap. Nothing non-scalar is mixed in here on
        // purpose: a value appended after the cap would be dropped by the cap
        // alone, which would leave the is_scalar filter untested. That case has
        // its own test below.
        $submitted = $this->buildOverCapSubmission($kept, $truncated);

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
     * Issue #204: the is_scalar filter has to bite on renderCalendar() too. The
     * non-scalar values are deliberately kept WITHIN the cap here - a value
     * appended past MAX_SUBMITTED_CATEGORIES would be dropped by the cap alone,
     * which would leave this guard untested (the mistake the separate cap test
     * above would have made).
     */
    public function testRenderCalendarDropsNonScalarCategoryValues()
    {
        $controller = CalendarController::create($this->objFromFixture(Calendar::class, 'calendar1'));

        $selected = $this->createCategory('Render Selected Category');
        $this->createFeedEvent('Render Selected Category Event', $selected);
        $this->createFeedEvent('Render Unselected Category Event', $this->createCategory('Render Unselected Category'));

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('renderCalendar');
        $method->setAccessible(true);

        $sanitised = $method->invoke($controller, new HTTPRequest('GET', '/', [
            'categories' => [[['nested' => 1]], (string)$selected->ID, [[424242]]],
        ]));
        $scalarOnly = $method->invoke($controller, new HTTPRequest('GET', '/', [
            'categories' => [(string)$selected->ID],
        ]));

        $this->assertContains(
            'Render Selected Category Event',
            $this->titlesFrom($sanitised['Events']),
            'the one scalar ID in the submission must still filter the rendered calendar'
        );
        $this->assertNotContains(
            'Render Unselected Category Event',
            $this->titlesFrom($sanitised['Events']),
            'the nested arrays must not turn the category filter off'
        );
        $this->assertSame(
            $this->titlesFrom($scalarOnly['Events']),
            $this->titlesFrom($sanitised['Events']),
            'a submission with non-scalar values mixed in must render the same events as the '
                . 'identical scalar-only submission'
        );
    }

    /**
     * Titles of the categories getAvailableCategoriesForTemplate() flagged
     * IsSelected, keyed by the category ID it reported.
     *
     * @return array<int, string>
     */
    private function selectedCategories($available): array
    {
        $selected = [];
        foreach ($available as $category) {
            if ($category->IsSelected) {
                $selected[(int)$category->ID] = $category->Title;
            }
        }
        return $selected;
    }

    /**
     * Titles of every category the template exposes, whether selected or not.
     *
     * @return string[]
     */
    private function availableCategoryTitles($available): array
    {
        $titles = [];
        foreach ($available as $category) {
            $titles[] = $category->Title;
        }
        return $titles;
    }

    /**
     * Issue #224 / #204: getAvailableCategoriesForTemplate() also read the raw
     * `categories` param, so its IsSelected flag was driven by an unbounded,
     * unsanitised submission. Asserted directly on the method (not only through
     * renderCalendar() calling it) for the over-cap case: the category at index 0
     * stays selected and a real category one past MAX_SUBMITTED_CATEGORIES does
     * not - if this fails, the filter UI is still reading the raw submission.
     */
    public function testAvailableCategoriesSelectionAppliesSubmissionCap()
    {
        $controller = CalendarController::create($this->objFromFixture(Calendar::class, 'calendar1'));

        $kept = $this->createCategory('Template Kept Category');
        $this->createFeedEvent('Template Kept Category Event', $kept);
        $truncated = $this->createCategory('Template Truncated Category');
        $this->createFeedEvent('Template Truncated Category Event', $truncated);

        $submitted = $this->buildOverCapSubmission($kept, $truncated);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getAvailableCategoriesForTemplate');
        $method->setAccessible(true);
        $available = $method->invoke($controller, new HTTPRequest('GET', '/', ['categories' => $submitted]));

        // Both must be exposed at all, or the "not selected" assertion below
        // would pass vacuously.
        $this->assertContains(
            $kept->Title,
            $this->availableCategoryTitles($available),
            'the index-0 category must appear in AvailableCategories'
        );
        $this->assertContains(
            $truncated->Title,
            $this->availableCategoryTitles($available),
            'the past-the-cap category must appear in AvailableCategories - it is used by a real '
                . 'event on this calendar, so a missing entry here means the fixture stopped proving anything'
        );

        $selected = $this->selectedCategories($available);
        $this->assertContains(
            $kept->Title,
            $selected,
            'the category at submission index 0 must stay selected through the cap'
        );
        $this->assertNotContains(
            $truncated->Title,
            $selected,
            'a real category one past the cap must not be marked IsSelected - if this fails, '
                . 'getAvailableCategoriesForTemplate() is still selecting from the raw submission'
        );
    }

    /**
     * Issue #224 / #204: non-scalar values in the submission must not influence
     * IsSelected on this call site either. Mixed WITHIN the cap, so it is the
     * is_scalar filter being tested and not the cap.
     */
    public function testAvailableCategoriesSelectionIgnoresNonScalarValues()
    {
        $controller = CalendarController::create($this->objFromFixture(Calendar::class, 'calendar1'));

        $selected = $this->createCategory('Template Selected Category');
        $this->createFeedEvent('Template Selected Category Event', $selected);
        $unselected = $this->createCategory('Template Unselected Category');
        $this->createFeedEvent('Template Unselected Category Event', $unselected);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getAvailableCategoriesForTemplate');
        $method->setAccessible(true);

        $sanitised = $method->invoke($controller, new HTTPRequest('GET', '/', [
            'categories' => [[[ 'nested' => 1 ]], (string)$selected->ID, [[424242]]],
        ]));
        $scalarOnly = $method->invoke($controller, new HTTPRequest('GET', '/', [
            'categories' => [(string)$selected->ID],
        ]));

        $this->assertSame(
            [(int)$selected->ID => $selected->Title],
            $this->selectedCategories($sanitised),
            'exactly the one scalar ID in the submission may be selected - the nested arrays must '
                . 'neither select a category of their own nor switch the filter off'
        );
        $this->assertSame(
            $this->selectedCategories($scalarOnly),
            $this->selectedCategories($sanitised),
            'a submission with non-scalar values mixed in must select the same categories as the '
                . 'identical scalar-only submission'
        );
        $this->assertContains(
            $unselected->Title,
            $this->availableCategoryTitles($sanitised),
            'the unselected category must be exposed unselected rather than dropped, or the '
                . 'equality assertion above could pass on an empty list'
        );
    }

    /**
     * Issue #204 guard against over-reach: an absent (or falsy) categories param
     * must stay "no category filter", exactly as before - the two endpoints must
     * not pick up events()' default-category fallback.
     *
     * The calendar is given a DefaultCategories entry that NO event uses, which
     * is what makes this a test rather than a tautology: were the fallback ever
     * added here, the no-param feed would shrink to that category's (empty) set
     * and these assertions would fail. On a fixture with no default categories
     * the fallback would be invisible to it.
     */
    public function testIcalWithoutCategoriesParamStaysUnfiltered()
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $controller = CalendarController::create($calendar);

        $category = $this->createCategory('Uncategorised Feed Category');
        $this->createFeedEvent('Categorised Feed Event', $category);
        $this->createFeedEvent('Plain Feed Event');

        $defaultOnly = $this->createCategory('Default Category No Event Uses');
        $calendar->DefaultCategories()->add($defaultOnly);
        $calendar->write();
        $calendar->publishRecursive();

        $this->assertTrue(
            $calendar->DefaultCategories()->exists(),
            'the fixture must actually attach a default category, or this test cannot see the fallback'
        );

        $body = $controller->ical(new HTTPRequest('GET', '/ical'))->getBody();
        $titles = $this->titlesFrom($body);

        $this->assertContains('Categorised Feed Event', $titles);
        $this->assertContains('Plain Feed Event', $titles);

        // Two more submissions that must behave the same way: an empty array, and
        // an array of empty strings. Both are "present but matched nothing" (the
        // second is a non-empty, therefore truthy, array - it reaches the resolver
        // and is dropped by the non-empty-value filter), which these endpoints
        // already treated as unfiltered, exactly like no param at all.
        foreach ([[], ['', '']] as $emptyValued) {
            $emptyBody = $controller->ical(new HTTPRequest('GET', '/ical', ['categories' => $emptyValued]))->getBody();
            $this->assertSame(
                $titles,
                $this->titlesFrom($emptyBody),
                'an empty-valued categories submission (' . json_encode($emptyValued) . ') must stay unfiltered'
            );
        }
    }

    /**
     * Issue #204, same guard on the other endpoint: renderCalendar() must not
     * pick up events()' default-category fallback either. Asserted with a
     * default category attached that no event uses, so a fallback appearing here
     * would empty the rendered list rather than pass unnoticed.
     */
    public function testRenderCalendarWithoutCategoriesParamStaysUnfiltered()
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $controller = CalendarController::create($calendar);

        $category = $this->createCategory('Render Uncategorised Category');
        $this->createFeedEvent('Render Categorised No Param Event', $category);
        $this->createFeedEvent('Render Plain No Param Event');

        $defaultOnly = $this->createCategory('Render Default Category No Event Uses');
        $calendar->DefaultCategories()->add($defaultOnly);
        $calendar->write();
        $calendar->publishRecursive();

        $this->assertTrue(
            $calendar->DefaultCategories()->exists(),
            'the fixture must actually attach a default category, or this test cannot see the fallback'
        );

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('renderCalendar');
        $method->setAccessible(true);

        foreach ([null, [], ['', '']] as $submission) {
            $request = $submission === null
                ? new HTTPRequest('GET', '/')
                : new HTTPRequest('GET', '/', ['categories' => $submission]);
            $titles = $this->titlesFrom($method->invoke($controller, $request)['Events']);

            $this->assertContains(
                'Render Categorised No Param Event',
                $titles,
                'renderCalendar() must stay unfiltered for ' . json_encode($submission) .
                    ' even though the calendar has a default category'
            );
            $this->assertContains(
                'Render Plain No Param Event',
                $titles,
                'the uncategorised event must survive too for ' . json_encode($submission)
            );
        }
    }
}
