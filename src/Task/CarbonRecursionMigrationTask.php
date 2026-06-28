<?php

namespace Dynamic\Calendar\Task;

use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

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
    private static $segment = 'carbon-recursion-migration';

    protected string $title = 'Carbon Recursion Migration';

    protected static string $description = 'Validates Carbon-based recurring events (migration no longer needed)';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->printHeader($output);

        // Check if RecursiveEvent class exists (legacy RRule system)
        if (!class_exists('Dynamic\Calendar\Page\RecursiveEvent')) {
            $this->printMessage(
                $output,
                "Migration is no longer needed - RecursiveEvent class has been removed " .
                "as part of the Carbon system migration.",
                'notice'
            );
            $this->printMessage(
                $output,
                "This task was designed to migrate from the legacy RRule system to the Carbon system.",
                'notice'
            );
            $this->printMessage(
                $output,
                "Since the legacy system has been completely removed, running validation instead...",
                'success'
            );
            $this->printMessage($output, "");

            $this->validateCarbonRecurrence($output);
        } else {
            $this->printMessage(
                $output,
                "RecursiveEvent class still exists - this indicates the migration may not be complete.",
                'warning'
            );
        }

        $this->printFooter($output);

        return Command::SUCCESS;
    }

    protected function validateCarbonRecurrence(PolyOutput $output): void
    {
        $this->printMessage($output, "Validating Carbon-based recurrence patterns...");

        $recurringEvents = EventPage::get()->filter('Recursion:not', 'NONE');
        $validCount = 0;
        $invalidCount = 0;

        if ($recurringEvents->count() === 0) {
            $this->printMessage($output, "No recurring events found to validate");
            return;
        }

        foreach ($recurringEvents as $event) {
            try {
                $occurrences = iterator_to_array($event->getOccurrences(null, null, 5));

                if (count($occurrences) > 0) {
                    $validCount++;
                    $this->printMessage($output, "✓ Valid: {$event->Title} ({$event->getRecurrenceDescription()})");
                } else {
                    $invalidCount++;
                    $this->printMessage($output, "✗ No occurrences: {$event->Title}", 'warning');
                }
            } catch (\Exception $e) {
                $invalidCount++;
                $this->printMessage($output, "✗ Error in {$event->Title}: " . $e->getMessage(), 'error');
            }
        }

        $this->printMessage(
            $output,
            "Validation complete: {$validCount} valid, {$invalidCount} invalid",
            $invalidCount > 0 ? 'warning' : 'success'
        );
    }

    protected function printHeader(PolyOutput $output): void
    {
        $this->printMessage($output, "=== Carbon Recursion Migration Task ===", 'header');
        $this->printMessage($output, "This task validates Carbon-based recurring events.");
        $this->printMessage($output, "The original migration functionality is no longer needed.");
        $this->printMessage($output, "");
    }

    protected function printFooter(PolyOutput $output): void
    {
        $this->printMessage($output, "");
        $this->printMessage($output, "=== Task Complete ===", 'header');
        $this->printMessage($output, "If you found any validation errors, check:");
        $this->printMessage($output, "1. Event recurrence configuration in the CMS");
        $this->printMessage($output, "2. Carbon date formatting and rules");
        $this->printMessage($output, "3. Custom recurrence patterns");
    }

    protected function printMessage(PolyOutput $output, string $message, string $type = 'info'): void
    {
        $prefix = match ($type) {
            'header' => '### ',
            'success' => '✓ ',
            'warning' => '⚠ ',
            'error' => '✗ ',
            'notice' => 'ℹ ',
            default => '  '
        };

        $output->writeln($prefix . $message);
    }
}
