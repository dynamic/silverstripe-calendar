#Calendar Filtering

The Calendar uses a semi-dynamic filtering mechanism to allow for additional modules to interact with the `Calendar_Controller::EventFilterForm()` method. The `Calendar::upcoming_events()` also has hooks to allow additional modules the ability to adjust the values for `filter()`, `filterAny()` and `exclude()`. Below are some examples of how this could be accomplished:

###Update methods

__config.yml__

```yaml
Calendar_Controller:
  extensions:
    - CalendarExtension
```

__CalendarExtension.php__

```php
class CalendarExtension extends Extension
{

	public funciton updateEventFilterFormFields(FieldList $fields) {
		$partial = $this->owner->config('partial_match_prefix);
		$fields->removeByName($partial.'.Title');
		$fields->addFieldToTab($partial.'.SubTitle');
	}

	public function updateCalendarFilter(&$filter) {
		$filter['MyAdditionalFilter] = 'MyValue';
	}
	
	public function updateCalendarFilterAny(&$filterAny) {
		$filterAny['MyAdditionalFilterAny] = 'AnotherValue';
	}
	
	public function updateCalendarExclude(&$exclude) {
		$exclude['ExcludeThisThing'] => 'MyExlcludeValue';
	}
}
```

###Change Getter Prefixes

__config.yml__

```yml
Calendar_Controller:
  filter_prefix: 'fltr'
  filter_any_prefix: 'fltrne'
  exclude_prefix: 'xcld'
```