<?php

namespace Dynamic\Calendar\Task;

use Dynamic\Calendar\Page\EventPage;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Versioned\Versioned;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Converts the legacy StartDatetime/EndDatetime columns into the separate Date and Time
 * columns introduced alongside them (issue #294).
 *
 * A row is only touched when a legacy composite value has no modern counterpart yet:
 * StartDatetime with no StartDate, or EndDatetime with no EndDate. A row with nothing to
 * convert is skipped outright rather than rewritten, so running the task no longer adds a
 * version - and bumps LastEdited - on every event on the site.
 *
 * Safety around Versioned, which is the risk in a task like this:
 *
 * - Pinned to draft. Versioned::DEFAULT_MODE is Stage.Live and choose_site_stage() does not
 *   override it for a CLI call, so unpinned, EventPage::get() would iterate LIVE records and
 *   write them back over the draft, destroying unpublished editorial work.
 * - The live mirror is gated on stagesDiffer() as well as isPublished(), and goes through
 *   publishSingle() rather than copyVersionToStage(). The gate here used to be
 *   isPublished() && isLatestVersion(), and because draft always holds the latest version,
 *   that collapsed to isPublished: every published event was republished, dragging whatever
 *   pending draft edit it found along with the conversion. copyVersionToStage() is also the
 *   inner half of publishing - it moves the version without firing onBeforePublish or
 *   onAfterPublish, so Fluent, Subsites and any other module listening for publication would
 *   keep serving stale copies. The stagesDiffer() gate is what makes publishing safe here: the
 *   draft holds no unpublished edits to carry along. A draft-only page is never published at
 *   all.
 *
 * Counts go to the operator, and a row that cannot be converted is named per row instead of
 * aborting the run: this task changes content rows on a production upgrade path, so silence is
 * not acceptable, and one unreadable legacy value must not stop the rest of the site from
 * converting.
 *
 * Reachable over the web only under BuildTask's own permissions_for_browser_execution. It is
 * idempotent - the fields it converts to are the ones it checks for - but it does write and
 * publish content, so run it while the site is quiet: a row an editor saves mid-run is written
 * from the snapshot this task read.
 *
 * Usage: sake calendar-datetime-conversion-task
 *        (also reachable in a browser at dev/tasks/calendar-datetime-conversion-task)
 */
class DateTimeConversion extends BuildTask
{
    private static string $segment = 'calendar-datetime-conversion-task';

    protected string $title = 'Calendar - Legacy Datetime Conversion Task';

    protected static string $description = 'Convert Datetime data to separate Date and Time data';

    /**
     * @var int Rows whose legacy value was written to the modern fields on draft.
     */
    protected int $converted = 0;

    /**
     * @var int Converted rows whose live copy was updated because it already matched draft.
     */
    protected int $published = 0;

    /**
     * @var int Converted rows deliberately kept off live because the draft held pending edits.
     */
    protected int $heldBack = 0;

    /**
     * @var int Rows with nothing to convert, which are not written at all.
     */
    protected int $skipped = 0;

    /**
     * @var int Rows that could not be converted.
     */
    protected int $failed = 0;

    /**
     * @param InputInterface $input
     * @param PolyOutput $output
     * @return int
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $output->writeln('Converting legacy Datetime values into separate Date and Time fields...');

        return Versioned::withVersionedMode(function () use ($output): int {
            Versioned::set_stage(Versioned::DRAFT);

            return $this->runOnDraft($output);
        });
    }

    /**
     * Convert every draft row, then report.
     *
     * @param PolyOutput $output
     * @return int
     */
    protected function runOnDraft(PolyOutput $output): int
    {
        foreach ($this->yieldEvents() as $event) {
            // Per row, not around the loop. The stage probes sit outside persist()'s try, so
            // without this one row that Versioned cannot answer aborts the whole run
            // part-way through, with no summary and no exit code.
            try {
                $this->convertEvent($event, $output);
            } catch (\Throwable $e) {
                $this->failed++;
                $output->writeln(sprintf('Error converting event %d: %s', $event->ID ?? 0, $e->getMessage()));
            }
        }

        $output->writeln(sprintf(
            'Done. Converted: %d, Published: %d, Held back: %d, Skipped: %d, Failed: %d.',
            $this->converted,
            $this->published,
            $this->heldBack,
            $this->skipped,
            $this->failed
        ));

        // A row this task could not convert is a real problem. A held-back row is not: the
        // conversion is on draft and publishing it is the editor's call, which is why those
        // are reported but do not fail the run.
        return $this->failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Apply the conversion to one draft row, mirroring to live only when that publishes
     * nothing unrelated.
     *
     * @param EventPage $event
     * @param PolyOutput $output
     * @return void
     */
    protected function convertEvent(EventPage $event, PolyOutput $output): void
    {
        if (!$event->exists()) {
            return;
        }

        // Resolved before anything else, and before the stage probes: an unreadable value
        // throws here, so a row is never left half converted.
        try {
            $conversion = $this->conversionFor($event);
        } catch (\Throwable $e) {
            $this->failed++;
            $output->writeln(sprintf('Error converting event %d: %s', $event->ID ?? 0, $e->getMessage()));

            return;
        }

        if ($conversion === null) {
            // Nothing to convert: no writeToStage(), no publish, no new version.
            $this->skipped++;

            return;
        }

        // Captured before the write: afterwards the draft has changed by definition, so a
        // stage comparison then would always report a difference.
        $wasPublished = $event->isPublished();
        $hadUnpublishedEdits = $event->stagesDiffer();

        foreach ($conversion as $field => $value) {
            $event->$field = $value;
        }

        if ($this->persist($event, $output, $wasPublished, $hadUnpublishedEdits)) {
            $this->converted++;
        }
    }

    /**
     * Write to draft, and mirror to live only when that publishes nothing unrelated.
     *
     * @param EventPage $event
     * @param PolyOutput $output
     * @param bool $wasPublished
     * @param bool $hadUnpublishedEdits
     * @return bool True when the draft write succeeded.
     */
    protected function persist(
        EventPage $event,
        PolyOutput $output,
        bool $wasPublished,
        bool $hadUnpublishedEdits
    ): bool {
        try {
            $this->writeDraft($event);

            if (!$wasPublished) {
                // A draft-only page must not gain a live version from maintenance.
                return true;
            }

            if ($hadUnpublishedEdits) {
                // publishSingle() moves the whole draft record, which would carry someone's
                // unpublished edits along with this conversion.
                $this->heldBack++;
                $output->writeln(sprintf(
                    'Event %d converted on draft only: it has unpublished edits, so publishing it '
                    . 'is left to whoever is editing it.',
                    $event->ID ?? 0
                ));

                return true;
            }

            // publishSingle(), not copyVersionToStage(): the latter moves the version without
            // firing the publish hooks, so Fluent, Subsites and anything else listening for
            // publication keeps serving stale copies. The stagesDiffer() gate above is what
            // makes publishing safe here: the draft holds no unpublished edits to carry along.
            $event->publishSingle();
            $this->published++;

            return true;
        } catch (\Throwable $e) {
            $this->failed++;
            $output->writeln(sprintf('Error converting event %d: %s', $event->ID ?? 0, $e->getMessage()));

            return false;
        }
    }

    /**
     * The modern fields a row is missing, derived from its legacy columns.
     *
     * Only a legacy value with no modern counterpart is converted: a date or time an editor
     * already chose is never overwritten from the legacy column. Returns null when the row
     * holds nothing to convert, which is what keeps a converted row from being rewritten on
     * the next run.
     *
     * Both halves are resolved before anything is returned, so a value this task cannot parse
     * leaves the row untouched rather than half converted.
     *
     * @param EventPage $event
     * @return array<string,string>|null
     */
    protected function conversionFor(EventPage $event): ?array
    {
        $conversion = [];

        if ($event->StartDatetime && !$event->StartDate) {
            $startTimestamp = $this->parseLegacy($event->StartDatetime, 'StartDatetime', $event);
            $conversion['StartDate'] = date('Y-m-d', $startTimestamp);
            $conversion['StartTime'] = date('H:i:s', $startTimestamp);
        }

        if ($event->EndDatetime && !$event->EndDate) {
            $endTimestamp = $this->parseLegacy($event->EndDatetime, 'EndDatetime', $event);
            $conversion['EndDate'] = date('Y-m-d', $endTimestamp);
            $conversion['EndTime'] = date('H:i:s', $endTimestamp);
        }

        return $conversion === [] ? null : $conversion;
    }

    /**
     * Read one legacy composite value as a timestamp.
     *
     * Refuses rather than guessing: strtotime() returns false for a value it cannot read, and
     * date() would turn that into 1970-01-01, quietly inventing a date on an event. The caller
     * turns this into a counted failure and carries on with the next row.
     *
     * @param mixed $value
     * @param string $field
     * @param EventPage $event
     * @return int
     */
    protected function parseLegacy(mixed $value, string $field, EventPage $event): int
    {
        $timestamp = is_string($value) ? strtotime(trim($value)) : false;

        if ($timestamp === false) {
            throw new \RuntimeException(sprintf(
                'Cannot parse %s value "%s" on event %d',
                $field,
                (string)$value,
                $event->ID ?? 0
            ));
        }

        return $timestamp;
    }

    /**
     * Write the row to the draft stage.
     *
     * A seam so the failure path can be exercised without replacing persist() wholesale, which
     * would bypass the try/catch whose accounting is the thing under test.
     *
     * @param EventPage $event
     * @return void
     */
    protected function writeDraft(EventPage $event): void
    {
        $event->writeToStage(Versioned::DRAFT);
    }

    /**
     * Every event, one at a time, on whichever stage the task pinned itself to.
     *
     * @return \Generator<int,EventPage>
     */
    protected function yieldEvents(): \Generator
    {
        foreach (EventPage::get() as $event) {
            yield $event;
        }
    }
}
