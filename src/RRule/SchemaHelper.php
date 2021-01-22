<?php

namespace Dynamic\Calendar\RRule;

use Dynamic\Calendar\RRule\Traits\SchemaValidator;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;

/**
 * Class SchemaHelper
 * @package Dynamic\Calendar\RRule
 */
class SchemaHelper
{
    use Configurable;
    use Extensible;
    use Injectable;
    use SchemaValidator;

    /**
     * @var array
     */
    private static $valid_keys = [];

    /**
     * SchemaHelper constructor.
     * @param array $pattern
     */
    public function __construct(array $pattern)
    {
        $this->setPattern($pattern);
    }

    /**
     * @return bool
     */
    public function isValidPattern()
    {
        $valid = is_array($this->getPattern());

        if ($valid) {
            $valid = $this->validateKeys();
        }

        return $valid;
    }

    /**
     * @return bool
     */
    protected function validateKeys()
    {
        $keys = static::config()->get('valid_keys', Config::UNINHERITED);

        foreach ($keys as $key) {
            if (!array_key_exists($key, $this->getPattern())) {
                return false;
            }
        }

        return true;
    }
}
