<?php

namespace Dynamic\Calendar\Model;

use Dynamic\Calendar\Controller\CalendarController;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use RyanPotter\SilverStripeColorField\Forms\ColorField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Hierarchy\Hierarchy;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Class Category
 *
 * @package calendar
 *
 * @property string Title
 * @property string Description
 * @property string URLSegment
 *
 * @property string ParentID
 * @method Category Parent()
 *
 * @method \SilverStripe\ORM\ManyManyList Events()
 *
 * @mixin Hierarchy
 */
class Category extends DataObject implements PermissionProvider
{
    /**
     * @var array
     */
    private static array $db = [
        'Title' => 'Varchar(100)',
        'Description' => 'Varchar(255)',
        'URLSegment' => 'Varchar(255)',
        'Color' => 'Varchar(9)', // Hex color code (e.g., #334597 or #FF334597 for alpha)
    ];

    /**
     * @var array
     */
    private static array $has_one = [
        'Parent' => Category::class,
    ];

    /**
     * @var array
     */
    private static array $belongs_many_many = [
        'Events' => EventPage::class,
    ];

    /**
     * @var array
     */
    private static array $extensions = [
        Hierarchy::class,
    ];

    /**
     * @var array
     */
    private static array $indexes = [
        'Title' => true,
        'URLSegment' => true,
    ];

    /**
     * @var array
     */
    private static array $summary_fields = [
        'Title' => 'Name',
        'Description' => 'Description',
        'ColorPreview' => 'Color',
        'Parent.Title' => 'Parent',
    ];

    /**
     * @var array
     */
    private static array $casting = [
        'IsSubcategory' => 'Boolean',
        'ColorPreview' => 'HTMLText',
    ];

    /**
     * @var string
     */
    private static string $table_name = 'Category';

    /**
     * @return FieldList
     */
    public function getCMSFields(): FieldList
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $remove = [
                'URLSegment',
                'Events',
            ];

            $allowedParentCategories = Category::get()
                ->filter('ParentID', 0)
                ->exclude('ID', $this->ID);

            if (!$allowedParentCategories->count()) {
                $remove[] = 'ParentID';
            }
            $fields->replaceField(
                'ParentID',
                DropdownField::create('ParentID')
                    ->setTitle('Parent Category')
                    ->setSource($allowedParentCategories)
                    ->setEmptyString('-- select --')
            );

            // Add color field for brand-consistent color selection with custom color picker
            $fields->addFieldToTab(
                'Root.Main',
                ColorField::create('Color', 'Category Color')
                    ->setDescription(
                        'Choose a color to represent this category in the calendar. Events in this category '
                        . 'will display with this color. You can pick from the palette or choose any custom color.'
                    ),
                'Description'
            );

            $fields->removeByName($remove);

