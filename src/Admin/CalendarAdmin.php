<?php

namespace Dynamic\Calendar\Admin;

use Dynamic\Calendar\Model\CalendarConfig;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\ValidationException;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;

/**
 * Class CalendarAdmin
 * @package Dynamic\Calendar\Admin
 */
class CalendarAdmin extends LeftAndMain
{
    /**
     * @var string
     */
    private static $url_segment = 'calendar-config';

    /**
     * @var string
     */
    private static $url_rule = '/$Action/$ID/$OtherID';

    /**
     * @var int
     */
    private static $menu_priority = 6;

    /**
     * @var string
     */
    private static $menu_title = 'Calendar';

    /**
     * @var string
     */
    private static $tree_class = CalendarConfig::class;

    /**
     * @var array
     */
    private static $required_permission_codes = ['EDIT_CAPERMISSION'];

    /**
     * Initialises the {@link SiteConfig} controller.
     */
    public function init()
    {
        parent::init();
        if (class_exists(SiteTree::class)) {
            Requirements::javascript('silverstripe/cms: client/dist/js/bundle.js');
        }
    }

    /**
     * @param null $id Not used.
     * @param null $fields Not used.
     *
     * @return Form
     */
    public function getEditForm($id = null, $fields = null)
    {
        $config = CalendarConfig::current_calendar_config();
        $fields = $config->getCMSFields();
        // Tell the CMS what URL the preview should show
        $home = Director::absoluteBaseURL();
        $fields->push(new HiddenField('PreviewURL', 'Preview URL', $home));
        // Added in-line to the form, but plucked into different view by LeftAndMain.Preview.js upon load
        /** @skipUpgrade */
        $fields->push($navField = new LiteralField('SilverStripeNavigator', $this->getSilverStripeNavigator()));
        $navField->setAllowHTML(true);
        // Retrieve validator, if one has been setup (e.g. via data extensions).
        if ($config->hasMethod("getCMSValidator")) {
            $validator = $config->getCMSValidator();
        } else {
            $validator = null;
        }
        $actions = $config->getCMSActions();
        $negotiator = $this->getResponseNegotiator();
        /** @var Form $form */
        $form = Form::create(
            $this,
            'EditForm',
            $fields,
            $actions,
            $validator
        )->setHTMLID('Form_EditForm');
        $form->setValidationResponseCallback(function (ValidationResult $errors) use ($negotiator, $form) {
            $request = $this->getRequest();
            if ($request->isAjax() && $negotiator) {
                $result = $form->forTemplate();
                return $negotiator->respond($request, array(
                    'CurrentForm' => function () use ($result) {
                        return $result;
                    }
                ));
            }
        });
        $form->addExtraClass('flexbox-area-grow fill-height cms-content cms-edit-form');
        $form->setAttribute('data-pjax-fragment', 'CurrentForm');
        if ($form->Fields()->hasTabSet()) {
            $form->Fields()->findOrMakeTab('Root')->setTemplate('SilverStripe\\Forms\\CMSTabSet');
        }
        $form->setHTMLID('Form_EditForm');
        $form->loadDataFrom($config);
        $form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
        // Use <button> to allow full jQuery UI styling
        $actions = $actions->dataFields();
        if ($actions) {
            /** @var \SilverStripe\Forms\FormAction $action */
            foreach ($actions as $action) {
                $action->setUseButtonTag(true);
            }
        }
        $this->extend('updateEditForm', $form);
        return $form;
    }

    /**
     * Save the current sites {@link CalendarConfig} into the database.
     *
     * @param $data
     * @param Form $form
     *
     * @return \SilverStripe\Control\HTTPResponse|\SilverStripe\ORM\FieldType\DBHTMLText
     * @throws \SilverStripe\Control\HTTPResponse_Exception
     */
    public function save_calendarconfig($data, $form)
    {
        $calendarConfig = CalendarConfig::current_calendar_config();
        $form->saveInto($calendarConfig);

        try {
            $calendarConfig->write();
        } catch (ValidationException $ex) {
            $form->sessionMessage(print_r($ex->getResult()->getMessages(), true), 'bad');

            return $this->getResponseNegotiator()->respond($this->request);
        }

        $this->response->addHeader('X-Status', rawurlencode(_t('LeftAndMain.SAVEDUP', 'Saved.')));

        return $form->forTemplate();
    }

    /**
     * @param bool $unlinked
     *
     * @return ArrayList
     */
    public function Breadcrumbs($unlinked = false)
    {
        $defaultTitle = self::menu_title(get_class($this));

        return ArrayList::create([
            ArrayData::create([
                'Title' => _t(static::class . ".MENUTITLE", $defaultTitle),
                'Link' => $this->Link(),
            ]),
        ]);
    }
}
