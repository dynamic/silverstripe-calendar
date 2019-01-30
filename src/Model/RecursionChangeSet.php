<?php

namespace Dynamic\Calendar\Model;

use Dynamic\Calendar\Page\EventPage;
use SilverStripe\ORM\DataObject;

/**
 * Class EventChanRecursionChangeSetgeSet
 * @package Dynamic\Calendar\Model
 *
 * @property string $RecursionPattern
 * @property string $RecursionHash
 * @property string $RecursiveRecords
 * @property int $EventPageVersion
 * @property int $EventPageID
 * @method EventPage EventPage()
 */
class RecursionChangeSet extends DataObject
{
    /**
     * @var string
     */
    private static $table_name = 'EventPageRecursionChangeSet';

    /**
     * @var null|array
     */
    private $event_data = null;

    /**
     * @var array
     */
    private static $db = [
        'RecursionPattern' => 'Text',
        'RecursionHash' => 'Varchar(255)',
        'RecursiveRecords' => 'Text',
        'EventPageVersion' => 'Int',
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'EventPage' => EventPage::class,
    ];

    /**
     *
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
    }

    /**
     * @param $eventData
     * @return $this
     */
    public function setEventData($eventData)
    {
        if ($eventData instanceof EventPage) {
            //$eventData->getInheritableQueryParams()
            $this->event_data = $this->dataFromArray($eventData->toMap());
        } elseif ($eventData === (array)$eventData) {
            $this->event_data = $this->dataFromArray($eventData);
        }

        return $this;
    }

    /**
     * @return array|null
     */
    public function getEventData()
    {
        return $this->event_data;
    }

    /**
     * @param array $dataArray
     * @return array
     */
    public function dataFromArray($dataArray = [])
    {
        if (empty($dataArray) || $dataArray !== (array)$dataArray) {
            return [];
        }

        foreach ($this->config()->get('ignore_changed') as $ignore) {
            if (in_array($ignore, $dataArray)) {
                unset($dataArray[$ignore]);
            }
        }

        return $dataArray;
    }

    /**
     * @return false|string
     */
    protected function serializeData()
    {
        return json_encode(serialize($this->getEventData()));
    }

    /**
     * @return mixed
     */
    public function getChangeSetData()
    {
        return unserialize(json_decode($this->Data));
    }

    /**
     * @return string
     */
    public function checksumFromData()
    {
        return md5($this->Data);
    }
}
