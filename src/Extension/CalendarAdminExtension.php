<?php

namespace Dynamic\Calendar\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\View\Requirements;

/**
 * CalendarAdminExtension
 *
 * Includes admin enhancements for calendar CMS interface
 */
class CalendarAdminExtension extends Extension
{
    /**
     * Include admin assets
     */
    public function onAfterInit()
    {
        // Only include in admin
        if ($this->shouldIncludeAdminAssets()) {
            $this->includeAdminAssets();
        }
    }

    /**
     * Check if we should include admin assets
     */
    protected function shouldIncludeAdminAssets(): bool
    {
        $controller = $this->getOwner();

        // Include in admin interface
        if ($controller instanceof \SilverStripe\Admin\ModelAdmin) {
            return true;
        }

        // Include in CMS edit forms for calendar pages
        if ($controller instanceof \SilverStripe\CMS\Controllers\CMSPageEditController) {
            return true;
        }

        return false;
    }

    /**
     * Include admin frontend assets
     */
    protected function includeAdminAssets(): void
    {
        // Include admin JavaScript using proper SilverStripe module syntax
        Requirements::javascript('dynamic/silverstripe-calendar:client/dist/js/admin.bundle.js');

        // Add admin configuration as data attribute for CSP compliance
        $adminConfig = [
            'timezone' => date_default_timezone_get(),
            'dateFormat' => 'Y-m-d',
            'timeFormat' => 'H:i:s'
        ];
        
        // Pass config via data attribute instead of inline script
        $this->getOwner()->CalendarAdminConfig = json_encode($adminConfig);
    }
}
