<?php

namespace Dynamic\Calendar\Task;

use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Versioned\Versioned;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Converts the legacy StartDatetime/EndDatetime columns into the separate Date and Time
 * columns introduced alongside them (issue #294).
 *
 * A row is only touched when a legacy composite value has no modern counterpart yet:
 * StartDatetime with no StartDate, or EndDatetime with no EndDate. A row with nothing to
 * convert is skipped outright rather than rewritten - measured against the pre-fix task, that
 * is one version row saved per untouched event on every run - and the count of them is
 * reported so "nothing to do" is distinguishable from "nothing found".
 *
 * Safety around Versioned, which is the risk in a task like this:
 *
 * - Pinned to draft, explicitly. Under sake no reading mode is set at all (choose_site_stage()
 *   runs only from VersionedHTTPMiddleware, which a CLI command does not go through), so
 *   records come out of the draft table anyway; over the web, DevelopmentAdmin::init() already
 *   sets draft for dev/tasks. The pin is therefore not what stops this task reading live -
 *   nothing was reading live - it is what makes the stage a precondition of the run instead of
 *   a side effect of the caller: Versioned::DEFAULT_MODE is Stage.Live, and any caller that
 *   gets the stage wrong would iterate LIVE records and write them back over the draft,
 *   destroying unpublished editorial work.
 * - The live mirror is gated on stagesDiffer() as well as isPublished(), and goes through
 *   publishSingle() rather than copyVersionToStage(). This is the actual bug: the gate here
 *   used to be isPublished() && isLatestVersion(), and because draft always holds the latest
 *   version, that collapsed to isPublished. Every published event was therefore republished,
 *   dragging whatever pending draft edit an editor had sitting there along with the conversion.
 *   copyVersionToStage() is also the inner half of publishing - it moves the version without
 *   firing onBeforePublish or onAfterPublish, so Fluent, Subsites and any other module
 *   listening for publication would keep serving stale copies. The stagesDiffer() gate is what
 *   makes publishing safe here: the draft holds no unpublished edits to carry along. A
 *   draft-only page is never published at all.
 *
 * A draft write and a publish are accounted for separately, because they fail differently. A
 * row whose draft write failed is counted failed and nothing landed. A row whose draft write
 * landed but whose publish threw is counted publish failed: the conversion is on draft and
 * stays there, so it is not counted again by a later run, which is why the run also names every
 * published row whose live copy is still unconverted - held-back rows included - so a state
 * this task created cannot go quiet.
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
     * @var int Rows whose draft conversion landed but whose publish threw.
     */
    protected int $publishFailed = 0;

    /**
     * @var int Rows with nothing to convert, which are not written at all.
     */
    protected int $skipped = 0;

    /**
     * @var int Rows whose draft write could not be completed.
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
            'Done. Converted: %d, Published: %d, Held back: %d, Skipped: %d, Publish failed: %d, Failed: %d.',
            $this->converted,
            $this->published,
            $this->heldBack,
            $this->skipped,
            $this->publishFailed,
            $this->failed
        ));

        // Everything this task left stale on live, named every run: a publish that failed is
        // not retried by a later one (draft is already converted, so the row is skipped), and
        // a held-back row is deliberately left to the editor. Both stay visible here.
        $outstanding = $this->countLiveOutstanding();

        if ($outstanding > 0) {
            $output->writeln(sprintf(
                'NOTE: %d published rows still hold an unconverted legacy value on live. Publishing '
                . 'them carries the conversion; held-back rows also carry any other draft edits.',
                $outstanding
            ));
        }

        // A draft write or a publish that failed is a real problem. Held-back rows are not: the
        // conversion is on draft and publishing it is the editor's call, which is why those are
        // reported but do not fail the run.
        return ($this->failed > 0 || $this->publishFailed > 0) ? Command::FAILURE : Command::SUCCESS;
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
        } catch (\Throwable $e) {
            $this->failed++;
            $output->writeln(sprintf('Error converting event %d: %s', $event->ID ?? 0, $e->getMessage()));

            return false;
        }

        if (!$wasPublished) {
            // A draft-only page must not gain a live version from maintenance.
            return true;
        }

        if ($hadUnpublishedEdits) {
            // publishSingle() moves the whole draft record, which would carry someone's
            // unpublished edits along with this conversion.
            $this->heldBack++;
            $output->writeln(sprintf(
                'Event %d converted on draft only: it has unpublished edits, so publishing it is '
                . 'left to whoever is editing it.',
                $event->ID ?? 0
            ));

            return true;
        }

        try {
            $this->publishLive($event);
            $this->published++;
        } catch (\Throwable $e) {
            // The draft conversion landed and stays landed, so this is not a failed conversion:
            // counting it as one would make the next run report the row as skipped and say
            // nothing about the live copy it still owes.
            $this->publishFailed++;
            $output->writeln(sprintf(
                'Event %d converted on draft but publishing failed: %s. Publish it manually.',
                $event->ID ?? 0,
                $e->getMessage()
            ));
        }

        return true;
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

        if ($this->needsStartConversion($event)) {
            $startTimestamp = $this->parseLegacy($event->StartDatetime, 'StartDatetime', $event);
            $conversion['StartDate'] = date('Y-m-d', $startTimestamp);
            $conversion['StartTime'] = date('H:i:s', $startTimestamp);
        }

        if ($this->needsEndConversion($event)) {
            $endTimestamp = $this->parseLegacy($event->EndDatetime, 'EndDatetime', $event);
            $conversion['EndDate'] = date('Y-m-d', $endTimestamp);
            $conversion['EndTime'] = date('H:i:s', $endTimestamp);
        }

        return $conversion === [] ? null : $conversion;
    }

    /**
     * Whether this record's start half still has a legacy value and no modern counterpart.
     *
     * One definition, used by the conversion and by the outstanding count alike, so the number
     * reported cannot drift from the work done.
     *
     * @param EventPage $event
     * @return bool
     */
    protected function needsStartConversion(EventPage $event): bool
    {
        return (bool) ($event->StartDatetime && !$event->StartDate);
    }

    /**
     * Whether this record's end half still has a legacy value and no modern counterpart.
     *
     * @param EventPage $event
     * @return bool
     */
    protected function needsEndConversion(EventPage $event): bool
    {
        return (bool) ($event->EndDatetime && !$event->EndDate);
    }

    /**
     * Read one legacy composite value as a timestamp.
     *
     * Refuses rather than guessing: strtotime() returns false for a value it cannot read and
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
     * Whether one record still holds a legacy composite value with no modern counterpart.
     *
     * The disjunction of needsStartConversion() and needsEndConversion(), the same two checks
     * the conversion itself makes, so the outstanding count cannot disagree with the work done.
     *
     * @param EventPage $event
     * @return bool
     */
    protected function hasUnconvertedLegacy(EventPage $event): bool
    {
        return $this->needsStartConversion($event) || $this->needsEndConversion($event);
    }

    /**
     * Published rows whose live copy still holds an unconverted legacy value.
     *
     * Read-only: publishing is a content decision, so this names what is outstanding rather
     * than quietly doing it.
     *
     * @return int
     */
    protected function countLiveOutstanding(): int
    {
        if (!DB::get_schema()->hasTable($this->eventTable() . '_Live')) {
            return 0;
        }

        $count = 0;

        foreach (Versioned::get_by_stage(EventPage::class, Versioned::LIVE) as $event) {
            if ($this->hasUnconvertedLegacy($event)) {
                $count++;
            }
        }

        return $count;
    }
    /**
     * The table carrying StartDatetime and StartDate.
     *
     * Not baseTable(): for a class in the SiteTree hierarchy that returns the hierarchy base,
     * which does not have these columns.
     *
     * @return string
     */
    protected function eventTable(): string
    {
        return DataObject::getSchema()->tableName(EventPage::class);
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
     * Mirror the converted draft row to live.
     *
     * A second seam, for the same reason as writeDraft(): a publish that throws fails a
     * different way from a write that throws, and the two counters are the thing under test.
     *
     * publishSingle(), not copyVersionToStage(): the latter moves the version without firing
     * the publish hooks, so Fluent, Subsites and anything else listening for publication keeps
     * serving stale copies. The stagesDiffer() gate in persist() is what makes publishing safe
     * here: the draft holds no unpublished edits to carry along.
     *
     * @param EventPage $event
     * @return void
     */
    protected function publishLive(EventPage $event): void
    {
        $event->publishSingle();
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
