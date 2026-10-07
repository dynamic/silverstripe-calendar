// Event modal escaping regression specs - issue #269.
//
// showEventModal() and renderEventDetails() built their markup with template literals and
// pushed the result through insertAdjacentHTML. The events feed JSON-encodes the CMS values
// but never HTML-escapes them, so an event titled `<img src=x onerror=console.log(1)>` was
// emitted verbatim and executed the moment a visitor clicked the event. The same applied to
// url, location, category and description.
//
// Why a browser spec and not a unit test: this module has no JS unit runner (no jest/vitest,
// nothing in devDependencies that can give a DOM to a module that touches document). The page
// is served from an ephemeral loopback port by the spec itself - no SilverStripe runtime, no
// external network. The specs import the real client/src module.
//
// Two stubs are load-bearing:
// - window.bootstrap.Modal: the component calls `new bootstrap.Modal(...)` off the global
//   that the theme provides. The stub records show() instead of animating, so the specs
//   assert on the markup that was inserted rather than on Bootstrap's transition.
// - the dynamic `import('@fullcalendar/core')` inside init() rejects on a bare specifier in
//   the browser; init() catches it and console.error's, which is why these specs drive
//   showEventModal() directly and never assert an absence of console errors.
//
// Bundle caveat: calendar.bundle.js does not export FullCalendarView, so the bundle artifact
// itself is not driven here - keeping it in sync with this source is tracked as #322.

const { test, expect } = require('@playwright/test');
const fs = require('fs');
const http = require('http');
const path = require('path');

const COMPONENTS = path.resolve(__dirname, '..', '..', 'client', 'src', 'js', 'components');

const FIXTURE = `<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Event modal fixture</title></head>
<body>
  <script>
    window.__pwned = false;
    window.__modalShown = 0;
    window.bootstrap = { Modal: class {
      constructor(element) { this.element = element; }
      show() { window.__modalShown += 1; }
    } };
  </script>
  <script type="module">
    import { FullCalendarView } from '/FullCalendarView.js';
    window.__openModal = function (event) {
      const view = new FullCalendarView(document.createElement('div'));
      view.showEventModal(event);
      return true;
    };
    window.__details = function (event) {
      const view = new FullCalendarView(document.createElement('div'));
      return view.renderEventDetails(event);
    };
    window.__strip = function (value) {
      return FullCalendarView.prototype.stripHtml.call(null, value);
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

// A minimal stand-in for the FullCalendar Event object the component reads: title, url,
// start/end/allDay and extendedProps.
function makeEvent(overrides = {})
{
    return Object.assign({
        title: 'Sunday Service',
        url: 'https://example.com/events/sunday-service',
        start: '2026-03-01T10:00:00',
        end: '2026-03-01T11:00:00',
        allDay: false,
        extendedProps: {}
    }, overrides);
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

test('a hostile event title renders as literal text and runs no script', async ({ page }) => {
    const payload = '<img src=x onerror="window.__pwned = true">';
    await openPage(page);

    const shown = await page.evaluate((event) => window.__openModal(event), makeEvent({ title: payload }));
    expect(shown).toBe(true);

    await expect(page.locator('#eventModalLabel')).toHaveText(payload);
    await expect(page.locator('#eventModal .modal-header img')).toHaveCount(0);
    await expect(page.evaluate(() => window.__pwned)).resolves.toBe(false);
    // The stub proves the modal path itself ran, so a passing escape cannot be an accident
    // of showEventModal() having bailed out early.
    await expect(page.evaluate(() => window.__modalShown)).resolves.toBe(1);
});

test('hostile location, category and description render as literal text', async ({ page }) => {
    await openPage(page);

    await page.evaluate((event) => window.__openModal(event), makeEvent({
        extendedProps: {
            location: '<img src=x onerror="window.__pwned = true">',
            category: '<svg onload="window.__pwned = true">',
            description: '</div><img src=x onerror="window.__pwned = true">'
        }
    }));

    await expect(page.locator('#eventModal .event-details')).toContainText('Location:');
    await expect(page.locator('#eventModal .event-details img, #eventModal .event-details svg')).toHaveCount(0);
    await expect(page.locator('#eventModal .event-details')).toContainText('<img src=x onerror="window.__pwned = true">');
    await expect(page.locator('#eventModal .event-details')).toContainText('<svg onload="window.__pwned = true">');
    await expect(page.evaluate(() => window.__pwned)).resolves.toBe(false);
});

test('a quote in the url cannot break out of the href attribute', async ({ page }) => {
    const payload = '"><img src=x onerror="window.__pwned = true">';
    await openPage(page);

    await page.evaluate((event) => window.__openModal(event), makeEvent({ url: payload }));

    const anchors = page.locator('#eventModal .modal-footer a');
    await expect(anchors).toHaveCount(1);
    await expect(anchors).toHaveAttribute('href', payload);
    await expect(page.locator('#eventModal .modal-footer img')).toHaveCount(0);
    await expect(page.evaluate(() => window.__pwned)).resolves.toBe(false);
});

test('a null title becomes an empty label instead of the literal string null', async ({ page }) => {
    await openPage(page);

    await page.evaluate((event) => window.__openModal(event), makeEvent({ title: null, url: null }));

    await expect(page.locator('#eventModalLabel')).toHaveText('');
    await expect(page.locator('#eventModal .modal-footer a')).toHaveAttribute('href', '');
});

test('renderEventDetails escapes ampersands and quotes without doubling them', async ({ page }) => {
    await openPage(page);

    const html = await page.evaluate((event) => window.__details(event), makeEvent({
        extendedProps: {
            location: 'Tom & Jerry\'s "Hall"',
            description: '100% & rising'
        }
    }));

    expect(html).toContain('Tom &amp; Jerry&#39;s &quot;Hall&quot;');
    expect(html).toContain('100% &amp; rising');
    expect(html).not.toContain('&amp;amp;');
    // The date line is escaped on the way out too, and a locale date string contains no
    // markup, so it must survive unchanged rather than being mangled.
    expect(html).toContain('<p><strong>Date:</strong> ');
});

test('a benign event still renders title, details and link', async ({ page }) => {
    await openPage(page);

    await page.evaluate((event) => window.__openModal(event), makeEvent({
        title: 'Community Lunch',
        url: 'https://example.com/events/lunch',
        extendedProps: {
            location: 'Parish Hall',
            category: 'outreach',
            description: 'Bring a plate.'
        }
    }));

    await expect(page.locator('#eventModalLabel')).toHaveText('Community Lunch');
    await expect(page.locator('#eventModal .modal-footer a')).toHaveAttribute(
        'href',
        'https://example.com/events/lunch'
    );
    await expect(page.locator('#eventModal .event-details')).toContainText('Location: Parish Hall');
    await expect(page.locator('#eventModal .event-details')).toContainText('Category: outreach');
    await expect(page.locator('#eventModal .event-details')).toContainText('Bring a plate.');
});

test('stripHtml flattens a hostile description without loading it', async ({ page }) => {
    await openPage(page);

    const text = await page.evaluate(
        (value) => window.__strip(value),
        '<img src=x onerror="window.__pwned = true">Choir practice'
    );

    await expect(page.evaluate(() => window.__pwned)).resolves.toBe(false);
    expect(text).toBe('Choir practice');
});

test('stripHtml still flattens markup and decodes entities the way the tooltip needs', async ({ page }) => {
    await openPage(page);

    const text = await page.evaluate(
        (value) => window.__strip(value),
        '<p>Tom &amp; Jerry <b>night</b></p>'
    );

    expect(text).toBe('Tom & Jerry night');
});
