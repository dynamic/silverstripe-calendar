<?php

namespace Dynamic\Calendar\Form;

use SilverStripe\Forms\TimeField;

/**
 * Class CalendarTimeField
 * @package Dynamic\Calendar\Form
 */
class CalendarTimeField extends TimeField
{
    /**
     * @return mixed|string
     */
    public function Value()
    {
        $localised = $this->internalToFrontend($this->value);
        if ($localised) {
            return $localised;
        }

        return '';
    }
}
