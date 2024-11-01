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
     * @return string
     */
    public function Value(): string
    {
        $localised = $this->internalToFrontend($this->value);
        if ($localised) {
            return $localised;
        }

        return '';
    }
}
