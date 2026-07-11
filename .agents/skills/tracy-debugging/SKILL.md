---
name: tracy-debugging
description: >
  Invoke when fetching web pages from localhost, debugging PHP errors, or interpreting Tracy output (BlueScreen, Tracy Bar,
  dump). Read BEFORE running curl or Chrome to any local development PHP URL – Tracy embeds SQL query logs, execution times,
  and error details into every response. For Chrome MCP, call list_console_messages() to read Tracy output. Essential when:
  500 error, blank page, PHP exception, slow page, N+1 queries, or inspecting variables with dump().
---

## Tracy Debugging

Tracy is enabled automatically in debug mode. In debug mode, Tracy displays errors in the browser. In production mode, errors are logged to the `log/` directory and the user sees a generic error page.

### Enabling Debug Mode

Debug mode is controlled in `app/Bootstrap.php`:

```php
// Auto-detect: debug on localhost, production elsewhere
$this->configurator->setDebugMode('secret@23.75.345.200');

// Force debug mode (development only!)
$this->configurator->setDebugMode(true);

// Enable Tracy with log directory
$this->configurator->enableTracy($this->rootDir . '/log');
```

In production, Tracy logs exceptions to `log/exception-*.html` files (full BlueScreen snapshots) and errors to `log/error.log`.

### Tracy Bar

Every successful page response includes a **Tracy Bar** at the bottom - a compact debug summary in markdown:

- Execution time and memory usage
- SQL queries (count, total time, individual queries with parameters)
- Request and response details
- Logged messages

Look for it at the end of every curl response. Use it to verify database queries, check performance, and confirm expected behavior.

### BlueScreen (Exceptions & Fatal Errors)

When an exception is thrown or a fatal error occurs, Tracy renders a **BlueScreen** error report. It contains:

- Exception class, message, and code
- Stack trace with file paths and line numbers
- Source code context around the error
- Arguments at each stack frame
- Request parameters, headers, environment

The BlueScreen provides enough information to diagnose most issues without looking at logs.

### Chrome MCP - Reading Tracy Output

When using Chrome MCP (browser automation), Tracy outputs everything into browser console. Read it all with one call:

```
list_console_messages()
```

What you get:

- **`[error]`** – BlueScreen error report (full markdown with stack trace, source snippets, arguments, environment). Only present when an exception occurred.
- **`[log]`** – Tracy Bar summary (execution time, memory, plus any panel info like warnings). Present on every page.

The page title changes to `Tracy: Exception: <message> #<code>` when an error occurs (set via JavaScript, reliably overrides any original title).

No screenshots or snapshots needed. Works regardless of where the error occurs on the page. The visual HTML BlueScreen and Tracy Bar are still rendered for the human user.

### Using dump() for Debugging

Insert `dump($variable)` anywhere in PHP code to inspect values:

```php
// In presenter or service code
dump($product);           // dumps single variable
dump($query->getSql());   // dumps SQL string
dump($form->getValues()); // dumps form data
```

With curl, dump output appears directly in the response as text. With Chrome MCP, use `take_snapshot` to read dump output from the rendered page.

### Debugging Workflow

**With Chrome MCP (preferred for web pages):**
1. **Navigate** - open the page in Chrome via `navigate_page`
2. **Check console** - `list_console_messages()` to read Tracy errors (`[error]`) and Bar info (`[log]`)
3. **Inspect** - use `take_snapshot` to read page content or dump output if needed
4. **Fix** - make the code change
5. **Verify** - reload and check console again

**With curl (API endpoints, CLI):**
1. **Reproduce** - fetch the page with curl to see current behavior
2. **Inspect** - read Tracy Bar for SQL queries, timing, and logged messages
3. **Add dump()** - insert dump() calls in PHP code to inspect specific values
4. **Fetch again** - curl the page to read dump output
5. **Fix** - make the code change based on findings
6. **Verify** - fetch once more to confirm the fix (check Tracy Bar + no errors)

### Common Patterns

**Check what SQL queries a page generates:**
```bash
curl https://example.l/admin/products
# Look at Tracy Bar at the bottom for SQL query list
```

**Inspect a variable at a specific point:**
```php
// Add to code temporarily
dump($this->getParameter('id'));
```
```bash
curl https://example.l/admin/product/edit/42
# dump output appears in the response
```

### Online Documentation

For detailed information, use WebFetch on these URLs:

- [Tracy](https://tracy.nette.org) – complete debugging guide
- [Tracy Bar & Panels](https://tracy.nette.org/en/extensions) – custom panels and extensions
