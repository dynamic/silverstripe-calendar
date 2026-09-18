// Active-filter badge regression specs - issue #159.
//
// updateActiveFiltersBadge() decided "was this field already counted?" with
// formData.get(fieldName)?.trim() !== ''. A control that contributes nothing to the initial
// FormData snapshot - an untouched <select multiple>, an unchecked checkbox - returns null
// from get(), so the comparison became undefined !== '', i.e. permanently true. Selecting the
// first category therefore never incremented the badge, and clearing such a field decremented
// a tally it had never contributed to.
//
// Why a browser spec and not a unit test: this module has no JS unit runner (no jest/vitest,
// nothing in devDependencies that can give a DOM to a module that reads document). The page
// below is served from an ephemeral loopback port by the spec itself - no SilverStripe
// runtime, no external network - and imports the real client/src module, so the assertions
// run against the shipped code path rather than a transcription of its arithmetic.
//
// Manual-only today: .github/workflows/ci.yml passes js: false to silverstripe/gha-ci, so no
// job runs 'npm test'. Until that gate is opened (tracked as #174) these specs protect the fix
// only when someone runs 'npx playwright test' locally.
//
// Only the 'change' listener is driven, deliberately: the sibling 'input' listener shares one
// module-level debounce timer across every field, which is tracked separately as #175.

const { test, expect } = require('@playwright/test');
const fs = require('fs');
const http = require('http');
const path = require('path');

const SOURCE = path.resolve(__dirname, '..', '..', 'client', 'src', 'js', 'components', 'FilterEnhancements.js');

// Mirrors the shape CalendarFilterForm.ss and Calendar.ss render, minus Choices.js: the
// badge lives on the toggle button, and SecurityID / action_doFilter are not filters. In
// production the categories control is wrapped by Choices.js (#176 covers its name having no
// [] suffix), which does not change what FormData sees for this fixture.
const FIXTURE = `<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Active filter badge fixture</title></head>
<body>
  <button type="button" class="js-toggle-filters" aria-expanded="false">Filter Events</button>
  <form class="calendar-filter-form" aria-label="Filter calendar events">
    <input type="hidden" name="SecurityID" value="8a1c0f4b">
    <input type="text" name="search" value="">
    <select name="eventType">
      <option value="">(All)</option>
      <option value="course">Course</option>
      <option value="event">Event</option>
    </select>
    <select name="categories" multiple size="3">
      <option value="arts">Arts</option>
      <option value="family">Family</option>
      <option value="music">Music</option>
    </select>
    <button type="submit" name="action_doFilter" value="1">Filter</button>
  </form>
  <script type="module" src="/FilterEnhancements.js"></script>
</body>
</html>`;

let server = null;
let origin = '';

test.beforeAll(async () => {
  server = http.createServer((request, response) => {
    if (request.url && request.url.split('?')[0] === '/FilterEnhancements.js') {
      try {
        response.writeHead(200, { 'Content-Type': 'text/javascript; charset=utf-8' });
        response.end(fs.readFileSync(SOURCE));
      } catch (error) {
        // A moved or renamed module must read as a failed assertion, not a dead server.
        response.writeHead(500, { 'Content-Type': 'text/plain' });
        response.end(`/* ${SOURCE} unreadable: ${error.message} */`);
      }
      return;
    }
    if (request.url && request.url.split('?')[0] !== '/') {
      response.writeHead(404, { 'Content-Type': 'text/plain' });
      response.end('not found');
      return;
    }
    response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    response.end(FIXTURE);
  });
  await new Promise((resolve, reject) => {
    server.once('error', reject);
    server.listen(0, '127.0.0.1', resolve);
  });
  origin = `http://127.0.0.1:${server.address().port}`;
});

test.afterAll(async () => {
  // If beforeAll never bound a socket, its own error is the one worth seeing.
  if (!server) {
    return;
  }
  const pending = server;
  server = null;
  await new Promise((resolve) => pending.close(resolve));
});

// Selects options and fires one change event, so the badge is driven by exactly one event per
// interaction instead of the change+input pair a real click produces.
async function setSelection(page, selector, values)
{
  await page.locator(selector).evaluate((element, selected) => {
    Array.from(element.options).forEach((option) => {
      option.selected = selected.includes(option.value);
    });
    element.dispatchEvent(new Event('change', { bubbles: true }));
  }, values);
}

async function dispatchChange(page, selector, value)
{
  await page.locator(selector).evaluate((element, next) => {
    element.value = next;
    element.dispatchEvent(new Event('change', { bubbles: true }));
  }, value);
}

const badge = (page) => page.locator('.js-toggle-filters .badge');

test('selecting the first category counts the filter it is the first of', async ({ page }) => {
  await page.goto(`${origin}/`);
  // An untouched multi-select is absent from the snapshot, so nothing is active yet.
  await expect(badge(page)).toHaveCount(0);

  await setSelection(page, 'select[name="categories"]', ['arts']);

  await expect(badge(page)).toHaveText('1');
});

test('clearing a never-counted field cannot drive the tally negative', async ({ page }) => {
  await page.goto(`${origin}/`);

  // Deselecting every option of a multi-select that was already empty used to take the tally
  // to -1, and because the badge is rendered only for a count above zero the next real filter
  // then landed on 0 and stayed invisible.
  await setSelection(page, 'select[name="categories"]', []);
  await expect(badge(page)).toHaveCount(0);

  await setSelection(page, 'select[name="categories"]', ['music']);

  await expect(badge(page)).toHaveText('1');
});

test('re-pointing an already-counted filter does not double count it', async ({ page }) => {
  await page.goto(`${origin}/`);

  await setSelection(page, 'select[name="eventType"]', ['course']);
  await expect(badge(page)).toHaveText('1');

  await setSelection(page, 'select[name="eventType"]', ['event']);

  await expect(badge(page)).toHaveText('1');
});

test('clearing a counted filter removes the badge again', async ({ page }) => {
  await page.goto(`${origin}/`);

  await setSelection(page, 'select[name="eventType"]', ['event']);
  await expect(badge(page)).toHaveText('1');

  await setSelection(page, 'select[name="eventType"]', ['']);

  await expect(badge(page)).toHaveCount(0);
});

test('non-filter fields stay out of the tally', async ({ page }) => {
  await page.goto(`${origin}/`);

  await dispatchChange(page, 'input[name="SecurityID"]', 'rotated-token');
  await expect(badge(page)).toHaveCount(0);

  await dispatchChange(page, 'input[name="search"]', '   ');
  await expect(badge(page)).toHaveCount(0);

  await dispatchChange(page, 'input[name="search"]', 'workshop');
  await expect(badge(page)).toHaveText('1');
});
