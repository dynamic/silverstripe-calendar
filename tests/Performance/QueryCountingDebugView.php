<?php

namespace Dynamic\Calendar\Tests\Performance;

use LogicException;
use SilverStripe\Dev\CliDebugView;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\Connect\Database;

/**
 * Counts database queries by intercepting the debug view that
 * Database::benchmarkQuery() writes to under ?showqueries.
 *
 * Same technique as the framework's own DBQueryCounterDebugView (used by
 * DataListEagerLoadingTest); Database::$queryCount has no public getter.
 */
class QueryCountingDebugView extends CliDebugView implements TestOnly
{
    private const RESET = 'RESET_SENTINEL';

    /** @var mixed */
    private $previousShowQueries = self::RESET;

    private int $queries = 0;

    public function startCounting(): void
    {
        if ($this->previousShowQueries !== self::RESET) {
            throw new LogicException('startCounting() called twice without stopCounting()');
        }

        $this->queries = 0;
        $this->previousShowQueries = $_REQUEST['showqueries'] ?? null;
        $_REQUEST['showqueries'] = 1;
    }

    public function stopCounting(): void
    {
        if ($this->previousShowQueries === null) {
            unset($_REQUEST['showqueries']);
        } else {
            $_REQUEST['showqueries'] = $this->previousShowQueries;
        }

        $this->previousShowQueries = self::RESET;
    }

    public function getCount(): int
    {
        return $this->queries;
    }

    public function renderMessage($message, $caller, $showHeader = true)
    {
        if (
            isset($caller['class'], $caller['function'])
            && is_a($caller['class'], Database::class, true)
            && $caller['function'] === 'displayQuery'
        ) {
            $this->queries++;
            return null;
        }

        return parent::renderMessage($message, $caller, $showHeader);
    }
}
