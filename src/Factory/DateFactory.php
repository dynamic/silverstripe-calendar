<?php

namespace Dynamic\Calendar\Factory;

use Carbon\Carbon;
use SilverStripe\Core\Injector\Injectable;

/**
 * Class DateFactory
 * @package Dynamic\Calendar\Factory
 */
class DateFactory
{
    use Injectable;

    /**
     * Get the "tomorrow" of a given date string
     *
     * @param $date
     * @return Carbon
     */
    public function getTomorrow($date)
    {
        return Carbon::parse($date)->addDay();
    }

    /**
     * Get the "yesterday" of a given date string
     *
     * @param $date
     * @return Carbon
     */
    public function getYesterday($date)
    {
        return Carbon::parse($date)->subDay();
    }
}
