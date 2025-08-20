<?php

namespace Dynamic\Calendar\Task;

use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;

/**
 * Carbon Recursion Migration Task
 *
 * This task was originally designed to migrate from RRule-based recurring events
 * to the new Carbon-based system. Since the RecursiveEvent class has been removed
 * and the system has fully transitioned to Carbon, this task now serves as a
 * validation tool for Carbon-based recurrence patterns.
 *
 * @package Dynamic\Calendar\Task
 */
class CarbonRecursionMigrationTask extends BuildTask
{
    /**
     * @var string
     */
    protected $title = 'Carbon Recursion Migration';

    /**
     * @var string
     */
    protected $description = 'Validates Carbon-based recurring events (migration no longer needed)';

    /**
     * @var bool
     */
    protected $enabled = true;

    /**
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        $this->printHeader();

        // Check if RecursiveEvent class exists (legacy RRule system)
        if (!class_exists('Dynamic\Calendar\Page\RecursiveEvent')) {
            $this->printMessage(
                "Migration is no longer needed - RecursiveEvent class has been removed " .
                "as part of the Carbon system migration.",
                'notice'
            );
            $this->printMessage(
                "This task was designed to migrate from the legacy RRule system to the Carbon system.",
                'notice'
            );
            $this->printMessage(
                "Since the legacy system has been completely removed, running validation instead...",
                'success'
            );
            $this->printMessage("");

            $this->validateCarbonRecurrence();
        } else {
            $this->printMessage(
                "RecursiveEvent class still exists - this indicates the migration may not be complete.",
                'warning'
            );
        }

        $this->printFooter();
    }

    /**
     * Validate Carbon-based recurrence patterns
     */
    protected function validateCarbonRecurrence(): void
    {
        $this->printMessage("Validating Carbon-based recurrence patterns...");

        $recurringEvents = EventPage::get()->filter('Recursion:not', 'NONE');
        $validCount = 0;
        $invalidCount = 0;

        if ($recurringEvents->count() === 0) {
            $this->printMessage("No recurring events found to validate");
            return;
        }

        foreach ($recurringEvents as $event) {
            try {
                // Test that we can generate occurrences
                $occurrences = iterator_to_array($event->getOccurrences(null, null, 5));

                if (count($occurrences) > 0) {
                    $validCount++;
                    $this->printMessage("✓ Valid: {$event->Title} ({$event->getRecurrenceDescription()})");
                } else {
                    $invalidCount++;
                    $this->printMessage("✗ No occurrences: {$event->Title}", 'warning');
                }
            } catch (\Exception $e) {
                $invalidCount++;
                $this->printMessage("✗ Error in {$event->Title}: " . $e->getMessage(), 'error');
            }
        }

        $this->printMessage(
            "Validation complete: {$validCount} valid, {$invalidCount} invalid",
            $invalidCount > 0 ? 'warning' : 'success'
        );
    }

    /**
     * Print header
     */
    protected function printHeader(): void
    {
        $this->printMessage("=== Carbon Recursion Migration Task ===", 'header');
        $this->printMessage("This task validates Carbon-based recurring events.");
        $this->printMessage("The original migration functionality is no longer needed.");
        $this->printMessage("");
    }

    /**
     * Print footer
     */
    protected function printFooter(): void
    {
        $this->printMessage("");
        $this->printMessage("=== Task Complete ===", 'header');
        $this->printMessage("If you found any validation errors, check:");
        $this->printMessage("1. Event recurrence configuration in the CMS");
        $this->printMessage("2. Carbon date formatting and rules");
        $this->printMessage("3. Custom recurrence patterns");
    }

    /**
     * Print a formatted message
     *
     * @param string $message
     * @param string $type
     */
    protected function printMessage(string $message, string $type = 'info'): void
    {
        $prefix = match ($type) {
            'header' => '### ',
            'success' => '✓ ',
            'warning' => '⚠ ',
            'error' => '✗ ',
            'notice' => 'ℹ ',
            default => '  '
        };

        echo $prefix . $message . PHP_EOL;
    }
}
