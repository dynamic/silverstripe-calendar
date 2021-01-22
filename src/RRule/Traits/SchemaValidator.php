<?php

namespace Dynamic\Calendar\RRule\Traits;

/**
 * Trait KeyValidator
 * @package Dynamic\Calendar\RRule\Traits
 */
trait SchemaValidator
{
    /**
     * @var array
     */
    private $pattern = [];

    /**
     * @param array $pattern
     * @return $this
     */
    public function setPattern(array $pattern): self
    {
        $this->pattern = $pattern;

        return $this;
    }

    /**
     * @return array
     */
    public function getPattern()
    {
        return $this->pattern;
    }
}
