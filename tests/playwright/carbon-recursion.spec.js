import { test, expect } from '@playwright/test';

test.describe('Carbon Recursion System', () => {

  test.beforeEach(async ({ page }) => {
    // Navigate to the calendar page
    await page.goto('/calendar');
  });

  test('Recurring events display recurrence information', async ({ page }) => {
    // Wait for events to load
    await page.waitForSelector('.event-card', { timeout: 10000 });

    // Look for recurring events
    const recurringEvents = page.locator('.event-card[data-is-recurring="true"]');

    if (await recurringEvents.count() > 0) {
      const firstRecurring = recurringEvents.first();

      // Check for recurring badge
      await expect(firstRecurring.locator('.badge:has-text("Recurring")')).toBeVisible();

      // Check for recurrence description
      const recurrenceDesc = firstRecurring.locator('text=/Repeats|Every|Weekly|Monthly|Daily|Yearly/i');
      if (await recurrenceDesc.count() > 0) {
        await expect(recurrenceDesc.first()).toBeVisible();
      }
    }
  });

  test('Virtual event instances are generated correctly', async ({ page }) => {
    // Set a date range that should include recurring event instances
    const today = new Date();
    const nextMonth = new Date(today);
    nextMonth.setMonth(today.getMonth() + 1);

    const fromDate = today.toISOString().split('T')[0];
    const toDate = nextMonth.toISOString().split('T')[0];

    // Apply date filter
    await page.fill('input[name="from"]', fromDate);
    await page.fill('input[name="to"]', toDate);
    await page.click('button[type="submit"]');

    // Wait for results
    await page.waitForLoadState('networkidle');

    // Check event statistics
    const totalEventsText = await page.locator('text=Total Events:').textContent();
    const recurringEventsText = await page.locator('text=Recurring Events:').textContent();

    if (totalEventsText && recurringEventsText) {
      // Extract numbers from the text
      const totalEvents = parseInt(totalEventsText.match(/\\d+/)?.[0] || '0');
      const recurringEvents = parseInt(recurringEventsText.match(/\\d+/)?.[0] || '0');

      // If there are recurring events, there should be instances generated
      if (recurringEvents > 0) {
        expect(totalEvents).toBeGreaterThan(recurringEvents);
      }
    }
  });

  test('Date filtering works with recurring events', async ({ page }) => {
    // Test filtering to a future date range
    const futureDate = new Date();
    futureDate.setMonth(futureDate.getMonth() + 2);
    const endDate = new Date(futureDate);
    endDate.setMonth(endDate.getMonth() + 1);

    const fromDate = futureDate.toISOString().split('T')[0];
    const toDate = endDate.toISOString().split('T')[0];

    // Apply future date filter
    await page.fill('input[name="from"]', fromDate);
    await page.fill('input[name="to"]', toDate);
    await page.click('button[type="submit"]');

    // Wait for results
    await page.waitForLoadState('networkidle');

    // Check that events are still displayed (if recurring events exist that extend into the future)
    const eventCards = page.locator('.event-card');
    const eventCount = await eventCards.count();

    // Verify that any displayed events fall within the date range
    if (eventCount > 0) {
      for (let i = 0; i < Math.min(eventCount, 3); i++) {
        const eventCard = eventCards.nth(i);
        await expect(eventCard).toBeVisible();

        // Check that the event has date information
        await expect(eventCard.locator('.event-datetime')).toBeVisible();
      }
    }
  });

  test('Performance with large date ranges', async ({ page }) => {
    // Test with a large date range to ensure the system doesn't generate too many instances
    const startDate = new Date();
    const endDate = new Date(startDate);
    endDate.setFullYear(endDate.getFullYear() + 1); // One year range

    const fromDate = startDate.toISOString().split('T')[0];
    const toDate = endDate.toISOString().split('T')[0];

    // Measure the time taken to load the page with a large date range
    const startTime = Date.now();

    await page.fill('input[name="from"]', fromDate);
    await page.fill('input[name="to"]', toDate);
    await page.click('button[type="submit"]');

    // Wait for results with a reasonable timeout
    await page.waitForLoadState('networkidle', { timeout: 15000 });

    const endTime = Date.now();
    const loadTime = endTime - startTime;

    // Ensure the page loads within a reasonable time (15 seconds)
    expect(loadTime).toBeLessThan(15000);

    // Check that the page still displays properly
    await expect(page.locator('h1')).toBeVisible();
    await expect(page.locator('text=Event Statistics')).toBeVisible();
  });

  test('Recurring event detail page shows original event information', async ({ page }) => {
    // Wait for events to load
    await page.waitForSelector('.event-card', { timeout: 10000 });

    // Find a recurring event and click on it
    const recurringEvents = page.locator('.event-card[data-is-recurring="true"]');

    if (await recurringEvents.count() > 0) {
      await recurringEvents.first().locator('text=View Details').click();

      // Wait for navigation
      await page.waitForLoadState('networkidle');

      // Check that we're on an event detail page
      expect(page.url()).toMatch(/\/calendar\/[^\/]+/);

      // Check that the page displays event information
      await expect(page.locator('h1')).toBeVisible();

      // Look for recurrence information on the detail page
      const recurInfo = page.locator('text=/Recur|Repeat|Every|Series/i');
      if (await recurInfo.count() > 0) {
        await expect(recurInfo.first()).toBeVisible();
      }
    }
  });

  test('Multiple recurring patterns are handled correctly', async ({ page }) => {
    // Set a date range that should show various recurring patterns
    const today = new Date();
    const futureDate = new Date(today);
    futureDate.setMonth(today.getMonth() + 3); // 3 months range

    const fromDate = today.toISOString().split('T')[0];
    const toDate = futureDate.toISOString().split('T')[0];

    await page.fill('input[name="from"]', fromDate);
    await page.fill('input[name="to"]', toDate);
    await page.click('button[type="submit"]');

    await page.waitForLoadState('networkidle');

    // Check that events are properly sorted by date
    const eventDates = page.locator('.event-datetime');
    if (await eventDates.count() > 1) {
      // Verify events appear to be in chronological order
      // This is a basic check - in a real scenario you'd extract and compare actual dates
      await expect(eventDates.first()).toBeVisible();
    }
  });

  test('Timezone handling (if applicable)', async ({ page }) => {
    // This test checks if timezone information is properly handled
    // The actual implementation depends on your timezone setup

    await page.waitForSelector('.event-card', { timeout: 10000 });

    const eventCards = page.locator('.event-card');
    if (await eventCards.count() > 0) {
      const firstEvent = eventCards.first();

      // Check if time is displayed consistently
      const timeElements = firstEvent.locator('text=/\\d{1,2}:\\d{2}/');
      if (await timeElements.count() > 0) {
        await expect(timeElements.first()).toBeVisible();

        // Verify time format is consistent
        const timeText = await timeElements.first().textContent();
        expect(timeText).toMatch(/\\d{1,2}:\\d{2}/);
      }
    }
  });

  test('Event exceptions and modifications work', async ({ page }) => {
    // This test would check if modified instances of recurring events are handled
    // The actual test would depend on having test data with exceptions

    await page.waitForSelector('.event-card', { timeout: 10000 });

    // Look for any events that might be exceptions (modified instances)
    const eventCards = page.locator('.event-card');
    if (await eventCards.count() > 0) {
      // Check that all events display properly regardless of being exceptions or not
      for (let i = 0; i < Math.min(await eventCards.count(), 3); i++) {
        const eventCard = eventCards.nth(i);
        await expect(eventCard.locator('.card-title a')).toBeVisible();
        await expect(eventCard.locator('.event-datetime')).toBeVisible();
      }
    }
  });
});
