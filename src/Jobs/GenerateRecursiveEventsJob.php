<?php

namespace Dynamic\Calendar\Jobs;

use Dynamic\Calendar\Page\EventPage;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * Class GenerateRecursiveEventsJob
 * @package Dynamic\Calendar\Jobs
 */
class GenerateRecursiveEventsJob extends AbstractQueuedJob implements QueuedJob
{
    /**
     * GenerateRecursiveEventsJob constructor.
     * @param EventPage|null $parent
     */
    public function __construct($parent = null)
    {
        if ($parent) {
            $this->parent = $parent;
        }
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return _t(self::class . '.RECURSIVEEVENTSJOBTITLE', 'Generate Recursive Events Job');
    }

    public function process()
    {

    }
}
