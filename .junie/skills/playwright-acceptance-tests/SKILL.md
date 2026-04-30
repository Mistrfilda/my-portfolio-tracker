---
name: playwright-acceptance-tests
description: Invoke before creating, modifying, debugging, or authoring Playwright browser/acceptance tests in `tests/Browser`. Covers the project-specific Playwright Test setup, `.env.browser-tests` configuration, MCP-assisted UI exploration, login helper, read-only smoke-test rules, selectors, console/runtime error checks, and validation commands.
---

## Playwright Acceptance Tests

Browser/acceptance tests are a small read-only smoke layer for the locally running Nette application. They verify that the UI opens, login works, and important pages render without frontend/runtime errors. Business logic stays in PHPUnit unit/integration tests.

### Current setup

- Playwright Test is configured in `playwright.config.ts`.
- Tests live in `tests/Browser/` and are written in TypeScript.
- The first project is Chromium only (`Desktop Chrome`). Add more browsers only when explicitly requested.
- `playwright.config.ts` loads `.env.browser-tests` through `dotenv`.
- `testDir` is `./tests/Browser`.
- There is no Playwright `webServer`; the PHP app must already be running locally.
- Traces, screenshots, and videos are retained only on failure.
- Browser tests are intentionally not part of `composer build-all`.

### Commands

- Run browser smoke tests:

```bash
npm run test-browser
```

- Run Playwright UI mode:

```bash
npm run test-browser:ui
```

- `npm run test-browser` and `npm run test-browser:ui` both run `npm run build-dev` first through npm `pre*` scripts.
- First-time local setup may require installing the browser binary:

```bash
npx playwright install chromium
```

### Environment files

- Commit only `.env.browser-tests.example`.
- Never commit `.env.browser-tests`; it is ignored in `.gitignore` and contains local secrets.
- Required variables:

```dotenv
PLAYWRIGHT_BASE_URL=http://localhost:8000
PLAYWRIGHT_LOGIN_USERNAME=
PLAYWRIGHT_LOGIN_PASSWORD=
```

- `PLAYWRIGHT_BASE_URL` must point to an already running local app.
- Missing credentials should fail early with a clear message from `tests/Browser/support/env.ts`.
- Do not add extra env variables unless they are clearly needed by a concrete test.

### Existing browser test layout

- `tests/Browser/smoke.spec.ts` — initial read-only smoke suite.
- `tests/Browser/support/env.ts` — reads and validates required env variables.
- `tests/Browser/support/login.ts` — logs in through the real UI and waits for the `Dashboard` heading.
- `tests/Browser/support/consoleErrors.ts` — records `console.error` and `pageerror` events and asserts that none occurred.

### Current smoke coverage

The first iteration checks:

- login page renders `My portfolio tracker`, username input, `Heslo`, and `Přihlásit se`,
- user can log in and sees the `Dashboard` heading,
- read-only pages render without frontend/runtime errors:
  - `portfolio-statistic-total/` → `Statistiky`,
  - `currency-overview/` → `Měnový přehled`,
  - `portfolio-statistic-chart/` → `Grafy`.

These pages were chosen from the real admin navigation and avoid sysadmin-only pages.

### MCP-assisted authoring workflow

When adding or changing browser tests, first inspect the real UI with Playwright/Chrome DevTools MCP if the local app is available.

Recommended workflow:

1. Confirm `.env.browser-tests` exists and the app is reachable at `PLAYWRIGHT_BASE_URL`.
2. Open the app with MCP and inspect the real login page.
3. Log in through the UI using the local test credentials.
4. Explore navigation from the rendered dashboard/sidebar, not from guesses.
5. Prefer URLs and pages that are clearly read-only for the current test user.
6. Watch console messages and page errors during exploration.
7. Translate the verified flow into Playwright selectors and assertions.

If MCP cannot connect because the app is not running, do not invent selectors from memory. Inspect the relevant Latte/presenter code and document that runtime exploration was not possible.

### Selector rules

- Prefer Playwright role, label, and visible-text selectors.
- Do not select by Tailwind/CSS utility classes.
- Current login selectors:
  - username: `page.locator('input[name="username"]')` because the username label is not a reliable label selector,
  - password: `page.getByLabel('Heslo')`,
  - submit: `page.getByRole('button', { name: 'Přihlásit se' })`.
- Page readiness should usually be asserted by a visible heading, for example `page.getByRole('heading', { name: 'Dashboard' })`.
- Add `data-testid` only when role/label/text selectors would be brittle and there is no stable semantic alternative.
- If a Latte/Nette template is changed for selectors, keep it minimal and follow the typed-template rules from `ui-base-presenters-templates`.

### Test design rules

- Keep browser tests read-only unless the user explicitly asks for a write-flow acceptance test.
- Do not test business calculations or domain logic through Playwright.
- Do not use external HTTP APIs, real RabbitMQ queues, or destructive operations.
- Do not auto-start Docker, a PHP server, workers, or databases from Playwright config.
- Avoid sysadmin-only pages unless the task explicitly provides a sysadmin test account.
- Keep helpers small and obvious; do not introduce storage state or complex fixtures unless login becomes a real bottleneck.
- Use `watchFrontendErrors(page)` around pages whose frontend/runtime health is part of the assertion.
- Dispose console/pageerror listeners in `finally` blocks.

### Adding a new smoke page

1. Use MCP to confirm the page is reachable by the test user and is read-only.
2. Record the stable path and the expected visible heading or key text.
3. Add an entry to `readOnlyPages` in `tests/Browser/smoke.spec.ts` when the page follows the same pattern.
4. Keep the assertion simple: navigate, assert heading/text, assert no frontend errors.
5. If the page needs special setup or writes data, do not add it to the smoke loop.

Example pattern:

```ts
const readOnlyPages = [
	{ path: 'portfolio-statistic-total/', heading: 'Statistiky' },
];
```

### Validation

For browser-test changes, run the relevant checks:

```bash
npm run test-browser
composer cs-fix && composer build-all
```

If the local app or credentials are unavailable, state that clearly and still validate Playwright configuration without login when possible, for example:

```bash
npx playwright test --list
```

For documentation-only changes to this skill, a full application build is not required; verify the skill content against the current files instead.

### Troubleshooting notes

- `net::ERR_CONNECTION_REFUSED` means the local app is not running or `PLAYWRIGHT_BASE_URL` is wrong.
- `PLAYWRIGHT_LOGIN_USERNAME is required` or `PLAYWRIGHT_LOGIN_PASSWORD is required` means `.env.browser-tests` is missing or incomplete.
- If Playwright cannot launch Chromium, run `npx playwright install chromium`.
- If clicking visible menu text is flaky, inspect the responsive/sidebar state with MCP and prefer direct `page.goto()` to verified read-only paths.
- Treat new `console.error` or `pageerror` failures as real frontend/runtime issues unless proven unrelated.