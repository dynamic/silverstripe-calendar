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
 * Normalises the AllDay column against the clock times (issue #150).
 *
 * The events feed used to derive its allDay value from StartTime while the ?allDay filter
 * read the AllDay column, so the two could disagree for the same record. The serializer now
 * reads the column, which makes the column responsible for what the clock times used to
 * imply. This task backfills that implication into the column, and clears the times the other
 * way round, so no existing row changes how it renders on upgrade.
 *
 * DEFINITION - "has no clock time" means the field is NULL. A stored '00:00:00' is a real
 * time, i.e. an event that begins at midnight, not an event with no time. That is the same
 * reading the serializer takes (it emits a composite midnight start) and the same reading
 * EventException takes, where ModifiedStartTime of '00:00:00' is an explicit override. It is
 * also the only reading that survives the database: on a TIME column, SQL compares '' by
 * coercing it to TIME '00:00:00', so a "field = ''" test reports a stored midnight as empty
 * and the two sides disagree. Every count and every branch here therefore comes from
 * divergenceOf(), and no SQL predicate is used to classify a row.
 *
 * Two divergent states, mirroring the two rows of issue #150's table:
 *
 * 1. AllDay off with no StartTime - the old serializer decided allDay from StartTime alone,
 *    so a row with no StartTime rendered as all-day whatever EndTime held. The column is set
 *    to 1 so those rows keep rendering that way instead of flipping to a timed event at
 *    midnight. Promotion therefore keys on StartTime, not on both time fields: an event with
 *    a lone EndTime used to render all-day too, and skipping it would leave a permanent
 *    regression the task exists to prevent. Promoting it lets onBeforeWrite() clear the
 *    orphan EndTime, which never influenced allDay before and does not afterwards.
 * 2. AllDay on while a StartTime or EndTime is still stored - the CMS hides rather than
 *    empties those fields, so the leftover is cleared. onBeforeWrite() does this on every
 *    save; this task covers rows not saved since.
 *
 * Safety around Versioned, which is the risk in a task like this:
 *
 * - Pinned to draft. Versioned::DEFAULT_MODE is Stage.Live and choose_site_stage() does not
 *   override it for a CLI call, so unpinned, EventPage::get() would iterate LIVE records and
 *   writing them back to draft would overwrite unpublished editorial work.
 * - The live mirror is gated on stagesDiffer() as well as isPublished(), and goes through
 *   publishSingle() rather than copyVersionToStage(). copyVersionToStage() is the inner half
 *   of publishing: it moves the version without firing onAfterPublish, so Fluent, Subsites
 *   and any other module listening for publication would keep serving stale copies. The
 *   stagesDiffer() gate is what makes publishing safe here - it means the draft holds no
 *   unpublished edits to carry along - and a draft-only page is never published at all.
 *
 * Reachable over the web only under BuildTask's own permissions_for_browser_execution. Note
 * that gate is browser-only: sake is not permission-checked, so this task carries no
 * Director::isDev() guard only because a dev-only backfill would be useless on the upgraded
 * production site it exists for. It is idempotent, but it does write and publish content, so
 * run it while the site is quiet: a row an editor saves mid-run is written from the snapshot
 * this task read.
 *
 * Usage: sake tasks:calendar-allday-normalisation-task
 *        (also reachable in a browser at dev/tasks/calendar-allday-normalisation-task)
 */
class AllDayNormalisationTask extends BuildTask
{
    // silverstripe/framework ^6.0 resolves a task's name through PolyCommand::$commandName.
    // The SS4/SS5 BuildTask::$segment spelling is read by nothing in SS6: left unset here the
    // task would register as tasks:Dynamic-Calendar-Task-AllDayNormalisationTask and the
    // invocation the README documents would fail with "command not found", which is
    // load-bearing on the upgrade path this task exists to serve.
    protected static string $commandName = 'calendar-allday-normalisation-task';

    protected string $title = 'Calendar AllDay Normalisation';

    protected static string $description = 'Backfills AllDay from the absence of clock times and clears times '
        . 'left on all-day events, so the AllDay column agrees with the events feed (issue #150)';

    /**
     * A row whose AllDay column must be set to 1.
     */
    public const DIVERGENT_UNTIMED = 'untimed';

    /**
     * A row whose clock times must be cleared.
     */
    public const DIVERGENT_STALE_TIME = 'stale-time';

    /**
     * @var int Rows promoted to all-day.
     */
    protected int $promoted = 0;

    /**
     * @var int Rows whose leftover times were cleared.
     */
    protected int $cleared = 0;

    /**
     * @var int Rows that failed to write.
     */
    protected int $failed = 0;

    /**
     * @var int Published rows whose live copy was deliberately not touched.
     */
    protected int $heldBack = 0;

    /**
     * @param InputInterface $input
     * @param PolyOutput $output
     * @return int
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $output->writeln('Normalising the AllDay column against the clock times...');

        return Versioned::withVersionedMode(function () use ($output): int {
            Versioned::set_stage(Versioned::DRAFT);

            return $this->runOnDraft($output);
        });
    }

    /**
     * Count, then migrate, then verify - all three through divergenceOf(), so the numbers
     * reported cannot drift from the work done.
     *
     * @param PolyOutput $output
     * @return int
     */
    protected function runOnDraft(PolyOutput $output): int
    {
        $tableName = $this->eventTable();

        if (!DB::get_schema()->hasTable($tableName)) {
            $output->writeln("ERROR: table {$tableName} does not exist. Run /dev/build first.");
            return Command::FAILURE;
        }

        $before = $this->countDivergent();
        $output->writeln(sprintf(
            'Divergent draft rows: %d with AllDay off and no clock time, %d all-day rows carrying a time.',
            $before[self::DIVERGENT_UNTIMED],
            $before[self::DIVERGENT_STALE_TIME]
        ));

        foreach (EventPage::get() as $event) {
            // Per row, not around the loop. isPublished() and stagesDiffer() are called
            // outside persist()'s try, so without this one bad row aborts the whole run
            // part-way through, with no summary and no exit code.
            try {
                $this->normaliseEvent($event, $output);
            } catch (\Throwable $e) {
                $this->failed++;
                $output->writeln(sprintf('Error normalising event %d: %s', $event->ID, $e->getMessage()));
            }
        }

        $output->writeln(sprintf(
            'Done. Promoted to all-day: %d, times cleared: %d, held back: %d, failed: %d.',
            $this->promoted,
            $this->cleared,
            $this->heldBack,
            $this->failed
        ));

        return $this->report($output);
    }

    /**
     * Verify draft and surface anything still outstanding on live.
     *
     * @param PolyOutput $output
     * @return int
     */
    protected function report(PolyOutput $output): int
    {
        $after = $this->countDivergent();

        if ($after[self::DIVERGENT_UNTIMED] === 0 && $after[self::DIVERGENT_STALE_TIME] === 0) {
            $output->writeln('Verified: no divergent draft row remains.');
        } else {
            $output->writeln(sprintf(
                'WARNING: %d untimed rows still have AllDay off, %d all-day rows still carry a time.',
                $after[self::DIVERGENT_UNTIMED],
                $after[self::DIVERGENT_STALE_TIME]
            ));
        }

        // Held-back rows are genuinely outstanding on live, so they are named rather than
        // folded into a success line. Counted read-only; publishing is the editor's call.
        if ($this->heldBack > 0) {
            $output->writeln(sprintf(
                'NOTE: %d published rows were normalised on draft only. Publishing them carries '
                . 'the change to live.',
                $this->heldBack
            ));
        }

        $liveDivergent = $this->countLiveDivergent();
        if ($liveDivergent > 0) {
            $output->writeln("NOTE: {$liveDivergent} divergent rows remain on live.");
        }

        $outstanding = $after[self::DIVERGENT_UNTIMED] + $after[self::DIVERGENT_STALE_TIME];

        return ($this->failed > 0 || $outstanding > 0) ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * The one definition of divergence, used by the counts and the migration alike.
     *
     * Reads the raw scalars, not the DBField objects, and treats a stored midnight as a time.
     *
     * @return string|null self::DIVERGENT_UNTIMED, self::DIVERGENT_STALE_TIME, or null.
     */
    protected function divergenceOf(EventPage $event): ?string
    {
        $isAllDay = (bool) $event->AllDay;

        // Promotion keys on StartTime alone: that is the field the old serializer used to
        // decide allDay, so a row with no StartTime rendered all-day whatever its EndTime
        // held. Keying on both fields would skip an EndTime-only row and leave a permanent
        // regression the backfill exists to prevent. Promoting it lets onBeforeWrite() clear
        // the orphan EndTime, which never influenced allDay before and does not afterwards.
        if (!$isAllDay && $this->isEmptyTime($event->StartTime)) {
            return self::DIVERGENT_UNTIMED;
        }

        // Clearing keys on either field: an all-day row should hold no clock time at all.
        $hasTime = !$this->isEmptyTime($event->StartTime) || !$this->isEmptyTime($event->EndTime);

        if ($isAllDay && $hasTime) {
            return self::DIVERGENT_STALE_TIME;
        }

        return null;
    }

    /**
     * Whether a clock-time value carries no time.
     *
     * '00:00:00' is NOT empty: it is midnight, a time an editor can mean. Only null and the
     * empty string mean nothing was entered.
     *
     * @param mixed $value
     */
    protected function isEmptyTime(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    /**
     * Apply divergenceOf() to one draft row, writing only where it is divergent.
     *
     * The stage probes sit inside the branches: they each cost a version lookup, so paying
     * them for every consistent row on a large calendar would be pure overhead.
     *
     * @param EventPage $event
     * @param PolyOutput $output
     * @return void
     */
    protected function normaliseEvent(EventPage $event, PolyOutput $output): void
    {
        if (!$event->exists()) {
            return;
        }

        $divergence = $this->divergenceOf($event);

        if ($divergence === null) {
            return;
        }

        // Captured before the write: afterwards the draft has changed by definition, so a
        // stage comparison then would always report a difference.
        $wasPublished = $event->isPublished();
        $hadUnpublishedEdits = $event->stagesDiffer();

        if ($divergence === self::DIVERGENT_UNTIMED) {
            $event->AllDay = 1;
            $verb = 'promoting';
        } else {
            // onBeforeWrite() nulls both times on an all-day write, so nothing to assign.
            $verb = 'clearing times for';
        }

        if ($this->persist($event, $output, $wasPublished, $hadUnpublishedEdits, $verb)) {
            if ($divergence === self::DIVERGENT_UNTIMED) {
                $this->promoted++;
            } else {
                $this->cleared++;
            }
        }
    }

    /**
     * Persist to draft, and mirror to live only when that publishes nothing unrelated.
     *
     * @return bool True when the draft write succeeded.
     */
    protected function persist(
        EventPage $event,
        PolyOutput $output,
        bool $wasPublished,
        bool $hadUnpublishedEdits,
        string $verb
    ): bool {
        try {
            $this->writeDraft($event);

            if (!$wasPublished) {
                // A draft-only page must not gain a live version from maintenance.
                return true;
            }

            if ($hadUnpublishedEdits) {
                // copyVersionToStage() publishes the whole draft record, which would carry
                // someone's unpublished edits along with this normalisation.
                $this->heldBack++;
                return true;
            }

            // publishSingle(), not copyVersionToStage(): the latter moves the version without
            // firing the publish hooks, so Fluent, Subsites and anything else listening for
            // publication keeps serving stale copies. The stagesDiffer() gate above is what
            // makes publishing safe here: the draft holds no unpublished edits to carry along.
            $event->publishSingle();

            return true;
        } catch (\Throwable $e) {
            $this->failed++;
            $output->writeln(sprintf('Error %s event %d: %s', $verb, $event->ID, $e->getMessage()));

            return false;
        }
    }

    /**
     * Write the row to the draft stage.
     *
     * A seam so the failure path can be exercised without replacing persist() wholesale,
     * which would bypass the try/catch whose accounting is the thing under test.
     *
     * @param EventPage $event
     * @return void
     */
    protected function writeDraft(EventPage $event): void
    {
        $event->writeToStage(Versioned::DRAFT);
    }
    /**
     * Draft rows in each divergent state, classified by divergenceOf().
     *
     * @return array{untimed:int, stale-time:int}
     */
    protected function countDivergent(): array
    {
        $counts = [
            self::DIVERGENT_UNTIMED => 0,
            self::DIVERGENT_STALE_TIME => 0,
        ];

        foreach (EventPage::get() as $event) {
            $divergence = $this->divergenceOf($event);

            if ($divergence !== null) {
                $counts[$divergence]++;
            }
        }

        return $counts;
    }

    /**
     * Divergent rows on live, read-only, by the same predicate.
     *
     * @return int
     */
    protected function countLiveDivergent(): int
    {
        if (!DB::get_schema()->hasTable($this->eventTable() . '_Live')) {
            return 0;
        }

        $count = 0;

        foreach (Versioned::get_by_stage(EventPage::class, Versioned::LIVE) as $event) {
            if ($this->divergenceOf($event) !== null) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * The table carrying AllDay, StartTime and EndTime.
     *
     * Not baseTable(): for a class in the SiteTree hierarchy that returns SiteTree, the base
     * of the tree, which does not have these columns.
     *
     * @return string
     */
    protected function eventTable(): string
    {
        return DataObject::getSchema()->tableName(EventPage::class);
    }
}
