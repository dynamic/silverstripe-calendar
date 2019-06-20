<?php

namespace Dynamic\Calendar\Task;

use Carbon\Carbon;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Dev\BuildTask;

/**
 * Class DateTimeTask
 * @package Dynamic\Calendar\Task
 */
class DateTimeTask extends BuildTask
{
    /**
     * @var string
     */
    protected $title = 'Calendar - Datetime Migration Task';

    /**
     * @var string
     */
    private static $segment = 'calendar-datetime-task';

    /**
     * @param \SilverStripe\Control\HTTPRequest $request
     */
    public function run($request)
    {
        $this->migrateDatetime();
    }

    /**
     *
     */
    protected function migrateDatetime()
    {
        /** @var EventPage $event */
        foreach ($this->getEvents() as $event) {
            $event->StartDate = Carbon::parse($event->StartDatetime)->format('Y-m-d');
            $event->StartTime = Carbon::parse($event->StartDatetime)->format('h:i:s');
            $event->EndDate = Carbon::parse($event->EndDatetime)->format('Y-m-d');
            $event->EndTime = Carbon::parse($event->EndDatetime)->format('h:i:s');

            $event->publishSingle();
        }
    }

    /**
     * @return \Generator
     */
    private function getEvents()
    {
        /** @var EventPage $event */
        foreach (EventPage::get() as $event) {
            yield $event;
        }
    }
}
