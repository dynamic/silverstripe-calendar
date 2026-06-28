<?php

namespace Dynamic\Calendar\Task;

use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Versioned\Versioned;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class DateTimeConversion
 * @package Dynamic\Calendar\Task
 */
class DateTimeConversion extends BuildTask
{
    private static string $segment = 'calendar-datetime-conversion-task';

    protected string $title = 'Calendar - Legacy Datetime Conversion Task';

    protected static string $description = 'Convert Datetime data to separate Date and Time data';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->convertData();
        return Command::SUCCESS;
    }

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

    protected function yieldEvents(): \Generator
    {
        foreach (EventPage::get() as $event) {
            yield $event;
        }
    }
}
