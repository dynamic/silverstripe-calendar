<?php

namespace Dynamic\Calendar\Extension;

use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Versioned\GridFieldArchiveAction;
use Symbiote\GridFieldExtensions\GridFieldAddExistingSearchButton;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * Class RelatedEventsDataExtension
 * @package Dynamic\Calendar\Extension
 */
class RelatedEventsDataExtension extends DataExtension
{
    /**
     * @var array
     */
    private static $many_many = [
        'RelatedEvents' => EventPage::class,
    ];

    /**
     * @var array
     */
    private static $many_many_extraFields = [
        'RelatedEvents' => [
            'SortOrder' => 'Int',
        ],
    ];

    /**
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        if ($this->owner->ID) {
            $fields->addFieldToTab(
                'Root.Related',
                GridField::create(
                    'RelatedEvents',
                    'Related Events',
                    $this->owner->RelatedEvents()->sort('SortOrder'),
                    $config = GridFieldConfig_RelationEditor::create()
                )
            );
            $config
                ->removeComponentsByType([
                    GridFieldAddNewButton::class,
                    GridFieldAddExistingAutocompleter::class,
                    GridFieldArchiveAction::class
                ])
                ->addComponents(
                    new GridFieldOrderableRows('SortOrder'),
                    $addnew = new GridFieldAddExistingSearchButton()
                );

            $addnew->setSearchList(EventPage::get()->exclude('ID', $this->owner->ID));
        }
    }

    /**
     * @return mixed
     */
    public function getRelatedEventsList()
    {
        return $this->owner->RelatedEvents()->sort('SortOrder');
    }
}
