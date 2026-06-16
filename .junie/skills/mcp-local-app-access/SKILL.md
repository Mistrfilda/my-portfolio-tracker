---
name: mcp-local-app-access
description: Invoke before using Playwright MCP or Chrome DevTools MCP to inspect the locally running Nette application behind login. Covers `.env.browser-tests`, safe credential handling, login selectors, snapshots, and console/runtime checks.
---

## MCP Local App Access

Use this skill when Junie needs to inspect or navigate the locally running application with Playwright MCP or Chrome DevTools MCP. This is not an acceptance-test skill; it is for manual MCP-assisted exploration of the real local UI behind login.

### Source of truth

- Use `.env.browser-tests` as the source of truth for local MCP browser sessions.
- Required variables:

```dotenv
PLAYWRIGHT_BASE_URL=http://localhost:8000
PLAYWRIGHT_LOGIN_USERNAME=
PLAYWRIGHT_LOGIN_PASSWORD=
```

- `PLAYWRIGHT_BASE_URL` must point to an already running local app.
- Never commit, edit, or print `.env.browser-tests`; it contains local secrets.
- Read credentials only for the current browser session and use them only in form fields or URLs.
- Do not paste the username or password into user-facing output, plans, summaries, logs, examples, or project files.

### Login flow

Use this flow whenever MCP needs an authenticated browser session:

1. Confirm that `.env.browser-tests` exists and contains `PLAYWRIGHT_BASE_URL`, `PLAYWRIGHT_LOGIN_USERNAME`, and `PLAYWRIGHT_LOGIN_PASSWORD`.
2. Open `PLAYWRIGHT_BASE_URL` with the MCP browser tool.
3. If the app does not redirect to login, navigate to `login/` relative to the base URL.
4. Log in through the real UI using the current project selectors:
   - username: `input[name="username"]`,
   - password: label `Heslo`,
   - submit: button `Přihlásit se`.
5. Wait until the `Dashboard` heading is visible before exploring authenticated pages.
6. Prefer MCP snapshots over screenshots for navigation and assertions.
7. Check console messages and page errors after exploration.

If the MCP browser cannot reach `PLAYWRIGHT_BASE_URL`, report that the local app is not reachable instead of guessing selectors or routes.

### Safety rules

- Do not use MCP exploration for destructive actions unless the user explicitly asks for that exact flow.
- Prefer read-only navigation and inspection.
- Do not invent selectors or routes when the running UI is unavailable; inspect relevant source files instead and state that runtime exploration was not possible.
- For local PHP/Tracy issues discovered during exploration, also use the `tracy-debugging` skill.

### Related files

- `playwright.config.ts` loads `.env.browser-tests` through `dotenv`.
- `tests/Browser/support/env.ts` documents the required variable names.
- `tests/Browser/support/login.ts` contains the current login selectors.