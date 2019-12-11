### Calendar ###

A calendar module for the SilverStripe cms with recursion options.


#### Installation ####
`composer require dynamic/silverstripe-calendar`

#### Upgrading from 1.0.0-alpha2

When upgrading from `1.0.0-alpha2`, you need to run the following task to migrate the Datetime data to the now separate Date and Time fields:

- `sake dev/tasks/calendar-datetime-conversion-task`