<?php

namespace Dynamic\Calendar\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\View\Requirements;

/**
 * CalendarFrontendExtension
 *
 * Includes modern frontend assets for calendar pages
 * Integrates with silverstripe-essentials-theme's Bootstrap 5.3 setup
 */
class CalendarFrontendExtension extends Extension
{
    /**
     * Include frontend assets on calendar pages
     */
    public function onAfterInit()
    {
        // Only include on calendar-related pages
        if ($this->shouldIncludeAssets()) {
            $this->includeCalendarAssets();
        }
    }

    /**
     * Check if we should include calendar assets
     */
    protected function shouldIncludeAssets(): bool
    {
        $controller = $this->getOwner();

        // Include on EventPage and Calendar pages
        if (
            $controller instanceof \Dynamic\Calendar\Controller\EventPageController ||
            $controller instanceof \Dynamic\Calendar\Controller\CalendarController
        ) {
            return true;
        }

        // Include if page has calendar elements
        if (method_exists($controller, 'getPage')) {
            $page = $controller->getPage();
            if ($page && method_exists($page, 'ElementalArea')) {
                $elementalArea = $page->ElementalArea();
                if (
                    $elementalArea && 
                    $elementalArea->Elements()->filter('ClassName', 'Dynamic\Calendar\Element\CalendarElement')->exists()
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Include calendar frontend assets
     */
    protected function includeCalendarAssets(): void
    {
        $resourceDir = 'vendor/dynamic/silverstripe-calendar/client/dist/';

        // Include CSS
        Requirements::css($resourceDir . 'css/calendar.bundle.css');

        // Include JavaScript (vendors first, then calendar)
        Requirements::javascript($resourceDir . 'js/vendors.bundle.js');
        Requirements::javascript($resourceDir . 'js/calendar.bundle.js');

        // Add calendar configuration
        $config = $this->getCalendarConfig();
        Requirements::customScript(
            "window.CalendarConfig = " . json_encode($config) . ";",
            'calendar-config'
        );
    }

    /**
     * Get calendar configuration for frontend
     */
    protected function getCalendarConfig(): array
    {
        return [
            'apiEndpoint' => $this->getOwner()->Link('events'),
            'dateFormat' => 'Y-m-d',
            'timeFormat' => 'H:i:s',
            'locale' => 'en',
            'timezone' => date_default_timezone_get(),
            'fullcalendar' => [
                'initialView' => 'dayGridMonth',
                'headerToolbar' => [
                    'left' => 'prev,next today',
                    'center' => 'title',
                    'right' => 'dayGridMonth,timeGridWeek,listWeek'
                ],
                'eventDisplay' => 'block',
                'dayMaxEvents' => 3,
                'moreLinkClick' => 'popover',
                'aspectRatio' => 1.35,
                'height' => 'auto',
                'themeSystem' => 'bootstrap5'
            ]
        ];
    }
}
