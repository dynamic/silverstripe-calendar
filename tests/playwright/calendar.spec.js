import { test, expect } from '@playwright/test';

test.describe('Dynamic Calendar Module', () => {

  test.beforeEach(async ({ page }) => {
    // Navigate to the calendar page
    await page.goto('/calendar');
  });

  test('Calendar page loads and displays basic structure', async ({ page }) => {
    // Check page title
    await expect(page.locator('h1')).toContainText('Calendar');

    // Check filter form exists
    await expect(page.locator('form')).toBeVisible();
    await expect(page.locator('input[name="from"]')).toBeVisible();
    await expect(page.locator('input[name="to"]')).toBeVisible();

    // Check event statistics section exists
    await expect(page.locator('text=Event Statistics')).toBeVisible();
    await expect(page.locator('text=Total Events:')).toBeVisible();
    await expect(page.locator('text=Recurring Events:')).toBeVisible();
    await expect(page.locator('text=One-time Events:')).toBeVisible();
  });

  test('Events are displayed with proper information', async ({ page }) => {
    // Wait for events to load
    await page.waitForSelector('.event-card', { timeout: 10000 });

    // Check if at least one event is displayed
    const eventCards = page.locator('.event-card');
    await expect(eventCards.first()).toBeVisible();

    // Check event card structure
    const firstEvent = eventCards.first();
    await expect(firstEvent.locator('.card-title a')).toBeVisible();
    await expect(firstEvent.locator('.event-datetime')).toBeVisible();
    await expect(firstEvent.locator('text=View Details')).toBeVisible();
  });

  test('Recurring events are properly marked', async ({ page }) => {
    // Look for recurring event badges
    const recurringBadges = page.locator('.badge:has-text("Recurring")');

    if (await recurringBadges.count() > 0) {
      // If recurring events exist, verify they're marked correctly
      await expect(recurringBadges.first()).toBeVisible();
      await expect(recurringBadges.first()).toContainText('Recurring');

      // Check that the event card has the correct data attribute
      const recurringEventCard = page.locator('.event-card[data-is-recurring="true"]').first();
      await expect(recurringEventCard).toBeVisible();
    }
  });

  test('Date filtering works correctly', async ({ page }) => {
    // Get current date for filtering
    const today = new Date();
    const futureDate = new Date(today);
    futureDate.setDate(today.getDate() + 30);

    const fromDate = today.toISOString().split('T')[0];
    const toDate = futureDate.toISOString().split('T')[0];

    // Fill in date filters
    await page.fill('input[name="from"]', fromDate);
    await page.fill('input[name="to"]', toDate);

    // Submit the filter form
    await page.click('button[type="submit"]');

    // Wait for page to reload/update
    await page.waitForLoadState('networkidle');

    // Check that the form values are preserved
    await expect(page.locator('input[name="from"]')).toHaveValue(fromDate);
    await expect(page.locator('input[name="to"]')).toHaveValue(toDate);
  });

  test('Clear filters button works', async ({ page }) => {
    // Set some filter values first
    await page.fill('input[name="from"]', '2025-01-01');
    await page.fill('input[name="to"]', '2025-12-31');

    // Click clear filters
    await page.click('text=Clear Filters');

    // Wait for page to reload
    await page.waitForLoadState('networkidle');

    // Check that we're back to the clean calendar URL
    expect(page.url()).toMatch(/\/calendar\/?$/);
  });

  test('Event details page is accessible', async ({ page }) => {
    // Wait for events to load
    await page.waitForSelector('.event-card', { timeout: 10000 });

    const eventCards = page.locator('.event-card');
    if (await eventCards.count() > 0) {
      // Click on the first event's "View Details" link
      await eventCards.first().locator('text=View Details').click();

      // Wait for navigation
      await page.waitForLoadState('networkidle');

      // Check that we're on an event detail page
      expect(page.url()).toMatch(/\/calendar\/[^\/]+/);

      // Check basic event page structure
      await expect(page.locator('h1')).toBeVisible();
    }
  });

  test('Categories are displayed when present', async ({ page }) => {
    // Wait for events to load
    await page.waitForSelector('.event-card', { timeout: 10000 });

    // Look for category badges
    const categoryBadges = page.locator('.badge.bg-secondary');

    if (await categoryBadges.count() > 0) {
      // If categories exist, verify they're displayed
      await expect(categoryBadges.first()).toBeVisible();
    }
  });

  test('Responsive design - mobile viewport', async ({ page }) => {
    // Set mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });

    // Check that the page still loads properly
    await expect(page.locator('h1')).toBeVisible();
    await expect(page.locator('form')).toBeVisible();

    // Check that event cards are still displayed
    const eventCards = page.locator('.event-card');
    if (await eventCards.count() > 0) {
      await expect(eventCards.first()).toBeVisible();
    }
  });

  test('Time information is displayed correctly', async ({ page }) => {
    // Wait for events to load
    await page.waitForSelector('.event-card', { timeout: 10000 });

    const eventCards = page.locator('.event-card');
    if (await eventCards.count() > 0) {
      const firstEvent = eventCards.first();

      // Check for date display
      await expect(firstEvent.locator('.event-datetime')).toBeVisible();

      // Look for time information (if present)
      const timeInfo = firstEvent.locator('text=/\\d{1,2}:\\d{2}/');
      if (await timeInfo.count() > 0) {
        await expect(timeInfo.first()).toBeVisible();
      }
    }
  });

  test('Error handling - invalid date filters', async ({ page }) => {
    // Try to set an invalid date range (end before start)
    await page.fill('input[name="from"]', '2025-12-31');
    await page.fill('input[name="to"]', '2025-01-01');

    // Submit the form
    await page.click('button[type="submit"]');

    // The page should still load without crashing
    await page.waitForLoadState('networkidle');
    await expect(page.locator('h1')).toBeVisible();
  });
});
