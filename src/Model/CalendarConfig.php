<?php

namespace Dynamic\Calendar\Model;

use Dynamic\Calendar\Admin\CalendarAdmin;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\ArrayData;
use SilverStripe\View\TemplateGlobalProvider;

/**
 * Class CalendarConfig
 *
 * @property int $EventInstancesToShow
 * @property bool $GlobalCalendar
 */
class CalendarConfig extends DataObject implements PermissionProvider, TemplateGlobalProvider
{
    /**
     * @var string
     */
    private static $singular_name = 'Calendar';

    /**
     * @var string
     */
    private static $plural_name = 'Calendar';

    /**
     * @var string
     */
    private static $description = 'Settings for the calendar installation';

    /**
     * @var string
     */
    private static $table_name = 'CalendarConfig';

    /**
     * @var array
     */
    private static $db = array(
        'EventInstancesToShow' => 'Int',
    );

    /**
     * Return data from the {@link CalendarConfig} that can be used in templates.
     *
     * @return ArrayData
     */
    public static function current_calendar_config_public()
    {
        $config = self::current_calendar_config();
        return ArrayData::create(
            array()
        );
    }

    /**
     * @return array
     */
    public static function get_template_global_variables()
    {
        return array(
            'CalendarConfig' => 'current_calendar_config',
        );
    }

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = FieldList::create(
            $root = Tabset::create("Root"),
            HiddenField::create('ID')
        );

        if ($this->canAccessCategories()) {
            $root->push(Tab::create(
                'Categories',
                GridField::create(
                    'Categories',
                    'Categories',
                    Category::get(),
                    GridFieldConfig_RecordEditor::create()
                )
            ));
        }

        $root->push(Tab::create(
            'Settings',
            NumericField::create('EventInstancesToShow')
                ->setTitle('Event Instances To Show')
        ));

        $this->extend('updateCMSFields', $fields);

        return $fields;
    }

    /**
     * Check if the current member can access Event records.
     *
     * @return bool
     */
    protected function canAccessEvents()
    {
        return Permission::check('CREATE_EVENT', 'any', Member::currentUserID())
            || Permission::check('EDIT_EVENT', 'any', Member::currentUserID())
            || Permission::check('DELETE_EVENT', 'any', Member::currentUserID());
    }

    /**
     * Check if the current member can access Category records.
     *
     * @return bool
     */
    protected function canAccessCategories()
    {
        return Permission::check('CREATE_CATEGORY', 'any', Member::currentUserID())
            || Permission::check('EDIT_CATEGORY', 'any', Member::currentUserID())
            || Permission::check('DELETE_CATEGORY', 'any', Member::currentUserID());
    }

    /**
     * Get the actions that are sent to the CMS. In
     * your extensions: updateEditFormActions($actions)
     *
     * @return FieldList
     */
    public function getCMSActions()
    {
        if (Permission::check('ADMIN') || Permission::check('EDIT_CAPERMISSION')) {
            $actions = new FieldList(
                FormAction::create('save_calendarconfig', _t('Calendar.SAVE', 'Save'))
                    ->addExtraClass('ss-ui-action-constructive')->setAttribute('data-icon', 'accept')
            );
        } else {
            $actions = new FieldList();
        }

        $this->extend('updateCMSActions', $actions);

        return $actions;
    }

    /**
     * @throws null
     */
    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        $calendarConfig = CalendarConfig::current_calendar_config();
        if (!$calendarConfig) {
            self::make_calendar_config();
            DB::alteration_message("Added default calendar config", "created");
        }
    }

    /**
     * Get the current sites {@link CalendarConfig}, and creates a new one
     * through {@link make_calendar_config()} if none is found.
     *
     * @return DataObject
     */
    public static function current_calendar_config()
    {
        if ($calendarConfig = CalendarConfig::get()->first()) {
            return $calendarConfig;
        }

        return self::make_calendar_config();
    }

    /**
     * Create {@link CalendarConfig} with defaults from language file.
     *
     * @return CalendarConfig
     */
    public static function make_calendar_config()
    {
        $calendarConfig = CalendarConfig::create();
        $calendarConfig->write();
        return $calendarConfig;
    }

    /**
     * Ensure the current user has access to edit this record
     *
     * @param null $member
     * @return bool|int
     */
    public function canEdit($member = null)
    {
        return Permission::check('EDIT_CAPERMISSION', 'any', $member ? $member : Member::currentUserID());
    }

    /**
     * Rick says no one can ever delete this, it's too important bra
     *
     * @param null $member
     * @return bool
     */
    public function canDelete($member = null)
    {
        return false;
    }

    /**
     * @return string
     */
    public function CMSEditLink()
    {
        return CalendarAdmin::singleton()->Link();
    }

    /**
     * @return array
     */
    public function providePermissions()
    {
        return array(
            'EDIT_CAPERMISSION' => array(
                'name' => _t('CalendarConfig.EDIT_CAPERMISSION', 'Manage Calendar configuration'),
                'category' => _t('Permissions.PERMISSIONS_CACATEGORY', 'Roles and access permissions'),
                'help' => _t(
                    'Calendar.EDIT_PERMISSION_CAHELP',
                    'Ability to edit global access settings/top-level page permissions.'
                ),
                'sort' => 400
            )
        );
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return SiteConfig::current_site_config()->Title;
    }
}
