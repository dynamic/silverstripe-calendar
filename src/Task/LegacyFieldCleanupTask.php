<?php

namespace Dynamic\Calendar\Task;

use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Control\Director;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Legacy Field Cleanup Task
 *
 * Migrates data from deprecated datetime fields to new date/time fields
 * and removes unused database columns.
 *
 * Usage: sake dev/tasks/calendar-legacy-field-cleanup-task
 */
class LegacyFieldCleanupTask extends BuildTask
{
    private static string $segment = 'calendar-legacy-field-cleanup-task';

    protected string $title = 'Calendar Legacy Field Cleanup';

    protected static string $description = 'Migrates data from deprecated StartDatetime/EndDatetime fields'
        . ' to StartDate/StartTime/EndDate/EndTime fields';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $output->writeln('Starting legacy field cleanup for Calendar module...');

        if (!Director::isDev()) {
            $output->writeln('ERROR: This task should only be run in dev mode for safety.');
            return Command::FAILURE;
        }

        $this->checkDeprecatedData($output);
        $this->migrateData($output);
        $this->verifyDataIntegrity($output);
        $this->showCleanupRecommendations($output);

        $output->writeln('Legacy field cleanup complete!');

        return Command::SUCCESS;
    }

    protected function checkDeprecatedData(PolyOutput $output): void
    {
        $output->writeln('Checking for data in deprecated fields...');

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
            $output->writeln('No deprecated fields found in database schema.');
            return;
        }

        $output->writeln('Found deprecated fields: ' . implode(', ', $existingFields));

        foreach ($existingFields as $field) {
            $count = DB::query("SELECT COUNT(*) FROM \"{$tableName}\" WHERE \"{$field}\" IS NOT NULL")->value();
            $output->writeln("Records with {$field} data: {$count}");
        }
    }

    protected function migrateData(PolyOutput $output): void
    {
        $output->writeln('Starting data migration...');

        $events = EventPage::get()->filter([
            'StartDatetime:not' => null
        ]);

        $migrated = 0;
        $errors = 0;

        foreach ($events as $event) {
            try {
                $updated = false;

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
                    $output->writeln("Migrated event: {$event->Title} (ID: {$event->ID})");
                }
            } catch (\Exception $e) {
                $errors++;
                $output->writeln("Error migrating event {$event->ID}: " . $e->getMessage());
            }
        }

        $output->writeln("Migration complete. Migrated: {$migrated}, Errors: {$errors}");
    }

    protected function verifyDataIntegrity(PolyOutput $output): void
    {
        $output->writeln('Verifying data integrity...');

        $eventsWithoutStartDate = EventPage::get()->filter(['StartDate' => null]);
        if ($eventsWithoutStartDate->count() > 0) {
            $output->writeln("WARNING: {$eventsWithoutStartDate->count()} events have no StartDate");
        }

        $events = EventPage::get()->exclude(['StartDate' => null]);
        $invalidDates = 0;

        foreach ($events as $event) {
            if (!strtotime($event->StartDate)) {
                $invalidDates++;
                $output->writeln("Invalid StartDate for event {$event->ID}: {$event->StartDate}");
            }
        }

        if ($invalidDates === 0) {
            $output->writeln('All dates appear valid.');
        } else {
            $output->writeln("Found {$invalidDates} events with invalid dates");
        }
    }

    protected function showCleanupRecommendations(PolyOutput $output): void
    {
        $output->writeln('=== CLEANUP RECOMMENDATIONS ===');
        $output->writeln('');
        $output->writeln('After verifying the migration was successful, you can:');
        $output->writeln('');
        $output->writeln('1. Remove deprecated fields from EventPage.php:');
        $output->writeln("   - Remove 'StartDatetime' => 'DBDatetime'");
        $output->writeln("   - Remove 'EndDatetime' => 'DBDatetime'");
        $output->writeln('');
        $output->writeln('2. Run dev/build to update the database schema');
        $output->writeln('');
        $output->writeln('3. Optional: Drop the deprecated columns from the database:');
        $output->writeln('   ALTER TABLE "EventPage" DROP COLUMN "StartDatetime";');
        $output->writeln('   ALTER TABLE "EventPage" DROP COLUMN "EndDatetime";');
        $output->writeln('');
        $output->writeln('IMPORTANT: Always backup your database before making schema changes!');
    }
}
