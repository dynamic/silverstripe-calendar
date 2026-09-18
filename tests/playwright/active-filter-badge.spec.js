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
//
// Also known, unfixed, and outside what these specs can see: the initial tally iterates
// formData.entries(), so a <select multiple> that arrives with two options pre-selected counts
// as two filters, and formData.set() later hands back only one of them - leaving the badge
// stuck at 1 on a form with nothing applied. Tracked as #228; this fixture's categories control
// starts with nothing selected, so it never reaches that state.

const { test, expect } = require('@playwright/test');
const fs = require('fs');
const http = require('http');
const path = require('path');

const SOURCE = path.resolve(__dirname, '..', '..', 'client', 'src', 'js', 'components', 'FilterEnhancements.js');

// Mirrors the shape CalendarFilterForm.ss and Calendar.ss render: the badge lives on the
// toggle button, and SecurityID / action_doFilter are not filters. Choices.js itself is not
// loaded here - the search_terms input below is hand-written to stand in for the clone
// Choices injects over the categories control (see the comment on that input), which is all
// the badge's code path can observe of it. #176 covers the real select having no [] suffix.
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
    <!-- Stands in for the search box Choices.js injects INSIDE this form when it clones the
         categories control: choices.js 10.2.0 templates.input gives that clone name=search_terms
         and searchEnabled is on for .js-choice (CalendarFilterForm.php), so its input/change
         events reach the form's delegated listeners like any real field's. Choices then empties
         it through Input.prototype.clear, which assigns value directly and dispatches nothing -
         so a tally that counted those keystrokes never gives the +1 back. -->
    <input type="search" name="search_terms" class="choices__input" value="">
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
      let body;
      try {
        body = fs.readFileSync(SOURCE);
      } catch (error) {
        // A moved or renamed module must read as a failed assertion, not a dead server.
        // Headers are written only after the read: calling writeHead twice is
        // ERR_HTTP_HEADERS_SENT, which escapes this listener uncaught and kills the worker.
        response.writeHead(500, { 'Content-Type': 'text/plain' });
        response.end(`/* ${SOURCE} unreadable: ${error.message} */`);
        return;
      }
      response.writeHead(200, { 'Content-Type': 'text/javascript; charset=utf-8' });
      response.end(body);
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
  // Once bound, beforeAll can no longer answer a server error, so swap the settling listener
  // for one that surfaces it instead of leaving a settled reject to swallow it.
  server.removeAllListeners('error');
  server.on('error', (error) => {
    console.error(`filter-badge fixture server error after bind: ${error.message}`);
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
  // keep-alive sockets would otherwise hold close() open on Node < 19.
  if (typeof pending.closeAllConnections === 'function') {
    pending.closeAllConnections();
  }
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

// The #159 coalesce made every key absent from the snapshot read as newly active - correct for
// the untouched multi-select, wrong for the search_terms clone, which is absent from the
// snapshot too and is never emptied by an event of its own. So this spec passes on the pre-fix
// source (where an absent key was inert) and only goes red if the guard is dropped from the
// fix; it is a guard against this PR's own fix, not against the original bug.
test('the search box Choices injects inside the form never counts as a filter', async ({ page }) => {
  await page.goto(`${origin}/`);

  await dispatchChange(page, 'input[name="search_terms"]', 'music');
  await expect(badge(page)).toHaveCount(0);

  await dispatchChange(page, 'input[name="search_terms"]', 'music festival');
  await expect(badge(page)).toHaveCount(0);

  // The real filter behind that search box still counts, exactly once.
  await setSelection(page, 'select[name="categories"]', ['music']);

  await expect(badge(page)).toHaveText('1');
});
