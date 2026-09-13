# SilverStripe Calendar

A comprehensive calendar module for the SilverStripe CMS with event management, recurring events, and category organization.

[![CI](https://github.com/dynamic/silverstripe-calendar/actions/workflows/ci.yml/badge.svg)](https://github.com/dynamic/silverstripe-calendar/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/dynamic/silverstripe-calendar/branch/2/graph/badge.svg)](https://codecov.io/gh/dynamic/silverstripe-calendar)

[![Latest Stable Version](https://poser.pugx.org/dynamic/silverstripe-calendar/v/stable)](https://packagist.org/packages/dynamic/silverstripe-calendar)
[![Total Downloads](https://poser.pugx.org/dynamic/silverstripe-calendar/downloads)](https://packagist.org/packages/dynamic/silverstripe-calendar)
[![Latest Unstable Version](https://poser.pugx.org/dynamic/silverstripe-calendar/v/unstable)](https://packagist.org/packages/dynamic/silverstripe-calendar)
[![License](https://poser.pugx.org/dynamic/silverstripe-calendar/license)](https://packagist.org/packages/dynamic/silverstripe-calendar)

## Requirements

- SilverStripe CMS ^5.0
- SilverStripe Lumberjack ^3.0
- Nesbot Carbon ^3.0
- Symbiote GridField Extensions ^4.0
- Symbiote Queued Jobs ^5.0
- Uncle Cheese Display Logic ^3.0
- Ryan Potter Color Field ^1.0
- DFT Frontend MultiSelectField ^1.0

## Installation

```bash
composer require dynamic/silverstripe-calendar
```

After installation, run `/dev/build?flush=all` to update your database.

## License

See [License](LICENSE.md)

## Features

- **Event Management**: Create and manage events with comprehensive details
- **Recurring Events**: Support for complex recurring event patterns using Carbon
- **Category Organization**: Organize events with color-coded categories
- **Calendar Display**: Multiple view modes (month, week, day, list)
- **Event Search & Filtering**: Advanced filtering by category, date range, and keywords
- **Admin Interface**: Comprehensive CMS interface for event management
- **Lumberjack Integration**: Nested event management within calendar pages
- **Frontend Calendar**: Interactive JavaScript calendar interface
- **Event Categories**: Color-coded categorization system
- **Responsive Design**: Mobile-friendly calendar views

## Usage

### Basic Setup

1. **Create a Calendar Page**: In the CMS, create a new page of type "Calendar"
2. **Configure Categories**: Use the Calendar Admin to create event categories
3. **Add Events**: Create events either through the Calendar Admin or as child pages of your Calendar page

### Calendar Page

The Calendar page serves as the main container for your events and provides the frontend calendar interface.

### Event Management

Events can be managed in two ways:

#### Calendar Admin Interface
Access through CMS Admin → Calendar to manage:
- Event Pages
- Categories

#### Lumberjack Interface
Manage events as child pages directly within your Calendar page for a hierarchical approach.

### Event Categories

Create color-coded categories to organize your events:
- Assign colors for visual distinction
- Filter events by category
- Organize events by type, department, or any classification system

### Recurring Events

The module supports complex recurring patterns:
- Daily, weekly, monthly, yearly recurrence
- Custom recurrence rules using Carbon date manipulation
- Exception dates for holidays or special circumstances
- End dates or occurrence limits

## Frontend Integration

### Hybrid Calendar Architecture

The module provides a hybrid frontend approach:
- **Primary Interface**: Interactive FullCalendar.js for calendar views
- **Server-Side Support**: Traditional templates for custom implementations
- **Responsive Design**: Mobile-friendly across all view modes

### FullCalendar Integration

The default `Calendar.ss` template includes an interactive calendar powered by FullCalendar with:
- Event navigation and filtering
- Category-based color coding
- Responsive month/week/day views
- Event details modal/popover
- AJAX event loading

### Custom Template Implementation

For custom implementations without FullCalendar, you can create server-side event listings:

```html
<!-- Custom Calendar Template Example -->
<div class="event-listing">
  <% loop $PaginatedEvents %>
    <div class="event-item">
      <h3><a href="$Link">$Title</a></h3>
      <p class="event-date">$StartDate.Nice</p>
      <% if $Category %><span class="badge" style="background-color: $Category.Color">$Category.Title</span><% end_if %>
      <p>$Content.Summary(100)</p>
    </div>
  <% end_loop %>

  <% if $PaginatedEvents.MoreThanOnePage %>
    <nav class="pagination">
      <% if $PaginatedEvents.NotFirstPage %>
        <a href="$PaginatedEvents.PrevLink">Previous</a>
      <% end_if %>
      <% loop $PaginatedEvents.PaginationSummary %>
        <% if $CurrentBool %>
          <span class="current">$PageNum</span>
        <% else %>
          <a href="$Link">$PageNum</a>
        <% end_if %>
      <% end_loop %>
      <% if $PaginatedEvents.NotLastPage %>
        <a href="$PaginatedEvents.NextLink">Next</a>
      <% end_if %>
    </nav>
  <% end_if %>
</div>
```

The `events_per_page` configuration controls server-side pagination for custom templates.

### Template Customization

Override templates by copying them to your theme:
- `Calendar.ss` - Main calendar page template
- `EventPage.ss` - Individual event template
- Calendar JavaScript components in `client/dist/`

## Configuration

### Basic Configuration

```yaml
# mysite/_config/calendar.yml
Dynamic\Calendar\Page\Calendar:
  # Default events per page
  events_per_page: 10

Dynamic\Calendar\Page\EventPage:
  # Default event duration in hours
  default_duration: 1
```

### Timezone Configuration

**IMPORTANT**: If your events are stored in a timezone other than UTC, you must configure the timezone to ensure ICS calendar feeds display correct times.

```yaml
# app/_config/calendar.yml (or mysite/_config/calendar.yml for older projects)
Dynamic\Calendar\Controller\CalendarController:
  # Timezone for event storage and ICS conversion
  # Use PHP timezone identifiers: https://www.php.net/manual/en/timezones.php
  timezone: 'America/Chicago'  # Central Time (US)
```

**How it works**:
- Event times are stored in your database in the configured timezone (e.g., 8:00 AM Central Time)
- When generating ICS feeds, times are parsed in the configured timezone and converted to UTC
- Calendar applications (Google Calendar, Outlook, Apple Calendar) receive UTC times and display them in the user's local timezone

**Common timezone examples**:
- `'America/New_York'` - Eastern Time (US)
- `'America/Chicago'` - Central Time (US)
- `'America/Denver'` - Mountain Time (US)
- `'America/Los_Angeles'` - Pacific Time (US)
- `'Europe/London'` - Greenwich Mean Time
- `'Australia/Sydney'` - Australian Eastern Time

**Note**: If not configured, the default timezone is `UTC`.

### Category Configuration

Categories support color customization and can be managed through the Calendar Admin interface.

### Recurring Events Configuration

Configure default recurrence options:

```yaml
Dynamic\Calendar\Model\RecurringEvent:
  # Maximum occurrences to generate
  max_occurrences: 500
  # Default recurrence end date (months from start)
  default_end_months: 12
```

### Template Configuration

The module templates use vanilla Bootstrap classes and should work out of the box. To disable theme-specific configurations for testing:

```yaml
# mysite/_config/calendar-templates.yml
Dynamic\Calendar\Page\Calendar:
  # Disable theme template overrides
  use_theme_templates: false

# For custom implementations without FullCalendar
Dynamic\Calendar\Page\Calendar:
  # Enable server-side event listing instead of FullCalendar
  enable_fullcalendar: false
```

## Development

### Frontend Development

The module includes a webpack-based build system for frontend assets:

```bash
# Install dependencies
npm install

# Development build
npm run build:dev

# Production build
npm run build

# Watch for changes
npm run watch
```

### Testing

Run the test suite:

```bash
# PHPUnit tests
vendor/bin/phpunit

# Code quality
vendor/bin/phpcs src/ tests/ --standard=phpcs.xml.dist
vendor/bin/phpstan analyse src/ --configuration=phpstan.neon.dist
```

## Upgrading

### From Version 1.x

When upgrading from version 1.x, run the datetime conversion task:

```bash
sake dev/tasks/calendar-datetime-conversion-task
```

This migrates datetime data to separate date and time fields.

### From Earlier 2.x Versions

- Ensure Carbon ^3.0 compatibility
- Review recurring event configurations
- Run `/dev/build` to apply database changes

### All-day events in the AJAX feed

The `AllDay` column is the single source of truth for the `allDay` value the AJAX events
feed emits (`CalendarController::events()`). It previously derived `allDay` from whether
`StartTime` was set, which disagreed with the `?allDay=0|1` filter, because that has
always filtered on the `AllDay` column. A single event could therefore match the all-day
filter and still be drawn as timed.

Two visible consequences:

- An event with `AllDay` off and no `StartTime` serialises `"allDay": false` and renders
  as a timed event at midnight, where it used to render all-day. The backfill task below
  flags those events as all-day again, so run it after upgrading.
- Saving a record with *All Day* ticked clears `StartTime` and `EndTime`. The CMS only
  hides those fields rather than emptying them, so a clock time entered before the tick
  used to survive the save.

Run the backfill once after upgrading to normalise existing rows:

```bash
sake dev/tasks/calendar-allday-normalisation-task
```

(`sake tasks:calendar-allday-normalisation-task` is the same command; in a browser it is at
`dev/tasks/calendar-allday-normalisation-task`.)

It sets `AllDay` on events that have no time (preserving how they already rendered) and
clears the times left behind on events already flagged all-day. Untouched rows are not
written, so consistent events gain no new version.

Run it while the site is quiet. It writes and publishes content, and it reads its rows up
front, so a record an editor saves part-way through the run is written from the snapshot the
task took rather than from their edit.

The task works on the draft stage and mirrors to live only where that publishes nothing
else: a page carrying unpublished edits is normalised on draft and reported as *held back*,
and its live copy keeps the old values until you publish it. Draft-only pages are
normalised on draft and never published. Re-running the task is harmless, so run it again
after publishing the held-back pages, or check the counts it prints.

**"No time" means `StartTime` is empty, so an event with a lone `EndTime` is promoted too.**
The old serializer decided `allDay` from `StartTime` alone, so a record with no `StartTime`
rendered as all-day whatever its `EndTime` said. Promoting those rows keeps them rendering
that way; the leftover `EndTime` is cleared by the all-day write, and never influenced
`allDay` before or after.

**A stored `00:00:00` is treated as midnight, not as no time.** An event with a `StartTime`
of `00:00:00` and *All Day* off is a timed event that begins at midnight, and the backfill
leaves it alone. Only a `NULL` time counts as "no time entered". This matters because on a
SQL `TIME` column a comparison against `''` coerces to `TIME '00:00:00'`, so a "time is
empty" test written in SQL reports a stored midnight as empty while PHP does not, and the
two disagree about the same row. The legacy `calendar-datetime-conversion-task` writes
`00:00:00` for midnight datetimes, so real upgraded installs do have these rows.

The events feed caches its JSON per calendar, date window and filter set, not per
serialisation format, so responses produced before the upgrade keep serving the old
`allDay` values until `json_cache_ttl` expires. Flush the cache, or wait out the TTL,
before concluding the backfill did not take effect.

### Per-occurrence all-day overrides

An `EventException` that sets *All Day* makes that occurrence serialise `"allDay": true`
with a date-only `start`, whatever its parent event says.

The reverse is not expressible, and this is a pre-existing limitation rather than a
consequence of the change above: `ModifiedAllDay` is a NOT NULL Boolean, so an exception
that leaves it at `0` is indistinguishable from one that never intended to override the
parent. A `ModifiedStartTime` entered on an occurrence of an all-day parent is therefore
discarded, and that occurrence stays all-day. Making the column nullable would fix it,
which is tracked in issue #143. Until then, an all-day event cannot have a single timed
occurrence.

One further pre-existing quirk, unchanged here: FullCalendar reads an all-day `end` as
exclusive, but the feed emits `EndDate` unchanged, so a multi-day all-day event renders one
day short. The ICS export does add the day, so the two exports of one event differ. Filed
as issue #187.

## Troubleshooting

### Common Issues

**Events not displaying**:
- Ensure `/dev/build?flush=all` has been run
- Check event date ranges and publication status

**JavaScript calendar not loading**:
- Verify frontend assets are built (`npm run build`)
- Check browser console for JavaScript errors

**Recurring events not generating**:
- Verify queued jobs are configured and running
- Check recurring event configuration and Carbon date logic

### Performance Optimization

For large numbers of events:
- Enable query caching in your environment
- Consider pagination limits
- Use category filtering to reduce load

## Contributing

We welcome contributions! Please read our [contributing guidelines](CONTRIBUTING.md) and:

1. Fork the repository
2. Create a feature branch
3. Write tests for new functionality
4. Ensure code quality standards are met
5. Submit a pull request

## Maintainers

* [Dynamic](https://www.dynamicagency.com) (<dev@dynamicagency.com>)

## Bugtracker

Bugs are tracked in the issues section of this repository. Before submitting an issue please read over existing issues to ensure yours is unique.

If the issue does look like a new bug:

- Create a new issue
- Describe the steps required to reproduce your issue, and the expected outcome. Unit tests, screenshots and screencasts can help here.
- Describe your environment as detailed as possible: SilverStripe version, Browser, PHP version, Operating System, any installed SilverStripe modules.

## Development and Contribution

If you would like to make contributions to the module please ensure you raise a pull request and discuss with the module maintainers.

## Related Modules

- [SilverStripe Elemental Calendar](https://github.com/dynamic/silverstripe-elemental-dynamic-calendar) - Elemental block integration
