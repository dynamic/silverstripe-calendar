# ICS Feed Subscription Feature

The SilverStripe Calendar module supports ICS (iCalendar) feeds, allowing users to subscribe to calendar events in external calendar applications with dynamic category filtering.

## Overview

The ICS feed functionality provides RFC 5545 compliant iCalendar feeds that can be imported or subscribed to in popular calendar applications like:

- Google Calendar
- Apple Calendar (iOS/macOS)
- Microsoft Outlook
- Mozilla Thunderbird
- Any other RFC 5545 compatible calendar application

## URL Structure

### Basic ICS Feed
```
/your-calendar-page/ical
```

### With Category Filtering
```
/your-calendar-page/ical?categories[]=1&categories[]=2
```

### With Date Range Filtering
```
/your-calendar-page/ical?from=2024-01-01&to=2024-12-31
```

### Combined Filtering
```
/your-calendar-page/ical?categories[]=1&from=2024-06-01&to=2024-12-31
```

## User Interface Subscription

For end users, the easiest way to subscribe to a calendar is through the web interface:

### Step-by-Step Guide

1. **Navigate to any Calendar page** in your SilverStripe site
2. **Apply desired filters** using the filter panel:
   - Select specific categories
   - Set date ranges 
   - Add search terms
   - Choose event types
3. **Click the "Subscribe to Calendar" button** in the page header
4. **Review your current filters** shown in the modal
5. **Copy the generated subscription URL** using the copy button
6. **Follow the platform-specific instructions** in the modal for:
   - Google Calendar
   - Microsoft Outlook  
   - Apple Calendar

The subscription URL will automatically include all your current filter settings, so you only receive events that match your preferences.

## Features

### User Interface
- **Subscribe Button**: User-friendly subscription interface directly on Calendar pages
- **Real-time Filter Integration**: Subscription URLs automatically respect current page filter settings
- **Copy to Clipboard**: One-click copying of subscription URLs
- **Multi-Platform Instructions**: Built-in guides for Google Calendar, Outlook, and Apple Calendar

### Event Data Included
- **Title** (SUMMARY)
- **Description** (DESCRIPTION) - HTML tags automatically stripped
- **Location** (LOCATION)
- **Start/End Dates and Times** (DTSTART/DTEND)
- **All-day event support** 
- **Categories** (CATEGORIES)
- **Event URLs** (URL)
- **Unique identifiers** (UID)

### Event Types Supported
- **One-time events**
- **Recurring events** - Each instance appears as a separate event in the feed
- **All-day events**
- **Timed events** with proper timezone handling (UTC)
- **Modified recurring instances** - Handles exceptions and modifications

### Filtering Options
- **Category filtering** - Show only events from specific categories
- **Date range filtering** - Limit events to specific date ranges
- **Combined filtering** - Use both category and date filters together

## Usage Examples

### Subscribe in Google Calendar
1. In Google Calendar, click the "+" next to "Other calendars"
2. Select "From URL"
3. Enter your ICS feed URL: `https://yoursite.com/calendar/ical`
4. Click "Add calendar"

### Subscribe in Apple Calendar
1. In Calendar app, go to File > New Calendar Subscription (macOS) or Settings > Accounts > Add Account > Other > Add CalDAV Account (iOS)
2. Enter your ICS feed URL: `https://yoursite.com/calendar/ical`
3. Configure update frequency and other settings as desired

### Subscribe in Outlook
1. In Outlook, go to File > Account Settings > Internet Calendars
2. Click "New..." and enter your ICS feed URL
3. Choose update frequency and click "Add"

## Technical Details

### HTTP Headers
The ICS endpoint sets appropriate headers for calendar subscription:
```
Content-Type: text/calendar; charset=utf-8
Content-Disposition: attachment; filename="calendar.ics"
Cache-Control: no-cache, must-revalidate
```

### RFC 5545 Compliance
- Proper ICS format structure
- Correct date/time formatting
- Character escaping for special characters
- UTC timezone conversion for timed events
- Unique event identifiers

### Performance Considerations
- Uses the same efficient event loading as JSON feeds
- Leverages existing Carbon-based recurring event system
- Respects existing category and date filtering
- Handles large event datasets gracefully

## Developer Information

### Integration with Existing System
The ICS feed functionality:
- Reuses `Calendar::getEventsFeed()` method for consistency
- Maintains compatibility with existing JSON feeds
- Uses the same filtering parameters and logic
- Handles virtual recurring event instances properly

### Customization
The ICS generation can be extended by:
- Overriding the `ical()` method in a custom controller
- Extending the `transformEventToICS()` method for additional fields
- Modifying the `generateICSContent()` method for custom formatting

### Error Handling
- Malformed events are logged and skipped gracefully
- Invalid dates/times are handled with fallbacks
- Empty calendars return valid but empty ICS files

## Troubleshooting

### Common Issues

**ICS feed not updating in calendar app**
- Check the calendar app's refresh/sync settings
- Some apps cache feeds for several hours
- Try removing and re-adding the subscription

**Events showing wrong times**
- Ensure your SilverStripe site has correct timezone settings
- All-day events should appear correctly regardless of timezone
- Timed events are converted to UTC in the ICS feed

**Missing events in feed**
- Check date range filters in the URL
- Verify category filters if using category-specific feeds
- Ensure events are published and within the date range

**Calendar app won't accept the feed URL**
- Ensure the URL is publicly accessible
- Some apps require HTTPS URLs
- Check that the URL returns proper ICS content when accessed directly