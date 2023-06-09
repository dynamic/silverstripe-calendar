<?php

namespace Dynamic\Calendar\RRule;

/**
 * Class CustomSchemaHelper
 * @package Dynamic\Calendar\RRule
 */
class CustomSchemaHelper extends SchemaHelper
{
    /**
     * @var array
     */
    private static $valid_keys = [
        'FREQ',
        'INTERVAL',
        'DTSTART',
    ];

    /**
     * @var array
     */
    private static $match_one = [
        'UNTIL',
        'COUNT',
    ];

    /**
     * @return bool
     */
    public function isValidPattern()
    {
        $valid = parent::isValidPattern();

        if ($valid) {
            $match = false;

            foreach ($this->config()->get('match_one') as $key) {
                if (!$match && array_key_exists($key, $this->getPattern())) {
                    $match = true;
                }
            }

            $valid = $match;
        }

        return $valid;
    }
}
