<?php

namespace Dynamic\Calendar\Task;

use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Versioned\Versioned;

/**
 * Class DateTimeConversion
 * @package Dynamic\Calendar\Task
 */
class DateTimeConversion extends BuildTask
{
    /**
     * @var string
     */
    protected $title = 'Calendar - Legacy Datetime Conversion Task';

    /**
     * @var string
     */
    private static string $segment = 'calendar-datetime-conversion-task';

    /**
     * @var string
     */
    protected $description = 'Convert Datetime data to separate Date and Time data';

    /**
     * @param HTTPRequest $request
     */
    public function run($request): void
    {
        $this->convertData();
    }

    /**
     *
     */
    protected function convertData(): void
    {
        /** @var EventPage $event */
        foreach ($this->yieldEvents() as $event) {
            if ($event instanceof EventPage && $event->exists()) {
                $isPublished = $event->isPublished();
                $latestPublished = $event->isLatestVersion();

                if ($event->StartDatetime && !$event->StartDate) {
                    $startTimestamp = strtotime($event->StartDatetime);

                    $event->StartDate = date('Y-m-d', $startTimestamp);
                    $event->StartTime = date('H:i:s', $startTimestamp);
                }

                if ($event->EndDatetime && !$event->EndDate) {
                    $endTimestamp = strtotime($event->EndDatetime);

                    $event->EndDate = date('Y-m-d', $endTimestamp);
                    $event->EndTime = date('H:i:s', $endTimestamp);
                }

                $event->writeToStage(Versioned::DRAFT);

                if ($isPublished && $latestPublished) {
                    $event->publishRecursive();
                }
            }
        }
    }

    /**
     * @return \Generator
     */
    protected function yieldEvents(): \Generator
    {
        foreach (EventPage::get() as $event) {
            yield $event;
        }
    }
}
