// All-day date ranges in the event modal - issue #187.
//
// The events feed used to emit an all-day event's `end` as `EndDate` unchanged. FullCalendar
// reads that value as EXCLUSIVE (the same convention RFC 5545 uses for DTEND), so issue #187
// made the server emit `EndDate + 1 day`, which is also what the ICS export already did.
//
// That fixes the grid, but showEventModal() renders the date line itself: formatEventDate()
// printed `event.end` verbatim, so once the feed carries the exclusive day the modal would
// name a last day that the event never covers, and every single-day all-day event would grow
// a two-date range. These specs pin the display side of the same convention: the modal shows
// the last covered day, which is the day before the exclusive end.
//
// Same harness as event-modal-xss.spec.js (no JS unit runner in this module, so the specs run
// in a browser against the real client/src module, served from an ephemeral loopback port by
// the spec itself). Dates are passed as local midnight strings ('YYYY-MM-DDT00:00:00') because
// a bare 'YYYY-MM-DD' is parsed as UTC midnight and would shift a day in a negative-offset
// timezone - the assertions then depend on the machine's clock. FullCalendar hands the
// component local-midnight Date objects for all-day events, which is what these mimic. The
// expected strings are built in-page from toLocaleDateString() so the spec is locale-neutral.
//
// Bundle caveat: client/dist/js/calendar.bundle.js does not export FullCalendarView, so the
// built artifact itself is not driven here - keeping it in sync is tracked as #322.

const { test, expect } = require('@playwright/test');
const fs = require('fs');
const http = require('http');
const path = require('path');

const COMPONENTS = path.resolve(__dirname, '..', '..', 'client', 'src', 'js', 'components');

const FIXTURE = `<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Event date range fixture</title></head>
<body>
  <script type="module">
    import { FullCalendarView } from '/FullCalendarView.js';
    window.__FullCalendarViewForTest = FullCalendarView;

    // FullCalendar's own convention: an all-day date is local midnight, not UTC midnight.
    window.__localDate = function (ymd) {
      return ymd + 'T00:00:00';
    };

    // Returns the rendered range alongside the labels the page itself would expect, so the
    // assertion compares like with like in whatever locale the browser uses.
    window.__renderRange = function (startYmd, endYmd, allDay) {
      const view = new FullCalendarView(document.createElement('div'));
      const start = new Date(window.__localDate(startYmd));
      const end = endYmd === null ? null : new Date(window.__localDate(endYmd));
      const formatted = view.formatEventDate({
        title: 'Range',
        url: 'https://example.com/events/range',
        start: start,
        end: end,
        allDay: allDay,
        extendedProps: {}
      });
      const labels = {
        formatted: formatted,
        startLabel: start.toLocaleDateString(),
        endLabel: end ? end.toLocaleDateString() : null
      };
      if (end) {
        const dayBefore = new Date(end.getTime());
        dayBefore.setDate(dayBefore.getDate() - 1);
        labels.dayBeforeEndLabel = dayBefore.toLocaleDateString();
      }
      return labels;
    };

    window.__ready = true;
  </script>
</body>
</html>`;

// The component imports './rollingListWeekView' with no extension - valid for webpack, not
// for the browser. Adding .js on the server side keeps the fixture honest about which module
// is under test instead of shadowing it with a copy.
function startServer()
{
    const server = http.createServer((req, res) => {
        const url = req.url.split('?')[0];
        let file = path.join(COMPONENTS, path.normalize(url));
        if (!file.startsWith(COMPONENTS)) {
            res.writeHead(403);
            res.end('forbidden');
            return;
        }
        if (!fs.existsSync(file) && fs.existsSync(file + '.js')) {
            file += '.js';
        }
        if (url === '/' || url === '/index.html') {
            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(FIXTURE);
            return;
        }
        if (!fs.existsSync(file) || !fs.statSync(file).isFile()) {
            res.writeHead(404);
            res.end('not found');
            return;
        }
        res.writeHead(200, { 'Content-Type': 'text/javascript; charset=utf-8' });
        res.end(fs.readFileSync(file, 'utf8'));
    });
    return new Promise((resolve) => {
        server.listen(0, '127.0.0.1', () => {
            resolve({ server, port: server.address().port });
        });
    });
}

let server;
let baseUrl;

test.beforeAll(async () => {
    const started = await startServer();
    server = started.server;
    baseUrl = `http://127.0.0.1:${started.port}/`;
});

test.afterAll(async () => {
    if (server) {
        await new Promise((resolve) => server.close(resolve));
    }
});

async function openPage(page)
{
    await page.goto(baseUrl);
    await page.waitForFunction(() => window.__ready === true, null, { timeout: 10000 });
}

test('a multi-day all-day event names its last covered day, not the exclusive end', async ({ page }) => {
    await openPage(page);

    // Stored 2027-03-03 to 2027-03-05; the feed emits the exclusive end 2027-03-06.
    const result = await page.evaluate(() => window.__renderRange('2027-03-03', '2027-03-06', true));

    expect(result.formatted).toBe(`${result.startLabel} - ${result.dayBeforeEndLabel}`);
    expect(result.formatted).not.toContain(result.endLabel);
});

test('a single-day all-day event shows one date, not a two-date range', async ({ page }) => {
    await openPage(page);

    // Stored 2027-03-04 with no EndDate; EndDate defaults to StartDate and the feed adds the
    // day, so the component receives end = start + 1 day.
    const result = await page.evaluate(() => window.__renderRange('2027-03-04', '2027-03-05', true));

    expect(result.formatted).toBe(result.startLabel);
    expect(result.formatted).not.toContain(' - ');
});

test('an all-day event whose end equals its start still shows one date', async ({ page }) => {
    await openPage(page);

    // The pre-#187 shape, and the shape of any feed that omits nothing: end == start must not
    // collapse to the day before.
    const result = await page.evaluate(() => window.__renderRange('2027-03-04', '2027-03-04', true));

    expect(result.formatted).toBe(result.startLabel);
});

test('an all-day event with no end at all shows one date', async ({ page }) => {
    await openPage(page);

    const result = await page.evaluate(() => window.__renderRange('2027-03-04', null, true));

    expect(result.formatted).toBe(result.startLabel);
    expect(result.formatted).not.toContain(' - ');
});

test('a timed event keeps its exact end time', async ({ page }) => {
    await openPage(page);

    // A timed event's end is an instant, not an exclusive date: #187 changed nothing on that
    // path, and the formatter must not shave an hour off the displayed range.
    const timed = await page.evaluate(() => {
      const start = new Date('2027-05-05T09:15:00');
      const end = new Date('2027-05-05T10:15:00');
      const el = document.createElement('div');
      const view = new window.__FullCalendarViewForTest(el);
      return {
        formatted: view.formatEventDate({
          title: 'Standup',
          url: 'https://example.com/events/standup',
          start: start,
          end: end,
          allDay: false,
          extendedProps: {}
        }),
        dateLabel: start.toLocaleDateString(),
        startTime: start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        endTime: end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
      };
    });

    expect(timed.formatted).toBe(`${timed.dateLabel} ${timed.startTime} - ${timed.endTime}`);
});
