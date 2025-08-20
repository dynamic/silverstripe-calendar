# ICS Feed Manual Testing Guide

This guide provides instructions for manually testing the ICS feed functionality in external calendar applications.

## Test URLs

Once your calendar is set up, you can test the ICS feed using these URL patterns:

### Basic ICS Feed
```
https://yoursite.com/your-calendar-page/ical
```

### With Category Filtering (replace with actual category IDs)
```
https://yoursite.com/your-calendar-page/ical?categories[]=1
https://yoursite.com/your-calendar-page/ical?categories[]=1&categories[]=2
```

### With Date Range Filtering
```
https://yoursite.com/your-calendar-page/ical?from=2024-01-01&to=2024-12-31
```

## Manual Testing Steps

### 1. Direct Browser Test
1. Open the ICS URL in your browser
2. Verify you get a download prompt or see ICS content
3. Check that the response headers are correct:
   - `Content-Type: text/calendar; charset=utf-8`
   - `Content-Disposition: attachment; filename="calendar.ics"`

### 2. ICS Content Validation
The downloaded ICS file should contain:
```
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Dynamic SilverStripe Calendar//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH
BEGIN:VEVENT
UID:1@yoursite.com
DTSTAMP:20240820T123456Z
SUMMARY:Event Title
DESCRIPTION:Event description without HTML tags
LOCATION:Event Location
DTSTART:20240821T140000Z
DTEND:20240821T160000Z
CATEGORIES:Category1,Category2
URL:https://yoursite.com/event-page
END:VEVENT
END:VCALENDAR
```

### 3. Google Calendar Subscription Test
1. Go to Google Calendar
2. Click the "+" next to "Other calendars"
3. Select "From URL"
4. Enter your ICS feed URL
5. Click "Add calendar"
6. Verify events appear in your calendar

### 4. Apple Calendar Subscription Test (macOS)
1. Open Calendar app
2. Go to File > New Calendar Subscription
3. Enter your ICS feed URL
4. Configure refresh settings (recommend 15 minutes for testing)
5. Click "OK"
6. Verify events appear in the sidebar calendar list

### 5. Apple Calendar Subscription Test (iOS)
1. Open Settings app
2. Go to Accounts & Passwords
3. Add Account > Other > Add CalDAV Account
4. Enter your ICS feed URL in the Server field
5. Configure other settings as needed
6. Save and verify events sync

### 6. Microsoft Outlook Test
1. In Outlook, go to File > Account Settings > Internet Calendars
2. Click "New..."
3. Enter your ICS feed URL
4. Set update frequency
5. Click "Add"
6. Verify events appear in Outlook

## Testing Checklist

### ✅ Basic Functionality
- [ ] ICS URL is accessible
- [ ] Correct HTTP headers are set
- [ ] Valid ICS structure (BEGIN/END VCALENDAR)
- [ ] Events are included (BEGIN/END VEVENT)

### ✅ Event Data Accuracy
- [ ] Event titles appear correctly (SUMMARY)
- [ ] Event descriptions appear without HTML (DESCRIPTION)
- [ ] Event locations are included (LOCATION)
- [ ] Start/end dates and times are correct (DTSTART/DTEND)
- [ ] All-day events use correct date format (VALUE=DATE)
- [ ] Timed events use UTC format
- [ ] Categories are included (CATEGORIES)
- [ ] Event URLs work (URL)

### ✅ Filtering Functionality
- [ ] Category filtering works with `?categories[]=1`
- [ ] Date range filtering works with `?from=YYYY-MM-DD&to=YYYY-MM-DD`
- [ ] Combined filtering works
- [ ] Empty results return valid (but empty) ICS

### ✅ External Calendar Integration
- [ ] Google Calendar can subscribe successfully
- [ ] Apple Calendar can subscribe successfully
- [ ] Microsoft Outlook can import successfully
- [ ] Events display correctly in external calendars
- [ ] Event times are correct in different timezones

### ✅ Recurring Events
- [ ] Recurring events appear as separate instances
- [ ] Modified recurring instances show correct data
- [ ] Deleted recurring instances are excluded

### ✅ Performance & Edge Cases
- [ ] Large number of events loads reasonably fast
- [ ] Empty calendars return valid ICS
- [ ] Invalid date parameters are handled gracefully
- [ ] Special characters in event data are escaped properly

## Common Issues & Solutions

### Issue: Calendar app can't find the feed
**Solution:** Ensure the URL is publicly accessible and returns proper ICS content when accessed directly.

### Issue: Events show wrong times
**Solution:** Check your SilverStripe timezone settings. Timed events are converted to UTC in the ICS feed.

### Issue: Some events are missing
**Solution:** Check if date range or category filters are applied to the URL.

### Issue: Calendar app won't update
**Solution:** Check the refresh/sync settings in the calendar application. Some apps cache feeds for hours.

### Issue: Special characters appear incorrectly
**Solution:** Verify the ICS escaping is working correctly. Characters like semicolons, commas, and newlines should be escaped.

## Automated Testing

You can also use online ICS validators:
- [iCalendar Validator](https://icalendar.org/validator.html)
- Upload or paste your ICS content to verify RFC 5545 compliance

## Browser Developer Tools Testing

1. Open browser developer tools (F12)
2. Navigate to the Network tab
3. Visit your ICS URL
4. Check the response headers and content
5. Verify the response is valid ICS format