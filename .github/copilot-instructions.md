# Dynamic SilverStripe Calendar Module

**ALWAYS follow these instructions first and fallback to additional search and context gathering only if the information here is incomplete or found to be in error.**

Dynamic SilverStripe Calendar is a SilverStripe CMS module providing calendar functionality with advanced recursion patterns, modern frontend architecture using Bootstrap 5, FullCalendar integration, and comprehensive event management capabilities.

## Working Effectively

### Prerequisites and Dependencies
- **PHP 8.3+** - Module requires PHP 8.3 or higher
- **Node.js 20+** - Frontend build system requires Node.js 20.19.4 or higher  
- **npm 10+** - Package management
- **Composer** - PHP dependency management

### Initial Setup
Run these commands in sequence to set up the development environment:

```bash
# Install frontend dependencies - NEVER CANCEL: takes ~20 seconds
npm install

# Install PHP dependencies - NEVER CANCEL: may take 10-15 minutes, often fails due to network issues
composer install --no-interaction
```

**CRITICAL:** Composer install frequently fails in sandboxed environments due to GitHub authentication issues. If composer install fails:
- This is a known limitation in constrained environments
- PHP files can still be syntax-checked with `php -l filename.php`  
- Focus on frontend development which works reliably
- Use alternative approaches documented below

### Frontend Build System
The module uses a modern webpack-based build system that ALWAYS works:

```bash
# Development build with source maps - NEVER CANCEL: takes ~3 seconds
npm run build:dev

# Production build with optimization - NEVER CANCEL: takes ~6 seconds  
npm run build

# Watch mode for development - NEVER CANCEL: starts in ~2 seconds
npm run dev
```

**Build Output:** All builds generate assets in `client/dist/`:
- `js/calendar.bundle.js` - Main calendar functionality
- `js/admin.bundle.js` - Admin interface enhancements  
- `js/vendors.bundle.js` - Third-party libraries
- `css/calendar.bundle.css` - Compiled styles

### Code Quality and Linting

**JavaScript Linting:**
```bash
# Requires .eslintrc.js config file to be created first
npm run lint:js
```

**CSS/SCSS Linting:**
```bash  
# Requires .stylelintrc.json config file to be created first
npm run lint:css
```

**IMPORTANT:** Linting configs are not included by default. Create basic configs:

`.eslintrc.js`:
```javascript
module.exports = {
    "env": {"browser": true, "es2021": true},
    "extends": "eslint:recommended",
    "parserOptions": {"ecmaVersion": 12, "sourceType": "module"}
};
```

`.stylelintrc.json`:
```json
{"extends": ["stylelint-config-standard-scss"]}
```

**PHP Code Quality:**
```bash
# Syntax check individual files (always works)
php -l src/Page/EventPage.php

# Check all PHP files for syntax errors
find src/ -name "*.php" -exec php -l {} \;

# Run PHP CodeSniffer (requires composer install)
vendor/bin/phpcs src/ tests/ --standard=PSR12

# Run PHPStan (requires composer install)  
vendor/bin/phpstan analyse src/
```

### Testing

**Frontend Testing with Playwright:**
```bash
# Install Playwright browsers - NEVER CANCEL: may take 15-20 minutes, often fails in sandboxed environments
npm run install-playwright

# Run tests - NEVER CANCEL: takes ~5-10 minutes when working
npm run test

# Run tests with browser UI visible
npm run test:headed

# Debug tests interactively
npm run test:debug
```

**CRITICAL:** Playwright installation often fails in sandboxed environments due to download restrictions. When this happens:
- Tests cannot be run but test files can still be examined
- Focus on manual testing and code review
- Test files are in `tests/playwright/`

**PHP Unit Testing:**
```bash
# Run PHPUnit tests (requires composer install)
vendor/bin/phpunit

# Run with coverage (requires composer install)
vendor/bin/phpunit --coverage-html coverage/
```

### Development Workflow

**ALWAYS follow this sequence when making changes:**

1. **Build frontend assets first:**
   ```bash
   npm run build:dev
   ```

2. **Check PHP syntax:**
   ```bash
   php -l path/to/changed/file.php
   ```

3. **Test functionality manually:**
   - View calendar pages in browser
   - Test event creation/editing
   - Verify filtering and navigation
   - Check mobile responsiveness

4. **Run available quality checks:**
   ```bash
   # If configs exist
   npm run lint:js
   npm run lint:css
   
   # If composer working
   vendor/bin/phpcs src/ tests/
   ```

### Known Working Commands (ALWAYS RELIABLE)

These commands work consistently in all environments:

```bash
# Node.js and npm are always available
node --version  # v20.19.4+
npm --version   # 10.8.2+

# Frontend builds always work
npm install     # ~20 seconds
npm run build   # ~6 seconds  
npm run dev     # Watch mode

# PHP syntax checking always works
php --version   # 8.3.6+
php -l filename.php

# Basic file operations
find src/ -name "*.php"
ls -la client/dist/
```

