# GitHub Copilot Instructions for SilverStripe Calendar

This document provides guidance for GitHub Copilot when working on the SilverStripe Calendar module.

## Project Overview

SilverStripe Calendar is a comprehensive calendar module for SilverStripe CMS that provides:
- Event management with recurring event support
- Category-based organization with color coding
- Multiple calendar views (month, week, day, list)
- FullCalendar.js frontend integration
- ICS/iCal feed support
- Lumberjack-based nested event management

## Tech Stack

- **Backend**: PHP 8.1+ with SilverStripe CMS 5.0+
- **Frontend**: JavaScript (ES6+), SCSS, Bootstrap 5
- **Build System**: Webpack 5
- **Testing**: PHPUnit for PHP, Playwright for frontend
- **Dependencies**: 
  - Carbon 3.0 for date/time handling
  - FullCalendar.js 6.1+ for calendar UI
  - SilverStripe Lumberjack for hierarchical page management
  - Symbiote Queued Jobs for background processing

## Code Style & Standards

### PHP
- Follow **PSR-12** coding standards
- Use SilverStripe naming conventions (CamelCase for methods, StudlyCaps for classes)
- All PHP code must pass `phpcs` and `phpstan` checks
- Type hints are required for method parameters and return types where possible
- Use dependency injection for services

### JavaScript
- Modern ES6+ syntax
- Follow existing code formatting patterns
- Use ESLint configuration when available

### CSS/SCSS
- Use Bootstrap 5 utility classes where appropriate
- Follow BEM naming convention for custom classes
- Ensure responsive design for all viewport sizes

## Development Commands

### PHP Development
```bash
# Install dependencies
composer install

# Run database build
vendor/bin/sake dev/build flush=all

# Run PHP tests
vendor/bin/phpunit

# Run PHP code sniffer
vendor/bin/phpcs src/ tests/ --standard=phpcs.xml.dist

# Run PHPStan static analysis
vendor/bin/phpstan analyse src/ --configuration=phpstan.neon.dist
```

### Frontend Development
```bash
# Install dependencies
npm install

# Development build with watch
npm run dev

# Production build
npm run build

# Lint JavaScript
npm run lint:js

# Lint CSS/SCSS
npm run lint:css

# Run Playwright tests
npm test
```

## Project Structure

```
├── src/                      # PHP source code
│   ├── Admin/               # CMS Admin classes
│   ├── Controller/          # Page controllers
│   ├── Extension/           # SilverStripe extensions
│   ├── Model/               # Data models (EventPage, Category, etc.)
│   ├── Page/                # Page types (Calendar, EventPage)
│   ├── Task/                # Build tasks and migrations
│   └── Traits/              # Shared traits
├── tests/                   # PHPUnit tests (mirrors src/ structure)
├── client/                  # Frontend assets
│   ├── src/                # Source files (JS, SCSS)
│   └── dist/               # Built assets (generated, not in git)
├── templates/              # SilverStripe templates
├── _config/                # SilverStripe configuration YAML
└── docs/                   # Documentation
```

## Key Architectural Patterns

### SilverStripe Patterns
- **Page Types**: Main content types extend `Page` class (e.g., `Calendar`, `EventPage`)
- **DataObjects**: Models extend `DataObject` (e.g., `Category`, `EventException`)
- **Extensions**: Use `Extension` pattern to enhance existing classes
- **ORM**: Use SilverStripe's ORM for database operations (`DataList`, `ArrayList`)
- **Controllers**: Page controllers handle routing and template rendering

### Recurring Events
- Use Carbon library for date/time manipulation
- EventPage can have recurring patterns (daily, weekly, monthly, yearly)
- EventInstance represents specific occurrences of recurring events
- EventException handles exceptions to recurring patterns

### Frontend Architecture
- Hybrid approach: FullCalendar.js + traditional templates
- AJAX endpoints for event loading
- Server-side rendering fallback for SEO

## Testing Guidelines

### PHP Tests
- Place tests in `tests/` directory mirroring `src/` structure
- Extend `SapphireTest` for SilverStripe-specific tests
- Use `FunctionalTest` for controller/integration tests
- Mock external dependencies and time-dependent operations
- Test both positive and negative cases

### Frontend Tests
- Playwright tests in `tests/` directory
- Test critical user interactions
- Verify responsive behavior
- Test calendar event loading and rendering

## Common Development Tasks

### Adding a New Field to EventPage
1. Add field in `EventPage::getCMSFields()`
2. Add to `$db` or appropriate config array
3. Run `dev/build` to update database
4. Update templates if needed for display
5. Add tests to verify field behavior

### Modifying Recurring Event Logic
1. Changes typically in `EventPage` or related models
2. Consider impact on existing event instances
3. Test thoroughly with various recurrence patterns
4. Update documentation if behavior changes

### Frontend Changes
1. Edit source files in `client/src/`
2. Run `npm run build:dev` to compile
3. Test in browser with dev tools open
4. Run `npm run build` for production before committing
5. Never commit `client/dist/` files (handled by build)

## SilverStripe-Specific Guidelines

### ORM Queries
```php
// Good: Use efficient ORM queries
$events = EventPage::get()
    ->filter(['CategoryID' => $categoryID])
    ->sort('StartDate ASC')
    ->limit(10);

// Good: Use exists() for checking existence
if ($events->exists()) { ... }

// Avoid: Loading all records unnecessarily
$events = EventPage::get()->toArray(); // Don't do this
```

### Template Variables
- Use `$Variable` for simple properties
- Use `<% loop %>` for collections
- Use `<% if %>` for conditionals
- Escape output appropriately (`$XMLVal`, `$RAW`)

### Configuration
- YAML config files in `_config/` directory
- Use dependency injection where appropriate
- Environment-specific config via `.env` file

## Breaking Changes to Avoid

- Don't modify public API methods without deprecation notice
- Maintain backward compatibility with SilverStripe 5.x
- Don't change database field types without migration task
- Preserve existing template variable names
- Don't remove or rename public methods without deprecation

## Performance Considerations

- Use database indexes for frequently queried fields
- Cache recurring event instances when appropriate
- Lazy load related objects in templates
- Optimize frontend bundle size
- Use queued jobs for long-running operations

## Security Best Practices

- Validate and sanitize all user input
- Use SilverStripe's CSRF protection
- Apply proper permission checks (`canView`, `canEdit`, `canDelete`)
- Escape template output to prevent XSS
- Use parameterized queries (ORM handles this)

## Documentation

- Update README.md for user-facing changes
- Document new configuration options in YAML comments
- Add PHPDoc blocks for all public methods
- Update CHANGELOG.md for releases
- Keep docs/ directory up to date

## Useful Resources

- [SilverStripe Documentation](https://docs.silverstripe.org/)
- [SilverStripe API Documentation](https://api.silverstripe.org/)
- [FullCalendar Documentation](https://fullcalendar.io/docs)
- [Carbon Documentation](https://carbon.nesbot.com/docs/)

## Issue Assignment Guidelines

Copilot works best with:
- Well-defined bug fixes
- Feature additions with clear requirements
- Test additions for existing functionality
- Documentation updates
- Code refactoring with specific goals
- UI/UX improvements with mockups or descriptions

Avoid assigning:
- Major architectural changes without detailed planning
- Issues requiring deep domain expertise
- Tasks with ambiguous requirements
- Security-critical changes without review

## Getting Help

When unsure about:
- SilverStripe conventions: Check official documentation or existing code patterns
- Module architecture: Review the comprehensive files in repository root
- Best practices: Refer to this document and SilverStripe coding standards
