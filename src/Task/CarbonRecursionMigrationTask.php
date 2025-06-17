<?php

namespace Dynamic\Calendar\Task;

use Dynamic\Calendar\Model\EventException;
use Dynamic\Calendar\Page\EventPage;
use Dynamic\Calendar\Page\RecursiveEvent;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

/**
 * Carbon Recursion Migration Task
 *
 * Migrates existing RRule-based recurring events to the new Carbon-based system.
 * This task handles:
 * - Converting RecursiveEvent instances to EventException records where needed
 * - Cleaning up orphaned RecursiveEvent records
 * - Validating Carbon-based recurrence patterns
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
    protected $description = 'Migrates from RRule-based to Carbon-based recurring events';

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

        $dryRun = $request->getVar('dry-run') === '1';
        $cleanOnly = $request->getVar('clean-only') === '1';

        if ($dryRun) {
            $this->printMessage("DRY RUN MODE - No changes will be made", 'notice');
        }

        if ($cleanOnly) {
            $this->cleanupRecursiveEvents($dryRun);
        } else {
            $this->migrateRecursiveEvents($dryRun);
            $this->cleanupRecursiveEvents($dryRun);
            $this->validateCarbonRecurrence();
        }

        $this->printFooter();
    }

    /**
     * Migrate RecursiveEvent instances to EventException records where needed
     *
     * @param bool $dryRun
     */
    protected function migrateRecursiveEvents(bool $dryRun): void
    {
        $this->printMessage("Migrating RecursiveEvent instances to Carbon system...");

        $recursiveEvents = RecursiveEvent::get();
        $migratedCount = 0;
        $skippedCount = 0;

        foreach ($recursiveEvents as $recursiveEvent) {
            $originalEvent = $recursiveEvent->Parent();

            if (!$originalEvent || !$originalEvent->exists()) {
                $this->printMessage("Skipping orphaned RecursiveEvent ID: {$recursiveEvent->ID}", 'warning');
                $skippedCount++;
                continue;
            }

            // Check if this recursive event has been modified from the original
            $hasModifications = $this->recursiveEventHasModifications($recursiveEvent, $originalEvent);

            if ($hasModifications) {
                // Create an EventException record for this modification
                if (!$dryRun) {
                    $this->createEventException($recursiveEvent, $originalEvent);
                }
                $migratedCount++;
                $this->printMessage("Migrated modified instance: {$recursiveEvent->Title} on {$recursiveEvent->StartDate}");
            } else {
                // This is just a standard recurrence instance, no exception needed
                $skippedCount++;
            }
        }

        $this->printMessage("Migration complete: {$migratedCount} instances migrated, {$skippedCount} skipped", 'success');
    }

    /**
     * Check if a RecursiveEvent has modifications compared to its parent
     *
     * @param RecursiveEvent $recursiveEvent
     * @param EventPage $originalEvent
     * @return bool
     */
    protected function recursiveEventHasModifications(RecursiveEvent $recursiveEvent, EventPage $originalEvent): bool
    {
        $fieldsToCheck = [
            'Title',
            'Content',
            'StartTime',
            'EndTime',
            'AllDay',
        ];

        foreach ($fieldsToCheck as $field) {
            if ($recursiveEvent->$field !== $originalEvent->$field) {
                return true;
            }
        }

        // Check if categories are different
        $originalCategories = $originalEvent->Categories()->column('ID');
        $recursiveCategories = $recursiveEvent->Categories()->column('ID');

        if (array_diff($originalCategories, $recursiveCategories) || array_diff($recursiveCategories, $originalCategories)) {
            return true;
        }

        return false;
    }

    /**
     * Create an EventException record from a RecursiveEvent
     *
     * @param RecursiveEvent $recursiveEvent
     * @param EventPage $originalEvent
     */
    protected function createEventException(RecursiveEvent $recursiveEvent, EventPage $originalEvent): void
    {
        // Check if exception already exists
        $existing = EventException::findForEventAndDate($originalEvent, $recursiveEvent->StartDate);
        if ($existing) {
            $this->printMessage("Exception already exists for {$originalEvent->Title} on {$recursiveEvent->StartDate}", 'warning');
            return;
        }

        $overrides = [];

        // Check each field for modifications
        if ($recursiveEvent->Title !== $originalEvent->Title) {
            $overrides['Title'] = $recursiveEvent->Title;
        }

        if ($recursiveEvent->Content !== $originalEvent->Content) {
            $overrides['Content'] = $recursiveEvent->Content;
        }

        if ($recursiveEvent->StartTime !== $originalEvent->StartTime) {
            $overrides['StartTime'] = $recursiveEvent->StartTime;
        }

        if ($recursiveEvent->EndTime !== $originalEvent->EndTime) {
            $overrides['EndTime'] = $recursiveEvent->EndTime;
        }

        if ($recursiveEvent->AllDay !== $originalEvent->AllDay) {
            $overrides['AllDay'] = $recursiveEvent->AllDay;
        }

        if (!empty($overrides)) {
            EventException::createModification(
                $originalEvent,
                $recursiveEvent->StartDate,
                $overrides,
                'Migrated from RecursiveEvent'
            );
        }
    }

    /**
     * Clean up RecursiveEvent records that are no longer needed
     *
     * @param bool $dryRun
     */
    protected function cleanupRecursiveEvents(bool $dryRun): void
    {
        $this->printMessage("Cleaning up RecursiveEvent records...");

        $recursiveEvents = RecursiveEvent::get();
        $deletedCount = 0;

        foreach ($recursiveEvents as $recursiveEvent) {
            if (!$dryRun) {
                $recursiveEvent->delete();
            }
            $deletedCount++;
        }

        if ($deletedCount > 0) {
            $action = $dryRun ? 'would be deleted' : 'deleted';
            $this->printMessage("{$deletedCount} RecursiveEvent records {$action}", 'success');
        } else {
            $this->printMessage("No RecursiveEvent records found to clean up");
        }

        // Also clean up the RecursiveEvent table structure if we're not in dry run mode
        if (!$dryRun && $deletedCount > 0) {
            $this->printMessage("Cleaning up RecursiveEvent database table...");
            // Note: Table cleanup would happen during dev/build, not here
        }
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

        $this->printMessage("Validation complete: {$validCount} valid, {$invalidCount} invalid",
                          $invalidCount > 0 ? 'warning' : 'success');
    }

    /**
     * Print header
     */
    protected function printHeader(): void
    {
        $this->printMessage("=== Carbon Recursion Migration Task ===", 'header');
        $this->printMessage("This task migrates from RRule-based to Carbon-based recurring events.");
        $this->printMessage("Options:");
        $this->printMessage("  ?dry-run=1     - Preview changes without making them");
        $this->printMessage("  ?clean-only=1  - Only clean up RecursiveEvent records");
        $this->printMessage("");
    }

    /**
     * Print footer
     */
    protected function printFooter(): void
    {
        $this->printMessage("");
        $this->printMessage("=== Migration Complete ===", 'header');
        $this->printMessage("Next steps:");
        $this->printMessage("1. Run dev/build to update database schema");
        $this->printMessage("2. Test recurring events in the CMS");
        $this->printMessage("3. Update any custom templates that reference RecursiveEvent");
    }

    /**
     * Print a formatted message
     *
     * @param string $message
     * @param string $type
     */
    protected function printMessage(string $message, string $type = 'info'): void
    {
        $prefix = match($type) {
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
