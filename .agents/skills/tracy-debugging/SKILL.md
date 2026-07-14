---
name: tracy-debugging
description: Invoke before requesting a local PHP page, diagnosing a Nette 500/blank page, interpreting Tracy BlueScreen or Bar output, inspecting PHP runtime errors, or investigating slow pages and query counts. Covers this project's Tracy bootstrap, browser/console inspection, log files, temporary `dump()` use, and safe verification.
---

# Tracy Debugging

Tracy is enabled by `src/Bootstrap.php` unless the caller boots the application with Tracy disabled. Debug mode controls whether the browser shows a BlueScreen; logged production-style failures remain under `log/`.

## Local web workflow

1. Use `mcp-local-app-access` before opening the authenticated local application.
2. Reproduce the exact route and record the status, page title, visible error, and relevant browser console messages.
3. For a BlueScreen, capture the exception class/message and the first application stack frame; do not paste secrets, request credentials, or the complete environment.
4. Inspect the smallest relevant source path and `log/` entry when the browser output is insufficient.
5. After a fix, reload the same route and confirm the error is gone; check console/runtime errors again.

Do not assume Tracy output is plain Markdown in a raw `curl` response. Browser HTML, console integration, and saved exception logs are different surfaces.

## Performance and queries

- Use the Tracy Bar in the rendered browser to inspect request time, memory, and Doctrine query panels.
- For suspected N+1 behavior, record the query count and repeated query shape before changing repository loading.
- Re-check the same page and dataset after the change.

## Temporary dumps

- Add `dump()` only when the current evidence cannot reveal a value safely.
- Never dump credentials, cookies, API keys, local configuration, full AI prompts/responses, or webhook URLs.
- Remove every dump introduced during debugging before validation.

## Safety and validation

- Never inspect local secret configuration files while tracing configuration loading.
- Do not perform destructive UI actions unless the user explicitly requested that flow.
- Run focused tests for the diagnosed behavior, then finish code/config changes with `composer cs-fix && composer build-all`.