### Known Problematic Commands

These commands frequently fail in constrained environments:

```bash
# Often fails due to network/auth issues
composer install

# Often fails due to download restrictions  
npm run install-playwright
npm run test

# Fail without config files
npm run lint:js
npm run lint:css

# Require composer dependencies
vendor/bin/phpunit
vendor/bin/phpcs
```

## Repository Structure

### Key Directories
- `src/` - PHP source code (SilverStripe module structure)
  - `Page/` - Page types (EventPage, Calendar, RecursiveEvent)
  - `Controller/` - Controllers (CalendarController, EventPageController)  
  - `Model/` - Data models (Category, EventException)
  - `Extension/` - SilverStripe extensions
  - `Factory/` - Factory classes for recursion
- `client/src/` - Frontend source code
  - `js/` - JavaScript modules and components
  - `scss/` - Sass stylesheets
- `client/dist/` - Built frontend assets (generated by webpack)
- `tests/` - Test suites
  - `Page/`, `Model/`, etc. - PHP unit tests
  - `playwright/` - Frontend end-to-end tests
- `templates/` - SilverStripe templates
- `_config/` - SilverStripe configuration
- `docs/` - Documentation

### Core Files
- `package.json` - Frontend dependencies and scripts
- `composer.json` - PHP dependencies  
- `webpack.config.js` - Frontend build configuration
- `phpunit.xml.dist` - PHP test configuration
- `phpcs.xml.dist` - PHP code standards
- `.github/workflows/ci.yml` - CI pipeline

## Architecture Overview

### Backend (PHP/SilverStripe)
- **SilverStripe 5.x** CMS module architecture
- **Carbon 3.0** for advanced date/time handling
- **EventPage** class with recursion capabilities using Carbon periods
- **Categories** system for event organization  
- **Exception handling** for modified recurring events
- **Caching layer** for performance optimization

### Frontend (JavaScript/CSS)
- **Bootstrap 5.3** UI framework
- **FullCalendar 6.x** for calendar display
- **Webpack 5** build system with Babel transpilation
- **Sass** for modular stylesheets
- **ES6+ modules** with modern JavaScript features
- **Responsive design** with mobile-first approach

### Key Technologies
- **PHP 8.3+** - Server-side language
- **SilverStripe 5.x** - CMS framework
- **Carbon 3.0** - Date manipulation
- **Node.js/npm** - Frontend tooling
- **Bootstrap 5** - CSS framework
- **FullCalendar** - Calendar widget
- **Choices.js** - Enhanced select elements

## Validation Scenarios

**ALWAYS test these user scenarios after making changes:**

1. **Calendar Display:**
   - Visit calendar page in browser
   - Verify calendar renders with events
   - Test month/week/day view switching

2. **Event Management:**
   - Create new event in admin
   - Edit existing event
   - Delete event and verify removal

3. **Filtering:**
   - Use category filters  
   - Apply date range filters
   - Clear filters and verify reset

4. **Responsiveness:**
   - Test on mobile viewport (375px wide)
   - Verify touch interactions work
   - Check keyboard navigation

5. **Recurring Events:**
   - Create weekly recurring event
   - Verify instances appear correctly
   - Test exception handling

## Troubleshooting

### Composer Issues
```bash
# If authentication fails
composer config --no-plugins allow-plugins.composer/installers true
composer config --no-plugins allow-plugins.silverstripe/vendor-plugin true

# Skip dev dependencies
composer install --no-dev --ignore-platform-reqs
```

### Build Issues  
```bash
# Clear npm cache
npm clean-install

# Clear webpack cache
rm -rf client/dist/*
npm run build
```

### Missing Dependencies
```bash
# Reinstall Node modules
rm -rf node_modules/
npm install
```

## Performance Expectations

- **npm install:** ~20 seconds
- **npm run build:** ~6 seconds  
- **npm run build:dev:** ~3 seconds
- **Webpack watch mode startup:** ~2 seconds
- **JavaScript linting:** ~1 second (with config)
- **CSS linting:** ~1 second (with config)
- **PHP syntax check:** <1 second per file
- **Composer install:** 10-15 minutes (when working)
- **Playwright install:** 15-20 minutes (when working)

**NEVER CANCEL builds or long-running commands.** If a command appears stuck, wait at least 20 minutes before considering alternatives.

## CI/CD Integration

The module uses GitHub Actions CI with SilverStripe's standard workflow:
- **PHP testing:** Multiple PHP versions with PostgreSQL/MySQL
- **Code quality:** PHPStan, PHPCS validation
- **Security:** Dependency vulnerability scanning

## Migration Notes

When upgrading from older versions:
- Run `sake dev/tasks/calendar-datetime-conversion-task` after updating from 1.0.0-alpha2
- Frontend assets require rebuilding: `npm run build`
- Check for breaking changes in Carbon 3.0 date handling

---

**Always build and exercise your changes using the validation scenarios above before considering your work complete.**