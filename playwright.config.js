const { defineConfig } = require('@playwright/test');

// Playwright configuration for this module's browser specs.
//
// Pinning testDir is the point of this file. With no config, Playwright roots discovery at
// the current working directory and hardcodes only a node_modules skip, so a *.spec.js
// shipped by a dependency under vendor/ would be collected: vendor/ is not ignored by this
// repo at all (absent from .gitignore, and .git/info/exclude carries no real entries either),
// so only Playwright's own built-in node_modules skip stands between a vendored spec and
// collection.
//
// respectGitIgnore keeps .gitignore filtering on inside testDir. Naming testDir flips that
// default to false, and the option is honoured from Playwright 1.45 up - hence the
// devDependency floor in package.json.
module.exports = defineConfig({
  testDir: './tests/playwright',
  respectGitIgnore: true,
  // Files still run in parallel across workers; this only serialises tests within a file.
  fullyParallel: false,
  // `list` only: nothing consumes playwright-report/, so no html reporter is enabled.
  reporter: 'list',
  // Pre-wired, not active: CI passes js: false today. When a JS job exists, a leftover
  // test.only must fail it visibly rather than silently shrink the run.
  forbidOnly: !!process.env.CI,
  // A spec that fails twice for the same reason must stay red.
  retries: 0,
  use: {
    headless: true,
    // Per-failure traces under test-results/.
    trace: 'retain-on-failure'
  }
  // No baseURL/webServer: the module ships no standalone dev server for these specs, so a
  // webServer block would only serve to spawn one unattended.
});
