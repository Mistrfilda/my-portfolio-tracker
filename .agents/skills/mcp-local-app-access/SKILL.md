---
name: mcp-local-app-access
description: Inspect the running local Nette app through isolated Playwright MCP, including navigation and permitted login.
---

## MCP Local App Access

Use this skill for MCP-assisted exploration of the real local UI behind login. Use Chrome DevTools or another browser surface only when the user explicitly requests it.

### Tool selection

- Use direct `mcp__playwright__browser_*` tools, starting with `mcp__playwright__browser_navigate`, in Playwright MCP's own isolated Chromium instance.
- Do not initialize the in-app Browser plugin or use `agent.browsers.getForUrl()` for this workflow unless the user explicitly requests that browser surface.
- Do not infer Playwright MCP availability from `agent.browsers.list()`; it describes a different browser surface.
- If the direct Playwright tools are not visible, perform tool discovery for `mcp__playwright__browser_navigate` before reporting Playwright unavailable.
- When the user explicitly requests Chrome DevTools MCP, use that direct MCP tool family instead and keep the same target and authentication flow below.

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
- Use stored login credentials only when permitted by the current task and active secret-handling instructions, and only in the real login form. Never put credentials in URLs.
- Do not paste the username or password into user-facing output, plans, summaries, logs, examples, or project files.

### Credential handling

- Reuse an authenticated MCP session before considering credentials. An explicit target URL does not require reading `.env.browser-tests` if that page already opens.
- When login is needed and credential use is permitted, confirm that `.env.browser-tests` and its required keys exist without printing their values; parse them only in the outer orchestration call that immediately passes them to the MCP login form.
- Keep the command result containing credentials internal. Do not emit it with `text()`, console output, logs, errors, or a returned code sample.
- Do not try to read `.env.browser-tests` from Playwright page evaluation or `browser_run_code_unsafe`; the isolated browser runtime may not expose Node.js `fs`, `process`, or `require`.
- Prefer the standard form-fill and click tools. If credentials are interpolated into generated browser code, keep that entire nested tool result un-emitted and return only a non-secret success status.
- If active instructions prohibit reading stored credentials, do not treat this skill as an exception. Continue source inspection and report that authenticated browser verification needs a permitted login path.

### Required navigation flow

Use this exact sequence whenever the user provides a target URL:

1. Store the exact requested URL as `targetUrl`; do not replace it with `PLAYWRIGHT_BASE_URL`.
2. Navigate directly to `targetUrl` first. Reuse an authenticated session when the application opens normally.
3. Only if login is required, check the permitted credential path above and the required keys without emitting their values.
4. If the application redirects to `/login/` and credential use is permitted, log in through the real UI using:
   - username: `input[name="username"]`,
   - password: label `Heslo`,
   - submit: button `Přihlásit se`.
5. Wait until the `Dashboard` heading is visible, then explicitly navigate to `targetUrl` again. Do not stop on the Dashboard or base URL.
6. Before analysing the UI, assert both:
   - the final URL matches the requested route, allowing only expected canonicalization or query parameters,
   - a page-specific title, heading, or landmark is visible.
7. Prefer MCP snapshots for navigation and assertions. Use screenshots when visual layout is part of the task.
8. Check console messages and page errors after exploration.

When no target URL is supplied, obtain only `PLAYWRIGHT_BASE_URL` from `.env.browser-tests` as `targetUrl` and follow the same authentication checks. Reuse the already running local app; do not start another development server.

If the MCP browser cannot reach `targetUrl` or `PLAYWRIGHT_BASE_URL`, report that the local app is not reachable instead of guessing selectors or routes.

### Completion gate

- Do not claim that the requested page was inspected merely because login or the Dashboard succeeded.
- Do not produce UI conclusions until the final URL and a target-specific page marker have both been verified.
- If authentication succeeds but the target assertion fails, fix the navigation first or report the exact blocker.

### Safety rules

- Do not use MCP exploration for destructive actions unless the user explicitly asks for that exact flow.
- Prefer read-only navigation and inspection.
- Do not invent selectors or routes when the running UI is unavailable; inspect relevant source files instead and state that runtime exploration was not possible.
- For local PHP/Tracy issues discovered during exploration, also use the `tracy-debugging` skill.

### Related files

- `playwright.config.ts` loads `.env.browser-tests` through `dotenv`.
- `tests/Browser/support/env.ts` documents the required variable names.
- `tests/Browser/support/login.ts` contains the current login selectors.
