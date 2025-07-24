<?php

namespace Dynamic\Calendar\Task;

use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\Control\Director;

/**
 * Legacy Field Cleanup Task
 *
 * Migrates data from deprecated datetime fields to new date/time fields
 * and removes unused database columns.
 *
 * Usage: sake dev/tasks/legacy-field-cleanup-task
 */
class LegacyFieldCleanupTask extends BuildTask
{
    /**
     * @var string
     */
    protected $title = 'Calendar Legacy Field Cleanup';

    /**
     * @var string
     */
    private static string $segment = 'calendar-legacy-field-cleanup-task';

    /**
     * @var string
     */
    protected $description = 'Migrates data from deprecated StartDatetime/EndDatetime fields to ' .
                           'StartDate/StartTime/EndDate/EndTime fields';

    /**
     * @var bool
     */
    protected $enabled = true;

    /**
     * Run the task
     */
    public function run($request)
    {
        $this->message('Starting legacy field cleanup for Calendar module...');

        // Check if we're in dev mode for safety
        if (!Director::isDev()) {
            $this->message('ERROR: This task should only be run in dev mode for safety.', 'error');
            return;
        }

        // Step 1: Check for existing data in deprecated fields
        $this->checkDeprecatedData();

        // Step 2: Migrate data if needed
        $this->migrateData();

        // Step 3: Verify data integrity
        $this->verifyDataIntegrity();

        // Step 4: Show cleanup recommendations
        $this->showCleanupRecommendations();

        $this->message('Legacy field cleanup complete!');
    }

    /**
     * Check for existing data in deprecated fields
     */
    protected function checkDeprecatedData()
    {
        $this->message('Checking for data in deprecated fields...');

        // Check if deprecated columns exist
        $schema = DB::get_schema();
        $tableName = EventPage::singleton()->baseTable();

        $deprecatedFields = ['StartDatetime', 'EndDatetime'];
        $existingFields = [];

        foreach ($deprecatedFields as $field) {
            if ($schema->hasField($tableName, $field)) {
                $existingFields[] = $field;
            }
        }

        if (empty($existingFields)) {
            $this->message('No deprecated fields found in database schema.');
            return;
        }

        $this->message('Found deprecated fields: ' . implode(', ', $existingFields));

        // Count records with data in deprecated fields
        foreach ($existingFields as $field) {
            $count = DB::query("SELECT COUNT(*) FROM \"{$tableName}\" WHERE \"{$field}\" IS NOT NULL")->value();
            $this->message("Records with {$field} data: {$count}");
        }
    }

    /**
     * Migrate data from deprecated fields to new fields
     */
    protected function migrateData()
    {
        $this->message('Starting data migration...');

        $events = EventPage::get()->filter([
            'StartDatetime:not' => null
        ]);

        $migrated = 0;
        $errors = 0;

        foreach ($events as $event) {
            try {
                $updated = false;

                // Migrate StartDatetime to StartDate and StartTime
                if ($event->StartDatetime) {
                    $datetime = new \DateTime($event->StartDatetime);

                    if (!$event->StartDate) {
                        $event->StartDate = $datetime->format('Y-m-d');
                        $updated = true;
                    }

                    if (!$event->StartTime) {
                        $event->StartTime = $datetime->format('H:i:s');
                        $updated = true;
                    }
                }

                // Migrate EndDatetime to EndDate and EndTime
                if ($event->EndDatetime) {
                    $datetime = new \DateTime($event->EndDatetime);

                    if (!$event->EndDate) {
                        $event->EndDate = $datetime->format('Y-m-d');
                        $updated = true;
                    }

                    if (!$event->EndTime) {
                        $event->EndTime = $datetime->format('H:i:s');
                        $updated = true;
                    }
                }

                if ($updated) {
                    $event->write();
                    $migrated++;
                    $this->message("Migrated event: {$event->Title} (ID: {$event->ID})");
                }
            } catch (\Exception $e) {
                $errors++;
                $this->message("Error migrating event {$event->ID}: " . $e->getMessage(), 'error');
            }
        }

        $this->message("Migration complete. Migrated: {$migrated}, Errors: {$errors}");
    }

    /**
     * Verify data integrity after migration
     */
    protected function verifyDataIntegrity()
    {
        $this->message('Verifying data integrity...');

        // Check for events without start dates
        $eventsWithoutStartDate = EventPage::get()->filter(['StartDate' => null]);
        if ($eventsWithoutStartDate->count() > 0) {
            $this->message("WARNING: {$eventsWithoutStartDate->count()} events have no StartDate", 'error');
        }

        // Check for events with invalid date formats
        $events = EventPage::get()->exclude(['StartDate' => null]);
        $invalidDates = 0;

        foreach ($events as $event) {
            if (!strtotime($event->StartDate)) {
                $invalidDates++;
                $this->message("Invalid StartDate for event {$event->ID}: {$event->StartDate}", 'error');
            }
        }

        if ($invalidDates === 0) {
            $this->message('All dates appear valid.');
        } else {
            $this->message("Found {$invalidDates} events with invalid dates", 'error');
        }
    }

    /**
     * Show cleanup recommendations
     */
    protected function showCleanupRecommendations()
    {
        $this->message('=== CLEANUP RECOMMENDATIONS ===');
        $this->message('');
        $this->message('After verifying the migration was successful, you can:');
        $this->message('');
        $this->message('1. Remove deprecated fields from EventPage.php:');
        $this->message("   - Remove 'StartDatetime' => 'DBDatetime'");
        $this->message("   - Remove 'EndDatetime' => 'DBDatetime'");
        $this->message('');
        $this->message('2. Run dev/build to update the database schema');
        $this->message('');
        $this->message('3. Optional: Drop the deprecated columns from the database:');
        $this->message('   ALTER TABLE "EventPage" DROP COLUMN "StartDatetime";');
        $this->message('   ALTER TABLE "EventPage" DROP COLUMN "EndDatetime";');
        $this->message('');
        $this->message('IMPORTANT: Always backup your database before making schema changes!');
    }

    /**
     * Output a message
     */
    protected function message($message, $type = 'info')
    {
        $prefix = '';
        switch ($type) {
            case 'error':
                $prefix = 'ERROR: ';
                break;
            case 'warning':
                $prefix = 'WARNING: ';
                break;
        }

        echo $prefix . $message . "\n";
    }
}