            if ($this->exists()) {
                $fields->addFieldToTab(
                    'Root.Events',
                    GridField::create(
                        'Events',
                        'Events',
                        $this->Events(),
                        GridFieldConfig_RecordViewer::create()
                    )
                );
            }
        });

        return parent::getCMSFields();
    }

    /**
     * This function allows the validation of Category data
     * on save attempt
     *
     * @return ValidationResult
     */
    public function validate(): ValidationResult
    {
        $result = parent::validate();

        if (!$this->Title) {
            $result->addFieldError('Title', 'A Title is required before you can save a category');
        }

        if (Category::get()->filter('Title', $this->Title)->exclude('ID', $this->ID)->first()) {
            $result->addFieldError('Title', 'A Category is already using that title. Please use a unique title.');
        }

        return $result;
    }

    /**
     * Get the legacy color mapping for backward compatibility
     * Maps color names from ColorPaletteField to consistent hex values
     *
     * @return array
     */
    private static function getLegacyColorMap(): array
    {
        return [
            'Navy Blue' => '#010E3B',        // Bethlehem primary navy
            'Blue' => '#334597',             // Bethlehem secondary blue
            'Gold' => '#E1AD3C',             // Bethlehem gold accent
            'Light Blue' => '#6FA8DC',       // Light blue accent (standardized)
            'Dark Blue' => '#1C4587',        // Dark blue accent
            'Brown' => '#71624E',            // Brown accent
            'Gray' => '#666666',             // Neutral gray
            'Black' => '#000000',            // Black
            'White' => '#FFFFFF',            // White
            'Red' => '#DC3545',              // Bootstrap danger red
            'Green' => '#198754',            // Bootstrap success green
            'Orange' => '#FD7E14',           // Bootstrap warning orange
            'Purple' => '#6F42C1',           // Bootstrap purple
            'Pink' => '#E91E63',             // Pink accent
            'Teal' => '#20C997',             // Teal accent
            'Magenta' => '#E91E63',          // Legacy magenta mapping
        ];
    }

    /**
     * Get the category color for frontend use
     * Returns the hex color value or a default if none set
     *
     * @return string
     */
    public function getColorPreview(): string
    {
        if (!$this->Color) {
            return '#334597'; // Default to Bethlehem blue if no color set
        }

        // If the color starts with #, it's already a hex value (from ColorField)
        if ($this->Color && strpos($this->Color, '#') === 0) {
            // Expand 3-character hex codes to 6 characters for consistency
            if (preg_match('/^#([a-fA-F0-9]{3})$/', $this->Color, $matches)) {
                $hex = $matches[1];
                $expanded = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
                return '#' . strtolower($expanded);
            }
            // For all hex values, ensure consistent lowercase
            return '#' . strtolower(ltrim($this->Color, '#'));
        }

        // Otherwise, it's a legacy color name from ColorPaletteField - map to hex values
        $colorMap = self::getLegacyColorMap();
        return $colorMap[$this->Color] ?? '#334597'; // Fallback to Bethlehem blue
    }

    /**
     * Get the color as a hex value (without HTML formatting)
     * Used by CalendarController for color calculations
     *
     * @return string
     */
    public function getColorHex(): string
    {
        $colorMap = self::getLegacyColorMap();

        // If it's already a hex color (3, 6, or 8 character), return without # prefix
        $pattern = '/^#?([a-fA-F0-9]{3}|[a-fA-F0-9]{6}|[a-fA-F0-9]{8})$/';
        if ($this->Color && preg_match($pattern, $this->Color, $matches)) {
            $hex = $matches[1];
            // Expand 3-character hex codes to 6 characters (e.g., "fff" -> "ffffff")
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
                return strtolower($hex);
            }
            // For 8-character hex (with alpha), keep uppercase as expected by tests
            if (strlen($hex) === 8) {
                return strtoupper($hex);
            }
            // For 6-character hex, use lowercase
            return strtolower($hex);
        }

        // Otherwise map from color name and remove # prefix
        $hexColor = $colorMap[$this->Color] ?? '#334597';
        return strtolower(ltrim($hexColor, '#'));
    }

    /**
     * @return void
     */
    public function onBeforeWrite(): void
    {
        parent::onBeforeWrite();

        if (!$this->URLSegment) {
            $siteTree = SiteTree::singleton();
            $this->URLSegment = $siteTree->generateURLSegment($this->Title);
        }
        // Ensure that this object has a non-conflicting URLSegment value.
        $count = 2;
        while (!$this->validURLSegment()) {
            $this->URLSegment = preg_replace('/-[0-9]+$/', null, $this->URLSegment) . '-' . $count;
            $count++;
        }
    }

    /**
     * This function determines a user's
     * ability to create a new Category
     *
     * @param null $member
     * @param array $context
     *
     * @return bool|int
     */
    public function canCreate($member = null, $context = []): bool|int
    {
        return Permission::check('CREATE_CATEGORY', 'any', $member);
    }

    /**
     * This function determines a user's
     * ability to edit an existing Category
     *
     * @param null $member
     * @param array $context
     *
     * @return bool|int
     */
    public function canEdit($member = null, $context = []): bool|int
    {
        return Permission::check('EDIT_CATEGORY', 'any', $member);
    }

    /**
     * This function determines a user's
     * ability to delete an existing Category
     *
     * @param null $member
     * @param array $context
     *
     * @return bool|int
     */
    public function canDelete($member = null, $context = []): bool|int
    {
        return Permission::check('DELETE_CATEGORY', 'any', $member);
    }

    /**
     * This function determines a user's
     * ability to view an existing Category
     *
     * @param null $member
     * @param array $context
     *
     * @return bool
     */
    public function canView($member = null, $context = []): bool
    {
        return true;
    }

    /**
     * This function provides permissions options
     * within the security section of the cms
     * for the Event object. The array keys are
     * referenced when executing the can() function above
     *
     * @return array
     */
    public function providePermissions(): array
    {
        return [
            "CREATE_CATEGORY" => [
                'name' => _t('Category.CreateCategory', "Create Category"),
                'category' => _t('Permissions.Permission_CategoryCreate_Permission', 'Calendar - Create Category'),
                'help' => _t('Category.Create_Permission_Category_Permission', 'Ability to create Category records.'),
                'sort' => 400,
            ],
            "EDIT_CATEGORY" => [
                'name' => _t('Category.EditCategory', "Edit Category"),
                'category' => _t('Permissions.Permission_CategoryEdit_Permission', 'Calendar - Edit Category'),
                'help' => _t('Category.Edit_Permission_Category_Permission', 'Ability to edit Category records.'),
                'sort' => 400,
            ],
            "DELETE_CATEGORY" => [
                'name' => _t('Category.DeleteCategory', "Delete Category"),
                'category' => _t('Permissions.Permission_CategoryDelete_Permission', 'Calendar - Delete Category'),
                'help' => _t('Category.Delete_Permission_Category_Permission', 'Ability to delete Category records.'),
                'sort' => 400,
            ],
        ];
    }

    /**
     * @return bool
     */
    public function validURLSegment(): bool
    {
        $exclude = [];
        if ($this->ID != 0) {
            $exclude = ['ID' => $this->ID];
        }

        return !SiteTree::get()->filter('URLSegment', $this->URLSegment)->first()
            && !static::get()->filter('URLSegment', $this->URLSegment)->exclude($exclude)->first();
    }

    /**
     * Method determining if this category is a sub-category
     *
     * @return bool
     */
    public function getIsSubcategory(): bool
    {
        return ($this->ParentID != 0);
    }

    /**
     * @return string
     */
    public function Link(): string
    {
        /** @var Calendar $cal */
        $cal = Calendar::get()->first();
        $requestVars = CalendarController::clean_request_vars(Controller::curr()->getRequest()->getVars());
        if (!$this->getIsActiveFilter()) {
            $requestVars[Config::inst()->get(
                CalendarController::class,
                'filter_any_prefix'
            ) . '_Categories' . htmlentities('[') . $this->ID . htmlentities(']')] = $this->ID;
        } else {
            unset(
                $requestVars[Config::inst()->get(
                    CalendarController::class,
                    'filter_any_prefix'
                ) . '_Categories'][$this->ID]
            );
        }
        //disable start var as changing the filter changes the result set
        if (isset($requestVars['start'])) {
            unset($requestVars['start']);
        }

        return $cal ? Controller::join_links($cal->Link(), '?' . http_build_query($requestVars)) : 'category';
    }

    /**
     * @return bool
     */
    public function getIsActiveFilter(): bool
    {
        $requestVars = CalendarController::clean_request_vars(Controller::curr()->getRequest()->getVars());

        return isset(
            $requestVars[Config::inst()->get(
                'Calendar_Controller',
                'filter_any_prefix'
            ) . '_Categories'][$this->ID]
        );
    }
}
