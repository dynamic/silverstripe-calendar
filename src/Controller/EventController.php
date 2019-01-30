<?php

namespace Dynamic\Calendar\Controller;

use Dynamic\Calendar\Model\Event;
use Dynamic\Calendar\Page\Calendar;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\View\SSViewer;
use SilverStripe\View\ThemeResourceLoader;
use SilverStripe\View\ViewableData_Customised;

/**
 * Class EventController
 */
class EventController extends CalendarController
{
    /**
     * @var array
     */
    private static $url_handlers = [
        '$EventID' => 'index',
    ];

    /**
     * @var Event|bool
     */
    private $event;

    /**
     * @var ManyManyList
     */
    private $categories;

    /**
     * @return Event|bool
     */
    public function getEvent()
    {
        if (!$this->event) {
            $this->setEvent($this->getEventFromRequest());
        }

        return $this->event;
    }

    /**
     * @param null $request
     *
     * @return Event|bool
     */
    public function getEventFromRequest($request = null)
    {
        if (!$request) {
            $request = $this->getRequest();
        }

        $eventID = $request->latestParam('EventID');
        if ($eventID) {
            /** @var Event $event */
            if ($event = Event::get()->filter('URLSegment', $eventID)->first()) {
                return $event;
            }
        }
        return false;
    }

    /**
     * @param $event
     *
     * @return $this
     */
    public function setEvent($event)
    {
        $this->event = $event;

        return $this;
    }

    /**
     * @return ManyManyList|bool
     * @throws \SilverStripe\Control\HTTPResponse_Exception
     */
    public function getCategories()
    {
        if (!$this->categories) {
            $this->setCategories($this->getEvent());
        }

        return $this->categories;
    }

    /**
     * @param $event
     *
     * @return $this
     */
    public function setCategories(Event $event)
    {
        if (!$event) {
            $this->categories = false;
            return $this;
        }

        if ($event->getIsPhantom()) {
            $event = Event::get()->byID($event->getBaseId());
        }
        $this->categories = $event->Categories();

        return $this;
    }

    /**
     * @param HTTPRequest $request
     *
     * @return ViewableData_Customised|\SilverStripe\ORM\FieldType\DBHTMLText
     * @throws \SilverStripe\Control\HTTPResponse_Exception
     */
    public function index(HTTPRequest $request)
    {
        if (!$request->latestParam('EventID')) {
            return $this->httpError(404);
        }

        if (!$this->getEvent()) {
            $this->httpError(404, "The event you're looking for isn't available.");
        }

        $viewer = new SSViewer($this->owner->getViewerTemplates());
        $templates = [Event::class, Calendar::class, \Page::class];
        $templates['type'] = 'Layout';
        $viewer->setTemplateFile('Layout', ThemeResourceLoader::inst()->findTemplate(
            $templates,
            SSViewer::get_themes()
        ));

        return $viewer->process($this->customise(
            [
                'Title' => $this->getEvent()->Title,
                'Event' => $this->getEvent(),
                'MetaTags' => $this->getEvent()->MetaTags(false),
            ]
        ));
    }
}
